<?php

namespace App\Domain\Academics\Actions;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Courses on offer in one semester (the current one unless told otherwise).
 */
class ListCourseOfferings
{
    public const DEFAULT_PER_PAGE = 25;

    /**
     * @param  array{semester?: string|null, programme?: string|null, department?: string|null, year?: int|null, q?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, CourseOffering>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $semesterId = ($filters['semester'] ?? null) !== null
            ? Semester::where('ulid', $filters['semester'])->value('id')
            : Semester::where('is_current', true)->value('id');

        return CourseOffering::query()
            ->with(['course.department', 'course.programmeCourses.programme', 'semester.academicYear'])
            ->where('semester_id', $semesterId ?? 0)
            ->when(($filters['programme'] ?? null) !== null || ($filters['year'] ?? null) !== null, function (Builder $query) use ($filters) {
                // Programme and year must match the SAME curriculum link.
                $query->whereHas('course.programmeCourses', function (Builder $link) use ($filters) {
                    $link->when($filters['programme'] ?? null, fn (Builder $q, string $ulid) => $q->whereHas('programme', fn (Builder $p) => $p->where('ulid', $ulid)))
                        ->when($filters['year'] ?? null, fn (Builder $q, int $year) => $q->where('year_of_study', $year));
                });
            })
            ->when($filters['department'] ?? null, fn (Builder $query, string $ulid) => $query->whereHas('course.department', fn (Builder $d) => $d->where('ulid', $ulid)))
            ->when($filters['q'] ?? null, function (Builder $query, string $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';

                $query->whereHas('course', fn (Builder $c) => $c->where('code', 'like', $like)->orWhere('title', 'like', $like));
            })
            ->orderBy(Course::query()->select('code')->whereColumn('courses.id', 'course_offerings.course_id'))
            ->paginate(min($filters['per_page'] ?? self::DEFAULT_PER_PAGE, 100));
    }
}
