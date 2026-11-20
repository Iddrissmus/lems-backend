<?php

namespace Database\Factories;

use App\Domain\Academics\Enums\StudentStatus;
use App\Models\Cohort;
use App\Models\Programme;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentProfile>
 */
class StudentProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'index_number' => 'IDX/'.fake()->unique()->numerify('#####'),
            'programme_id' => Programme::factory(),
            'cohort_id' => fn (array $attributes) => Cohort::factory()->create(['programme_id' => $attributes['programme_id']])->id,
            'year_of_study' => 1,
            'status' => StudentStatus::Active,
            'repeat_count' => 0,
        ];
    }
}
