<?php

namespace App\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Models\StudentProfile;
use App\Models\User;

/**
 * Who may look up students and create them. A student reading their OWN record
 * goes through /me/student, not through here. super_admin passes via Gate::before.
 */
class StudentProfilePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::StudentsView->value);
    }

    public function view(User $actor, StudentProfile $student): bool
    {
        return $actor->can(Permission::StudentsView->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::AccountsCreate->value);
    }
}
