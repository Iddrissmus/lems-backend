<?php

namespace Database\Seeders;

use App\Domain\Identity\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * One demo account per role for local development only, plus
 * first-login@demo.test which must change its password on first use.
 * Every password is the demo password (DEMO_PASSWORD in .env, "password" when not set);
 * running the seeder again applies the current value to the existing accounts.
 * Refuses to run anywhere but local.
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DemoUsersSeeder is for the local environment only.');
        }

        $password = (string) config('demo.password');

        if (strlen($password) < 8) {
            throw new RuntimeException('DEMO_PASSWORD must be at least 8 characters.');
        }

        // Hash once and reuse: hashing a dozen passwords one by one is slow.
        $hash = Hash::make($password);

        foreach (Role::cases() as $role) {
            $user = User::firstOrCreate(
                ['email' => $role->value.'@demo.test'],
                ['name' => ucwords(str_replace('_', ' ', $role->value)).' (demo)', 'password' => $hash, 'status' => 'active'],
            );

            $user->forceFill(['password' => $hash, 'email_verified_at' => now()])->save();
            $user->syncRoles([$role->value]);
        }

        // One account that must pick its own password, to show the forced
        // change at first login. The others stay usable straight away.
        $firstLogin = User::firstOrCreate(
            ['email' => 'first-login@demo.test'],
            ['name' => 'First Login (demo)', 'password' => $hash, 'status' => 'active'],
        );
        // Re-running the seeder puts it back to "must change", so the demo can be repeated.
        $firstLogin->forceFill([
            'password' => $hash,
            'email_verified_at' => now(),
            'must_change_password' => true,
        ])->save();
        $firstLogin->syncRoles([Role::Lecturer->value]);
    }
}
