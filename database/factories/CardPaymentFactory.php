<?php

namespace Database\Factories;

use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\CardPayment;
use App\Models\CreditCard;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CardPayment>
 */
class CardPaymentFactory extends Factory
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
            'source_account_id' => fn (array $attributes): int => Account::factory()->create(['user_id' => $attributes['user_id']])->id,
            'ledger_entry_id' => fn (array $attributes): int => LedgerEntry::factory()->create([
                'user_id' => $attributes['user_id'],
                'reference_type' => 'account',
                'reference_id' => $attributes['source_account_id'],
                'type' => LedgerEntryType::CardPayment,
                'planning_type' => null,
            ])->id,
            'amount' => '100.00',
            'paid_on' => '2026-09-10',
            'operation_id' => fake()->uuid(),
        ];
    }
}
