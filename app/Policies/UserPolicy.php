<?php

namespace App\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\Role;
use App\Models\User;

/**
 * Who may manage accounts. super_admin passes every check through the
 * Gate::before bypass in AppServiceProvider, so the rules below describe
 * everyone else. The key rule: only a super_admin may touch a super_admin
 * account or hand out the super_admin role.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::UsersView->value);
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->can(Permission::UsersView->value);
    }

    /**
     * @param  array<int, string>  $roles  roles the new account would receive
     */
    public function invite(User $actor, array $roles): bool
    {
        return $actor->can(Permission::UsersInvite->value)
            && ! in_array(Role::SuperAdmin->value, $roles, true);
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->can(Permission::UsersUpdate->value)
            && ! $target->isSuperAdmin();
    }

    public function disable(User $actor, User $target): bool
    {
        return $actor->can(Permission::UsersDisable->value)
            && ! $target->isSuperAdmin();
    }

    /**
     * @param  array<int, string>  $roles  the full set of roles being assigned
     */
    public function assignRoles(User $actor, User $target, array $roles): bool
    {
        return $actor->can(Permission::UsersAssignRoles->value)
            && ! $target->isSuperAdmin()
            && ! in_array(Role::SuperAdmin->value, $roles, true);
    }
}
