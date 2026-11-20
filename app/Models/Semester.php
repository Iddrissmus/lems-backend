<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\SemesterFactory;
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
 * @property int $academic_year_id
 * @property int $number
 * @property Carbon $starts_on
 * @property int $length_weeks
 * @property Carbon $ends_on
 * @property bool $is_current
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['academic_year_id', 'number', 'starts_on', 'length_weeks', 'ends_on', 'is_current'])]
class Semester extends Model
{
    /** @use HasFactory<SemesterFactory> */
    use BelongsToTenant, HasFactory, HasPublicUlid;

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return HasMany<CourseOffering, $this>
     */
    public function offerings(): HasMany
    {
        return $this->hasMany(CourseOffering::class);
    }

    /**
     * @return HasMany<RegistrationWindow, $this>
     */
    public function registrationWindows(): HasMany
    {
        return $this->hasMany(RegistrationWindow::class);
    }

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'starts_on' => 'date',
            'length_weeks' => 'integer',
            'ends_on' => 'date',
            'is_current' => 'boolean',
        ];
    }
}
