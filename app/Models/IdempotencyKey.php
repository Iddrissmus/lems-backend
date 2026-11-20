<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A write we have seen before, so a retry can be answered without repeating it.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property string $key
 * @property string $method
 * @property string $path
 * @property string $request_hash
 * @property int|null $status
 * @property string|null $response
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'key', 'method', 'path', 'request_hash', 'status', 'response'])]
class IdempotencyKey extends Model
{
    use BelongsToTenant, MassPrunable;

    /** How long a key is remembered. */
    public const HOURS = 24;

    public function isExpired(): bool
    {
        return $this->created_at === null || $this->created_at->lt(now()->subHours(self::HOURS));
    }

    /**
     * Old keys are deleted by `model:prune` (scheduled daily).
     *
     * @return Builder<IdempotencyKey>
     */
    public function prunable(): Builder
    {
        return static::withoutGlobalScopes()->where('created_at', '<', now()->subHours(self::HOURS));
    }

    protected function casts(): array
    {
        return [
            'response' => 'encrypted',
            'status' => 'integer',
        ];
    }
}
