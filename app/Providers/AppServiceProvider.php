<?php

namespace App\Providers;

use App\Domain\Audit\AuditSubscriber;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Password policy for every "password" rule that uses Password::defaults().
        // Production also rejects passwords found in known data breaches.
        Password::defaults(fn () => Password::min(10)
            ->mixedCase()
            ->numbers()
            ->when($this->app->isProduction(), fn (Password $rule) => $rule->uncompromised()));

        // Password reset emails link to the frontend page, not to the API.
        ResetPassword::createUrlUsing(fn (object $user, string $token) => config('app.frontend_url').'/reset-password?'.http_build_query([
            'token' => $token,
            'email' => $user->email,
        ]));

        // Short, stable names stored in the database instead of class names
        // (e.g. audit_logs.subject_type = "user"). Renaming a class stays safe.
        Relation::morphMap(['user' => User::class]);

        // Every domain event that matters becomes an audit entry.
        Event::subscribe(AuditSubscriber::class);

        // super_admin passes every authorization check. Returning null (not
        // false) for everyone else lets the normal policies decide.
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        // Public auth endpoints: 20 requests per minute per IP.
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(20)->by((string) $request->ip()));
    }
}
