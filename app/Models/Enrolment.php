<?php

namespace App\Models;

use App\Domain\Academics\Enums\EnrolmentStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\EnrolmentFactory;
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
 * @property int $student_profile_id
 * @property int $course_offering_id
 * @property EnrolmentStatus $status
 * @property Carbon $registered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['student_profile_id', 'course_offering_id', 'status', 'registered_at'])]
class Enrolment extends Model
{
    /** @use HasFactory<EnrolmentFactory> */
    use BelongsToTenant, HasFactory, HasPublicUlid;

    /**
     * @return BelongsTo<StudentProfile, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

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
            'status' => EnrolmentStatus::class,
            'registered_at' => 'datetime',
        ];
    }
}
