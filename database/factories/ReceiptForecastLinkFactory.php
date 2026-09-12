<?php

namespace Database\Factories;

use App\Models\LedgerEntry;
use App\Models\ReceiptForecast;
use App\Models\ReceiptForecastLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceiptForecastLink>
 */
class ReceiptForecastLinkFactory extends Factory
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
            'receipt_forecast_id' => fn (array $attributes): int => ReceiptForecast::factory()->create(['user_id' => $attributes['user_id']])->id,
            'ledger_entry_id' => fn (array $attributes): int => LedgerEntry::factory()->income()->create(['user_id' => $attributes['user_id']])->id,
            'operation_id' => fake()->uuid(),
            'linked_at' => now(),
            'version' => 1,
        ];
    }
}
