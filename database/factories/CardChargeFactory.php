<?php

namespace Database\Factories;

use App\Enums\CardChargeType;
use App\Enums\CardInstallmentStatus;
use App\Enums\ExpensePlanningType;
use App\Models\CardCharge;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CardCharge> */
class CardChargeFactory extends Factory
{
    protected $model = CardCharge::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'credit_card_id' => fn (array $attributes): int => CreditCard::factory()->create(['user_id' => $attributes['user_id']])->id,
            'category_id' => fn (array $attributes): int => Category::factory()->create(['user_id' => $attributes['user_id'], 'type' => 'expense'])->id,
            'type' => CardChargeType::Interest,
            'description' => 'Juros confirmados',
            'planning_type' => ExpensePlanningType::Extraordinary,
            'amount' => '15.00',
            'paid_amount' => '0.00',
            'charged_on' => '2026-09-09',
            'due_on' => '2026-09-12',
            'status' => CardInstallmentStatus::Pending,
            'operation_id' => (string) Str::uuid(),
        ];
    }
}
