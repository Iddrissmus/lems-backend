<?php

namespace App\Domain\Identity\Actions;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ListUsers
{
    public const DEFAULT_PER_PAGE = 25;

    public const MAX_PER_PAGE = 100;

    /** Allowed values for ?sort= (prefix with "-" for descending). */
    public const SORTS = ['name', 'email', 'created_at', 'last_login_at'];

    /**
     * @param  array{q?: string|null, status?: string|null, role?: string|null, sort?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'name';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        return User::query()
            ->with('roles') // avoid one query per user
            ->when($filters['q'] ?? null, function ($query, string $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';

                $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->role($role))
            ->orderBy($column, $direction)
            ->orderBy('id') // stable order when values tie
            ->paginate(min($filters['per_page'] ?? self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE));
    }
}
