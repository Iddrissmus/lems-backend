<?php

namespace App\Http\Resources;

use App\Models\Enrolment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A course a student is registered for. Needs `offering.course` loaded.
 *
 * @mixin Enrolment
 */
class EnrolmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $course = $this->offering->course;

        return [
            'id' => $this->ulid,
            'course_offering_id' => $this->offering->ulid,
            'code' => $course->code,
            'title' => $course->title,
            'credit_hours' => (float) $course->credit_hours,
            'status' => $this->status->value,
            'registered_at' => $this->registered_at->toIso8601String(),
        ];
    }
}
