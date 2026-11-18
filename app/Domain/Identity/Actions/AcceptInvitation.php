<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcceptInvitation
{
    public function execute(string $token, string $password): User
    {
        return DB::transaction(function () use ($token, $password): User {
            $invitation = Invitation::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            // One message for unknown, used and expired links: tells nothing extra.
            if ($invitation === null || ! $invitation->isUsable() || $invitation->user->status !== UserStatus::Invited) {
                throw new ApiException(422, 'invalid_token', 'This invitation link is invalid or has expired.');
            }

            $user = $invitation->user;
            $user->forceFill([
                'password' => $password,
                'status' => UserStatus::Active,
                // They proved they control this mailbox by opening the link.
                'email_verified_at' => now(),
            ])->save();

            $invitation->update(['accepted_at' => now()]);

            return $user;
        });
    }
}
