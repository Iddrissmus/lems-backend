<?php

use App\Domain\Identity\Actions\LoginUser;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

function login(array $overrides = []): TestResponse
{
    return test()->postJson('/api/v1/auth/login', array_merge([
        'email' => 'ama@example.com',
        'password' => 'password',
    ], $overrides));
}

beforeEach(function () {
    RateLimiter::clear('login:ama@example.com');
    $this->user = User::factory()->create(['email' => 'ama@example.com']);
});

it('logs in with valid credentials and returns a bearer token', function () {
    login(['device_name' => 'Chrome on Windows'])
        ->assertOk()
        ->assertJsonStructure(['data' => ['token', 'token_type', 'expires_at', 'user' => [
            'id', 'name', 'email', 'status', 'must_change_password', 'roles', 'permissions',
        ]]])
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.id', $this->user->ulid)
        ->assertJsonMissingPath('data.user.password');

    expect($this->user->fresh()->last_login_at)->not->toBeNull()
        ->and($this->user->tokens()->first()->name)->toBe('Chrome on Windows');
});

it('reports the real expiry of the token (12 hours) and enforces it', function () {
    $answer = login()->assertOk()->json('data');

    expect(Carbon\Carbon::parse($answer['expires_at'])->between(now()->addHours(12)->subMinute(), now()->addHours(12)->addMinute()))->toBeTrue();

    $this->travel(11)->hours();
    $this->withToken($answer['token'])->getJson('/api/v1/me')->assertOk();

    $this->travel(2)->hours();
    app('auth')->forgetGuards(); // within one test the guard would remember the user
    $this->withToken($answer['token'])->getJson('/api/v1/me')->assertStatus(401);
});

it('expects the email to be case-insensitive', function () {
    login(['email' => '  AMA@Example.com '])->assertOk();
});

it('rejects a wrong password with a 401 invalid_credentials', function () {
    login(['password' => 'wrong'])
        ->assertStatus(401)
        ->assertJsonPath('code', 'invalid_credentials');
});

it('answers an unknown email exactly like a wrong password', function () {
    $unknown = login(['email' => 'nobody@example.com']);
    $wrong = login(['password' => 'wrong']);

    expect($unknown->status())->toBe($wrong->status())
        ->and($unknown->json())->toBe($wrong->json());
});

it('refuses invited and disabled accounts', function (string $state) {
    $user = User::factory()->{$state}()->create(['email' => 'blocked@example.com']);

    login(['email' => $user->email])->assertStatus(401)->assertJsonPath('code', 'invalid_credentials');
})->with(['invited', 'disabled']);

it('validates the request body', function () {
    $this->postJson('/api/v1/auth/login', [])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['email', 'password']);
});

it('locks the email after repeated failures and answers 429 with Retry-After', function () {
    foreach (range(1, LoginUser::MAX_ATTEMPTS) as $ignored) {
        login(['password' => 'wrong'])->assertStatus(401);
    }

    // Even the right password is refused while locked.
    login()
        ->assertStatus(429)
        ->assertJsonPath('code', 'too_many_requests')
        ->assertHeader('Retry-After');
});

it('locks an unknown email the same way so accounts cannot be discovered', function () {
    RateLimiter::clear('login:ghost@example.com');

    foreach (range(1, LoginUser::MAX_ATTEMPTS) as $ignored) {
        login(['email' => 'ghost@example.com', 'password' => 'x'])->assertStatus(401);
    }

    login(['email' => 'ghost@example.com', 'password' => 'x'])->assertStatus(429);
});

it('clears the failure count after a successful login', function () {
    foreach (range(1, LoginUser::MAX_ATTEMPTS - 1) as $ignored) {
        login(['password' => 'wrong'])->assertStatus(401);
    }

    login()->assertOk();

    login(['password' => 'wrong'])->assertStatus(401);
});
