<?php

namespace Database\Factories;

use App\Domain\Academics\Enums\EnrolmentStatus;
use App\Models\CourseOffering;
use App\Models\Enrolment;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrolment>
 */
class EnrolmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_profile_id' => StudentProfile::factory(),
            'course_offering_id' => CourseOffering::factory(),
            'status' => EnrolmentStatus::Registered,
            'registered_at' => now(),
        ];
    }
}
