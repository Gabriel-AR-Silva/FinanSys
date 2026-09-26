<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Category;
use App\Models\ExpenseCommitment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExpenseCommitment> */
class ExpenseCommitmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => fn (array $attributes): int => Account::factory()->create(['user_id' => $attributes['user_id']])->id,
            'category_id' => fn (array $attributes): int => Category::factory()->create([
                'user_id' => $attributes['user_id'],
                'type' => 'expense',
                'status' => 'active',
            ])->id,
            'description' => fake()->randomElement(['Aluguel', 'Curso', 'Seguro', 'Manutenção']),
            'amount' => fake()->randomElement(['120.00', '250.00', '480.00', '900.00']),
            'paid_amount' => '0.00',
            'due_on' => now('America/Sao_Paulo')->addDays(fake()->numberBetween(3, 25))->toDateString(),
            'planning_type' => 'fixed',
            'status' => 'pending',
            'version' => 1,
            'operation_id' => fake()->uuid(),
        ];
    }
}
