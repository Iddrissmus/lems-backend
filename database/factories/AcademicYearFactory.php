<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->numerify('20##/20##'),
            'starts_on' => '2026-09-07',
            'ends_on' => '2027-08-29',
            'is_current' => false,
        ];
    }
}
