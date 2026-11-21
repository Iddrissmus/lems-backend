<?php

namespace App\Domain\Academics\Events;

use App\Models\CourseOffering;
use App\Models\User;

/** The Head of Academics released a course's results to students. The audit log listens. */
final class ResultsReleased
{
    public function __construct(
        public readonly CourseOffering $offering,
        public readonly ?User $actor,
    ) {}
}
