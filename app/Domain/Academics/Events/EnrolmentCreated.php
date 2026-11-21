<?php

namespace App\Domain\Academics\Events;

use App\Models\Enrolment;
use App\Models\User;

/** A student was registered for a course. The audit log listens. */
final class EnrolmentCreated
{
    public function __construct(
        public readonly Enrolment $enrolment,
        public readonly ?User $actor,
    ) {}
}
