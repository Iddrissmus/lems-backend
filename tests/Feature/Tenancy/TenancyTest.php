<?php

use App\Domain\Identity\Enums\Role;
use App\Exceptions\TenantNotResolved;
use App\Models\AuditLog;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

// Multi-tenancy is built in and switched off (TENANCY_ENABLED=false). These tests
// prove both halves: off means one college and no change for anyone; on means a
// record of college A is invisible and untouchable for college B.

function tenancyContext(): TenantContext
{
    return app(TenantContext::class);
}

function newTenant(string $slug): Tenant
{
    return Tenant::create(['name' => ucfirst($slug), 'slug' => $slug]);
}

/**
 * A request as the owner of a real token. Real requests start with a fresh app;
 * inside one test the guard would remember the previous caller, so reset it.
 */
function apiAs(string $token): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken($token);
}

function defaultTenantId(): int
{
    return (int) Tenant::where('slug', 'scnmtc')->value('id');
}

describe('with tenancy off (the default)', function () {
    it('has one default tenant that owns everything', function () {
        expect(Tenant::where('slug', 'scnmtc')->exists())->toBeTrue();

        $user = User::factory()->create();
        $entry = AuditLog::create(['event' => 'user.updated']);

        expect($user->tenant_id)->toBe(defaultTenantId())
            ->and($entry->tenant_id)->toBe(defaultTenantId());
    });

    it('scopes every query to the default tenant already', function () {
        $mine = User::factory()->create();
        $other = tenancyContext()->runAs(newTenant('other'), fn () => User::factory()->create());

        expect(User::pluck('id')->all())->toContain($mine->id)->not->toContain($other->id)
            ->and(User::withoutGlobalScope(TenantScope::class)->pluck('id')->all())->toContain($mine->id, $other->id);
    });

    it('seeds the demo users into the default tenant, also through DatabaseSeeder', function () {
        app()['env'] = 'local';

        $this->seed(DatabaseSeeder::class);

        $tenants = User::withoutGlobalScope(TenantScope::class)->where('email', 'like', '%@demo.test')->pluck('tenant_id')->unique();

        expect(User::where('email', 'student@demo.test')->exists())->toBeTrue()
            ->and($tenants->all())->toBe([defaultTenantId()]);
    });

    it('is off unless TENANCY_ENABLED is set', function () {
        expect(config('tenancy.enabled'))->toBeFalse();
    });
});

