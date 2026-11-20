<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\CourseOfferingFactory;
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
 * @property int $course_id
 * @property int $semester_id
 * @property Carbon|null $exam_date
 * @property Carbon|null $marks_submitted_at
 * @property int|null $marks_submitted_by
 * @property Carbon|null $results_released_at
 * @property int|null $results_released_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['course_id', 'semester_id', 'exam_date'])]
class CourseOffering extends Model
{
    /** @use HasFactory<CourseOfferingFactory> */
    use BelongsToTenant, HasFactory, HasPublicUlid;

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * @return HasMany<LecturerAssignment, $this>
     */
    public function lecturerAssignments(): HasMany
    {
        return $this->hasMany(LecturerAssignment::class);
    }

    /**
     * @return HasMany<Enrolment, $this>
     */
    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class);
    }

    /**
     * @return HasMany<Assessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marks_submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'results_released_by');
    }

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'marks_submitted_at' => 'datetime',
            'results_released_at' => 'datetime',
        ];
    }
}
