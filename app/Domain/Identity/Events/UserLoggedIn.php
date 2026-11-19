<?php

namespace App\Domain\Identity\Events;

use App\Models\User;

/** A user signed in. */
final class UserLoggedIn
{
    public function __construct(public readonly User $user) {}
}
