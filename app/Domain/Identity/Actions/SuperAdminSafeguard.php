<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Models\User;

/**
 * The college must never be locked out of its own system: at least one
 * active super_admin has to remain after any change.
 */
class SuperAdminSafeguard
{
    public function ensureAnotherActiveExists(User $leaving): void
    {
        $others = User::query()
            ->role(Role::SuperAdmin->value)
            ->where('status', UserStatus::Active->value)
            ->whereKeyNot($leaving->getKey())
            ->exists();

        if (! $others) {
            throw new ApiException(409, 'last_super_admin', 'At least one active super admin must remain.');
        }
    }
}
