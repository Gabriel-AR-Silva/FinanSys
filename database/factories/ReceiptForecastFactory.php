<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\ReceiptForecast;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceiptForecast>
 */
class ReceiptForecastFactory extends Factory
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
            'category_id' => fn (array $attributes): int => Category::factory()->create(['user_id' => $attributes['user_id'], 'type' => 'income', 'status' => 'active'])->id,
            'amount' => '1000.00',
            'expected_on' => now('America/Sao_Paulo')->toDateString(),
            'status' => 'expected',
            'operation_id' => fake()->uuid(),
        ];
    }
}
