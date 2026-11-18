<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Events\UserLoggedOut;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutUser
{
    /** Revokes only the token used for this request. */
    public function execute(User $user): void
    {
        $token = $user->currentAccessToken();

        DB::transaction(function () use ($user, $token): void {
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }

            event(new UserLoggedOut($user));
        });
    }
}
