<?php

namespace App\Domain\Identity\Events;

use App\Models\User;

/** A user signed out (their current token was revoked). */
final class UserLoggedOut
{
    public function __construct(public readonly User $user) {}
}
