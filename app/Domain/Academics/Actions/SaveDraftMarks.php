<?php

namespace App\Domain\Academics\Actions;

use App\Domain\Academics\Enums\EnrolmentStatus;
use App\Domain\Academics\Enums\ScoreStatus;
use App\Exceptions\GradeLocked;
use App\Models\AssessmentScore;
use App\Models\CourseOffering;
use App\Models\Enrolment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A lecturer saves draft marks for a course. The whole request is checked first: if any
 * row is wrong nothing is saved. A null score clears a draft mark. Once the course is
 * submitted every write is refused with 423 grade_locked (the database trigger
 * guards the same rule underneath).
 */
class SaveDraftMarks
{
    public function __construct(private readonly BuildMarksSheet $sheet) {}

    /**
     * @param  array<int, array{enrolment_id: string, score: float|int|string|null}>  $marks
     */
    public function execute(CourseOffering $offering, array $marks, User $actor): void
    {
        DB::transaction(function () use ($offering, $marks, $actor): void {
            // Lock the course row so a submit running at the same moment cannot interleave.
            $locked = CourseOffering::query()->whereKey($offering->id)->lockForUpdate()->firstOrFail();

            if ($locked->marks_submitted_at !== null) {
                throw new GradeLocked;
            }

            $assessment = $this->sheet->overallAssessment($locked);

            $enrolments = Enrolment::query()
                ->where('course_offering_id', $locked->id)
                ->where('status', EnrolmentStatus::Registered)
                ->whereIn('ulid', array_column($marks, 'enrolment_id'))
                ->get()
                ->keyBy('ulid');

            $errors = [];
            foreach ($marks as $i => $row) {
                if (! $enrolments->has($row['enrolment_id'])) {
                    $errors["marks.{$i}.enrolment_id"] = 'This student is not registered for this course.';
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            foreach ($marks as $row) {
                /** @var Enrolment $enrolment */
                $enrolment = $enrolments->get($row['enrolment_id']);

                $existing = AssessmentScore::query()
                    ->where('assessment_id', $assessment->id)
                    ->where('enrolment_id', $enrolment->id)
                    ->orderByDesc('version')
                    ->first();

                if ($row['score'] === null) {
                    $existing?->delete();

                    continue;
                }

                if ($existing === null) {
                    AssessmentScore::create([
                        'assessment_id' => $assessment->id,
                        'enrolment_id' => $enrolment->id,
                        'version' => 1,
                        'score' => $row['score'],
                        'status' => ScoreStatus::Draft,
                        'entered_by' => $actor->id,
                    ]);
                } else {
                    $existing->update(['score' => $row['score'], 'entered_by' => $actor->id]);
                }
            }
        });
    }
}
