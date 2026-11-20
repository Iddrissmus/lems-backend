<?php

namespace App\Http\Resources;

use App\Models\CourseOffering;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A course on offer in a semester. `classes` lists who it is for (one course,
 * such as Study Skills, can serve several programmes).
 *
 * @mixin CourseOffering
 */
class CourseOfferingResource extends JsonResource
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
                'id' => $course->ulid,
                'code' => $course->code,
                'title' => $course->title,
                'credit_hours' => (float) $course->credit_hours,
                'department' => $course->relationLoaded('department') ? new DepartmentResource($course->department) : null,
            ],
            'semester' => [
                'id' => $this->semester->ulid,
                'academic_year' => $this->semester->academicYear->name,
                'number' => $this->semester->number,
            ],
            'classes' => $course->programmeCourses
                ->map(fn ($link) => [
                    'programme' => ['id' => $link->programme->ulid, 'code' => $link->programme->code, 'name' => $link->programme->name],
                    'year_of_study' => $link->year_of_study,
                ])
                ->unique(fn (array $class) => $class['programme']['id'].'-'.$class['year_of_study'])
                ->values(),
            'exam_date' => $this->exam_date?->toDateString(),
        ];
    }
}
