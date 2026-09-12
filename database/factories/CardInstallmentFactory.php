<?php

namespace Database\Factories;

use App\Models\CardInstallment;
use App\Models\CardPurchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CardInstallment>
 */
class CardInstallmentFactory extends Factory
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
            'card_purchase_id' => fn (array $attributes): int => CardPurchase::factory()->create(['user_id' => $attributes['user_id']])->id,
            'installment_number' => 1,
            'gross_amount' => '100.00',
            'paid_amount' => '0.00',
            'due_on' => '2026-10-12',
            'original_due_on' => '2026-10-12',
            'status' => 'pending',
        ];
    }
}
