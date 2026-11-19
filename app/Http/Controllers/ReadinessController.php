<?php

namespace App\Http\Controllers;

use App\Support\Health\ReadinessCheck;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Health', weight: 40)]
class ReadinessController extends Controller
{
    #[Endpoint(title: 'Readiness check', description: '200 when the database and cache both work, 503 otherwise. Use it for uptime monitoring. Liveness (process is up) is the separate /up route outside /api/v1. Public endpoint.')]
    public function __invoke(ReadinessCheck $readiness): JsonResponse
    {
        $result = $readiness->run();

        return response()->json([
            'status' => $result['ready'] ? 'ready' : 'unavailable',
            'checks' => $result['checks'],
        ], $result['ready'] ? 200 : 503);
    }
}
