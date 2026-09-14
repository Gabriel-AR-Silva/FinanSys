<?php

namespace Database\Factories;

use App\Models\CardInstallment;
use App\Models\CardPayment;
use App\Models\CardPaymentAllocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CardPaymentAllocation>
 */
class CardPaymentAllocationFactory extends Factory
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
            'card_payment_id' => fn (array $attributes): int => CardPayment::factory()->create(['user_id' => $attributes['user_id']])->id,
            'card_installment_id' => fn (array $attributes): int => CardInstallment::factory()->create(['user_id' => $attributes['user_id']])->id,
            'amount' => '100.00',
        ];
    }
}
