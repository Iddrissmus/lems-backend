<?php

namespace App\Domain\Academics\Actions;

use App\Models\RegistrationWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListRegistrationWindows
{
    public const DEFAULT_PER_PAGE = 25;

    /**
     * @param  array{semester?: string|null, status?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, RegistrationWindow>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        return RegistrationWindow::query()
            ->with('semester.academicYear')
            ->when($filters['semester'] ?? null, fn (Builder $q, string $ulid) => $q->whereHas('semester', fn (Builder $s) => $s->where('ulid', $ulid)))
            ->when($filters['status'] ?? null, function (Builder $q, string $status) {
                match ($status) {
                    RegistrationWindow::OPEN => $q->whereNull('closed_at')->where('opens_at', '<=', now())->where('closes_at', '>', now()),
                    RegistrationWindow::NOT_YET_OPEN => $q->whereNull('closed_at')->where('opens_at', '>', now()),
                    RegistrationWindow::CLOSED => $q->where(fn (Builder $c) => $c->whereNotNull('closed_at')->orWhere('closes_at', '<=', now())),
                    default => $q->whereRaw('1 = 0'),
                };
            })
            ->orderByDesc('opens_at')
            ->orderByDesc('id')
            ->paginate(min($filters['per_page'] ?? self::DEFAULT_PER_PAGE, 100));
    }
}
