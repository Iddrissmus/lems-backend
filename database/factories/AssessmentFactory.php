<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\CourseOffering;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_offering_id' => CourseOffering::factory(),
            'name' => 'Overall',
            'weight_percent' => 100,
            'max_score' => 100,
        ];
    }
}
