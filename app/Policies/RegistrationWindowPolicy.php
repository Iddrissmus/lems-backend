<?php

namespace App\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Models\RegistrationWindow;
use App\Models\User;

/**
 * Who may open and close registration windows. super_admin passes through
 * the Gate::before bypass.
 */
class RegistrationWindowPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::RegistrationManage->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::RegistrationManage->value);
    }

    public function close(User $actor, RegistrationWindow $window): bool
    {
        return $actor->can(Permission::RegistrationManage->value);
    }
}
