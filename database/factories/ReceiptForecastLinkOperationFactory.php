<?php

namespace Database\Factories;

use App\Models\ReceiptForecastLink;
use App\Models\ReceiptForecastLinkOperation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceiptForecastLinkOperation>
 */
class ReceiptForecastLinkOperationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $link = ReceiptForecastLink::factory()->create();

        return [
            'user_id' => $link->user_id,
            'receipt_forecast_link_id' => $link->id,
            'receipt_forecast_id' => $link->receipt_forecast_id,
            'ledger_entry_id' => $link->ledger_entry_id,
            'operation_id' => fake()->uuid(),
            'kind' => 'linked',
        ];
    }
}
