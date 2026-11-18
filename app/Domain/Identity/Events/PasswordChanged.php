<?php

namespace App\Domain\Identity\Events;

use App\Models\User;

/** A signed-in user changed their own password. */
final class PasswordChanged
{
    public function __construct(public readonly User $user) {}
}
