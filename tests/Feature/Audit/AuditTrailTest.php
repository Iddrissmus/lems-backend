<?php

use App\Domain\Audit\AuditLogger;
use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Identity\Actions\InviteUser;
use App\Domain\Identity\Actions\LoginUser;
use App\Domain\Identity\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

function lastAudit(AuditEvent $event): ?AuditLog
{
    return AuditLog::where('event', $event->value)->latest('id')->first();
}

describe('authentication', function () {
    beforeEach(function () {
        RateLimiter::clear('login:ama@example.com');
        $this->user = User::factory()->create(['email' => 'ama@example.com']);
    });

    it('records a successful login with actor, subject, IP and user agent', function () {
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 TestBrowser'])
            ->postJson('/api/v1/auth/login', ['email' => 'ama@example.com', 'password' => 'password'])
            ->assertOk();

        $entry = lastAudit(AuditEvent::LoginSucceeded);

        expect($entry->actor_id)->toBe($this->user->id)
            ->and($entry->subject_type)->toBe('user')
            ->and($entry->subject_id)->toBe($this->user->ulid)
            ->and($entry->ip_address)->not->toBeNull()
            ->and($entry->user_agent)->toBe('Mozilla/5.0 TestBrowser');
    });

    it('records a failed login with the email but never the password', function () {
        $this->postJson('/api/v1/auth/login', ['email' => 'ama@example.com', 'password' => 'my-secret-guess'])
            ->assertStatus(401);

        $entry = lastAudit(AuditEvent::LoginFailed);

        expect($entry->actor_id)->toBeNull()
            ->and($entry->after)->toBe(['email' => 'ama@example.com'])
            ->and(json_encode($entry->toArray()))->not->toContain('my-secret-guess');
    });

    it('records the lockout once, when it starts', function () {
        foreach (range(1, LoginUser::MAX_ATTEMPTS) as $ignored) {
            $this->postJson('/api/v1/auth/login', ['email' => 'ama@example.com', 'password' => 'x']);
        }
        // Blocked attempts after the lock must not flood the log.
        $this->postJson('/api/v1/auth/login', ['email' => 'ama@example.com', 'password' => 'x'])->assertStatus(429);
        $this->postJson('/api/v1/auth/login', ['email' => 'ama@example.com', 'password' => 'x'])->assertStatus(429);

        expect(AuditLog::where('event', 'auth.login_locked')->count())->toBe(1)
            ->and(AuditLog::where('event', 'auth.login_failed')->count())->toBe(LoginUser::MAX_ATTEMPTS)
            ->and(lastAudit(AuditEvent::LoginLockedOut)->after)->toBe([
                'email' => 'ama@example.com',
                'locked_for_seconds' => LoginUser::LOCKOUT_SECONDS,
            ]);
    });

    it('records logout', function () {
        $token = $this->user->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        expect(lastAudit(AuditEvent::Logout)->actor_id)->toBe($this->user->id);
    });

    it('records a password change without any password in the entry', function () {
        $token = $this->user->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'password',
            'password' => 'Brand-New-Passw0rd',
            'password_confirmation' => 'Brand-New-Passw0rd',
        ])->assertOk();

        $entry = lastAudit(AuditEvent::PasswordChanged);

        expect($entry->actor_id)->toBe($this->user->id)
            ->and($entry->before)->toBeNull()
            ->and($entry->after)->toBeNull();
    });

    it('records a password reset', function () {
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'ama@example.com',
            'token' => Password::createToken($this->user),
            'password' => 'Brand-New-Passw0rd',
            'password_confirmation' => 'Brand-New-Passw0rd',
        ])->assertOk();

        expect(lastAudit(AuditEvent::PasswordReset)->subject_id)->toBe($this->user->ulid);
    });

    it('records an accepted invitation', function () {
        Notification::fake();
        $token = null;
        app(InviteUser::class)->execute('Kofi', 'kofi@example.com', [Role::Student->value]);
        Notification::assertSentTo(User::where('email', 'kofi@example.com')->first(), InvitationNotification::class,
            function ($n, $c, $notifiable) use (&$token) {
                parse_str((string) parse_url($n->toMail($notifiable)->actionUrl, PHP_URL_QUERY), $q);
                $token = $q['token'];

                return true;
            });

        $this->postJson('/api/v1/auth/invitations/accept', [
            'token' => $token, 'password' => 'Welcome-Passw0rd-7', 'password_confirmation' => 'Welcome-Passw0rd-7',
        ])->assertOk();

        expect(lastAudit(AuditEvent::InvitationAccepted)->subject_id)
            ->toBe(User::where('email', 'kofi@example.com')->first()->ulid);
    });
});

