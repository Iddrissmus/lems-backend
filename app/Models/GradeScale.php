<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\GradeScaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $ulid
 * @property string $name
 * @property bool $is_active
 * @property bool $is_demo
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'is_active', 'is_demo', 'notes'])]
class GradeScale extends Model
{
    /** @use HasFactory<GradeScaleFactory> */
    use BelongsToTenant, HasFactory, HasPublicUlid;

    /**
     * The scale in use, with its bands from highest to lowest. Null when none is active.
     */
    public static function active(): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->with(['bands' => fn ($bands) => $bands->orderBy('sort')])
            ->first();
    }

    /**
     * The letter for a score: the band with the highest starting mark that the score reaches.
     * Reads the bands as loaded (see active()), so call it freely in a loop.
     */
    public function letterFor(float|string $score): ?string
    {
        $score = (float) $score;

        return $this->bands
            ->sortByDesc(fn (GradeScaleBand $band) => (float) $band->min_score)
            ->first(fn (GradeScaleBand $band) => $score >= (float) $band->min_score)
            ?->letter;
    }

    /**
     * @return HasMany<GradeScaleBand, $this>
     */
    public function bands(): HasMany
    {
        return $this->hasMany(GradeScaleBand::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }
}
