<?php

namespace App\Http\Resources;

use App\Models\GradeScale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The active grade scale. For the demo it is synthetic: official is always false and the
 * points are a sample so the frontend can show a GPA labelled "not official".
 *
 * @mixin GradeScale
 */
class GradeScaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'is_demo' => $this->is_demo,
            'official' => false,
            'bands' => $this->bands->map(fn ($band) => [
                'letter' => $band->letter,
                'min_score' => (float) $band->min_score,
                'max_score' => (float) $band->max_score,
                'points' => $band->points === null ? null : (float) $band->points,
            ])->values(),
        ];
    }
}
