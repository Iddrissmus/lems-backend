<?php

use App\Domain\Academics\Enums\ScoreStatus;
use App\Exceptions\GradeLocked;
use App\Models\AssessmentScore;
use App\Models\Enrolment;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

// Grade locking (BE-023): once a score is submitted, nothing can change it, and that
// is enforced twice: in the model (a clear 423 grade_locked) and in the DATABASE
// itself, so no future endpoint, console command or raw query can write around it.

function submittedScore(): AssessmentScore
{
    return AssessmentScore::factory()->create([
        'status' => ScoreStatus::Submitted,
        'letter_grade' => 'B',
        'submitted_at' => now(),
    ]);
}

describe('before submission', function () {
    it('lets the lecturer change a draft score as often as they like', function () {
        $score = AssessmentScore::factory()->create(['score' => 55]);

        $score->update(['score' => 61]);
        $score->update(['score' => 64.5]);

        expect($score->fresh()->score)->toBe('64.50')
            ->and($score->fresh()->status)->toBe(ScoreStatus::Draft);
    });

    it('lets a draft be submitted, which is the moment it locks', function () {
        $score = AssessmentScore::factory()->create();

        $score->update(['status' => ScoreStatus::Submitted, 'letter_grade' => 'C', 'submitted_at' => now()]);

        expect($score->fresh()->status)->toBe(ScoreStatus::Submitted);
    });

    it('lets a draft be deleted', function () {
        $score = AssessmentScore::factory()->create();

        expect($score->delete())->toBeTrue()
            ->and(AssessmentScore::count())->toBe(0);
    });
});

describe('after submission, in the application', function () {
    it('refuses to change the score, answering 423 grade_locked', function () {
        $score = submittedScore();

        try {
            $score->update(['score' => 99]);
            $this->fail('The update should have been refused.');
        } catch (GradeLocked $e) {
            expect($e->getStatusCode())->toBe(423)
                ->and($e->errorCode())->toBe('grade_locked');
        }

        expect($score->fresh()->score)->toBe('70.00');
    });

    it('refuses to change anything else on the row, including un-submitting it', function () {
        $score = submittedScore();

        expect(fn () => $score->update(['status' => ScoreStatus::Draft]))->toThrow(GradeLocked::class)
            ->and(fn () => $score->update(['letter_grade' => 'A']))->toThrow(GradeLocked::class)
            ->and(fn () => $score->update(['reason' => 'edited']))->toThrow(GradeLocked::class);
    });

    it('refuses to delete it', function () {
        $score = submittedScore();

        expect(fn () => $score->delete())->toThrow(GradeLocked::class)
            ->and(AssessmentScore::count())->toBe(1);
    });
});

describe('after submission, in the database', function () {
    it('rejects a raw UPDATE that skips the model', function () {
        $score = submittedScore();

        expect(fn () => DB::table('assessment_scores')->where('id', $score->id)->update(['score' => 99]))
            ->toThrow(QueryException::class, 'grade_locked');

        expect($score->fresh()->score)->toBe('70.00');
    });

    it('rejects a raw DELETE', function () {
        $score = submittedScore();

        expect(fn () => DB::table('assessment_scores')->where('id', $score->id)->delete())
            ->toThrow(QueryException::class, 'grade_locked');

        expect(AssessmentScore::count())->toBe(1);
    });

    it('rejects an update made with model events switched off', function () {
        $score = submittedScore();

        expect(fn () => AssessmentScore::withoutEvents(fn () => $score->forceFill(['score' => 99])->save()))
            ->toThrow(QueryException::class, 'grade_locked');
    });

    it('rejects a bulk update', function () {
        $score = submittedScore();

        expect(fn () => AssessmentScore::withoutGlobalScopes()->whereKey($score->id)->update(['score' => 1]))
            ->toThrow(QueryException::class, 'grade_locked');
    });
});

describe('corrections', function () {
    it('are new versions: the original stays exactly as it was', function () {
        $original = submittedScore();

        $correction = AssessmentScore::factory()->create([
            'assessment_id' => $original->assessment_id,
            'enrolment_id' => $original->enrolment_id,
            'version' => 2,
            'score' => 74,
            'supersedes_id' => $original->id,
            'reason' => 'Marks were added up wrongly',
            'status' => ScoreStatus::Submitted,
            'letter_grade' => 'B',
            'submitted_at' => now(),
        ]);

        expect($original->fresh()->score)->toBe('70.00')
            ->and($correction->supersedes->is($original))->toBeTrue()
            ->and($correction->reason)->toBe('Marks were added up wrongly');
    });

    it('cannot reuse a version number for the same student and assessment', function () {
        $original = submittedScore();

        expect(fn () => AssessmentScore::factory()->create([
            'assessment_id' => $original->assessment_id,
            'enrolment_id' => $original->enrolment_id,
            'version' => 1,
        ]))->toThrow(UniqueConstraintViolationException::class);
    });

    it('leave other students untouched by a lock', function () {
        $locked = submittedScore();
        $other = AssessmentScore::factory()->create(['assessment_id' => $locked->assessment_id, 'enrolment_id' => Enrolment::factory()]);

        $other->update(['score' => 12]);

        expect($other->fresh()->score)->toBe('12.00');
    });
});
