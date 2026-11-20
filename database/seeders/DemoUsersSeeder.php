<?php

namespace Database\Seeders;

use App\Domain\Identity\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * One demo account per role for local development only, plus
 * first-login@demo.test which must change its password on first use.
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

        // One account that must pick its own password, to show the forced
        // change at first login. The others stay usable straight away.
        $firstLogin = User::firstOrCreate(
            ['email' => 'first-login@demo.test'],
            ['name' => 'First Login (demo)', 'password' => 'password', 'status' => 'active'],
        );
        $firstLogin->forceFill(['email_verified_at' => now(), 'must_change_password' => true])->save();
        $firstLogin->syncRoles([Role::Lecturer->value]);
    }
}
