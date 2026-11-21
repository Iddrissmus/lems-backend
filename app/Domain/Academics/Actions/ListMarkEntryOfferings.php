<?php

namespace App\Domain\Academics\Actions;

use App\Domain\Academics\Enums\OfferingMarkStatus;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Courses with the state of their marks, for two audiences: a lecturer sees only the
 * courses they teach (forLecturer), the Head of Academics sees every course (forDashboard).
 * Both default to the current semester.
 */
class ListMarkEntryOfferings
{
    public const DEFAULT_PER_PAGE = 25;

    /**
     * @return Collection<int, CourseOffering>
     */
    public function forLecturer(User $lecturer, ?string $semesterUlid = null)
    {
        return $this->query($semesterUlid)->taughtBy($lecturer)->get();
    }

    /**
     * @param  array{semester?: string|null, department?: string|null, status?: string|null, per_page?: int|null}  $filters
     * @return array{page: LengthAwarePaginator<int, CourseOffering>, summary: array<string, int>}
     */
    public function forDashboard(array $filters): array
    {
        $base = fn (): Builder => $this->query($filters['semester'] ?? null)
            ->when($filters['department'] ?? null, fn (Builder $q, string $ulid) => $q->whereHas('course.department', fn (Builder $d) => $d->where('ulid', $ulid)));

        $status = isset($filters['status']) ? OfferingMarkStatus::tryFrom($filters['status']) : null;

        $page = $base()
            ->with('lecturerAssignments.staff.user')
            ->when($status, fn (Builder $q, OfferingMarkStatus $s) => $q->inMarkStatus($s))
            ->paginate(min($filters['per_page'] ?? self::DEFAULT_PER_PAGE, 100));

        // The summary ignores the status filter so the tabs can show every count at once.
        $summary = [];
        foreach (OfferingMarkStatus::cases() as $case) {
            $summary[$case->value] = $base()->inMarkStatus($case)->count();
        }

        return ['page' => $page, 'summary' => $summary];
    }

    /**
     * @return Builder<CourseOffering>
     */
    private function query(?string $semesterUlid): Builder
    {
        $semester = $semesterUlid !== null
            ? Semester::where('ulid', $semesterUlid)->first()
            : Semester::where('is_current', true)->first();

        return CourseOffering::query()
            ->with(['course.programmeCourses.programme', 'semester.academicYear'])
            ->withMarkCounts()
            // A semester that was asked for but does not exist matches nothing (id 0), not everything.
            ->when($semesterUlid !== null || $semester !== null, fn (Builder $q) => $q->where('semester_id', $semester->id ?? 0))
            ->orderBy(Course::query()->select('code')->whereColumn('courses.id', 'course_offerings.course_id'));
    }
}
