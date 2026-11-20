<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Programme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Programme>
 */
class ProgrammeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'name' => fake()->unique()->words(3, true),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'duration_years' => 3,
        ];
    }
}
