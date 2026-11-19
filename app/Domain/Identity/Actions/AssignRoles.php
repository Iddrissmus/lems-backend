<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Events\UserRolesChanged;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssignRoles
{
    public function __construct(private readonly SuperAdminSafeguard $safeguard) {}

    /**
     * Replaces the user's roles with exactly this set.
     *
     * @param  array<int, string>  $roles
     */
    public function execute(User $target, array $roles, ?User $actor = null): User
    {
        $before = $target->getRoleNames()->sort()->values()->all();
        $after = collect($roles)->unique()->sort()->values()->all();

        if ($before === $after) {
            return $target;
        }

        DB::transaction(function () use ($target, $before, $after, $actor): void {
            // Lock the row so two admins cannot race each other.
            User::query()->whereKey($target->getKey())->lockForUpdate()->first();

            if ($target->isSuperAdmin() && ! in_array(Role::SuperAdmin->value, $after, true)) {
                $this->safeguard->ensureAnotherActiveExists($target);
            }

            $target->syncRoles($after);

            // Inside the transaction: the audit entry is saved with the change or not at all.
            event(new UserRolesChanged($target, $before, $after, $actor));
        });

        return $target;
    }
}
