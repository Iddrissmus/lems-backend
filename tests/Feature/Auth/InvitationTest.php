<?php

use App\Domain\Identity\Actions\InviteUser;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Sends an invitation and returns the plain token the email would carry.
 */
function inviteAndGetToken(string $email = 'kofi@example.com', ?User $by = null): string
{
    Notification::fake();

    app(InviteUser::class)->execute('Kofi Mensah', $email, [Role::Student->value], $by);

    $token = null;
    Notification::assertSentTo(User::where('email', $email)->first(), InvitationNotification::class,
        function ($notification, $channels, $notifiable) use (&$token) {
            $url = $notification->toMail($notifiable)->actionUrl;
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $token = $query['token'];

            return str_starts_with($url, config('app.frontend_url').'/accept-invitation?token=');
        });

    return $token;
}

const INVITE_PASSWORD = 'Welcome-Passw0rd-7';

describe('inviting', function () {
    it('creates an invited account and stores only a hash of the token', function () {
        $admin = User::factory()->create();
        $token = inviteAndGetToken('kofi@example.com', $admin);

        $user = User::where('email', 'kofi@example.com')->first();
        $invitation = $user->invitations()->first();

        expect($user->status)->toBe(UserStatus::Invited)
            ->and($user->password)->toBeNull()
            ->and($invitation->token_hash)->toBe(hash('sha256', $token))
            ->and($invitation->token_hash)->not->toBe($token)
            ->and($invitation->invited_by_id)->toBe($admin->id)
            ->and($invitation->expires_at->isFuture())->toBeTrue();
    });

    it('refuses to invite an email that already has an active account', function () {
        User::factory()->create(['email' => 'taken@example.com']);

        expect(fn () => app(InviteUser::class)->execute('Someone', 'taken@example.com', [Role::Student->value]))
            ->toThrow(ApiException::class, 'A user with this email already exists.');
    });

    it('re-inviting replaces the old link', function () {
        $first = inviteAndGetToken();
        $second = inviteAndGetToken();

        expect(User::where('email', 'kofi@example.com')->first()->invitations()->count())->toBe(1);

        $this->postJson('/api/v1/auth/invitations/accept', [
            'token' => $first,
            'password' => INVITE_PASSWORD,
            'password_confirmation' => INVITE_PASSWORD,
        ])->assertStatus(422);

        $this->postJson('/api/v1/auth/invitations/accept', [
            'token' => $second,
            'password' => INVITE_PASSWORD,
            'password_confirmation' => INVITE_PASSWORD,
        ])->assertOk();
    });
});

describe('accepting', function () {
    it('activates the account, verifies the email and allows login', function () {
        $token = inviteAndGetToken();

        $this->postJson('/api/v1/auth/invitations/accept', [
            'token' => $token,
            'password' => INVITE_PASSWORD,
            'password_confirmation' => INVITE_PASSWORD,
        ])->assertOk()->assertJsonPath('user.status', 'active');

        $user = User::where('email', 'kofi@example.com')->first();
        expect($user->status)->toBe(UserStatus::Active)
            ->and($user->email_verified_at)->not->toBeNull()
            ->and($user->invitations()->first()->accepted_at)->not->toBeNull();

        $this->postJson('/api/v1/auth/login', ['email' => 'kofi@example.com', 'password' => INVITE_PASSWORD])
            ->assertOk();
    });

    it('works only once', function () {
        $token = inviteAndGetToken();
        $payload = ['token' => $token, 'password' => INVITE_PASSWORD, 'password_confirmation' => INVITE_PASSWORD];

        $this->postJson('/api/v1/auth/invitations/accept', $payload)->assertOk();
        $this->postJson('/api/v1/auth/invitations/accept', $payload)
            ->assertStatus(422)->assertJsonPath('code', 'invalid_token');
    });

    it('rejects an expired link', function () {
        $token = inviteAndGetToken();

        $this->travel(InviteUser::VALID_FOR_HOURS + 1)->hours();

        $this->postJson('/api/v1/auth/invitations/accept', [
            'token' => $token,
            'password' => INVITE_PASSWORD,
            'password_confirmation' => INVITE_PASSWORD,
        ])->assertStatus(422)->assertJsonPath('code', 'invalid_token');
    });

    it('rejects an unknown token', function () {
        $this->postJson('/api/v1/auth/invitations/accept', [
            'token' => 'nope',
            'password' => INVITE_PASSWORD,
            'password_confirmation' => INVITE_PASSWORD,
        ])->assertStatus(422)->assertJsonPath('code', 'invalid_token');
    });

    it('enforces the password policy', function () {
        $token = inviteAndGetToken();

        $this->postJson('/api/v1/auth/invitations/accept', [
            'token' => $token,
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        expect(User::where('email', 'kofi@example.com')->first()->status)->toBe(UserStatus::Invited);
    });

    it('cannot be used to take over a disabled account', function () {
        $token = inviteAndGetToken();
        User::where('email', 'kofi@example.com')->update(['status' => UserStatus::Disabled]);

        $this->postJson('/api/v1/auth/invitations/accept', [
            'token' => $token,
            'password' => INVITE_PASSWORD,
            'password_confirmation' => INVITE_PASSWORD,
        ])->assertStatus(422);
    });
});
