<?php

use App\Domain\Audit\AuditLogger;
use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Identity\Enums\Role;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

describe('access', function () {
    it('lets super_admin read the audit log', function () {
        actingAsRole(Role::SuperAdmin);

        $this->getJson('/api/v1/audit-logs')->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
    });

    it('forbids everyone else, including head_of_academics', function (Role $role) {
        actingAsRole($role);

        $this->getJson('/api/v1/audit-logs')->assertStatus(403)->assertJsonPath('code', 'forbidden');
    })->with([Role::HeadOfAcademics, Role::Registrar, Role::Bursar, Role::Lecturer, Role::Student]);

    it('requires a token', function () {
        $this->getJson('/api/v1/audit-logs')->assertStatus(401);
    });

    it('offers no way to create, change or delete entries', function () {
        actingAsRole(Role::SuperAdmin);
        $entry = app(AuditLogger::class)->record(AuditEvent::Logout);

        $this->postJson('/api/v1/audit-logs', [])->assertStatus(405);
        $this->putJson("/api/v1/audit-logs/{$entry->ulid}", [])->assertStatus(404);
        $this->deleteJson("/api/v1/audit-logs/{$entry->ulid}")->assertStatus(404);
    });
});

describe('listing', function () {
    beforeEach(function () {
        $this->admin = actingAsRole(Role::SuperAdmin);
        $this->audit = app(AuditLogger::class);

        $this->student = userWithRole(Role::Student);
        $this->audit->record(AuditEvent::UserRolesChanged, $this->admin, $this->student, ['roles' => ['student']], ['roles' => ['lecturer']]);
        $this->audit->record(AuditEvent::UserDisabled, $this->admin, $this->student, ['status' => 'active'], ['status' => 'disabled']);
        $this->audit->record(AuditEvent::LoginSucceeded, $this->student, $this->student);
    });

    it('returns entries newest first with a safe, complete shape', function () {
        $response = $this->getJson('/api/v1/audit-logs')->assertOk();

        expect(collect($response->json('data'))->pluck('event')->take(3)->all())
            ->toBe(['auth.login', 'user.disabled', 'user.roles_changed']);

        $response->assertJsonPath('data.1.actor.email', $this->admin->email)
            ->assertJsonPath('data.1.subject', ['type' => 'user', 'id' => $this->student->ulid])
            ->assertJsonPath('data.1.after', ['status' => 'disabled'])
            ->assertJsonMissingPath('data.1.actor_id')
            ->assertJsonMissingPath('data.1.ulid');

        // Internal numeric ids and secrets never appear.
        expect($response->getContent())->not->toContain('password')->not->toContain('"id":1,');
    });

    it('filters by exact event and by group', function () {
        $this->getJson('/api/v1/audit-logs?event=user.disabled')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/audit-logs?event=user.*')->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/audit-logs?event=auth.*')->assertJsonCount(1, 'data');
    });

    it('filters by actor and by subject', function () {
        $this->getJson("/api/v1/audit-logs?actor={$this->admin->ulid}")->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/audit-logs?subject_id={$this->student->ulid}&subject_type=user")->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/audit-logs?subject_id=01ARZ3NDEKTSV4RRFFQ69G5FAV')->assertJsonCount(0, 'data');
    });

    it('filters by date range', function () {
        $this->travelTo(now()->addDays(3));
        $this->audit->record(AuditEvent::Logout, $this->student, $this->student);
        $from = urlencode(now()->subHour()->toIso8601String());

        $this->getJson("/api/v1/audit-logs?from={$from}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.event', 'auth.logout');
    });

    it('paginates and caps the page size', function () {
        $this->getJson('/api/v1/audit-logs?per_page=2')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.per_page', 2);

        $this->getJson('/api/v1/audit-logs?per_page=101')->assertStatus(422)->assertJsonValidationErrors('per_page');
    });

    it('rejects malformed filters', function () {
        $this->getJson('/api/v1/audit-logs?event=DROP TABLE')->assertStatus(422)->assertJsonValidationErrors('event');
        $this->getJson('/api/v1/audit-logs?actor=short')->assertStatus(422)->assertJsonValidationErrors('actor');
        $this->getJson('/api/v1/audit-logs?from=2026-02-01&to=2026-01-01')->assertStatus(422)->assertJsonValidationErrors('to');
    });

    it('treats a wildcard-looking filter literally', function () {
        // Only a trailing ".*" is a group; "%" never reaches the query as a wildcard.
        $this->getJson('/api/v1/audit-logs?event=user%25')->assertStatus(422);
    });

    it('loads actors without one query per row (no N+1)', function () {
        foreach (range(1, 10) as $ignored) {
            $this->audit->record(AuditEvent::Logout, userWithRole(Role::Student));
        }

        DB::enableQueryLog();
        $this->getJson('/api/v1/audit-logs?per_page=50')->assertOk();
        $userQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_starts_with($q['query'], 'select * from `users`'))->count();
        DB::disableQueryLog();

        // One for the signed-in user, one to load every actor at once.
        expect($userQueries)->toBeLessThanOrEqual(3);
    });
});

it('truncates an oversized user agent', function () {
    $this->withHeaders(['User-Agent' => str_repeat('a', 600)])->getJson('/api/v1/ready');

    $entry = app(AuditLogger::class)->record(AuditEvent::Logout);

    expect(strlen((string) $entry->user_agent))->toBeLessThanOrEqual(255);
});

it('has a stored count that matches what the API reports', function () {
    actingAsRole(Role::SuperAdmin);
    app(AuditLogger::class)->record(AuditEvent::Logout);

    $this->getJson('/api/v1/audit-logs')->assertJsonPath('meta.total', AuditLog::count());
});
