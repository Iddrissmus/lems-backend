<?php

namespace App\Console\Commands;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Creates the very first super_admin. Needed once per environment because
 * there is no public registration and no password is ever stored in code.
 */
#[Signature('lems:create-super-admin {--name= : Full name} {--email= : Email address}')]
#[Description('Create an active super_admin account (prompts for the password)')]
class CreateSuperAdmin extends Command
{
    public function handle(): int
    {
        $name = (string) ($this->option('name') ?: $this->ask('Full name'));
        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('Email address'))));
        $password = (string) $this->secret('Password');
        $confirmation = (string) $this->secret('Confirm password');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        // Make sure the roles exist, even on a brand-new database.
        $this->callSilently('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);

        $user = new User(['name' => $name, 'email' => $email, 'password' => $password, 'status' => UserStatus::Active]);
        $user->email_verified_at = now();
        $user->save();
        $user->assignRole(Role::SuperAdmin->value);

        $this->components->info("Super admin {$email} created.");

        return self::SUCCESS;
    }
}
