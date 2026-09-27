<?php

namespace Database\Factories;

use App\Models\FinancialGoal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FinancialGoal> */
class FinancialGoalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'pocket_id' => null,
            'name' => fake()->randomElement(['Reserva', 'Viagem', 'Notebook', 'Entrada da moto']),
            'target_amount' => fake()->randomElement(['1500.00', '3000.00', '5000.00', '10000.00']),
            'target_date' => now('America/Sao_Paulo')->addMonths(fake()->numberBetween(2, 12))->toDateString(),
            'operation_id' => fake()->uuid(),
        ];
    }
}
