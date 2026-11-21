<?php

namespace App\Domain\Academics\Actions;

use App\Domain\Academics\Enums\EnrolmentStatus;
use App\Domain\Academics\Enums\ScoreStatus;
use App\Models\AssessmentScore;
use App\Models\Enrolment;
use App\Models\GradeScale;
use App\Models\StudentProfile;

/**
 * A student's released results: letter grade and credit hours per course, grouped by
 * semester (docs/api/demo-contract.md section 9). Only released courses appear and only
 * the stored letter grade leaves the API: never a score, a percentage or a GPA.
 */
class GetMyResults
{
    /**
     * @return array<string, mixed>
     */
    public function execute(StudentProfile $student): array
    {
        $student->loadMissing('programme');

        $enrolments = Enrolment::query()
            ->where('student_profile_id', $student->id)
            ->where('status', EnrolmentStatus::Registered)
            ->whereHas('offering', fn ($o) => $o->whereNotNull('results_released_at'))
            ->with(['offering.course', 'offering.semester.academicYear'])
            ->get();

        // The current grade of each course is the highest submitted version.
        $letters = AssessmentScore::query()
            ->whereIn('enrolment_id', $enrolments->pluck('id'))
            ->where('status', ScoreStatus::Submitted)
            ->orderBy('version')
            ->get(['enrolment_id', 'letter_grade'])
            ->mapWithKeys(fn (AssessmentScore $s) => [$s->enrolment_id => $s->letter_grade]);

        $semesters = $enrolments
            ->filter(fn (Enrolment $e) => $letters->get($e->id) !== null)
            ->groupBy(fn (Enrolment $e) => $e->offering->semester_id)
            ->map(function ($group) use ($letters): array {
                $semester = $group->first()->offering->semester;

                $courses = $group
                    ->sortBy(fn (Enrolment $e) => $e->offering->course->code)
                    ->map(fn (Enrolment $e) => [
                        'course_offering_id' => $e->offering->ulid,
                        'code' => $e->offering->course->code,
                        'title' => $e->offering->course->title,
                        'credit_hours' => (float) $e->offering->course->credit_hours,
                        'letter_grade' => $letters->get($e->id),
                        'released_at' => $e->offering->results_released_at?->toIso8601String(),
                    ])
                    ->values();

                return [
                    'semester' => [
                        'id' => $semester->ulid,
                        'academic_year' => $semester->academicYear->name,
                        'number' => $semester->number,
                    ],
                    'courses' => $courses->all(),
                    'credit_hours_total' => (float) $courses->sum('credit_hours'),
                    '_starts_on' => $semester->starts_on->toDateString(),
                ];
            })
            ->sortBy('_starts_on')
            ->map(fn (array $row) => array_diff_key($row, ['_starts_on' => true]))
            ->values();

        $scale = GradeScale::active();

        return [
            'student' => [
                'index_number' => $student->index_number,
                'programme' => ['code' => $student->programme->code, 'name' => $student->programme->name],
                'year_of_study' => $student->year_of_study,
            ],
            'semesters' => $semesters->all(),
            'grading' => [
                'scale_name' => $scale?->name,
                'is_demo' => $scale->is_demo ?? false,
                'official' => false,
            ],
        ];
    }
}
