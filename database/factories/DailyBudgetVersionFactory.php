<?php

namespace Database\Factories;

use App\Models\DailyBudgetVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DailyBudgetVersion> */
class DailyBudgetVersionFactory extends Factory
{
    public function definition(): array
    {
        $user = User::factory();

        return [
            'user_id' => $user,
            'actor_id' => $user,
            'amount' => fake()->randomElement(['50.00', '65.00', '80.00', '100.00']),
            'effective_at' => now('UTC')->startOfDay(),
            'recorded_at' => now('UTC'),
            'origin' => 'manual',
            'reason' => 'Cenário de desenvolvimento',
            'operation_id' => fake()->uuid(),
        ];
    }
}
