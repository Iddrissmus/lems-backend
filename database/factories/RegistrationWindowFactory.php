<?php

namespace Database\Factories;

use App\Models\RegistrationWindow;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistrationWindow>
 */
class RegistrationWindowFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'semester_id' => Semester::factory(),
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDays(30),
        ];
    }
}
