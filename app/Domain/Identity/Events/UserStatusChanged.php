<?php

namespace App\Domain\Identity\Events;

use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;

/** An account was disabled or re-enabled. */
final class UserStatusChanged
{
    public function __construct(
        public readonly User $user,
        public readonly UserStatus $before,
        public readonly UserStatus $after,
        public readonly ?User $actor,
    ) {}
}
