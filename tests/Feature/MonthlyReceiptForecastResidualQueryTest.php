<?php

namespace Tests\Feature;

use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\ReceiptForecast;
use App\Models\ReceiptForecastLink;
use App\Models\User;
use App\Queries\MonthlyReceiptForecastResidualQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;
use UnexpectedValueException;

class MonthlyReceiptForecastResidualQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_unreceived_residual_of_current_month_is_projected_without_double_counting_receipts(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $forecast = ReceiptForecast::factory()->for($user)->create(['amount' => '100.00', 'expected_on' => '2026-09-30']);
        $receipt = LedgerEntry::factory()->income()->create(['user_id' => $user->id, 'amount' => '40.00']);
        ReceiptForecastLink::factory()->create([
            'user_id' => $user->id,
            'receipt_forecast_id' => $forecast->id,
            'ledger_entry_id' => $receipt->id,
        ]);
        $unreceived = ReceiptForecast::factory()->for($user)->create(['amount' => '25.35', 'expected_on' => '2026-09-01']);
        ReceiptForecast::factory()->for($user)->create(['amount' => '300.00', 'expected_on' => '2026-09-15', 'status' => 'cancelled']);
        ReceiptForecast::factory()->for($user)->create(['amount' => '400.00', 'expected_on' => '2026-10-01']);
        ReceiptForecast::factory()->for($other)->create(['amount' => '999.00', 'expected_on' => '2026-09-01']);

        $result = app(MonthlyReceiptForecastResidualQuery::class)->forUserInMonth($user, '2026-09');

        $this->assertSame('85.35', $result['pending_total']);
        $this->assertSame([$unreceived->id, $forecast->id], array_column($result['forecasts'], 'id'));
        $this->assertSame('40.00', $result['forecasts'][1]['received']);
        $this->assertSame('60.00', $result['forecasts'][1]['pending']);
        $this->assertSame('current_receipt_forecasts_only', $result['coverage']);
        $this->assertSame('999.00', app(MonthlyReceiptForecastResidualQuery::class)->forUserInMonth($other, '2026-09')['pending_total']);
    }

    public function test_reversed_receipt_does_not_reduce_forecast_residual(): void
    {
        $user = User::factory()->create();
        $forecast = ReceiptForecast::factory()->for($user)->create(['amount' => '100.00', 'expected_on' => '2026-09-15']);
        $receipt = LedgerEntry::factory()->income()->create(['user_id' => $user->id, 'amount' => '40.00']);
        ReceiptForecastLink::factory()->create(['user_id' => $user->id, 'receipt_forecast_id' => $forecast->id, 'ledger_entry_id' => $receipt->id]);
        LedgerEntry::factory()->create([
            'user_id' => $user->id,
            'type' => LedgerEntryType::Refund,
            'planning_type' => null,
            'reversal_of_operation_id' => $receipt->operation_id,
            'amount' => '40.00',
        ]);

        $result = app(MonthlyReceiptForecastResidualQuery::class)->forUserInMonth($user, '2026-09');

        $this->assertSame('100.00', $result['pending_total']);
        $this->assertSame('0.00', $result['forecasts'][0]['received']);
    }

    public function test_foreign_receipt_link_fails_closed_instead_of_counting_another_users_income(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $forecast = ReceiptForecast::factory()->for($user)->create(['amount' => '100.00', 'expected_on' => '2026-09-15']);
        $foreignReceipt = LedgerEntry::factory()->income()->create(['user_id' => $other->id, 'amount' => '40.00']);
        ReceiptForecastLink::factory()->create([
            'user_id' => $user->id,
            'receipt_forecast_id' => $forecast->id,
            'ledger_entry_id' => $foreignReceipt->id,
        ]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Inconsistent receipt forecast linkage.');
        app(MonthlyReceiptForecastResidualQuery::class)->forUserInMonth($user, '2026-09');
    }

    public function test_invalid_month_is_rejected_and_empty_month_is_explicitly_empty(): void
    {
        $user = User::factory()->create();
        $result = app(MonthlyReceiptForecastResidualQuery::class)->forUserInMonth($user, '2026-02');
        $this->assertSame('0.00', $result['pending_total']);
        $this->assertSame([], $result['forecasts']);

        $this->expectException(InvalidArgumentException::class);
        app(MonthlyReceiptForecastResidualQuery::class)->forUserInMonth($user, '2026-13');
    }
}
