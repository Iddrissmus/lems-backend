<?php

namespace App\Http\Resources;

use App\Models\Programme;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Programme
 */
class ProgrammeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'code' => $this->code,
            'name' => $this->name,
            'duration_years' => $this->duration_years,
            'department' => new DepartmentResource($this->whenLoaded('department')),
        ];
    }
}
