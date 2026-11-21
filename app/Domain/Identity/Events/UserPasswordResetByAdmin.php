<?php

namespace App\Domain\Identity\Events;

use App\Models\User;

/** Staff issued someone a new temporary password. The audit log records the fact, never the password. */
final class UserPasswordResetByAdmin
{
    public function __construct(
        public readonly User $user,
        public readonly User $actor,
    ) {}
}
