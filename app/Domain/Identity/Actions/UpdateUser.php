<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Events\UserUpdated;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateUser
{
    /**
     * Only the display name can change here. Email changes and role changes
     * have their own rules and endpoints.
     */
    public function execute(User $target, string $name, ?User $actor = null): User
    {
        $before = ['name' => $target->name];

        if ($before['name'] === $name) {
            return $target;
        }

        DB::transaction(function () use ($target, $name, $before, $actor): void {
            $target->update(['name' => $name]);

            event(new UserUpdated($target, $before, ['name' => $name], $actor));
        });

        return $target;
    }
}
