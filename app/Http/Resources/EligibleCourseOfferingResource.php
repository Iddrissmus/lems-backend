<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * A course a student may register for, with whether they already have. The
 * `registered` flag is set on the model by ListEligibleCourseOfferings.
 */
class EligibleCourseOfferingResource extends CourseOfferingResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + ['registered' => (bool) $this->resource->getAttribute('registered')];
    }
}
