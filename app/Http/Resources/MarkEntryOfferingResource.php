<?php

namespace App\Http\Resources;

use App\Models\CourseOffering;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A course with the state of its marks (docs/api/demo-contract.md sections 7 and 8).
 * Needs the offering loaded with withMarkCounts(). `lecturers` appears when the
 * assignments are loaded (the Head of Academics dashboard).
 *
 * @mixin CourseOffering
 */
class MarkEntryOfferingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $course = $this->course;

        return [
            'id' => $this->ulid,
            'course' => [
                'code' => $course->code,
                'title' => $course->title,
                'credit_hours' => (float) $course->credit_hours,
            ],
            'semester' => [
                'id' => $this->semester->ulid,
                'academic_year' => $this->semester->academicYear->name,
                'number' => $this->semester->number,
            ],
            'classes' => $course->programmeCourses
                ->map(fn ($link) => ['programme' => ['code' => $link->programme->code, 'name' => $link->programme->name], 'year_of_study' => $link->year_of_study])
                ->unique(fn (array $class) => $class['programme']['code'].'-'.$class['year_of_study'])
                ->values(),
            'exam_date' => $this->exam_date?->toDateString(),
            'enrolled_count' => (int) ($this->getAttributes()['enrolled_count'] ?? 0),
            'marks_entered_count' => (int) ($this->getAttributes()['marks_entered_count'] ?? 0),
            'status' => $this->markStatus()->value,
            'submitted_at' => $this->marks_submitted_at?->toIso8601String(),
            'released_at' => $this->results_released_at?->toIso8601String(),
            'lecturers' => $this->whenLoaded('lecturerAssignments', fn () => $this->lecturerAssignments
                ->map(fn ($assignment) => ['id' => $assignment->staff->user->ulid, 'name' => $assignment->staff->user->name])
                ->values()),
        ];
    }
}
