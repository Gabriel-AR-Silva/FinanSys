<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ReceiptForecast;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinancialSettingsReceiptTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_tab_contains_only_the_authenticated_users_forecasts_for_selected_month(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        ReceiptForecast::factory()->for($user)->create(['expected_on' => '2026-09-19', 'amount' => '100.00']);
        ReceiptForecast::factory()->for($user)->create(['expected_on' => '2026-10-19', 'amount' => '200.00']);
        ReceiptForecast::factory()->for($other)->create(['expected_on' => '2026-09-19', 'amount' => '900.00']);

        $this->actingAs($user)->get(route('financial-settings.edit', ['month' => '2026-09', 'tab' => 'receipts']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('FinancialSettings/Edit')
                ->where('activeTab', 'receipts')
                ->where('month', '2026-09')
                ->has('receiptForecasts.data', 1)
                ->where('receiptForecasts.data.0.amount', '100.00')
                ->has('receiptCategories')
                ->has('availableReceipts'));
    }

    public function test_forecast_created_from_settings_returns_to_receipts_tab_without_creating_income(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['type' => 'income']);

        $this->actingAs($user)->post(route('receipt-forecasts.store', ['from_settings' => 1]), [
            'category_id' => $category->id,
            'amount' => '40.00',
            'expected_on' => '2026-09-19',
            'recurrence_count' => 1,
            'operation_id' => (string) Str::uuid(),
        ])->assertRedirect(route('financial-settings.edit', ['month' => '2026-09', 'tab' => 'receipts']));

        $this->assertDatabaseHas('receipt_forecasts', ['user_id' => $user->id, 'amount' => '40.00']);
        $this->assertDatabaseCount('ledger_entries', 0);
    }
}
