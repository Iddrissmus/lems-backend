<?php

namespace App\Domain\Audit\Actions;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ListAuditLogs
{
    public const DEFAULT_PER_PAGE = 50;

    public const MAX_PER_PAGE = 100;

    /**
     * Newest first. `actor` is the public id of the person who did it.
     *
     * @param  array{event?: string|null, actor?: string|null, subject_type?: string|null, subject_id?: string|null, from?: string|null, to?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        return AuditLog::query()
            ->with('actor') // avoid one query per row
            ->when($filters['event'] ?? null, function ($query, string $event) {
                str_ends_with($event, '.*')
                    ? $query->where('event', 'like', substr($event, 0, -1).'%')
                    : $query->where('event', $event);
            })
            ->when($filters['actor'] ?? null, fn ($query, string $ulid) => $query->whereIn(
                'actor_id',
                User::withTrashed()->where('ulid', $ulid)->select('id'),
            ))
            ->when($filters['subject_type'] ?? null, fn ($query, string $type) => $query->where('subject_type', $type))
            ->when($filters['subject_id'] ?? null, fn ($query, string $id) => $query->where('subject_id', $id))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->where('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->orderByDesc('id') // stable order for entries in the same second
            ->paginate(min($filters['per_page'] ?? self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE));
    }
}
