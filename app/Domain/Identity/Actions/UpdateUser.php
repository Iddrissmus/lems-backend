<?php

namespace App\Domain\Identity\Actions;

use App\Models\User;

class UpdateUser
{
    /**
     * Only the display name can change here. Email changes and role changes
     * have their own rules and endpoints.
     */
    public function execute(User $target, string $name): User
    {
        $target->update(['name' => $name]);

        return $target;
    }
}
