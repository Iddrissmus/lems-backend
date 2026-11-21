<?php

namespace App\Domain\Academics\Actions;

use App\Domain\Academics\Events\ResultsReleased;
use App\Exceptions\ApiException;
use App\Models\CourseOffering;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The Head of Academics releases a submitted course's results to students (A7). Release is
 * per course and cannot be undone through the API. Releasing a released course changes nothing.
 */
class ReleaseResults
{
    public function execute(CourseOffering $offering, User $actor): CourseOffering
    {
        return DB::transaction(function () use ($offering, $actor): CourseOffering {
            $locked = CourseOffering::query()->whereKey($offering->id)->lockForUpdate()->firstOrFail();

            if ($locked->marks_submitted_at === null) {
                throw new ApiException(409, 'not_submitted', 'The lecturer has not submitted these marks yet, so they cannot be released.');
            }

            if ($locked->results_released_at !== null) {
                return $locked;
            }

            $locked->forceFill(['results_released_at' => now(), 'results_released_by' => $actor->id])->save();

            event(new ResultsReleased($locked, $actor));

            return $locked;
        });
    }
}
