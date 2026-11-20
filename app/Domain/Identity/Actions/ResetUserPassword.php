<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Events\UserPasswordResetByAdmin;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Support\Security\TemporaryPassword;
use Illuminate\Support\Facades\DB;

/**
 * Staff issue an active account a new temporary password (for someone locked
 * out). The person must change it at first sign-in, and is signed out
 * everywhere. Returns the password for the caller to hand over, once.
 */
class ResetUserPassword
{
    public function execute(User $target, User $actor): string
    {
        if ($target->status !== UserStatus::Active) {
            throw new ApiException(409, 'conflict', 'Only an active account can be given a new temporary password.');
        }

        $password = TemporaryPassword::generate();

        DB::transaction(function () use ($target, $actor, $password): void {
            $target->forceFill(['password' => $password, 'must_change_password' => true])->save();

            // Whoever held the old password must be signed out everywhere.
            $target->tokens()->delete();

            event(new UserPasswordResetByAdmin($target, $actor));
        });

        return $password;
    }
}
