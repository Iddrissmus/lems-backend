<?php

namespace App\Http\Resources;

use App\Models\Cohort;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Cohort
 */
class CohortResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'intake_year' => $this->intake_year,
            'current_year_of_study' => $this->current_year_of_study,
            'programme_id' => $this->whenLoaded('programme', fn () => $this->programme->ulid),
        ];
    }
}
