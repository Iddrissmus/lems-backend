<?php

namespace Database\Factories;

use App\Domain\Academics\Enums\ScoreStatus;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Enrolment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentScore>
 */
class AssessmentScoreFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'enrolment_id' => Enrolment::factory(),
            'version' => 1,
            'score' => 70,
            'status' => ScoreStatus::Draft,
            'entered_by' => User::factory(),
        ];
    }
}
