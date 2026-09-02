<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Pocket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pocket> */
class PocketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => fn (array $attributes) => Account::factory()->create(['user_id' => $attributes['user_id']]),
            'name' => fake()->randomElement(['Reserva', 'Viagem', 'Emergência']),
            'operation_id' => fake()->uuid(),
        ];
    }
}
