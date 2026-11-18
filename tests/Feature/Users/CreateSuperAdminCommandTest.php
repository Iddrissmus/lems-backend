<?php

use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use Spatie\Permission\Models\Role;

it('creates an active, verified super_admin and prompts for the password', function () {
    $this->artisan('lems:create-super-admin', ['--name' => 'Iddriss', '--email' => 'Admin@Example.com'])
        ->expectsQuestion('Password', 'Strong-Passw0rd-1')
        ->expectsQuestion('Confirm password', 'Strong-Passw0rd-1')
        ->assertSuccessful();

    $user = User::where('email', 'admin@example.com')->first();

    expect($user->isSuperAdmin())->toBeTrue()
        ->and($user->status)->toBe(UserStatus::Active)
        ->and($user->email_verified_at)->not->toBeNull();

    $this->postJson('/api/v1/auth/login', ['email' => 'admin@example.com', 'password' => 'Strong-Passw0rd-1'])
        ->assertOk();
});

it('refuses a weak password and creates nothing', function () {
    $this->artisan('lems:create-super-admin', ['--name' => 'Iddriss', '--email' => 'admin@example.com'])
        ->expectsQuestion('Password', 'weak')
        ->expectsQuestion('Confirm password', 'weak')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

it('refuses an email that is already used', function () {
    User::factory()->create(['email' => 'admin@example.com']);

    $this->artisan('lems:create-super-admin', ['--name' => 'Iddriss', '--email' => 'admin@example.com'])
        ->expectsQuestion('Password', 'Strong-Passw0rd-1')
        ->expectsQuestion('Confirm password', 'Strong-Passw0rd-1')
        ->assertFailed();
});

it('creates the roles itself on a brand-new database', function () {
    Role::query()->delete();

    $this->artisan('lems:create-super-admin', ['--name' => 'Iddriss', '--email' => 'admin@example.com'])
        ->expectsQuestion('Password', 'Strong-Passw0rd-1')
        ->expectsQuestion('Confirm password', 'Strong-Passw0rd-1')
        ->assertSuccessful();

    expect(User::first()->isSuperAdmin())->toBeTrue();
});
