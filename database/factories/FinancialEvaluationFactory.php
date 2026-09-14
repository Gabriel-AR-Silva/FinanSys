<?php

namespace Database\Factories;

use App\Models\FinancialEvaluation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialEvaluation>
 */
class FinancialEvaluationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'evaluation_date' => now()->toDateString(),
            'view' => 'current',
            'rules_version' => '2026-09-08',
            'source' => 'recorded',
            'evaluated_at' => now(),
            'result' => [],
        ];
    }
}
