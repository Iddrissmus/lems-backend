<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Events\UserStatusChanged;
use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DisableUser
{
    public function __construct(private readonly SuperAdminSafeguard $safeguard) {}

    public function execute(User $target, User $actor): User
    {
        if ($target->is($actor)) {
            throw new ApiException(422, 'cannot_disable_self', 'You cannot disable your own account.');
        }

        $before = $target->status;

        if ($before === UserStatus::Disabled) {
            return $target;
        }

        DB::transaction(function () use ($target): void {
            if ($target->isSuperAdmin()) {
                $this->safeguard->ensureAnotherActiveExists($target);
            }

            $target->forceFill(['status' => UserStatus::Disabled])->save();

            // Sign them out everywhere, right now.
            $target->tokens()->delete();
        });

        event(new UserStatusChanged($target, $before, UserStatus::Disabled, $actor));

        return $target;
    }
}
