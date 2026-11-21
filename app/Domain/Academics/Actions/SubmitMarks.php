<?php

namespace App\Domain\Academics\Actions;

use App\Domain\Academics\Enums\EnrolmentStatus;
use App\Domain\Academics\Enums\ScoreStatus;
use App\Domain\Academics\Events\MarksSubmitted;
use App\Exceptions\ApiException;
use App\Exceptions\ApiValidationException;
use App\Models\AssessmentScore;
use App\Models\CourseOffering;
use App\Models\Enrolment;
use App\Models\GradeScale;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A lecturer submits a course's marks. Every registered student must have a mark (A6).
 * The letter grades are stored with the scale used, the scores become submitted and the
 * database refuses any later change. There is no override for lecturers or tutors.
 */
class SubmitMarks
{
    public function __construct(private readonly BuildMarksSheet $sheet) {}

    public function execute(CourseOffering $offering, User $actor): CourseOffering
    {
        return DB::transaction(function () use ($offering, $actor): CourseOffering {
            $locked = CourseOffering::query()->whereKey($offering->id)->lockForUpdate()->firstOrFail();

            if ($locked->marks_submitted_at !== null) {
                throw new ApiException(409, 'already_submitted', 'These marks have already been submitted.');
            }

            $assessment = $this->sheet->overallAssessment($locked);

            $enrolments = Enrolment::query()
                ->where('course_offering_id', $locked->id)
                ->where('status', EnrolmentStatus::Registered)
                ->with('student.user')
                ->get();

            if ($enrolments->isEmpty()) {
                throw new ApiValidationException('marks_incomplete', 'There are no registered students for this course, so there is nothing to submit.', ['marks' => ['No students are registered for this course.']]);
            }

            $scores = AssessmentScore::query()
                ->where('assessment_id', $assessment->id)
                ->whereIn('enrolment_id', $enrolments->pluck('id'))
                ->get()
                ->keyBy('enrolment_id');

            $missing = $enrolments
                ->filter(fn (Enrolment $e) => ! $scores->has($e->id))
                ->sortBy(fn (Enrolment $e) => $e->student->index_number)
                ->map(fn (Enrolment $e) => "{$e->student->index_number} ({$e->student->user->name}) has no mark.")
                ->values()
                ->all();

            if ($missing !== []) {
                throw new ApiValidationException('marks_incomplete', 'Every registered student needs a mark before you can submit.', ['marks' => $missing]);
            }

            $scale = GradeScale::active()
                ?? throw new ApiException(409, 'no_grade_scale', 'No grade scale is active, so letter grades cannot be given.');

            $now = now();

            foreach ($scores as $score) {
                $score->update([
                    'letter_grade' => $scale->letterFor($score->score),
                    'grade_scale_id' => $scale->id,
                    'status' => ScoreStatus::Submitted,
                    'submitted_at' => $now,
                    'submitted_by' => $actor->id,
                ]);
            }

            $locked->forceFill(['marks_submitted_at' => $now, 'marks_submitted_by' => $actor->id])->save();

            event(new MarksSubmitted($locked, $actor, $scores->count(), $scale));

            return $locked;
        });
    }
}
