<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\RegistrationWindowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $ulid
 * @property int $semester_id
 * @property Carbon $opens_at
 * @property Carbon $closes_at
 * @property array<int, array<string, mixed>>|null $audience
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['semester_id', 'opens_at', 'closes_at', 'audience'])]
class RegistrationWindow extends Model
{
    /** @use HasFactory<RegistrationWindowFactory> */
    use BelongsToTenant, HasFactory, HasPublicUlid;

    public const NOT_YET_OPEN = 'not_yet_open';

    public const OPEN = 'open';

    public const CLOSED = 'closed';

    /** not_yet_open, open or closed (closed early by staff, or past its closing time). */
    public function status(): string
    {
        if ($this->closed_at !== null || now()->gte($this->closes_at)) {
            return self::CLOSED;
        }

        return now()->lt($this->opens_at) ? self::NOT_YET_OPEN : self::OPEN;
    }

    public function isOpen(): bool
    {
        return $this->status() === self::OPEN;
    }

    /** Does the audience include this student's programme and year? An empty audience means everyone. */
    public function coversStudent(StudentProfile $student): bool
    {
        $audience = $this->audience ?? [];

        if ($audience === []) {
            return true;
        }

        foreach ($audience as $entry) {
            if ((int) $entry['programme_id'] === $student->programme_id && (int) $entry['year_of_study'] === $student->year_of_study) {
                return true;
            }
        }

        return false;
    }

    /**
     * The audience with programme details, for the API.
     *
     * @return array<int, array{programme: array{id: string, code: string, name: string}, year_of_study: int}>
     */
    public function audienceDetails(): array
    {
        $entries = $this->audience ?? [];
        $programmes = Programme::whereIn('id', array_column($entries, 'programme_id'))->get()->keyBy('id');
        $out = [];

        foreach ($entries as $entry) {
            $programme = $programmes->get((int) $entry['programme_id']);

            if ($programme !== null) {
                $out[] = [
                    'programme' => ['id' => $programme->ulid, 'code' => $programme->code, 'name' => $programme->name],
                    'year_of_study' => (int) $entry['year_of_study'],
                ];
            }
        }

        return $out;
    }

    /**
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'audience' => 'array',
            'closed_at' => 'datetime',
        ];
    }
}
