<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates (or re-invites) an account and emails a one-time link.
 * Accounts are admin-created only; there is no public registration.
 */
class InviteUser
{
    public const VALID_FOR_HOURS = 72;

    public function execute(string $name, string $email, ?User $invitedBy = null): Invitation
    {
        $plainToken = Str::random(64);
        $expiresAt = now()->addHours(self::VALID_FOR_HOURS);

        $invitation = DB::transaction(function () use ($name, $email, $invitedBy, $plainToken, $expiresAt): Invitation {
            $user = User::withTrashed()->where('email', $email)->lockForUpdate()->first();

            if ($user !== null && $user->status !== UserStatus::Invited) {
                throw new ApiException(409, 'conflict', 'A user with this email already exists.');
            }

            $user ??= new User(['email' => $email]);
            $user->fill(['name' => $name, 'status' => UserStatus::Invited])->save();

            if ($user->trashed()) {
                $user->restore();
            }

            // Only the newest link works.
            $user->invitations()->whereNull('accepted_at')->delete();

            return $user->invitations()->create([
                'invited_by_id' => $invitedBy?->getKey(),
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => $expiresAt,
            ]);
        });

        $invitation->user->notify(new InvitationNotification($plainToken, $expiresAt));

        return $invitation;
    }
}
