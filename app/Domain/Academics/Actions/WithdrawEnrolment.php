<?php

namespace App\Domain\Academics\Actions;

use App\Domain\Academics\Enums\EnrolmentStatus;
use App\Domain\Academics\Events\EnrolmentWithdrawn;
use App\Exceptions\ApiException;
use App\Models\AssessmentScore;
use App\Models\Enrolment;
use App\Models\RegistrationWindow;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A student withdraws from a course while a registration window is open for them.
 * The registration is kept with status "withdrawn" (history is never deleted).
 * Not possible once marks exist for the course: that would orphan them.
 */
class WithdrawEnrolment
{
    public function execute(Enrolment $enrolment, User $actor): Enrolment
    {
        $student = $enrolment->student;

        if ($enrolment->status !== EnrolmentStatus::Registered) {
            throw new ApiException(409, 'conflict', 'You have already withdrawn from this course.');
        }

        $semester = Semester::where('is_current', true)->first();
        $open = $semester !== null && RegistrationWindow::where('semester_id', $semester->id)->get()
            ->contains(fn (RegistrationWindow $w) => $w->isOpen() && $w->coversStudent($student));

        if (! $open) {
            throw new ApiException(422, 'registration_closed', 'Registration is closed for you right now, so you cannot withdraw.');
        }

        if (AssessmentScore::where('enrolment_id', $enrolment->id)->exists()) {
            throw new ApiException(409, 'conflict', 'Marks have already been entered for this course, so it cannot be withdrawn.');
        }

        return DB::transaction(function () use ($enrolment, $actor): Enrolment {
            $enrolment->update(['status' => EnrolmentStatus::Withdrawn]);

            event(new EnrolmentWithdrawn($enrolment, $actor));

            return $enrolment;
        });
    }
}
