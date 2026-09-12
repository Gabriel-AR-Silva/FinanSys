<?php

namespace Database\Factories;

use App\Models\MonthlyFinancialSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MonthlyFinancialSetting> */
class MonthlyFinancialSettingFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'month' => '2026-09', 'protection_type' => 'fixed', 'protection_value' => '0.00', 'version' => 1];
    }
}
