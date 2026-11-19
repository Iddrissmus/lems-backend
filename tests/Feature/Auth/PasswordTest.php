<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

const NEW_PASSWORD = 'Fresh-Passw0rd-42';

describe('forgot password', function () {
    it('emails a frontend reset link to an active account', function () {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertStatus(202);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, config('app.frontend_url').'/reset-password?token=')
                && str_contains($url, urlencode($user->email));
        });
    });

    it('gives the same answer for unknown, invited and disabled emails and sends nothing', function () {
        Notification::fake();
        $invited = User::factory()->invited()->create();
        $disabled = User::factory()->disabled()->create();

        foreach (['nobody@example.com', $invited->email, $disabled->email] as $email) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => $email])
                ->assertStatus(202)
                ->assertJsonPath('message', 'If that email belongs to an active account, a reset link has been sent.');
        }

        Notification::assertNothingSent();
    });

    it('validates the email', function () {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    });
});

describe('reset password', function () {
    it('sets the new password, revokes every token and lets the user sign in', function () {
        $user = User::factory()->create();
        $user->createToken('old-device');
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => NEW_PASSWORD,
            'password_confirmation' => NEW_PASSWORD,
        ])->assertOk();

        expect($user->tokens()->count())->toBe(0);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => NEW_PASSWORD])
            ->assertOk();
    });

    it('rejects a bad token with invalid_token', function () {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => 'wrong',
            'password' => NEW_PASSWORD,
            'password_confirmation' => NEW_PASSWORD,
        ])->assertStatus(422)->assertJsonPath('code', 'invalid_token');
    });

    it('does not allow a token to be used twice', function () {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $payload = [
            'email' => $user->email,
            'token' => $token,
            'password' => NEW_PASSWORD,
            'password_confirmation' => NEW_PASSWORD,
        ];

        $this->postJson('/api/v1/auth/reset-password', $payload)->assertOk();
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertStatus(422);
    });

    it('enforces the password policy', function (string $weak) {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => Password::createToken($user),
            'password' => $weak,
            'password_confirmation' => $weak,
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    })->with(['too short' => 'Ab1', 'no digit' => 'Abcdefghijkl', 'no uppercase' => 'abcdefghij12']);
});

describe('change password', function () {
    it('changes the password and signs out the other devices only', function () {
        $user = User::factory()->create();
        $current = $user->createToken('this-device')->plainTextToken;
        $user->createToken('other-device');

        $this->withToken($current)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'password',
            'password' => NEW_PASSWORD,
            'password_confirmation' => NEW_PASSWORD,
        ])->assertOk();

        expect($user->tokens()->pluck('name')->all())->toBe(['this-device']);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => NEW_PASSWORD])
            ->assertOk();
    });

    it('rejects a wrong current password', function () {
        $user = User::factory()->create();

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'not-it',
                'password' => NEW_PASSWORD,
                'password_confirmation' => NEW_PASSWORD,
            ])->assertStatus(422)->assertJsonValidationErrors('current_password');
    });

    it('requires the new password to differ and be confirmed', function () {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->withToken($token)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'password',
            'password' => NEW_PASSWORD,
            'password_confirmation' => 'different',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    });

    it('requires a token', function () {
        $this->postJson('/api/v1/auth/change-password', [])->assertStatus(401);
    });
});
