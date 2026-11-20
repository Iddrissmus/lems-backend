<?php

namespace App\Domain\Academics\Actions;

use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListStudents
{
    public const DEFAULT_PER_PAGE = 25;

    /**
     * @param  array{q?: string|null, programme?: string|null, cohort?: string|null, department?: string|null, year?: int|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, StudentProfile>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        return StudentProfile::query()
            ->with(['user', 'programme', 'cohort'])
            ->withCount('registeredEnrolments')
            ->when($filters['q'] ?? null, function (Builder $query, string $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';

                $query->where(fn (Builder $q) => $q
                    ->where('index_number', 'like', $like)
                    ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like)));
            })
            ->when($filters['programme'] ?? null, fn (Builder $q, string $ulid) => $q->whereHas('programme', fn (Builder $p) => $p->where('ulid', $ulid)))
            ->when($filters['cohort'] ?? null, fn (Builder $q, string $ulid) => $q->whereHas('cohort', fn (Builder $c) => $c->where('ulid', $ulid)))
            ->when($filters['department'] ?? null, fn (Builder $q, string $ulid) => $q->whereHas('programme.department', fn (Builder $d) => $d->where('ulid', $ulid)))
            ->when($filters['year'] ?? null, fn (Builder $q, int $year) => $q->where('year_of_study', $year))
            ->orderBy('index_number')
            ->paginate(min($filters['per_page'] ?? self::DEFAULT_PER_PAGE, 100));
    }
}
