<?php

namespace Database\Factories;

use App\Models\CardCharge;
use App\Models\CardChargePaymentAllocation;
use App\Models\CardPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CardChargePaymentAllocation> */
class CardChargePaymentAllocationFactory extends Factory
{
    protected $model = CardChargePaymentAllocation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'card_charge_id' => fn (array $attributes): int => CardCharge::factory()->create(['user_id' => $attributes['user_id']])->id,
            'card_payment_id' => fn (array $attributes): int => CardPayment::factory()->create([
                'user_id' => $attributes['user_id'],
                'credit_card_id' => CardCharge::query()->findOrFail($attributes['card_charge_id'])->credit_card_id,
            ])->id,
            'amount' => '15.00',
        ];
    }
}
