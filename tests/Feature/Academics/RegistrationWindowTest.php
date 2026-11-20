<?php

use App\Domain\Identity\Enums\Role;
use App\Models\AuditLog;
use App\Models\Programme;
use App\Models\RegistrationWindow;
use App\Models\Semester;
use App\Models\StudentProfile;

// Registration windows (docs/api/demo-contract.md section 4): when students may register.

beforeEach(function () {
    $this->semester = Semester::factory()->create(['is_current' => true]);
    $this->rgn = Programme::factory()->create(['code' => 'RGN']);
});

function openingIn(string $opens, string $closes, array $extra = []): array
{
    return array_merge(['opens_at' => now()->add($opens)->toIso8601String(), 'closes_at' => now()->add($closes)->toIso8601String()], $extra);
}

describe('who may manage windows', function () {
    it('lets super_admin and head_of_academics list, open and close them', function (Role $role) {
        actingAsRole($role);
        $window = RegistrationWindow::factory()->create(['semester_id' => $this->semester->id]);

        $this->getJson('/api/v1/registration-windows')->assertOk();
        $this->postJson('/api/v1/registration-windows', openingIn('1 day', '10 days', ['semester_id' => $this->semester->ulid]))->assertCreated();
        $this->postJson("/api/v1/registration-windows/{$window->ulid}/close")->assertOk();
    })->with([Role::SuperAdmin, Role::HeadOfAcademics]);

    it('keeps everyone else out', function (Role $role) {
        actingAsRole($role);
        $window = RegistrationWindow::factory()->create(['semester_id' => $this->semester->id]);

        $this->getJson('/api/v1/registration-windows')->assertForbidden()->assertJsonPath('code', 'forbidden');
        $this->postJson('/api/v1/registration-windows', openingIn('1 day', '10 days', ['semester_id' => $this->semester->ulid]))->assertForbidden();
        $this->postJson("/api/v1/registration-windows/{$window->ulid}/close")->assertForbidden();

        expect($window->fresh()->closed_at)->toBeNull();
    })->with([Role::IctAdmin, Role::Registrar, Role::AcademicAffairs, Role::Lecturer, Role::Bursar, Role::Student]);

    it('needs a token', function () {
        $this->getJson('/api/v1/registration-windows')->assertUnauthorized();
    });
});

