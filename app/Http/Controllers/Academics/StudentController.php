<?php

namespace App\Http\Controllers\Academics;

use App\Domain\Academics\Actions\ListStudents;
use App\Domain\Academics\Actions\RegisterStudent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\ListStudentsRequest;
use App\Http\Requests\Academics\StoreStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\StudentProfile;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

#[Group('Students', weight: 32)]
class StudentController extends Controller
{
    #[Endpoint(title: 'List students', description: 'Requires students:view. Search q (name, email or index number); filter by programme, cohort, department (ids) and year; paged.')]
    public function index(ListStudentsRequest $request, ListStudents $list): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', StudentProfile::class);

        return StudentResource::collection($list->execute($request->validated()));
    }

    #[Endpoint(title: 'Show a student', description: 'Requires students:view.')]
    public function show(StudentProfile $student): StudentResource
    {
        Gate::authorize('view', $student);

        return new StudentResource($student->load(['user', 'programme', 'cohort'])->loadCount('registeredEnrolments'));
    }

    #[Endpoint(title: 'Register a student', description: 'Requires accounts:create. Creates the portal account, the student record and the index number in one step; optionally registers first courses (course_offering_ids), all or nothing. The answer carries meta.temporary_password, shown ONCE: hand it to the student, who must change it at first sign-in. It is never returned again and never written to the audit log. Send an Idempotency-Key header when retrying.')]
    public function store(StoreStudentRequest $request, RegisterStudent $register): JsonResponse
    {
        Gate::authorize('create', StudentProfile::class);

        /** @var User $actor */
        $actor = $request->user();

        /** @var array{name: string, email: string, programme_id: string, cohort_id?: string|null, year_of_study?: int|null, moh_membership_id?: string|null, course_offering_ids?: array<int, string>|null} $data */
        $data = $request->validated();

        [$student, $temporaryPassword] = $register->execute($data, $actor);

        return (new StudentResource($student->loadCount('registeredEnrolments')))
            ->additional(['meta' => ['temporary_password' => $temporaryPassword]])
            ->response()
            ->setStatusCode(201);
    }
}
