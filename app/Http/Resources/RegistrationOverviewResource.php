<?php

namespace App\Http\Resources;

use App\Models\Enrolment;
use App\Models\RegistrationWindow;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Where a student stands with registration this semester. Wraps the array made by
 * GetRegistrationOverview: semester, window (the most relevant one that covers the
 * student, or null) and the courses registered.
 *
 * @property array{semester: Semester|null, window: RegistrationWindow|null, registered: Collection<int, Enrolment>} $resource
 */
class RegistrationOverviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $semester = $this->resource['semester'];
        $window = $this->resource['window'];

        return [
            'semester' => $semester === null ? null : [
                'id' => $semester->ulid,
                'academic_year' => $semester->academicYear->name,
                'number' => $semester->number,
            ],
            'window' => $window === null ? null : [
                'status' => $window->status(),
                'opens_at' => $window->opens_at->toIso8601String(),
                'closes_at' => $window->closes_at->toIso8601String(),
            ],
            'registered' => EnrolmentResource::collection($this->resource['registered']),
        ];
    }
}
