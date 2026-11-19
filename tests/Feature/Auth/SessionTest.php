<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires a token for /me', function () {
    $this->getJson('/api/v1/me')
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated');
});

it('returns the signed-in user from /me', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->ulid)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonMissingPath('data.password');
});

it('rejects an expired token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->travel(13)->hours();

    $this->withToken($token)->getJson('/api/v1/me')->assertStatus(401);
});

it('blocks a disabled account that still holds a token', function () {
    $user = User::factory()->disabled()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me')
        ->assertStatus(403)
        ->assertJsonPath('code', 'account_disabled');
});

it('logs out by revoking only the current token', function () {
    $user = User::factory()->create();
    $current = $user->createToken('this-device')->plainTextToken;
    $user->createToken('other-device');

    $this->withToken($current)->postJson('/api/v1/auth/logout')->assertNoContent();

    expect($user->tokens()->count())->toBe(1)
        ->and($user->tokens()->first()->name)->toBe('other-device');
});

it('requires a token to log out', function () {
    $this->postJson('/api/v1/auth/logout')->assertStatus(401);
});
