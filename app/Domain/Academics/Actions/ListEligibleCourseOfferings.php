<?php

namespace App\Domain\Academics\Actions;

use App\Domain\Academics\Enums\EnrolmentStatus;
use App\Models\CourseOffering;
use App\Models\Enrolment;
use App\Models\Semester;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The courses a student may register for: those on offer this semester that are
 * in the curriculum of their programme for their year, each marked with whether
 * they have registered already.
 */
class ListEligibleCourseOfferings
{
    /**
     * @return Collection<int, CourseOffering>
     */
    public function execute(StudentProfile $student, ?string $search = null): Collection
    {
        $semester = Semester::where('is_current', true)->first();

        if ($semester === null) {
            return new Collection;
        }

        $registeredIds = Enrolment::where('student_profile_id', $student->id)
            ->where('status', EnrolmentStatus::Registered->value)
            ->pluck('course_offering_id')
            ->all();

        $offerings = CourseOffering::query()
            ->with(['course.department', 'course.programmeCourses.programme', 'semester.academicYear'])
            ->where('semester_id', $semester->id)
            ->whereHas('course.programmeCourses', fn (Builder $link) => $link
                ->where('programme_id', $student->programme_id)
                ->where('year_of_study', $student->year_of_study)
                ->where('semester_number', $semester->number))
            ->when($search, function (Builder $query, string $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';

                $query->whereHas('course', fn (Builder $c) => $c->where('code', 'like', $like)->orWhere('title', 'like', $like));
            })
            ->get()
            ->sortBy(fn (CourseOffering $o) => $o->course->code)
            ->values();

        foreach ($offerings as $offering) {
            $offering->setAttribute('registered', in_array($offering->id, $registeredIds, true));
        }

        return new Collection($offerings->all());
    }
}
