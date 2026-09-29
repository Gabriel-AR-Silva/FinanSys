<?php

namespace Database\Factories;

use App\Models\InvestmentPosition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvestmentPosition> */
class InvestmentPositionFactory extends Factory
{
    protected $model = InvestmentPosition::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'asset_type' => 'Renda variável',
            'ticker' => strtoupper(fake()->lexify('????')).'3',
            'name' => fake()->company(),
            'quantity' => '10.00000000',
            'average_cost' => '20.0000',
            'total_invested' => '200.00',
            'current_value' => '220.00',
            'valuation_source' => 'manual',
            'valued_on' => now('America/Sao_Paulo')->toDateString(),
        ];
    }
}
