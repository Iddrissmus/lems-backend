<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\IndexNumberSequenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $programme_id
 * @property int $year
 * @property int $last_serial
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['programme_id', 'year', 'last_serial'])]
class IndexNumberSequence extends Model
{
    /** @use HasFactory<IndexNumberSequenceFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return BelongsTo<Programme, $this>
     */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_serial' => 'integer',
        ];
    }
}
