<?php

namespace Database\Seeders;

use App\Domain\Identity\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * One demo account per role for local development only.
 * Every password is "password". Refuses to run anywhere but local.
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DemoUsersSeeder is for the local environment only.');
        }

        foreach (Role::cases() as $role) {
            $user = User::firstOrCreate(
                ['email' => $role->value.'@demo.test'],
                ['name' => ucwords(str_replace('_', ' ', $role->value)).' (demo)', 'password' => 'password', 'status' => 'active'],
            );

            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles([$role->value]);
        }
    }
}
