<?php

namespace App\Models;

use App\Domain\Academics\Enums\OfferingMarkStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\CourseOfferingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
     * Where this course's marks are: not started, draft, submitted or released.
     * Uses marks_entered_count when it was loaded (see scopeWithMarkCounts).
     */
    public function markStatus(): OfferingMarkStatus
    {
        if ($this->results_released_at !== null) {
            return OfferingMarkStatus::Released;
        }

        if ($this->marks_submitted_at !== null) {
            return OfferingMarkStatus::Submitted;
        }

        $entered = $this->getAttributes()['marks_entered_count']
            ?? $this->enrolments()->whereHas('scores')->count();

        return (int) $entered > 0 ? OfferingMarkStatus::Draft : OfferingMarkStatus::NotStarted;
    }

    /** Is this user one of the lecturers assigned to this course? */
    public function isTaughtBy(User $user): bool
    {
        return $this->lecturerAssignments()->whereHas('staff', fn (Builder $staff) => $staff->where('user_id', $user->id))->exists();
    }

    /**
     * Only the offerings this user is assigned to teach.
     *
     * @param  Builder<CourseOffering>  $query
     */
    public function scopeTaughtBy(Builder $query, User $user): void
    {
        $query->whereHas('lecturerAssignments.staff', fn (Builder $staff) => $staff->where('user_id', $user->id));
    }

    /**
     * Adds enrolled_count (registered students) and marks_entered_count (of those, how many have a mark).
     *
     * @param  Builder<CourseOffering>  $query
     */
    public function scopeWithMarkCounts(Builder $query): void
    {
        $query->withCount([
            'enrolments as enrolled_count' => fn (Builder $e) => $e->where('status', 'registered'),
            'enrolments as marks_entered_count' => fn (Builder $e) => $e->where('status', 'registered')->whereHas('scores'),
        ]);
    }

    /**
     * Only offerings in the given mark status.
     *
     * @param  Builder<CourseOffering>  $query
     */
    public function scopeInMarkStatus(Builder $query, OfferingMarkStatus $status): void
    {
        match ($status) {
            OfferingMarkStatus::Released => $query->whereNotNull('results_released_at'),
            OfferingMarkStatus::Submitted => $query->whereNotNull('marks_submitted_at')->whereNull('results_released_at'),
            OfferingMarkStatus::Draft => $query->whereNull('marks_submitted_at')->whereNull('results_released_at')
                ->whereHas('enrolments', fn (Builder $e) => $e->whereHas('scores')),
            OfferingMarkStatus::NotStarted => $query->whereNull('marks_submitted_at')->whereNull('results_released_at')
                ->whereDoesntHave('enrolments', fn (Builder $e) => $e->whereHas('scores')),
        };
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
