<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\AssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $ulid
 * @property int $course_offering_id
 * @property string $name
 * @property string $weight_percent
 * @property string $max_score
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['course_offering_id', 'name', 'weight_percent', 'max_score'])]
class Assessment extends Model
{
    /** @use HasFactory<AssessmentFactory> */
    use BelongsToTenant, HasFactory, HasPublicUlid;

    /**
     * @return BelongsTo<CourseOffering, $this>
     */
    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'course_offering_id');
    }

    /**
     * @return HasMany<AssessmentScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(AssessmentScore::class);
    }

    protected function casts(): array
    {
        return [
            'weight_percent' => 'decimal:2',
            'max_score' => 'decimal:2',
        ];
    }
}
