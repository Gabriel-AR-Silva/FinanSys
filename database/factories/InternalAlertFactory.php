<?php

namespace Database\Factories;

use App\Models\InternalAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternalAlert>
 */
class InternalAlertFactory extends Factory
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
            'alert_date' => now()->toDateString(),
            'view' => 'current',
            'financial_evaluation_id' => null,
            'current_situation' => 'outside_plan',
            'worst_situation' => 'outside_plan',
            'current_deficit' => null,
            'deficit_seen' => false,
            'recovered_at' => null,
            'payload' => [],
        ];
    }
}
