<?php

namespace Database\Factories;

use App\Models\Cohort;
use App\Models\Programme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cohort>
 */
class CohortFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'programme_id' => Programme::factory(),
            'name' => 'Class of '.fake()->unique()->numberBetween(2027, 2090),
            'intake_year' => fake()->unique()->numberBetween(2000, 2090),
            'current_year_of_study' => 1,
        ];
    }
}
