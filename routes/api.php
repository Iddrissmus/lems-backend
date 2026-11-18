<?php

use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\ReadinessController;
use Illuminate\Support\Facades\Route;

// All routes here are served under /api/v1 (prefix set in bootstrap/app.php).
// Endpoints are added module by module, after the OpenAPI contract is agreed.

// Liveness is the built-in /up route. Readiness also checks database and cache.
Route::get('/ready', ReadinessController::class)->name('ready');

// Public auth endpoints (rate limited per IP; login also locks per email).
Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::post('login', LoginController::class)->name('auth.login');
    Route::post('forgot-password', ForgotPasswordController::class)->name('auth.forgot-password');
    Route::post('reset-password', ResetPasswordController::class)->name('auth.reset-password');
    Route::post('invitations/accept', AcceptInvitationController::class)->name('auth.invitations.accept');
});

// Everything below needs a valid bearer token of an active account.
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('auth/logout', LogoutController::class)->name('auth.logout');
    Route::post('auth/change-password', ChangePasswordController::class)->name('auth.change-password');
    Route::get('me', MeController::class)->name('me');
});
