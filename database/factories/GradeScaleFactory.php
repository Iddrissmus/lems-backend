<?php

namespace Database\Factories;

use App\Models\GradeScale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeScale>
 */
class GradeScaleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Demo scale '.fake()->unique()->numerify('###'),
            'is_active' => true,
            'is_demo' => true,
        ];
    }
}
