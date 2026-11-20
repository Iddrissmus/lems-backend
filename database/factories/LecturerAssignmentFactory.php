<?php

namespace Database\Factories;

use App\Models\CourseOffering;
use App\Models\LecturerAssignment;
use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LecturerAssignment>
 */
class LecturerAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_offering_id' => CourseOffering::factory(),
            'staff_profile_id' => StaffProfile::factory(),
        ];
    }
}