describe('with tenancy on', function () {
    beforeEach(function () {
        config(['tenancy.enabled' => true]);
        $this->a = newTenant('college-a');
        $this->b = newTenant('college-b');
        $this->inA = fn (callable $fn) => tenancyContext()->runAs($this->a, $fn);
        $this->inB = fn (callable $fn) => tenancyContext()->runAs($this->b, $fn);
    });

    it('gives each user the tenant they were created in', function () {
        $user = ($this->inB)(fn () => User::factory()->create());

        expect($user->tenant_id)->toBe($this->b->id);
    });

    it('hides tenant B users from tenant A, on every kind of lookup', function () {
        $userA = ($this->inA)(fn () => User::factory()->create(['email' => 'a@example.com']));
        $userB = ($this->inB)(fn () => User::factory()->create(['email' => 'b@example.com']));

        ($this->inA)(function () use ($userA, $userB) {
            expect(User::pluck('email')->all())->toBe(['a@example.com'])
                ->and(User::where('email', 'b@example.com')->first())->toBeNull()
                ->and(User::find($userB->id))->toBeNull()
                ->and(User::where('ulid', $userB->ulid)->exists())->toBeFalse()
                ->and(User::count())->toBe(1)
                ->and(User::find($userA->id))->not->toBeNull();
        });

        ($this->inB)(fn () => expect(User::pluck('email')->all())->toBe(['b@example.com']));
    });

    it('fails closed: with no tenant known, nothing is visible and nothing can be saved', function () {
        ($this->inA)(fn () => User::factory()->create());

        expect(tenancyContext()->id())->toBeNull()
            ->and(User::count())->toBe(0)
            ->and(fn () => User::factory()->create())->toThrow(TenantNotResolved::class)
            ->and(fn () => AuditLog::create(['event' => 'user.updated']))->toThrow(TenantNotResolved::class);
    });

    it('refuses to create a record for a different tenant than the current one', function () {
        ($this->inA)(function () {
            $make = fn () => User::factory()->create(['tenant_id' => $this->b->id]);

            expect($make)->toThrow(TenantNotResolved::class);
        });
    });

    it('refuses to move a record to another tenant', function () {
        $user = ($this->inA)(fn () => User::factory()->create());

        ($this->inA)(function () use ($user) {
            $user->tenant_id = $this->b->id;

            expect(fn () => $user->save())->toThrow(TenantNotResolved::class);
        });
    });

    it('does not let mass assignment choose the tenant', function () {
        $user = ($this->inA)(fn () => new User(['name' => 'X', 'email' => 'x@example.com', 'tenant_id' => $this->b->id]));

        expect($user->getAttribute('tenant_id'))->toBeNull();
    });

    it('puts the previous tenant back after runAs, even after an error', function () {
        tenancyContext()->set($this->a);

        expect(fn () => ($this->inB)(fn () => throw new RuntimeException('boom')))->toThrow(RuntimeException::class);
        expect(tenancyContext()->id())->toBe($this->a->id);
    });

    describe('through the API', function () {
        beforeEach(function () {
            $this->adminA = ($this->inA)(fn () => userWithRole(Role::SuperAdmin, ['email' => 'admin.a@example.com']));
            $this->adminB = ($this->inB)(fn () => userWithRole(Role::SuperAdmin, ['email' => 'admin.b@example.com']));
            $this->studentB = ($this->inB)(fn () => userWithRole(Role::Student, ['email' => 'student.b@example.com']));

            // A real token (not actingAs), to prove sign-in works without a tenant known up front.
            $this->tokenA = $this->adminA->createToken('test')->plainTextToken;
            $this->tokenB = $this->adminB->createToken('test')->plainTextToken;
        });

        it('authenticates a token and works out the tenant from its owner', function () {
            apiAs($this->tokenA)->getJson('/api/v1/me')
                ->assertOk()
                ->assertJsonPath('data.email', 'admin.a@example.com');
        });

        it('lists only the users of the caller tenant', function () {
            $emails = collect(apiAs($this->tokenA)->getJson('/api/v1/users')->assertOk()->json('data'))->pluck('email');

            expect($emails->all())->toBe(['admin.a@example.com']);

            $emailsB = collect(apiAs($this->tokenB)->getJson('/api/v1/users')->assertOk()->json('data'))->pluck('email')->sort()->values();

            expect($emailsB->all())->toBe(['admin.b@example.com', 'student.b@example.com']);
        });

        it('answers 404, not 403, for another tenant user (it does not even exist for them)', function () {
            $id = $this->studentB->ulid;

            apiAs($this->tokenA)->getJson("/api/v1/users/{$id}")->assertNotFound();
            apiAs($this->tokenA)->patchJson("/api/v1/users/{$id}", ['name' => 'Hacked'])->assertNotFound();
            apiAs($this->tokenA)->postJson("/api/v1/users/{$id}/disable")->assertNotFound();
            apiAs($this->tokenA)->putJson("/api/v1/users/{$id}/roles", ['roles' => ['lecturer']])->assertNotFound();

            expect($this->studentB->fresh()?->name)->not->toBe('Hacked')
                ->and(($this->inB)(fn () => User::find($this->studentB->id)->status->value))->toBe('active');
        });

        it('shows each tenant only its own audit trail', function () {
            ($this->inA)(fn () => AuditLog::create(['event' => 'user.updated', 'subject_type' => 'user', 'subject_id' => 'only-a']));
            ($this->inB)(fn () => AuditLog::create(['event' => 'user.updated', 'subject_type' => 'user', 'subject_id' => 'only-b']));

            $subjectsA = collect(apiAs($this->tokenA)->getJson('/api/v1/audit-logs?event=user.updated')->assertOk()->json('data'))->pluck('subject.id');
            $subjectsB = collect(apiAs($this->tokenB)->getJson('/api/v1/audit-logs?event=user.updated')->assertOk()->json('data'))->pluck('subject.id');

            expect($subjectsA->all())->toContain('only-a')->not->toContain('only-b')
                ->and($subjectsB->all())->toContain('only-b')->not->toContain('only-a');
        });

        it('records changes made through the API in the callers tenant', function () {
            apiAs($this->tokenA)->postJson('/api/v1/users/invite', [
                'name' => 'New Person', 'email' => 'new.person@example.com', 'roles' => ['lecturer'],
            ])->assertCreated();

            $created = User::withoutGlobalScope(TenantScope::class)->where('email', 'new.person@example.com')->firstOrFail();
            $entry = AuditLog::withoutGlobalScope(TenantScope::class)->where('event', 'user.invited')->latest('id')->firstOrFail();

            expect($created->tenant_id)->toBe($this->a->id)
                ->and($entry->tenant_id)->toBe($this->a->id);
        });

        it('leaves no tenant behind after a request', function () {
            apiAs($this->tokenA)->getJson('/api/v1/me')->assertOk();

            expect(tenancyContext()->id())->toBeNull();
        });
    });
});
