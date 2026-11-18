<?php

namespace App\Domain\Identity\Events;

use App\Models\User;

final class UserRolesChanged
{
    /**
     * @param  array<int, string>  $before
     * @param  array<int, string>  $after
     */
    public function __construct(
        public readonly User $user,
        public readonly array $before,
        public readonly array $after,
        public readonly ?User $actor,
    ) {}
}
