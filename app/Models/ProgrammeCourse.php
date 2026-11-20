<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProgrammeCourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $programme_id
 * @property int $course_id
 * @property int $year_of_study
 * @property int $semester_number
 * @property string $curriculum_version
 * @property bool $is_required
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['programme_id', 'course_id', 'year_of_study', 'semester_number', 'curriculum_version', 'is_required'])]
class ProgrammeCourse extends Model
{
    /** @use HasFactory<ProgrammeCourseFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return BelongsTo<Programme, $this>
     */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    protected function casts(): array
    {
        return [
            'year_of_study' => 'integer',
            'semester_number' => 'integer',
            'is_required' => 'boolean',
        ];
    }
}
