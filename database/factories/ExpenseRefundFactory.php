<?php

namespace Database\Factories;

use App\Models\ExpenseRefund;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseRefund>
 */
class ExpenseRefundFactory extends Factory
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
            'expense_ledger_entry_id' => fn (array $attributes): int => LedgerEntry::factory()->expense()->create(['user_id' => $attributes['user_id']])->id,
            'refund_ledger_entry_id' => fn (array $attributes): int => LedgerEntry::factory()->create(['user_id' => $attributes['user_id'], 'type' => 'refund'])->id,
            'operation_id' => fake()->uuid(),
        ];
    }
}
