<?php

namespace Database\Factories;

use App\Models\CardPurchase;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CardPurchase>
 */
class CardPurchaseFactory extends Factory
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
            'credit_card_id' => fn (array $attributes): int => CreditCard::factory()->create(['user_id' => $attributes['user_id']])->id,
            'category_id' => fn (array $attributes): int => Category::factory()->create(['user_id' => $attributes['user_id'], 'type' => 'expense'])->id,
            'description' => fake()->words(3, true),
            'planning_type' => 'ordinary',
            'gross_amount' => '300.00',
            'purchased_on' => '2026-09-01',
            'installments_count' => 3,
            'operation_id' => fake()->uuid(),
        ];
    }
}
