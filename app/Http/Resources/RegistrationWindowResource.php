<?php

namespace App\Http\Resources;

use App\Models\RegistrationWindow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RegistrationWindow
 */
class RegistrationWindowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'semester' => $this->when($this->relationLoaded('semester'), fn () => [
                'id' => $this->semester->ulid,
                'academic_year' => $this->semester->academicYear->name,
                'number' => $this->semester->number,
            ]),
            'opens_at' => $this->opens_at->toIso8601String(),
            'closes_at' => $this->closes_at->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'audience' => $this->audienceDetails(),
            'status' => $this->status(),
        ];
    }
}
