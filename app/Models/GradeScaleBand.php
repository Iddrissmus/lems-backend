<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\GradeScaleBandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $grade_scale_id
 * @property string $letter
 * @property string $min_score
 * @property string $max_score
 * @property string|null $points
 * @property int $sort
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['grade_scale_id', 'letter', 'min_score', 'max_score', 'points', 'sort'])]
class GradeScaleBand extends Model
{
    /** @use HasFactory<GradeScaleBandFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return BelongsTo<GradeScale, $this>
     */
    public function scale(): BelongsTo
    {
        return $this->belongsTo(GradeScale::class, 'grade_scale_id');
    }

    protected function casts(): array
    {
        return [
            'min_score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'points' => 'decimal:2',
            'sort' => 'integer',
        ];
    }
}
