<?php

use App\Http\Controllers\ReadinessController;
use Illuminate\Support\Facades\Route;

// All routes here are served under /api/v1 (prefix set in bootstrap/app.php).
// Endpoints are added module by module, after the OpenAPI contract is agreed.

// Liveness is the built-in /up route. Readiness also checks database and cache.
Route::get('/ready', ReadinessController::class)->name('ready');
