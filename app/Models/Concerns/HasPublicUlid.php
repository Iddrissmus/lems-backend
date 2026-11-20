<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * The API only ever shows the `ulid` column as a record's id and never accepts
 * the internal number (the same rule as User).
 */
trait HasPublicUlid
{
    use HasUlids;

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
