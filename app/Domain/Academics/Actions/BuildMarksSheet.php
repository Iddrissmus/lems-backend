<?php

namespace App\Domain\Academics\Actions;

use App\Domain\Academics\Enums\EnrolmentStatus;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\CourseOffering;
use App\Models\Enrolment;
use App\Models\GradeScale;

/**
 * The class list of a course with each student's saved mark (docs/api/demo-contract.md
 * section 7). It is both the lecturer's review screen and what the Head of Academics
 * sees before releasing. Letter grades of draft marks are a preview from the active
 * scale; submitted marks show the letter that was stored at submission.
 */
class BuildMarksSheet
{
    public const ASSESSMENT_NAME = 'Overall';

    /**
     * @return array<string, mixed>
     */
    public function execute(CourseOffering $offering): array
    {
        $offering->loadMissing('course', 'semester.academicYear');

        $assessment = $this->overallAssessment($offering);
        $scale = GradeScale::active();

        $enrolments = Enrolment::query()
            ->where('course_offering_id', $offering->id)
            ->where('status', EnrolmentStatus::Registered)
            ->with('student.user')
            ->get()
            ->sortBy(fn (Enrolment $e) => $e->student->index_number)
            ->values();

        // The current mark of each student is their highest version.
        $current = AssessmentScore::query()
            ->where('assessment_id', $assessment->id)
            ->whereIn('enrolment_id', $enrolments->pluck('id'))
            ->orderBy('version')
            ->get()
            ->keyBy('enrolment_id');

        $students = $enrolments->map(function (Enrolment $enrolment) use ($current, $scale): array {
            /** @var AssessmentScore|null $score */
            $score = $current->get($enrolment->id);

            return [
                'enrolment_id' => $enrolment->ulid,
                'student' => [
                    'id' => $enrolment->student->ulid,
                    'index_number' => $enrolment->student->index_number,
                    'name' => $enrolment->student->user->name,
                ],
                'score' => $score === null ? null : (float) $score->score,
                'letter_grade' => $score === null ? null : ($score->letter_grade ?? $scale?->letterFor($score->score)),
                'state' => $score === null ? 'empty' : $score->status->value,
                'version' => $score?->version,
            ];
        });

        $entered = $students->whereNotNull('score')->count();

        return [
            'offering' => [
                'id' => $offering->ulid,
                'course' => ['code' => $offering->course->code, 'title' => $offering->course->title],
                'status' => $offering->markStatus()->value,
                'exam_date' => $offering->exam_date?->toDateString(),
            ],
            'assessment' => [
                'id' => $assessment->ulid,
                'name' => $assessment->name,
                'max_score' => (float) $assessment->max_score,
            ],
            'grading' => [
                'scale_name' => $scale?->name,
                'is_demo' => $scale->is_demo ?? false,
            ],
            'students' => $students->all(),
            'summary' => [
                'enrolled' => $students->count(),
                'entered' => $entered,
                'missing' => $students->count() - $entered,
            ],
        ];
    }

    /**
     * The single "Overall" assessment of a course. Created the first time it is needed so a
     * course never has to be set up by hand for the demo.
     */
    public function overallAssessment(CourseOffering $offering): Assessment
    {
        return Assessment::firstOrCreate(
            ['course_offering_id' => $offering->id, 'name' => self::ASSESSMENT_NAME],
            ['weight_percent' => 100, 'max_score' => 100],
        );
    }
}
