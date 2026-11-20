<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\GradeScaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $ulid
 * @property string $name
 * @property bool $is_active
 * @property bool $is_demo
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'is_active', 'is_demo', 'notes'])]
class GradeScale extends Model
{
    /** @use HasFactory<GradeScaleFactory> */
    use BelongsToTenant, HasFactory, HasPublicUlid;

    /**
     * @return HasMany<GradeScaleBand, $this>
     */
    public function bands(): HasMany
    {
        return $this->hasMany(GradeScaleBand::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }
}
