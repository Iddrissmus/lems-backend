<?php

namespace App\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Models\CourseOffering;
use App\Models\User;

/**
 * Who may work with a course's marks. These are permission checks only; whether a
 * lecturer is assigned to the course is checked separately and answers 404, so a
 * lecturer cannot learn that another lecturer's course exists. super_admin passes
 * through the Gate::before bypass.
 */
class CourseOfferingPolicy
{
    public function teach(User $actor): bool
    {
        return $actor->can(Permission::CoursesTeach->value);
    }

    public function enterMarks(User $actor, CourseOffering $offering): bool
    {
        return $actor->can(Permission::MarksEnter->value);
    }

    public function submitMarks(User $actor, CourseOffering $offering): bool
    {
        return $actor->can(Permission::MarksSubmit->value);
    }

    public function viewDashboard(User $actor): bool
    {
        return $actor->can(Permission::AcademicsDashboardView->value);
    }

    public function release(User $actor, CourseOffering $offering): bool
    {
        return $actor->can(Permission::ResultsRelease->value);
    }
}
