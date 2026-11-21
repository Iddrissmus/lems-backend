<?php

namespace App\Models;

use App\Domain\Academics\Enums\StudentStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\StudentProfileFactory;
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
 * @property int $user_id
 * @property string $index_number
 * @property string|null $moh_membership_id
 * @property int $programme_id
 * @property int $cohort_id
 * @property int $year_of_study
 * @property StudentStatus $status
 * @property int $repeat_count
 * @property string|null $photo_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'index_number', 'moh_membership_id', 'programme_id', 'cohort_id', 'year_of_study', 'status', 'repeat_count', 'photo_path'])]
class StudentProfile extends Model
{
    /** @use HasFactory<StudentProfileFactory> */
    use BelongsToTenant, HasFactory, HasPublicUlid, SoftDeletes;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Programme, $this>
     */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    /**
     * @return BelongsTo<Cohort, $this>
     */
    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    /**
     * @return HasMany<Enrolment, $this>
     */
    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class);
    }

    /**
     * Courses the student is currently registered for (not withdrawn).
     *
     * @return HasMany<Enrolment, $this>
     */
    public function registeredEnrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class)->where('status', 'registered');
    }

    protected function casts(): array
    {
        return [
            'year_of_study' => 'integer',
            'status' => StudentStatus::class,
            'repeat_count' => 'integer',
        ];
    }
}
