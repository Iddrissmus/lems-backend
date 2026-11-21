<?php

namespace App\Domain\Academics\Events;

use App\Models\CourseOffering;
use App\Models\GradeScale;
use App\Models\User;

/** A lecturer submitted a course's marks and they locked. The audit log listens. */
final class MarksSubmitted
{
    public function __construct(
        public readonly CourseOffering $offering,
        public readonly ?User $actor,
        public readonly int $marksCount,
        public readonly GradeScale $scale,
    ) {}
}
