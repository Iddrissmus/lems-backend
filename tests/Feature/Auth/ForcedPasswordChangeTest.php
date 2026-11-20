<?php

use App\Domain\Identity\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;

it('tells the client at login that a password change is required', function () {
    User::factory()->mustChangePassword()->create(['email' => 'new@demo.test']);

    $this->postJson('/api/v1/auth/login', ['email' => 'new@demo.test', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.user.must_change_password', true);
});

it('shows normal accounts as not needing a change', function () {
    actingAsRole(Role::Lecturer);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.must_change_password', false);
});

it('blocks every working endpoint until the password is changed', function () {
    $user = userWithRole(Role::SuperAdmin, ['must_change_password' => true]);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/users')
        ->assertForbidden()
        ->assertJsonPath('code', 'password_change_required');

    $this->getJson('/api/v1/audit-logs')
        ->assertForbidden()
        ->assertJsonPath('code', 'password_change_required');
});

it('still allows /me, change password and logout while a change is pending', function () {
    $user = userWithRole(Role::Lecturer, ['must_change_password' => true]);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.must_change_password', true);
    $this->postJson('/api/v1/auth/logout')->assertNoContent();
});

it('clears the flag when the user changes their password', function () {
    $user = userWithRole(Role::SuperAdmin, ['must_change_password' => true]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/auth/password/change', [
        'current_password' => 'password',
        'password' => 'A-brand-new-Passw0rd',
        'password_confirmation' => 'A-brand-new-Passw0rd',
    ])->assertOk();

    expect($user->fresh()->must_change_password)->toBeFalse();

    $this->getJson('/api/v1/users')->assertOk();
});

it('clears the flag when the user resets a forgotten password', function () {
    $user = User::factory()->mustChangePassword()->create(['email' => 'reset@demo.test']);
    $token = Password::createToken($user);

    $this->postJson('/api/v1/auth/password/reset', [
        'email' => 'reset@demo.test',
        'token' => $token,
        'password' => 'A-brand-new-Passw0rd',
        'password_confirmation' => 'A-brand-new-Passw0rd',
    ])->assertOk();

    expect($user->fresh()->must_change_password)->toBeFalse();
});
