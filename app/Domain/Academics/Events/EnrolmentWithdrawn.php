<?php

namespace App\Domain\Academics\Events;

use App\Models\Enrolment;
use App\Models\User;

/** A student withdrew from a course. The audit log listens. */
final class EnrolmentWithdrawn
{
    public function __construct(
        public readonly Enrolment $enrolment,
        public readonly ?User $actor,
    ) {}
}
