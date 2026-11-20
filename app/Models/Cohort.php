<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\CohortFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $ulid
 * @property int $programme_id
 * @property string $name
 * @property int $intake_year
 * @property int $current_year_of_study
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['programme_id', 'name', 'intake_year', 'current_year_of_study'])]
class Cohort extends Model
{
    /** @use HasFactory<CohortFactory> */
    use BelongsToTenant, HasFactory, HasPublicUlid, SoftDeletes;

    /**
     * @return BelongsTo<Programme, $this>
     */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    /**
     * @return HasMany<StudentProfile, $this>
     */
    public function students(): HasMany
    {
        return $this->hasMany(StudentProfile::class);
    }

    protected function casts(): array
    {
        return [
            'intake_year' => 'integer',
            'current_year_of_study' => 'integer',
        ];
    }
}
