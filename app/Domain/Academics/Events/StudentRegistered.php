<?php

namespace App\Domain\Academics\Events;

use App\Models\StudentProfile;
use App\Models\User;

/** Staff created a student record and portal account. The audit log listens. The password is never part of this event. */
final class StudentRegistered
{
    public function __construct(
        public readonly StudentProfile $student,
        public readonly ?User $actor,
    ) {}
}
