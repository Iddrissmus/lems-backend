<?php

namespace Database\Factories;

use App\Models\IndexNumberSequence;
use App\Models\Programme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndexNumberSequence>
 */
class IndexNumberSequenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'programme_id' => Programme::factory(),
            'year' => 2026,
            'last_serial' => 0,
        ];
    }
}
