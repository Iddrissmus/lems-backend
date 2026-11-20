<?php

namespace Database\Factories;

use App\Models\GradeScale;
use App\Models\GradeScaleBand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeScaleBand>
 */
class GradeScaleBandFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grade_scale_id' => GradeScale::factory(),
            'letter' => 'A',
            'min_score' => 80,
            'max_score' => 100,
            'sort' => 1,
        ];
    }
}
