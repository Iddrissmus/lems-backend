<?php

namespace App\Http\Controllers\Academics;

use App\Domain\Academics\Actions\GetRegistrationOverview;
use App\Domain\Academics\Actions\ListEligibleCourseOfferings;
use App\Domain\Academics\Actions\RegisterForCourses;
use App\Domain\Academics\Actions\WithdrawEnrolment;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\RegisterCoursesRequest;
use App\Http\Resources\EligibleCourseOfferingResource;
use App\Http\Resources\EnrolmentResource;
use App\Http\Resources\RegistrationOverviewResource;
use App\Http\Resources\StudentResource;
use App\Models\Enrolment;
use App\Models\StudentProfile;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * A student's own side of registration. Everything here works only on the signed-in
 * student's own record: there is no id in the URL to tamper with.
 */
#[Group('My registration (students)', weight: 33)]
class MeStudentController extends Controller
{
    #[Endpoint(title: 'My student record', description: 'Requires registration:self. The signed-in student\'s own record. 404 if the account has no student record.')]
    public function profile(Request $request): StudentResource
    {
        $student = $this->student($request);

        return new StudentResource($student->load(['user', 'programme', 'cohort'])->loadCount('registeredEnrolments'));
    }

    #[Endpoint(title: 'My registration this semester', description: 'Requires registration:self. The current semester, the registration window that applies to me (open, else the next to open, else the latest closed, or null if none covers my programme and year), and the courses I have registered.')]
    public function registration(Request $request, GetRegistrationOverview $overview): RegistrationOverviewResource
    {
        return new RegistrationOverviewResource($overview->execute($this->student($request)));
    }

    #[Endpoint(title: 'Courses I can register for', description: 'Requires registration:self. Courses on offer this semester that are in my programme\'s curriculum for my year, each with registered true or false. Optional q searches code or title.')]
    public function courseOfferings(Request $request, ListEligibleCourseOfferings $list): AnonymousResourceCollection
    {
        $student = $this->student($request);
        $search = $request->query('q');

        return EligibleCourseOfferingResource::collection($list->execute($student, is_string($search) ? $search : null));
    }

    #[Endpoint(title: 'Register for courses', description: 'Requires registration:self and an open registration window for my programme and year. All or nothing: if any course is refused nothing is registered and the answer is 422 registration_rejected with a reason per course. 422 registration_closed when no window is open. Send an Idempotency-Key header when retrying.')]
    public function register(RegisterCoursesRequest $request, RegisterForCourses $register): JsonResponse
    {
        $student = $this->student($request);

        /** @var User $actor */
        $actor = $request->user();

        $enrolments = $register->execute($student, $request->offeringIds(), $actor);

        return EnrolmentResource::collection($enrolments)->response()->setStatusCode(201);
    }

    #[Endpoint(title: 'Withdraw from a course', description: 'Requires registration:self, an open registration window, and that no marks exist for the course yet. The registration is kept with status withdrawn. 404 for a registration that is not mine.')]
    public function withdraw(Request $request, Enrolment $enrolment, WithdrawEnrolment $withdraw): EnrolmentResource
    {
        $student = $this->student($request);

        // Someone else's registration looks exactly like one that does not exist.
        if ($enrolment->student_profile_id !== $student->id) {
            throw new ApiException(404, 'not_found', 'The requested resource was not found.');
        }

        /** @var User $actor */
        $actor = $request->user();

        return new EnrolmentResource($withdraw->execute($enrolment->load('offering.course', 'student'), $actor));
    }

    private function student(Request $request): StudentProfile
    {
        Gate::authorize('selfRegister');

        /** @var User $user */
        $user = $request->user();

        return $user->studentProfile
            ?? throw new ApiException(404, 'not_found', 'There is no student record for this account.');
    }
}
