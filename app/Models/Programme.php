<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\ProgrammeFactory;
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
 * @property int $department_id
 * @property string $name
 * @property string $code
 * @property int|null $duration_years
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['department_id', 'name', 'code', 'duration_years'])]
class Programme extends Model
{
    /** @use HasFactory<ProgrammeFactory> */
    use BelongsToTenant, HasFactory, HasPublicUlid, SoftDeletes;

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<Cohort, $this>
     */
    public function cohorts(): HasMany
    {
        return $this->hasMany(Cohort::class);
    }

    /**
     * @return HasMany<ProgrammeCourse, $this>
     */
    public function programmeCourses(): HasMany
    {
        return $this->hasMany(ProgrammeCourse::class);
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
            'duration_years' => 'integer',
        ];
    }
}
