<?php

namespace App\Providers;

use App\Domain\Audit\AuditSubscriber;
use App\Domain\Identity\Enums\Permission;
use App\Models\Enrolment;
use App\Models\PersonalAccessToken;
use App\Models\RegistrationWindow;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\Api\ErrorEnvelopeDocumentation;
use App\Support\Http\ProxyTrust;
use App\Support\Tenancy\TenantContext;
use Dedoc\Scramble\Scramble;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One answer to "which college is this request for?" for the whole request.
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Tokens find their owner without needing a tenant first (see PersonalAccessToken).
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Real client IP and https when behind a tunnel or reverse proxy (off by default).
        ProxyTrust::configure((string) config('app.trusted_proxies'));

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
        Relation::morphMap([
            'user' => User::class,
            'student' => StudentProfile::class,
            'enrolment' => Enrolment::class,
            'registration_window' => RegistrationWindow::class,
        ]);

        // Every domain event that matters becomes an audit entry.
        Event::subscribe(AuditSubscriber::class);

        // super_admin passes every authorization check. Returning null (not
        // false) for everyone else lets the normal policies decide.
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        // The interactive API docs at /docs are for developers: available
        // locally and on staging, never in production.
        Gate::define('viewApiDocs', fn (?User $user = null) => $this->app->environment(['local', 'staging']));

        // Reference data (departments, programmes, courses on offer...) is for anyone
        // who works with students or registration. super_admin passes through Gate::before.
        Gate::define('viewReferenceData', fn (User $user) => $user->hasAnyPermission([
            Permission::StudentsView->value,
            Permission::RegistrationManage->value,
            Permission::RegistrationSelf->value,
            Permission::AccountsCreate->value,
        ]));

        // Document our real error format ({message, errors, code}).
        Scramble::configure()
            ->withDocumentTransformers(ErrorEnvelopeDocumentation::class)
            // Docs page at /docs and the spec at /docs.json (the package default is /docs/api).
            ->expose(
                ui: fn (Router $router, $action) => $router->get('docs', $action)->name('scramble.docs.ui'),
                document: fn (Router $router, $action) => $router->get('docs.json', $action)->name('scramble.docs.document'),
            );

        // Public auth endpoints: 20 requests per minute per IP.
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(20)->by((string) $request->ip()));
    }
}