describe('user management', function () {
    it('records who invited whom, with the roles', function () {
        Notification::fake();
        $actor = actingAsRole(Role::HeadOfAcademics);

        $this->postJson('/api/v1/users/invite', [
            'name' => 'Efua', 'email' => 'efua@example.com', 'roles' => [Role::Lecturer->value],
        ])->assertStatus(201);

        $entry = lastAudit(AuditEvent::UserInvited);

        expect($entry->actor_id)->toBe($actor->id)
            ->and($entry->subject_id)->toBe(User::where('email', 'efua@example.com')->first()->ulid)
            ->and($entry->after)->toBe(['email' => 'efua@example.com', 'roles' => ['lecturer']]);
    });

    it('records the name change with before and after, and skips a no-op', function () {
        actingAsRole(Role::HeadOfAcademics);
        $target = userWithRole(Role::Lecturer, ['name' => 'Old Name']);

        $this->patchJson("/api/v1/users/{$target->ulid}", ['name' => 'New Name'])->assertOk();
        $this->patchJson("/api/v1/users/{$target->ulid}", ['name' => 'New Name'])->assertOk();

        expect(AuditLog::where('event', 'user.updated')->count())->toBe(1)
            ->and(lastAudit(AuditEvent::UserUpdated)->before)->toBe(['name' => 'Old Name'])
            ->and(lastAudit(AuditEvent::UserUpdated)->after)->toBe(['name' => 'New Name']);
    });

    it('records role changes with before and after', function () {
        $actor = actingAsRole(Role::HeadOfAcademics);
        $target = userWithRole(Role::Student);

        $this->putJson("/api/v1/users/{$target->ulid}/roles", ['roles' => [Role::Lecturer->value]])->assertOk();

        $entry = lastAudit(AuditEvent::UserRolesChanged);

        expect($entry->actor_id)->toBe($actor->id)
            ->and($entry->subject_id)->toBe($target->ulid)
            ->and($entry->before)->toBe(['roles' => ['student']])
            ->and($entry->after)->toBe(['roles' => ['lecturer']]);
    });

    it('records disable and enable', function () {
        actingAsRole(Role::HeadOfAcademics);
        $target = userWithRole(Role::Lecturer);

        $this->postJson("/api/v1/users/{$target->ulid}/disable")->assertOk();
        $this->postJson("/api/v1/users/{$target->ulid}/enable")->assertOk();

        expect(lastAudit(AuditEvent::UserDisabled)->after)->toBe(['status' => 'disabled'])
            ->and(lastAudit(AuditEvent::UserEnabled)->before)->toBe(['status' => 'disabled'])
            ->and(lastAudit(AuditEvent::UserEnabled)->after)->toBe(['status' => 'active']);
    });

    it('writes nothing for a request that was refused', function () {
        actingAsRole(Role::Lecturer);
        $target = userWithRole(Role::Student);

        $this->postJson("/api/v1/users/{$target->ulid}/disable")->assertStatus(403);
        $this->putJson("/api/v1/users/{$target->ulid}/roles", ['roles' => ['lecturer']])->assertStatus(403);

        expect(AuditLog::whereIn('event', ['user.disabled', 'user.roles_changed'])->count())->toBe(0);
    });
});

describe('integrity', function () {
    it('saves the change and its audit entry together, or neither', function () {
        actingAsRole(Role::HeadOfAcademics);
        $target = userWithRole(Role::Student);

        // Make the audit write fail: the role change must be rolled back with it.
        $this->mock(AuditLogger::class)->shouldReceive('record')->andThrow(new RuntimeException('audit store down'));

        $this->putJson("/api/v1/users/{$target->ulid}/roles", ['roles' => [Role::Lecturer->value]])->assertStatus(500);

        expect($target->fresh()->getRoleNames()->all())->toBe(['student']);
    });

    it('leaves no audit entry when the action itself is refused', function () {
        $me = actingAsRole(Role::SuperAdmin);

        $this->putJson("/api/v1/users/{$me->ulid}/roles", ['roles' => [Role::Lecturer->value]])->assertStatus(409);

        expect(AuditLog::where('event', 'user.roles_changed')->count())->toBe(0);
    });

    it('cannot be edited or deleted through the model', function () {
        $entry = app(AuditLogger::class)->record(AuditEvent::LoginSucceeded);

        expect(fn () => $entry->update(['event' => 'tampered']))->toThrow(LogicException::class, 'cannot be changed')
            ->and(fn () => $entry->delete())->toThrow(LogicException::class, 'cannot be deleted');

        expect($entry->fresh()->event)->toBe('auth.login');
    });

    it('keeps the entry readable if the actor account is later deleted', function () {
        $user = userWithRole(Role::Lecturer);
        $entry = app(AuditLogger::class)->record(AuditEvent::Logout, $user, $user);

        $user->delete(); // soft delete, as the app always does

        expect($entry->fresh()->actor->id)->toBe($user->id);
    });
});
