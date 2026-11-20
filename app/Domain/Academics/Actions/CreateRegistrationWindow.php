<?php

namespace App\Domain\Academics\Actions;

use App\Domain\Academics\Events\RegistrationWindowCreated;
use App\Models\Programme;
use App\Models\RegistrationWindow;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Opens a registration window for a semester. The audience is a list of
 * programme and year pairs; an empty list means everyone.
 */
class CreateRegistrationWindow
{
    /**
     * @param  array{semester_id: string, opens_at: string, closes_at: string, audience?: array<int, array{programme_id: string, year_of_study: int}>|null}  $data
     */
    public function execute(array $data, User $actor): RegistrationWindow
    {
        $semester = Semester::where('ulid', $data['semester_id'])->first()
            ?? throw ValidationException::withMessages(['semester_id' => ['This semester does not exist.']]);

        $audience = $this->audience($data['audience'] ?? []);

        return DB::transaction(function () use ($semester, $data, $audience, $actor): RegistrationWindow {
            $window = RegistrationWindow::create([
                'semester_id' => $semester->id,
                // Stored in UTC whatever offset the client sent.
                'opens_at' => Carbon::parse($data['opens_at'])->utc(),
                'closes_at' => Carbon::parse($data['closes_at'])->utc(),
                'audience' => $audience === [] ? null : $audience,
            ]);

            // Not mass-assignable on purpose: who created it.
            $window->forceFill(['created_by' => $actor->id])->save();

            event(new RegistrationWindowCreated($window, $actor));

            return $window;
        });
    }

    /**
     * Turns public programme ids into internal ones (checked inside the caller's college).
     *
     * @param  array<int, array{programme_id: string, year_of_study: int}>  $entries
     * @return array<int, array{programme_id: int, year_of_study: int}>
     */
    private function audience(array $entries): array
    {
        $out = [];

        foreach ($entries as $index => $entry) {
            $programme = Programme::where('ulid', $entry['programme_id'])->first()
                ?? throw ValidationException::withMessages(["audience.{$index}.programme_id" => ['This programme does not exist.']]);

            $out[] = ['programme_id' => $programme->id, 'year_of_study' => (int) $entry['year_of_study']];
        }

        return $out;
    }
}
