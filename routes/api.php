<?php

use App\Http\Controllers\Academics\ReferenceDataController;
use App\Http\Controllers\Academics\RegistrationWindowController;
use App\Http\Controllers\Audit\AuditLogController;
use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\ReadinessController;
use App\Http\Controllers\Users\RoleController;
use App\Http\Controllers\Users\UserController;
use App\Http\Controllers\Users\UserInvitationController;
use App\Http\Controllers\Users\UserRolesController;
use App\Http\Controllers\Users\UserStatusController;
use Illuminate\Support\Facades\Route;

// All routes here are served under /api/v1 (prefix set in bootstrap/app.php).
// Endpoints are added module by module, after the OpenAPI contract is agreed.

// /health: the app is running. /ready: database and cache work too. Liveness for
// the web server itself is the built-in /up route outside /api/v1.
Route::get('/health', HealthController::class)->name('health');
Route::get('/ready', ReadinessController::class)->name('ready');

// Public auth endpoints (rate limited per IP; login also locks per email).
Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::post('login', LoginController::class)->name('auth.login');
    Route::post('password/forgot', ForgotPasswordController::class)->name('auth.password.forgot');
    Route::post('password/reset', ResetPasswordController::class)->name('auth.password.reset');
    Route::post('invitations/accept', AcceptInvitationController::class)->name('auth.invitations.accept');
});

// Everything below needs a valid bearer token of an active account.
Route::middleware(['auth:sanctum', 'tenant', 'active', 'idempotent'])->group(function () {
    // Open even while a password change is pending: the user needs these to fix it.
    Route::post('auth/logout', LogoutController::class)->name('auth.logout');
    Route::post('auth/password/change', ChangePasswordController::class)->name('auth.password.change');
    Route::get('me', MeController::class)->name('me');

    // Everything else waits until the user has chosen their own password.
    Route::middleware('password.changed')->group(function () {
        // Account management (permission checks live in UserPolicy).
        Route::get('roles', RoleController::class)->name('roles.index');
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users/invite', [UserInvitationController::class, 'store'])->name('users.invite');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/resend-invitation', [UserInvitationController::class, 'resend'])->name('users.resend-invitation');
        Route::put('users/{user}/roles', [UserRolesController::class, 'update'])->name('users.roles.update');
        Route::post('users/{user}/disable', [UserStatusController::class, 'disable'])->name('users.disable');
        Route::post('users/{user}/enable', [UserStatusController::class, 'enable'])->name('users.enable');

        // Reference data for the registration screens (read-only).
        Route::get('departments', [ReferenceDataController::class, 'departments'])->name('departments.index');
        Route::get('programmes', [ReferenceDataController::class, 'programmes'])->name('programmes.index');
        Route::get('cohorts', [ReferenceDataController::class, 'cohorts'])->name('cohorts.index');
        Route::get('semesters', [ReferenceDataController::class, 'semesters'])->name('semesters.index');
        Route::get('course-offerings', [ReferenceDataController::class, 'courseOfferings'])->name('course-offerings.index');

        // Registration windows: when students may register.
        Route::get('registration-windows', [RegistrationWindowController::class, 'index'])->name('registration-windows.index');
        Route::post('registration-windows', [RegistrationWindowController::class, 'store'])->name('registration-windows.store');
        Route::post('registration-windows/{window}/close', [RegistrationWindowController::class, 'close'])->name('registration-windows.close');

        // Audit trail: read-only, super_admin only for now.
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });
});
