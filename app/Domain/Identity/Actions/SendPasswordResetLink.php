<?php

namespace App\Domain\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Password;

class SendPasswordResetLink
{
    /**
     * Silent by design: the caller gets the same answer whether or not the
     * email exists, so this cannot be used to discover accounts. Only active
     * accounts are emailed (invited users must accept their invitation,
     * disabled users must not get back in).
     */
    public function execute(string $email): void
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! $user->status->canLogin()) {
            return;
        }

        Password::sendResetLink(['email' => $email]);
    }
}
