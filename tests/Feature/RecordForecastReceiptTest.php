<?php

namespace Tests\Feature;

use App\Actions\RecalculateReceiptForecast;
use App\Models\Account;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\ReceiptForecast;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecordForecastReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_receipt_records_one_income_and_links_it_only_once_on_replay(): void
    {
        $this->travelTo(new \DateTimeImmutable('2026-09-18T15:00:00Z'));
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'income']);
        $forecast = ReceiptForecast::factory()->for($user)->for($category)->create([
            'amount' => '100.00', 'expected_on' => '2026-09-19', 'version' => 1,
        ]);
        $payload = [
            'mode' => 'new', 'account_id' => $account->id, 'amount' => '40.00',
            'occurred_at' => '2026-09-18', 'forecast_version' => $forecast->version,
            'operation_id' => (string) Str::uuid(),
        ];
        $url = route('receipt-forecasts.receipts.store', ['forecast' => $forecast->id, 'from_settings' => 1]);

        $this->actingAs($user)->post($url, $payload)
            ->assertRedirect(route('financial-settings.edit', ['month' => '2026-09', 'tab' => 'receipts']));

        $this->assertSame(1, LedgerEntry::query()->whereBelongsTo($user)->where('type', 'income')->count());
        $this->assertDatabaseHas('receipt_forecast_links', ['user_id' => $user->id, 'receipt_forecast_id' => $forecast->id]);
        $this->assertSame('40.00', app(RecalculateReceiptForecast::class)->calculate($user, $forecast->fresh())['received']);
        $this->assertSame('60.00', app(RecalculateReceiptForecast::class)->calculate($user, $forecast->fresh())['pending']);

        $this->post($url, $payload)->assertRedirect();
        $this->assertSame(1, LedgerEntry::query()->whereBelongsTo($user)->where('type', 'income')->count());
        $this->assertDatabaseCount('receipt_forecast_links', 1);
    }

    public function test_foreign_account_fails_without_creating_income_or_link(): void
    {
        $this->travelTo(new \DateTimeImmutable('2026-09-18T15:00:00Z'));
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreignAccount = Account::factory()->for($other)->create();
        $category = Category::factory()->for($user)->create(['type' => 'income']);
        $forecast = ReceiptForecast::factory()->for($user)->for($category)->create(['expected_on' => '2026-09-19', 'version' => 1]);

        $this->actingAs($user)->post(route('receipt-forecasts.receipts.store', $forecast->id), [
            'mode' => 'new', 'account_id' => $foreignAccount->id, 'amount' => '40.00',
            'occurred_at' => '2026-09-18', 'forecast_version' => $forecast->version,
            'operation_id' => (string) Str::uuid(),
        ])->assertSessionHasErrors('account_id');

        $this->assertSame(0, LedgerEntry::query()->whereBelongsTo($user)->count());
        $this->assertDatabaseCount('receipt_forecast_links', 0);
    }
}
