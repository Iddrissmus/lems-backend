<?php

namespace App\Http\Resources;

use App\Models\StudentProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A student record. Never carries a password: a temporary one is returned once,
 * in `meta`, by the endpoint that creates the student.
 *
 * @mixin StudentProfile
 */
class StudentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'index_number' => $this->index_number,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->ulid,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'status' => $this->user->status->value,
                'must_change_password' => (bool) $this->user->must_change_password,
            ]),
            'programme' => $this->whenLoaded('programme', fn () => [
                'id' => $this->programme->ulid,
                'code' => $this->programme->code,
                'name' => $this->programme->name,
            ]),
            'cohort' => $this->whenLoaded('cohort', fn () => [
                'id' => $this->cohort->ulid,
                'name' => $this->cohort->name,
                'intake_year' => $this->cohort->intake_year,
            ]),
            'year_of_study' => $this->year_of_study,
            'status' => $this->status->value,
            'moh_membership_id' => $this->moh_membership_id,
            'registered_offerings' => $this->whenCounted('registeredEnrolments'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
