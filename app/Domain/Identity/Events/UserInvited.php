<?php

namespace App\Domain\Identity\Events;

use App\Models\User;

/** An account was created or re-invited. The audit log listens to this (step 5). */
final class UserInvited
{
    /**
     * @param  array<int, string>  $roles
     */
    public function __construct(
        public readonly User $user,
        public readonly array $roles,
        public readonly ?User $actor,
    ) {}
}
