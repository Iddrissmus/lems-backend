<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Programme;
use App\Models\ProgrammeCourse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgrammeCourse>
 */
class ProgrammeCourseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'programme_id' => Programme::factory(),
            'course_id' => Course::factory(),
            'year_of_study' => 1,
            'semester_number' => 1,
            'curriculum_version' => 'v1',
            'is_required' => true,
        ];
    }
}