describe('opening a window', function () {
    beforeEach(fn () => actingAsRole(Role::HeadOfAcademics));

    it('creates it with the shape the registration setup screen needs', function () {
        $response = $this->postJson('/api/v1/registration-windows', openingIn('-1 hour', '14 days', [
            'semester_id' => $this->semester->ulid,
            'audience' => [['programme_id' => $this->rgn->ulid, 'year_of_study' => 1]],
        ]))->assertCreated();

        $response->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.semester.id', $this->semester->ulid)
            ->assertJsonPath('data.semester.number', 1)
            ->assertJsonPath('data.closed_at', null)
            ->assertJsonPath('data.audience', [['programme' => ['id' => $this->rgn->ulid, 'code' => 'RGN', 'name' => $this->rgn->name], 'year_of_study' => 1]]);

        expect(strlen($response->json('data.id')))->toBe(26);
    });

    it('treats a missing or empty audience as everyone', function () {
        $this->postJson('/api/v1/registration-windows', openingIn('1 day', '5 days', ['semester_id' => $this->semester->ulid]))
            ->assertCreated()->assertJsonPath('data.audience', [])->assertJsonPath('data.status', 'not_yet_open');

        $this->postJson('/api/v1/registration-windows', openingIn('1 day', '5 days', ['semester_id' => $this->semester->ulid, 'audience' => []]))
            ->assertCreated()->assertJsonPath('data.audience', []);
    });

    it('stores times in UTC whatever offset was sent', function () {
        $this->postJson('/api/v1/registration-windows', [
            'semester_id' => $this->semester->ulid,
            'opens_at' => '2030-11-23T10:00:00+02:00',
            'closes_at' => '2030-12-07T17:00:00+02:00',
        ])->assertCreated()
            ->assertJsonPath('data.opens_at', '2030-11-23T08:00:00+00:00')
            ->assertJsonPath('data.closes_at', '2030-12-07T15:00:00+00:00');
    });

    it('refuses bad input with field errors', function (array $change, string $field) {
        $body = array_merge(openingIn('1 day', '5 days', ['semester_id' => $this->semester->ulid]), $change);

        $this->postJson('/api/v1/registration-windows', $body)
            ->assertStatus(422)->assertJsonPath('code', 'validation_failed')->assertJsonValidationErrorFor($field, 'errors');
    })->with([
        'closing before opening' => [['closes_at' => now()->subDay()->toIso8601String()], 'closes_at'],
        'no semester' => [['semester_id' => null], 'semester_id'],
        'unknown semester' => [['semester_id' => '01jd2k9m4q7x3v8n5r6t1y0zzz'], 'semester_id'],
        'unknown programme' => [['audience' => [['programme_id' => '01jd2k9m4q7x3v8n5r6t1y0zzz', 'year_of_study' => 1]]], 'audience.0.programme_id'],
        'year out of range' => [['audience' => [['programme_id' => '01jd2k9m4q7x3v8n5r6t1y0zab', 'year_of_study' => 11]]], 'audience.0.year_of_study'],
        'not a date' => [['opens_at' => 'tomorrow-ish'], 'opens_at'],
    ]);

    it('writes an audit entry saying who opened it and for whom', function () {
        $actor = actingAsRole(Role::HeadOfAcademics);

        $id = $this->postJson('/api/v1/registration-windows', openingIn('-1 hour', '14 days', [
            'semester_id' => $this->semester->ulid,
            'audience' => [['programme_id' => $this->rgn->ulid, 'year_of_study' => 2]],
        ]))->assertCreated()->json('data.id');

        $entry = AuditLog::where('event', 'registration_window.created')->firstOrFail();

        expect($entry->actor_id)->toBe($actor->id)
            ->and($entry->subject_type)->toBe('registration_window')
            ->and($entry->subject_id)->toBe($id)
            ->and($entry->after['audience'])->toBe([['programme' => 'RGN', 'year_of_study' => 2]]);
    });

    it('does nothing twice when retried with the same Idempotency-Key', function () {
        $body = openingIn('1 day', '5 days', ['semester_id' => $this->semester->ulid]);
        $headers = ['Idempotency-Key' => 'window-retry-0001'];

        $first = $this->postJson('/api/v1/registration-windows', $body, $headers)->assertCreated();
        $second = $this->postJson('/api/v1/registration-windows', $body, $headers)->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

        expect($second->json('data.id'))->toBe($first->json('data.id'))
            ->and(RegistrationWindow::count())->toBe(1)
            ->and(AuditLog::where('event', 'registration_window.created')->count())->toBe(1);
    });
});

describe('the status is worked out from the clock', function () {
    it('is not_yet_open, open, then closed', function () {
        $window = RegistrationWindow::factory()->create(['semester_id' => $this->semester->id, 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(5)]);

        expect($window->status())->toBe('not_yet_open')->and($window->isOpen())->toBeFalse();

        $this->travel(2)->days();
        expect($window->fresh()->status())->toBe('open')->and($window->fresh()->isOpen())->toBeTrue();

        $this->travel(5)->days();
        expect($window->fresh()->status())->toBe('closed');
    });

    it('is closed as soon as staff close it, even before its closing time', function () {
        $window = RegistrationWindow::factory()->create(['semester_id' => $this->semester->id]);
        $window->forceFill(['closed_at' => now()])->save();

        expect($window->status())->toBe('closed');
    });

    it('knows whether it covers a student', function () {
        $first = StudentProfile::factory()->create(['programme_id' => $this->rgn->id, 'year_of_study' => 1]);
        $second = StudentProfile::factory()->create(['programme_id' => $this->rgn->id, 'year_of_study' => 2]);
        $everyone = RegistrationWindow::factory()->create(['semester_id' => $this->semester->id, 'audience' => null]);
        $yearOne = RegistrationWindow::factory()->create(['semester_id' => $this->semester->id, 'audience' => [['programme_id' => $this->rgn->id, 'year_of_study' => 1]]]);

        expect($everyone->coversStudent($second))->toBeTrue()
            ->and($yearOne->coversStudent($first))->toBeTrue()
            ->and($yearOne->coversStudent($second))->toBeFalse();
    });
});

