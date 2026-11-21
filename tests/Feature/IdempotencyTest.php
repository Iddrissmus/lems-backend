<?php

use App\Domain\Identity\Enums\Role;
use App\Models\AuditLog;
use App\Models\IdempotencyKey;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

// Idempotency-Key (docs/api/demo-contract.md section 1): a retried write does nothing twice.
// POST /users/invite is used as the write because it creates a user and an audit entry.

const KEY = 'a1b2c3d4-e5f6-4789-a012-3456789abcde';

function invite(string $email = 'retry@example.com', ?string $key = KEY): TestResponse
{
    return test()->postJson('/api/v1/users/invite', ['name' => 'Retry Person', 'email' => $email, 'roles' => ['lecturer']], $key === null ? [] : ['Idempotency-Key' => $key]);
}

beforeEach(fn () => actingAsRole(Role::SuperAdmin));

it('does nothing twice when the same request is sent again with the same key', function () {
    $first = invite()->assertCreated();
    $second = invite();

    $second->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

    expect($second->json())->toBe($first->json())
        ->and($first->headers->get('Idempotent-Replayed'))->toBeNull()
        ->and(User::where('email', 'retry@example.com')->count())->toBe(1)
        ->and(AuditLog::where('event', 'user.invited')->count())->toBe(1);
});

it('treats a request without the header as a new one every time', function () {
    $first = invite(key: null)->assertCreated();
    $second = invite(key: null)->assertCreated(); // re-inviting a not-yet-accepted account is allowed

    expect($second->headers->get('Idempotent-Replayed'))->toBeNull()
        ->and(IdempotencyKey::count())->toBe(0)
        ->and(AuditLog::where('event', 'user.invited')->count())->toBe(2)
        ->and($first->json('data.id'))->toBe($second->json('data.id'));
});

it('refuses the same key for a different request', function () {
    invite('first@example.com')->assertCreated();

    invite('second@example.com')->assertStatus(422)->assertJsonPath('code', 'idempotency_key_reused');

    expect(User::where('email', 'second@example.com')->exists())->toBeFalse();
});

it('refuses the same key on a different endpoint', function () {
    invite()->assertCreated();

    $this->postJson('/api/v1/auth/logout', [], ['Idempotency-Key' => KEY])->assertStatus(422)->assertJsonPath('code', 'idempotency_key_reused');
});

it('keeps keys separate per user', function () {
    invite('mine@example.com')->assertCreated();

    actingAsRole(Role::SuperAdmin);
    $other = invite('theirs@example.com')->assertCreated();

    expect($other->headers->get('Idempotent-Replayed'))->toBeNull()
        ->and(IdempotencyKey::count())->toBe(2);
});

it('does not burn the key when the request fails, so it can be fixed and retried', function () {
    $this->postJson('/api/v1/users/invite', ['name' => 'X', 'email' => 'not-an-email', 'roles' => ['lecturer']], ['Idempotency-Key' => KEY])
        ->assertStatus(422)->assertJsonPath('code', 'validation_failed');

    expect(IdempotencyKey::count())->toBe(0);

    invite('fixed@example.com')->assertCreated();
});

it('answers 409 while the first request with that key is still running', function () {
    IdempotencyKey::create([
        'user_id' => auth()->id() ?? User::first()->id,
        'key' => KEY,
        'method' => 'POST',
        'path' => 'api/v1/users/invite',
        'request_hash' => hash('sha256', 'POST|api/v1/users/invite|'.json_encode(['name' => 'Retry Person', 'email' => 'retry@example.com', 'roles' => ['lecturer']])),
    ]);

    invite()->assertStatus(409)->assertJsonPath('code', 'request_in_progress');
});

it('frees a key after 24 hours', function () {
    invite('old@example.com')->assertCreated();
    IdempotencyKey::query()->update(['created_at' => now()->subHours(25)]);

    $again = invite('new@example.com');

    $again->assertCreated();
    expect($again->headers->get('Idempotent-Replayed'))->toBeNull()
        ->and(IdempotencyKey::count())->toBe(1);
});

it('stores the answer encrypted, because some answers hold a one-time password', function () {
    invite('secret.person@example.com')->assertCreated();

    $raw = DB::table('idempotency_keys')->value('response');

    expect($raw)->not->toContain('secret.person@example.com')
        ->and(IdempotencyKey::first()->response)->toContain('secret.person@example.com');
});

it('belongs to a tenant like everything else', function () {
    invite()->assertCreated();

    expect(IdempotencyKey::first()->tenant_id)->toBe(defaultTenantId());
});

it('refuses a malformed key', function () {
    foreach (['short', str_repeat('a', 101), 'has spaces in it!'] as $bad) {
        invite(key: $bad)->assertStatus(422)->assertJsonPath('code', 'validation_failed');
    }
});

it('ignores the header on reads', function () {
    $this->getJson('/api/v1/users', ['Idempotency-Key' => KEY])->assertOk();

    expect(IdempotencyKey::count())->toBe(0);
});

it('can be pruned after 24 hours', function () {
    invite()->assertCreated();
    IdempotencyKey::query()->update(['created_at' => now()->subHours(25)]);
    invite('recent@example.com', 'ffffffff-0000-4000-8000-000000000001')->assertCreated();

    $this->artisan('model:prune', ['--model' => [IdempotencyKey::class]])->assertSuccessful();

    expect(IdempotencyKey::count())->toBe(1);
});
