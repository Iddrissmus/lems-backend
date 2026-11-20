<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\CourseFactory;
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
 * @property string $code
 * @property string $title
 * @property string $credit_hours
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['department_id', 'code', 'title', 'credit_hours'])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use BelongsToTenant, HasFactory, HasPublicUlid, SoftDeletes;

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<CourseOffering, $this>
     */
    public function offerings(): HasMany
    {
        return $this->hasMany(CourseOffering::class);
    }

    /**
     * @return HasMany<ProgrammeCourse, $this>
     */
    public function programmeCourses(): HasMany
    {
        return $this->hasMany(ProgrammeCourse::class);
    }

    protected function casts(): array
    {
        return [
            'credit_hours' => 'decimal:1',
        ];
    }
}
