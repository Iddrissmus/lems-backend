<?php

namespace App\Http\Controllers\Academics;

use App\Domain\Academics\Actions\BuildMarksSheet;
use App\Domain\Academics\Actions\ListMarkEntryOfferings;
use App\Domain\Academics\Actions\ReleaseResults;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\ListMarkEntryOfferingsRequest;
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
 * The Head of Academics' side: mark entry by course, the submitted marks for review,
 * and releasing results to students.
 */
#[Group('Academics dashboard and release', weight: 41)]
class AcademicsResultsController extends Controller
{
    #[Endpoint(title: 'Mark entry by course', description: 'Requires academics:dashboard:view. Every course with its lecturers, how many students have a mark and its status (not_started, draft, submitted, released). Defaults to the current semester. Filters: semester, department (ids), status. meta.summary counts the courses in each status, ignoring the status filter. Paged.')]
    public function index(ListMarkEntryOfferingsRequest $request, ListMarkEntryOfferings $list): AnonymousResourceCollection
    {
        Gate::authorize('viewDashboard', CourseOffering::class);

        /** @var array{semester?: string|null, department?: string|null, status?: string|null, per_page?: int|null} $filters */
        $filters = $request->validated();

        $result = $list->forDashboard($filters);

        return MarkEntryOfferingResource::collection($result['page'])->additional(['meta' => ['summary' => $result['summary']]]);
    }

    #[Endpoint(title: 'Submitted marks of a course (review before release)', description: 'Requires academics:dashboard:view. The class list with each student\'s score and letter grade, for review before release. 409 not_submitted while the lecturer has not submitted.')]
    public function show(CourseOffering $offering, BuildMarksSheet $sheet): MarksSheetResource
    {
        Gate::authorize('viewDashboard', CourseOffering::class);

        if ($offering->marks_submitted_at === null) {
            throw new ApiException(409, 'not_submitted', 'The lecturer has not submitted these marks yet.');
        }

        return new MarksSheetResource($sheet->execute($offering));
    }

    #[Endpoint(title: 'Release a course\'s results to students', description: 'Requires results:release. The course must be submitted (409 not_submitted otherwise). After release students can see their letter grades. Release is per course and cannot be undone through the API; releasing a released course answers 200 with the same data. Send an Idempotency-Key header when retrying.')]
    public function release(Request $request, CourseOffering $offering, ReleaseResults $release): JsonResponse
    {
        Gate::authorize('release', $offering);

        /** @var User $actor */
        $actor = $request->user();

        $released = $release->execute($offering, $actor);

        return response()->json(['data' => [
            'offering_id' => $released->ulid,
            'status' => $released->markStatus()->value,
            'released_at' => $released->results_released_at?->toIso8601String(),
        ]]);
    }
}
