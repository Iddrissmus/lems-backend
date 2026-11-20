<?php

namespace App\Domain\Academics\Actions;

use App\Domain\Academics\Enums\EnrolmentStatus;
use App\Domain\Academics\Events\EnrolmentCreated;
use App\Exceptions\ApiException;
use App\Exceptions\ApiValidationException;
use App\Models\CourseOffering;
use App\Models\Enrolment;
use App\Models\ProgrammeCourse;
use App\Models\RegistrationWindow;
use App\Models\Semester;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Registers a student for courses in the current semester. All or nothing: if any
 * course is refused, nothing is registered and the answer explains each refusal.
 *
 * The rules are only these (docs/api/demo-contract.md section 6): a registration
 * window is open for the student (skipped when staff register on a student's
 * behalf), the course is offered this semester, it is in the curriculum for the
 * student's programme, year and this semester, and the student is not already
 * registered. Credit limits, clashes, prerequisites and arrears gating are NOT
 * enforced: the College has not confirmed them (BE-065).
 */
class RegisterForCourses
{
    /**
     * @param  array<int, string>  $offeringIds  public ids of course offerings
     * @return Collection<int, Enrolment>
     */
    public function execute(StudentProfile $student, array $offeringIds, ?User $actor, bool $enforceWindow = true): Collection
    {
        $semester = Semester::where('is_current', true)->first()
            ?? throw new ApiException(422, 'registration_closed', 'There is no current semester to register for.');

        if ($enforceWindow && ! $this->windowOpenFor($student, $semester)) {
            throw new ApiException(422, 'registration_closed', 'Registration is closed for you right now.');
        }

        $own = $actor !== null && $actor->id === $student->user_id;
        [$offerings, $errors] = $this->check($student, $semester, array_values($offeringIds), $own);

        if ($errors !== []) {
            throw new ApiValidationException('registration_rejected', 'Some courses could not be registered.', $errors);
        }

        return DB::transaction(function () use ($student, $offerings, $actor): Collection {
            return collect($offerings)->map(function (CourseOffering $offering) use ($student, $actor): Enrolment {
                // A course the student withdrew from can be registered again.
                $enrolment = Enrolment::firstOrNew(['student_profile_id' => $student->id, 'course_offering_id' => $offering->id]);
                $enrolment->fill(['status' => EnrolmentStatus::Registered, 'registered_at' => now()])->save();
                $enrolment->setRelation('offering', $offering);

                event(new EnrolmentCreated($enrolment, $actor));

                return $enrolment;
            });
        });
    }

    private function windowOpenFor(StudentProfile $student, Semester $semester): bool
    {
        return RegistrationWindow::where('semester_id', $semester->id)->get()
            ->contains(fn (RegistrationWindow $window) => $window->isOpen() && $window->coversStudent($student));
    }

    /**
     * @param  array<int, string>  $offeringIds
     * @return array{0: array<int, CourseOffering>, 1: array<string, array<int, string>>}
     */
    private function check(StudentProfile $student, Semester $semester, array $offeringIds, bool $own): array
    {
        $who = $own ? 'your' : "the student's";
        $offerings = [];
        $errors = [];

        foreach ($offeringIds as $index => $id) {
            $offering = CourseOffering::with('course')->where('ulid', $id)->first();
            $field = "course_offering_ids.{$index}";

            if ($offering === null || $offering->semester_id !== $semester->id) {
                $errors[$field] = ['This course is not offered this semester.'];
            } elseif (! $this->inCurriculum($student, $offering, $semester)) {
                $errors[$field] = ["This course is not in {$who} programme's curriculum for this year and semester."];
            } elseif ($this->alreadyRegistered($student, $offering)) {
                $errors[$field] = [$own ? 'You are already registered for this course.' : 'The student is already registered for this course.'];
            } else {
                $offerings[] = $offering;
            }
        }

        return [$offerings, $errors];
    }

    private function inCurriculum(StudentProfile $student, CourseOffering $offering, Semester $semester): bool
    {
        return ProgrammeCourse::where('programme_id', $student->programme_id)
            ->where('course_id', $offering->course_id)
            ->where('year_of_study', $student->year_of_study)
            ->where('semester_number', $semester->number)
            ->exists();
    }

    private function alreadyRegistered(StudentProfile $student, CourseOffering $offering): bool
    {
        return Enrolment::where('student_profile_id', $student->id)
            ->where('course_offering_id', $offering->id)
            ->where('status', EnrolmentStatus::Registered->value)
            ->exists();
    }
}
