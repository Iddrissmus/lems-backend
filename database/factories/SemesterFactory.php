<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Semester>
 */
class SemesterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'number' => 1,
            'starts_on' => '2026-09-07',
            'length_weeks' => 16,
            'ends_on' => '2026-12-27', // 16 weeks from the start
            'is_current' => false,
        ];
    }
}