describe('listing windows', function () {
    beforeEach(function () {
        actingAsRole(Role::HeadOfAcademics);
        $this->upcoming = RegistrationWindow::factory()->create(['semester_id' => $this->semester->id, 'opens_at' => now()->addDays(2), 'closes_at' => now()->addDays(9)]);
        $this->open = RegistrationWindow::factory()->create(['semester_id' => $this->semester->id, 'opens_at' => now()->subDay(), 'closes_at' => now()->addDays(9)]);
        $this->expired = RegistrationWindow::factory()->create(['semester_id' => $this->semester->id, 'opens_at' => now()->subDays(20), 'closes_at' => now()->subDays(10)]);
        $this->stopped = RegistrationWindow::factory()->create(['semester_id' => $this->semester->id, 'opens_at' => now()->subDays(3), 'closes_at' => now()->addDays(9)]);
        $this->stopped->forceFill(['closed_at' => now()])->save();
        $this->otherSemester = Semester::factory()->create(['academic_year_id' => $this->semester->academic_year_id, 'number' => 2]);
        RegistrationWindow::factory()->create(['semester_id' => $this->otherSemester->id]);
    });

    it('filters by status', function () {
        $ids = fn (string $status) => collect($this->getJson("/api/v1/registration-windows?status={$status}&semester={$this->semester->ulid}")->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

        expect($ids('open'))->toBe([$this->open->ulid])
            ->and($ids('not_yet_open'))->toBe([$this->upcoming->ulid])
            ->and($ids('closed'))->toBe(collect([$this->expired->ulid, $this->stopped->ulid])->sort()->values()->all());
    });

    it('filters by semester, newest opening first, and pages', function () {
        $this->getJson("/api/v1/registration-windows?semester={$this->semester->ulid}")->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.id', $this->upcoming->ulid)
            ->assertJsonPath('meta.total', 4);

        $this->getJson('/api/v1/registration-windows?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 5);
    });

    it('refuses an unknown status', function () {
        $this->getJson('/api/v1/registration-windows?status=sometimes')->assertStatus(422)->assertJsonPath('code', 'validation_failed');
    });
});

describe('closing a window', function () {
    it('closes it early and records who did it', function () {
        $actor = actingAsRole(Role::HeadOfAcademics);
        $window = RegistrationWindow::factory()->create(['semester_id' => $this->semester->id]);

        $this->postJson("/api/v1/registration-windows/{$window->ulid}/close")
            ->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.id', $window->ulid);

        $fresh = $window->fresh();
        $entry = AuditLog::where('event', 'registration_window.closed')->firstOrFail();

        expect($fresh->closed_at)->not->toBeNull()
            ->and($fresh->closed_by)->toBe($actor->id)
            ->and($entry->actor_id)->toBe($actor->id)
            ->and($entry->subject_id)->toBe($window->ulid);
    });

    it('does nothing when it is already closed, and says so with 200', function () {
        actingAsRole(Role::HeadOfAcademics);
        $expired = RegistrationWindow::factory()->create(['semester_id' => $this->semester->id, 'opens_at' => now()->subDays(20), 'closes_at' => now()->subDays(10)]);

        $this->postJson("/api/v1/registration-windows/{$expired->ulid}/close")->assertOk()->assertJsonPath('data.status', 'closed');
        $this->postJson("/api/v1/registration-windows/{$expired->ulid}/close")->assertOk();

        expect($expired->fresh()->closed_at)->toBeNull()
            ->and(AuditLog::where('event', 'registration_window.closed')->count())->toBe(0);
    });

    it('answers 404 for an id that does not exist, and never accepts the numeric id', function () {
        actingAsRole(Role::HeadOfAcademics);
        $window = RegistrationWindow::factory()->create(['semester_id' => $this->semester->id]);

        $this->postJson('/api/v1/registration-windows/01jd2k9m4q7x3v8n5r6t1y0zzz/close')->assertNotFound();
        $this->postJson("/api/v1/registration-windows/{$window->id}/close")->assertNotFound();
    });
});

describe('between colleges', function () {
    it('shows and lets a college close only its own windows', function () {
        config(['tenancy.enabled' => true]);
        $a = newTenant('college-a');
        $b = newTenant('college-b');

        [$headA, $windowA] = tenancyContext()->runAs($a, fn () => [
            userWithRole(Role::HeadOfAcademics),
            RegistrationWindow::factory()->create(),
        ]);
        $windowB = tenancyContext()->runAs($b, fn () => RegistrationWindow::factory()->create());
        $token = $headA->createToken('t')->plainTextToken;

        $ids = collect(apiAs($token)->getJson('/api/v1/registration-windows')->assertOk()->json('data'))->pluck('id')->all();

        expect($ids)->toBe([$windowA->ulid]);

        apiAs($token)->postJson("/api/v1/registration-windows/{$windowB->ulid}/close")->assertNotFound();

        expect($windowB->fresh()?->closed_at)->toBeNull();
    });
});
