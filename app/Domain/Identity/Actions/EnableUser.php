<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Events\UserStatusChanged;
use App\Models\User;

class EnableUser
{
    public function execute(User $target, User $actor): User
    {
        $before = $target->status;

        if ($before !== UserStatus::Disabled) {
            return $target;
        }

        // Someone who never accepted their invitation has no password yet,
        // so they go back to "invited", not "active".
        $after = $target->password === null ? UserStatus::Invited : UserStatus::Active;

        $target->forceFill(['status' => $after])->save();

        event(new UserStatusChanged($target, $before, $after, $actor));

        return $target;
    }
}
