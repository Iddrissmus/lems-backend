<?php

namespace App\Http\Controllers\Academics;

use App\Domain\Academics\Actions\BuildMarksSheet;
use App\Domain\Academics\Actions\ListMarkEntryOfferings;
use App\Domain\Academics\Actions\SaveDraftMarks;
use App\Domain\Academics\Actions\SubmitMarks;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\ListMarkEntryOfferingsRequest;
use App\Http\Requests\Academics\SaveMarksRequest;
use App\Http\Resources\MarkEntryOfferingResource;
use App\Http\Resources\MarksSheetResource;
use App\Models\CourseOffering;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * A lecturer's marks: their courses, the class list, saving draft marks and submitting.
 * A lecturer only ever reaches courses they are assigned to; any other course answers 404.
 */
#[Group('Marks (lecturers)', weight: 40)]
class LecturerMarksController extends Controller
{
    #[Endpoint(title: 'My courses', description: 'Requires courses:teach. The courses I am assigned to, with how many students are registered and how many have a mark. Defaults to the current semester; filter with semester (a semester id). status is not_started, draft, submitted or released.')]
    public function index(ListMarkEntryOfferingsRequest $request, ListMarkEntryOfferings $list): AnonymousResourceCollection
    {
        Gate::authorize('teach', CourseOffering::class);

        /** @var User $user */
        $user = $request->user();

        $semester = $request->validated('semester');

        return MarkEntryOfferingResource::collection($list->forLecturer($user, is_string($semester) ? $semester : null));
    }

    #[Endpoint(title: 'Class list and marks (review)', description: 'Requires courses:teach and being assigned to the course (404 otherwise). The registered students with their saved mark, a letter grade preview from the active scale, and a summary of how many are missing. This is the review screen.')]
    public function show(Request $request, CourseOffering $offering, BuildMarksSheet $sheet): MarksSheetResource
    {
        Gate::authorize('teach', CourseOffering::class);
        $this->assigned($request, $offering);

        return new MarksSheetResource($sheet->execute($offering));
    }

    #[Endpoint(title: 'Save draft marks', description: 'Requires marks:enter and being assigned to the course. Body: marks, a list of {enrolment_id, score}; score is 0 to 100 with up to 2 decimals, or null to clear a draft mark. The whole request is checked first; if any row is wrong nothing is saved (422 with errors such as marks.1.score). Answers the same class list as the review. 423 grade_locked once the course is submitted. Send an Idempotency-Key header when retrying.')]
    public function update(SaveMarksRequest $request, CourseOffering $offering, SaveDraftMarks $save, BuildMarksSheet $sheet): MarksSheetResource
    {
        Gate::authorize('enterMarks', $offering);
        $this->assigned($request, $offering);

        /** @var User $actor */
        $actor = $request->user();

        $save->execute($offering, $request->marks(), $actor);

        return new MarksSheetResource($sheet->execute($offering->refresh()));
    }

    #[Endpoint(title: 'Submit marks (locks them)', description: 'Requires marks:submit and being assigned to the course. Every registered student must have a mark, otherwise 422 marks_incomplete with errors.marks listing who is missing. On success the letter grades are stored using the active scale and the marks lock: nobody can change them afterwards (423 grade_locked). Submitting twice answers 409 already_submitted. Send an Idempotency-Key header when retrying.')]
    public function submit(Request $request, CourseOffering $offering, SubmitMarks $submit): JsonResponse
    {
        Gate::authorize('submitMarks', $offering);
        $this->assigned($request, $offering);

        /** @var User $actor */
        $actor = $request->user();

        $submitted = $submit->execute($offering, $actor);

        return response()->json(['data' => [
            'offering_id' => $submitted->ulid,
            'status' => $submitted->markStatus()->value,
            'submitted_at' => $submitted->marks_submitted_at?->toIso8601String(),
            'marks_count' => $submitted->enrolments()->where('status', 'registered')->whereHas('scores')->count(),
        ]]);
    }

    /** A course that is not mine looks exactly like one that does not exist. */
    private function assigned(Request $request, CourseOffering $offering): void
    {
        /** @var User $user */
        $user = $request->user();

        if (! $offering->isTaughtBy($user)) {
            throw new ApiException(404, 'not_found', 'The requested resource was not found.');
        }
    }
}
