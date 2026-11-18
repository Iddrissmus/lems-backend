<?php

namespace App\Http\Controllers;

use App\Support\Health\ReadinessCheck;
use Illuminate\Http\JsonResponse;

class ReadinessController extends Controller
{
    public function __invoke(ReadinessCheck $readiness): JsonResponse
    {
        $result = $readiness->run();

        return response()->json([
            'status' => $result['ready'] ? 'ready' : 'unavailable',
            'checks' => $result['checks'],
        ], $result['ready'] ? 200 : 503);
    }
}
