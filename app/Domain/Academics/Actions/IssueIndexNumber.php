<?php

namespace App\Domain\Academics\Actions;

use App\Models\IndexNumberSequence;
use App\Models\Programme;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Issues the next index number for a programme and year (BE-008).
 *
 * Collision-safe: the sequence row is locked while the next serial is taken, so
 * two registrations at the same moment can never receive the same number.
 *
 * The format below is a PLACEHOLDER (programme code / year / 4-digit serial).
 * The College has not confirmed its format; change it here only, once it does.
 */
class IssueIndexNumber
{
    public function execute(Programme $programme, int $year): string
    {
        return DB::transaction(function () use ($programme, $year): string {
            $sequence = $this->lockedSequence($programme, $year);

            $sequence->last_serial++;
            $sequence->save();

            return sprintf('%s/%d/%04d', $programme->code, $year, $sequence->last_serial);
        });
    }

    private function lockedSequence(Programme $programme, int $year): IndexNumberSequence
    {
        $find = fn () => IndexNumberSequence::query()
            ->where('programme_id', $programme->id)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        $sequence = $find();

        if ($sequence !== null) {
            return $sequence;
        }

        try {
            IndexNumberSequence::create(['programme_id' => $programme->id, 'year' => $year, 'last_serial' => 0]);
        } catch (UniqueConstraintViolationException) {
            // Someone else created it a moment ago: fine, lock theirs.
        }

        return $find() ?? throw new \RuntimeException('Could not create the index number sequence.');
    }
}
