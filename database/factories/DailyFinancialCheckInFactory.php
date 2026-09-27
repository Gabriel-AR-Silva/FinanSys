<?php

namespace Database\Factories;

use App\Models\DailyBudgetVersion;
use App\Models\DailyFinancialCheckIn;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DailyFinancialCheckIn> */
class DailyFinancialCheckInFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'actor_id' => fn (array $attributes): int => $attributes['user_id'],
            'local_date' => now('America/Sao_Paulo')->toDateString(),
            'revision' => 1,
            'supersedes_id' => null,
            'daily_budget_version_id' => fn (array $attributes): int => DailyBudgetVersion::factory()->create([
                'user_id' => $attributes['user_id'],
                'actor_id' => $attributes['user_id'],
            ])->id,
            'budget_amount' => '80.00',
            'eligible_spent' => '55.00',
            'margin' => '25.00',
            'rules_version' => 'v2',
            'source' => 'recorded',
            'confirmed_at' => now('UTC'),
            'reason' => 'Cenário de desenvolvimento',
            'operation_id' => fake()->uuid(),
        ];
    }
}
