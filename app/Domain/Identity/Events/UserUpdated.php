<?php

namespace App\Domain\Identity\Events;

use App\Models\User;

final class UserUpdated
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function __construct(
        public readonly User $user,
        public readonly array $before,
        public readonly array $after,
        public readonly ?User $actor,
    ) {}
}
