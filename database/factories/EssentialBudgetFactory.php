<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\EssentialBudget;
use App\Models\MonthlyFinancialSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EssentialBudget> */
class EssentialBudgetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'monthly_financial_setting_id' => MonthlyFinancialSetting::factory(),
            'user_id' => fn (array $attributes): int => MonthlyFinancialSetting::findOrFail($attributes['monthly_financial_setting_id'])->user_id,
            'category_id' => fn (array $attributes): int => Category::factory()->create(['user_id' => $attributes['user_id'], 'type' => 'expense'])->id,
            'amount' => '600.00',
        ];
    }
}
