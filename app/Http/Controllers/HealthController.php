<?php

namespace App\Http\Controllers;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Health', weight: 40)]
class HealthController extends Controller
{
    #[Endpoint(title: 'Health check', description: 'Answers 200 while the application is running: status, API version and server time (UTC). It does not touch the database; use /ready for that. Public endpoint, not wrapped in data because it is meant for monitors.')]
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'version' => (string) config('scramble.info.version'),
            'time' => now()->toIso8601String(),
        ]);
    }
}
