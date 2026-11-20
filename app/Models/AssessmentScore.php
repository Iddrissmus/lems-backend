<?php

namespace App\Models;

use App\Domain\Academics\Enums\ScoreStatus;
use App\Exceptions\GradeLocked;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\AssessmentScoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $ulid
 * @property int $assessment_id
 * @property int $enrolment_id
 * @property int $version
 * @property string $score
 * @property string|null $letter_grade
 * @property int|null $grade_scale_id
 * @property ScoreStatus $status
 * @property int $entered_by
 * @property Carbon|null $submitted_at
 * @property int|null $submitted_by
 * @property int|null $supersedes_id
 * @property string|null $reason
 * @property int|null $approved_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['assessment_id', 'enrolment_id', 'version', 'score', 'letter_grade', 'grade_scale_id', 'status', 'entered_by', 'submitted_at', 'submitted_by', 'supersedes_id', 'reason', 'approved_by'])]
class AssessmentScore extends Model
{
    /** @use HasFactory<AssessmentScoreFactory> */
    use BelongsToTenant, HasFactory, HasPublicUlid;

    protected static function booted(): void
    {
        // Application-level lock. The database trigger is the real guard; this gives a
        // clear 423 grade_locked before the query is even sent.
        static::updating(fn (self $score) => $score->getRawOriginal('status') === 'submitted' ? throw new GradeLocked : null);
        static::deleting(fn (self $score) => $score->getRawOriginal('status') === 'submitted' ? throw new GradeLocked : null);
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * @return BelongsTo<Enrolment, $this>
     */
    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(Enrolment::class);
    }

    /**
     * @return BelongsTo<AssessmentScore, $this>
     */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(AssessmentScore::class, 'supersedes_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'score' => 'decimal:2',
            'status' => ScoreStatus::class,
            'submitted_at' => 'datetime',
        ];
    }
}
