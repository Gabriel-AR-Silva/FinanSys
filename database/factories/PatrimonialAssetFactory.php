<?php

namespace Database\Factories;

use App\Models\PatrimonialAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PatrimonialAsset> */
class PatrimonialAssetFactory extends Factory
{
    public function definition(): array
    {
        $value = fake()->randomElement([3500, 8500, 18000, 32000]);

        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Notebook', 'Moto', 'Reserva investida', 'Equipamentos']),
            'category' => fake()->randomElement(['Tecnologia', 'Veículo', 'Investimentos', 'Trabalho']),
            'estimated_value' => number_format($value, 2, '.', ''),
            'debt_balance' => '0.00',
            'valued_on' => now('America/Sao_Paulo')->toDateString(),
        ];
    }
}
