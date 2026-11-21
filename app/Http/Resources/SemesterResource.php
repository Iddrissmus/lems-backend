<?php

namespace App\Http\Resources;

use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Semester
 */
class SemesterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'academic_year' => $this->whenLoaded('academicYear', fn () => $this->academicYear->name),
            'number' => $this->number,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'length_weeks' => $this->length_weeks,
            'is_current' => $this->is_current,
        ];
    }
}
