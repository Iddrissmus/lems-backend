<?php

namespace App\Support\Health;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Readiness = "can this instance do its job right now?" (database and cache
 * reachable). Liveness is the built-in /up route. Failure details go to the
 * log, never into the response.
 */
final class ReadinessCheck
{
    /**
     * @return array{ready: bool, checks: array<string, string>}
     */
    public function run(): array
    {
        $checks = [
            'database' => $this->attempt(fn () => DB::select('select 1')),
            'cache' => $this->attempt(function () {
                Cache::put('readiness-probe', true, 10);

                if (Cache::get('readiness-probe') !== true) {
                    throw new \RuntimeException('Cache read-back failed.');
                }
            }),
        ];

        return [
            'ready' => ! in_array('fail', $checks, true),
            'checks' => $checks,
        ];
    }

    private function attempt(callable $probe): string
    {
        try {
            $probe();

            return 'ok';
        } catch (Throwable $e) {
            report($e);

            return 'fail';
        }
    }
}
