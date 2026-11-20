<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\AcademicYearFactory;
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
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property bool $is_current
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'starts_on', 'ends_on', 'is_current'])]
class AcademicYear extends Model
{
    /** @use HasFactory<AcademicYearFactory> */
    use BelongsToTenant, HasFactory, HasPublicUlid;

    /**
     * @return HasMany<Semester, $this>
     */
    public function semesters(): HasMany
    {
        return $this->hasMany(Semester::class);
    }

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
        ];
    }
}
