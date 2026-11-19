<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

beforeEach(function () {
    Route::prefix('api/v1/_test')->group(function () {
        Route::get('validation', fn () => throw ValidationException::withMessages(['email' => ['The email is required.']]));
        Route::get('unauthenticated', fn () => throw new AuthenticationException);
        Route::get('forbidden', fn () => throw new AuthorizationException);
        Route::get('model-missing', fn () => throw new ModelNotFoundException);
        Route::get('conflict', fn () => throw new ConflictHttpException('Invoice already paid.'));
        Route::get('throttled', fn () => throw new TooManyRequestsHttpException(30));
        Route::get('boom', fn () => throw new RuntimeException('secret database password'));
        Route::post('only-post', fn () => 'ok');
    });
});

function assertEnvelope($response, int $status, string $code): void
{
    $response->assertStatus($status)
        ->assertExactJsonStructure(['message', 'errors', 'code'])
        ->assertJsonPath('code', $code);
}

it('formats validation errors as 422 with field errors', function () {
    $response = $this->getJson('/api/v1/_test/validation');

    assertEnvelope($response, 422, 'validation_failed');
    $response->assertJsonPath('errors.email.0', 'The email is required.');
});

it('formats unauthenticated as 401', function () {
    assertEnvelope($this->getJson('/api/v1/_test/unauthenticated'), 401, 'unauthenticated');
});

it('formats forbidden as 403', function () {
    assertEnvelope($this->getJson('/api/v1/_test/forbidden'), 403, 'forbidden');
});

it('formats unknown routes as 404', function () {
    assertEnvelope($this->getJson('/api/v1/nothing-here'), 404, 'not_found');
});

it('formats a missing model as 404', function () {
    assertEnvelope($this->getJson('/api/v1/_test/model-missing'), 404, 'not_found');
});

it('formats a wrong method as 405', function () {
    assertEnvelope($this->getJson('/api/v1/_test/only-post'), 405, 'method_not_allowed');
});

it('formats conflicts as 409 and keeps our message', function () {
    $response = $this->getJson('/api/v1/_test/conflict');

    assertEnvelope($response, 409, 'conflict');
    $response->assertJsonPath('message', 'Invoice already paid.');
});

it('formats throttling as 429 and keeps Retry-After', function () {
    $response = $this->getJson('/api/v1/_test/throttled');

    assertEnvelope($response, 429, 'too_many_requests');
    $response->assertHeader('Retry-After', '30');
});

it('hides internals on unexpected errors when debug is off', function () {
    config(['app.debug' => false]);

    $response = $this->getJson('/api/v1/_test/boom');

    assertEnvelope($response, 500, 'server_error');
    expect($response->getContent())->not->toContain('secret database password');
});

it('always returns errors as an object', function () {
    $this->getJson('/api/v1/nothing-here')->assertSee('"errors":{}', false);
});
