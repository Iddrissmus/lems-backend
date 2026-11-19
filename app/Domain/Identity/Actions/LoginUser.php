<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Events\LoginFailed;
use App\Domain\Identity\Events\LoginLockedOut;
use App\Domain\Identity\Events\UserLoggedIn;
use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Checks credentials and issues a bearer token.
 *
 * Lockout: 5 failed attempts for the same email lock that email for
 * 15 minutes. It is keyed on the email (not the user row) so a locked
 * known account and an unknown address behave identically and the
 * response never reveals whether an account exists.
 */
class LoginUser
{
    public const MAX_ATTEMPTS = 5;

    public const LOCKOUT_SECONDS = 900;

    public function execute(string $email, string $password, string $deviceName): NewAccessToken
    {
        $key = 'login:'.Str::lower($email);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw new TooManyRequestsHttpException(RateLimiter::availableIn($key));
        }

        $user = User::query()->where('email', $email)->first();

        if (! $this->credentialsAreValid($user, $password)) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);

            event(new LoginFailed($email));

            // Record the lock once, at the moment it starts (not on every blocked try).
            if (RateLimiter::attempts($key) === self::MAX_ATTEMPTS) {
                event(new LoginLockedOut($email, self::LOCKOUT_SECONDS));
            }

            throw new ApiException(401, 'invalid_credentials', 'The email or password is incorrect.');
        }

        RateLimiter::clear($key);

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken($deviceName);

        event(new UserLoggedIn($user));

        return $token;
    }

    /**
     * @phpstan-assert-if-true User $user
     */
    private function credentialsAreValid(?User $user, string $password): bool
    {
        // Always run one hash check so response time does not reveal
        // whether the email belongs to an account.
        $hash = $user->password ?? Hash::make(Str::random(32));
        $matches = Hash::check($password, $hash);

        return $user !== null
            && $user->password !== null
            && $user->status->canLogin()
            && $matches;
    }
}
