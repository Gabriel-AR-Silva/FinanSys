<?php

namespace Database\Factories;

use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LedgerEntry> */
class LedgerEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reference_type' => 'account',
            'reference_id' => fn (array $attributes) => Account::factory()->create(['user_id' => $attributes['user_id']]),
            'type' => LedgerEntryType::Expense,
            'amount' => fake()->randomFloat(2, 10, 500),
            'operation_id' => fake()->uuid(),
            'occurred_at' => fake()->dateTimeBetween('-1 year'),
            'description' => fake()->sentence(3),
        ];
    }

    public function income(): static
    {
        return $this->state(fn () => ['type' => LedgerEntryType::Income]);
    }

    public function expense(): static
    {
        return $this->state(fn () => ['type' => LedgerEntryType::Expense]);
    }

    public function openingBalance(): static
    {
        return $this->state(fn () => ['type' => LedgerEntryType::OpeningBalance]);
    }
}
