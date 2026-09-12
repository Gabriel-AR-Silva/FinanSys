<?php

namespace Tests\Feature;

use App\Actions\DeleteAccount;
use App\Actions\DeleteManualLedgerEntry;
use App\Actions\RecalculateReceiptForecast;
use App\Actions\RestoreAccount;
use App\Actions\RestoreManualLedgerEntry;
use App\Actions\ReverseLedgerOperation;
use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\ReceiptForecast;
use App\Models\ReceiptForecastLink;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class ReceiptForecastReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_and_excess_receipts_update_progress_without_creating_ledger_entries(): void
    {
        $user = User::factory()->create();
        $forecast = ReceiptForecast::factory()->for($user)->create(['amount' => '1000.00']);
        $first = $this->income($user, '400.00');
        $second = $this->income($user, '700.00');

        $this->actingAs($user)->post(route('receipt-forecasts.receipts.store', $forecast), $this->payload($first, 1))->assertRedirect()->assertSessionHas('success');

        $forecast->refresh();
        $this->assertSame('expected', $forecast->status->value);
        $this->assertSame(2, $forecast->version);
        $this->assertSame([
            'received' => '400.00', 'pending' => '600.00', 'excess' => '0.00', 'fulfilled' => false,
        ], app(RecalculateReceiptForecast::class)->calculate($user, $forecast));

        $this->post(route('receipt-forecasts.receipts.store', $forecast), $this->payload($second, 2))->assertRedirect()->assertSessionHasNoErrors();

        $forecast->refresh();
        $this->assertSame('fulfilled', $forecast->status->value);
        $this->assertSame(3, $forecast->version);
        $this->assertSame([
            'received' => '1100.00', 'pending' => '0.00', 'excess' => '100.00', 'fulfilled' => true,
        ], app(RecalculateReceiptForecast::class)->calculate($user, $forecast));
        $this->assertDatabaseCount('ledger_entries', 2);
        $this->assertDatabaseCount('receipt_forecast_links', 2);
        $this->assertSame(4, AuditLog::count());
    }

    public function test_link_is_idempotent_and_one_income_cannot_belong_to_two_forecasts(): void
    {
        $user = User::factory()->create();
        $firstForecast = ReceiptForecast::factory()->for($user)->create(['amount' => '2000.00']);
        $secondForecast = ReceiptForecast::factory()->for($user)->create();
        $income = $this->income($user, '500.00');
        $payload = $this->payload($income, 1);

        $this->actingAs($user)->post(route('receipt-forecasts.receipts.store', $firstForecast), $payload)->assertRedirect();
        $this->post(route('receipt-forecasts.receipts.store', $firstForecast), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('receipt-forecasts.receipts.store', $secondForecast), array_replace($payload, ['operation_id' => (string) Str::uuid()]))
            ->assertSessionHasErrors('ledger_entry_id');
        $this->post(route('receipt-forecasts.receipts.store', $secondForecast), $payload)->assertSessionHasErrors('operation_id');

        $this->assertDatabaseCount('receipt_forecast_links', 1);
        $this->assertSame(2, $firstForecast->fresh()->version);
        $this->assertSame(1, $secondForecast->fresh()->version);
        $this->assertSame(2, AuditLog::count());
    }

    public function test_guest_foreign_user_and_ineligible_entries_cannot_be_linked(): void
    {
        $owner = User::factory()->create();
        $forecast = ReceiptForecast::factory()->for($owner)->create();
        $income = $this->income($owner, '100.00');
        $foreignIncome = $this->income(User::factory()->create(), '100.00');
        $expense = LedgerEntry::factory()->expense()->for($owner)->create();

        $this->post(route('receipt-forecasts.receipts.store', $forecast), $this->payload($income, 1))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->post(route('receipt-forecasts.receipts.store', $forecast), $this->payload($income, 1))->assertNotFound();
        $this->actingAs($owner)->post(route('receipt-forecasts.receipts.store', $forecast), $this->payload($foreignIncome, 1))->assertNotFound();
        $this->post(route('receipt-forecasts.receipts.store', $forecast), $this->payload($expense, 1))->assertSessionHasErrors('ledger_entry_id');
        $forecast->update(['status' => 'cancelled']);
        $this->post(route('receipt-forecasts.receipts.store', $forecast), $this->payload($income, 1))->assertSessionHasErrors('forecast');

        $this->assertDatabaseCount('receipt_forecast_links', 0);
    }

    public function test_stale_forecast_version_does_not_create_link(): void
    {
        $user = User::factory()->create();
        $forecast = ReceiptForecast::factory()->for($user)->create(['version' => 2]);
        $income = $this->income($user, '100.00');

        $this->actingAs($user)->post(route('receipt-forecasts.receipts.store', $forecast), $this->payload($income, 1))
            ->assertSessionHasErrors('forecast_version');

        $this->assertDatabaseCount('receipt_forecast_links', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_future_series_edit_skips_an_occurrence_that_already_received_value(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['type' => 'income']);
        $operationId = (string) Str::uuid();
        $this->actingAs($user)->post(route('receipt-forecasts.store'), [
            'category_id' => $category->id,
            'amount' => '1000.00',
            'expected_on' => '2027-01-31',
            'recurrence_count' => 3,
            'operation_id' => $operationId,
        ])->assertRedirect();
        $series = ReceiptForecast::query()->orderBy('series_position')->get();
        $second = $series[1];
        $income = $this->income($user, '100.00');
        $this->post(route('receipt-forecasts.receipts.store', $second), $this->payload($income, 1))->assertRedirect();

        $first = $series[0]->fresh();
        $this->put(route('receipt-forecasts.update', $first), [
            'category_id' => $category->id,
            'amount' => '1200.00',
            'expected_on' => '2027-01-30',
            'version' => $first->version,
            'edit_scope' => 'future',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $updated = ReceiptForecast::query()->orderBy('series_position')->get();
        $this->assertSame(['1200.00', '1000.00', '1200.00'], $updated->pluck('amount')->all());
        $this->assertSame(['2027-01-30', '2027-02-28', '2027-03-30'], $updated->pluck('expected_on')->map->toDateString()->all());
        $this->assertSame([30, 31, 30], $updated->pluck('original_day')->all());
    }

    public function test_partial_forecast_can_reschedule_only_its_residual_without_moving_received_money(): void
    {
        $user = User::factory()->create();
        $forecast = ReceiptForecast::factory()->for($user)->create(['amount' => '1000.00', 'expected_on' => '2026-09-15']);
        $income = $this->income($user, '400.00');
        $this->actingAs($user)->post(route('receipt-forecasts.receipts.store', $forecast), $this->payload($income, 1))->assertRedirect();

        $this->put(route('receipt-forecasts.update', $forecast), [
            'category_id' => $forecast->category_id,
            'amount' => '1000.00',
            'expected_on' => '2026-10-20',
            'version' => 2,
            'edit_scope' => 'this',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $forecast->refresh();
        $this->assertSame('2026-10-20', $forecast->expected_on->toDateString());
        $this->assertSame('2026-09-10', $income->fresh()->occurred_at->toDateString());
        $this->assertSame(['received' => '400.00', 'pending' => '600.00', 'excess' => '0.00', 'fulfilled' => false], app(RecalculateReceiptForecast::class)->calculate($user, $forecast));

        $this->put(route('receipt-forecasts.update', $forecast), [
            'category_id' => $forecast->category_id,
            'amount' => '399.99',
            'expected_on' => '2026-10-20',
            'version' => 3,
            'edit_scope' => 'this',
        ])->assertSessionHasErrors('amount');
    }

    public function test_reversal_detaches_receipt_and_reopens_only_the_pending_amount(): void
    {
        $user = User::factory()->create();
        $forecast = ReceiptForecast::factory()->for($user)->create(['amount' => '1000.00']);
        $income = $this->income($user, '1200.00');
        $this->actingAs($user)->post(route('receipt-forecasts.receipts.store', $forecast), $this->payload($income, 1))->assertRedirect();

        app(ReverseLedgerOperation::class)->handle($user, $income->id, (string) Str::uuid());

        $forecast->refresh();
        $link = ReceiptForecastLink::sole();
        $this->assertSame('expected', $forecast->status->value);
        $this->assertSame(3, $forecast->version);
        $this->assertSame('ledger_reversed', $link->unlink_reason->value);
        $this->assertNotNull($link->unlinked_at);
        $this->assertSame([
            'received' => '0.00', 'pending' => '1000.00', 'excess' => '0.00', 'fulfilled' => false,
        ], app(RecalculateReceiptForecast::class)->calculate($user, $forecast));
    }

    public function test_delete_detaches_and_restore_does_not_silently_restore_link(): void
    {
        $user = User::factory()->create();
        $forecast = ReceiptForecast::factory()->for($user)->create(['amount' => '500.00']);
        $income = $this->income($user, '500.00');
        $this->actingAs($user)->post(route('receipt-forecasts.receipts.store', $forecast), $this->payload($income, 1))->assertRedirect();

        app(DeleteManualLedgerEntry::class)->handle($user, $income->id);
        app(RestoreManualLedgerEntry::class)->handle($user, $income->id);

        $forecast->refresh();
        $link = ReceiptForecastLink::sole();
        $this->assertSame('expected', $forecast->status->value);
        $this->assertSame('ledger_deleted', $link->unlink_reason->value);
        $this->assertNotNull($link->unlinked_at);
        $this->assertSame('0.00', app(RecalculateReceiptForecast::class)->calculate($user, $forecast)['received']);
    }

    public function test_restored_income_requires_explicit_idempotent_relink_confirmation(): void
    {
        $user = User::factory()->create();
        $forecast = ReceiptForecast::factory()->for($user)->create(['expected_on' => '2026-09-15', 'amount' => '500.00']);
        $income = $this->income($user, '500.00');
        $this->actingAs($user)->post(route('receipt-forecasts.receipts.store', $forecast), $this->payload($income, 1))->assertRedirect();
        app(DeleteManualLedgerEntry::class)->handle($user, $income->id);
        app(RestoreManualLedgerEntry::class)->handle($user, $income->id);

        $this->get(route('receipt-forecasts.index', ['month' => '2026-09']))
            ->assertInertia(fn (Assert $page) => $page->where('availableReceipts.0.id', $income->id));
        $payload = $this->payload($income, $forecast->fresh()->version);
        $this->post(route('receipt-forecasts.receipts.store', $forecast), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('receipt-forecasts.receipts.store', $forecast), $payload)->assertRedirect()->assertSessionHasNoErrors();

        $link = ReceiptForecastLink::sole();
        $this->assertNull($link->unlinked_at);
        $this->assertNull($link->unlink_reason);
        $this->assertSame(3, $link->version);
        $this->assertSame('500.00', app(RecalculateReceiptForecast::class)->calculate($user, $forecast->fresh())['received']);
        $this->assertDatabaseCount('receipt_forecast_link_operations', 2);
    }

    public function test_account_cascade_restore_does_not_silently_restore_link(): void
    {
        $user = User::factory()->create();
        $forecast = ReceiptForecast::factory()->for($user)->create(['amount' => '500.00']);
        $income = $this->income($user, '500.00');
        $this->actingAs($user)->post(route('receipt-forecasts.receipts.store', $forecast), $this->payload($income, 1))->assertRedirect();

        app(DeleteAccount::class)->handle($user, (int) $income->reference_id);
        app(RestoreAccount::class)->handle($user, (int) $income->reference_id);

        $link = ReceiptForecastLink::sole();
        $this->assertNotNull($link->unlinked_at);
        $this->assertSame('ledger_deleted', $link->unlink_reason->value);
        $this->assertSame('expected', $forecast->fresh()->status->value);
        $this->assertSame('0.00', app(RecalculateReceiptForecast::class)->calculate($user, $forecast->fresh())['received']);
    }

    public function test_index_exposes_progress_links_and_only_available_owned_income(): void
    {
        $user = User::factory()->create();
        $forecast = ReceiptForecast::factory()->for($user)->create(['expected_on' => '2026-09-15', 'amount' => '1000.00']);
        $linked = $this->income($user, '250.00');
        $available = $this->income($user, '300.00');
        $expense = LedgerEntry::factory()->expense()->for($user)->create();
        $foreign = $this->income(User::factory()->create(), '400.00');
        $this->actingAs($user)->post(route('receipt-forecasts.receipts.store', $forecast), $this->payload($linked, 1))->assertRedirect();

        $this->get(route('receipt-forecasts.index', ['month' => '2026-09']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('forecasts.data.0.received', '250.00')
                ->where('forecasts.data.0.pending', '750.00')
                ->where('forecasts.data.0.excess', '0.00')
                ->has('forecasts.data.0.receipts', 1)
                ->has('availableReceipts', 1)
                ->where('availableReceipts.0.id', $available->id));

        $this->assertNotSame($expense->id, $available->id);
        $this->assertNotSame($foreign->id, $available->id);
    }

    public function test_audit_failure_rolls_back_link_and_forecast_progress(): void
    {
        $user = User::factory()->create();
        $forecast = ReceiptForecast::factory()->for($user)->create();
        $income = $this->income($user, '100.00');
        $this->mock(AuditRecorder::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('audit unavailable'));

        $this->actingAs($user)->post(route('receipt-forecasts.receipts.store', $forecast), $this->payload($income, 1))->assertServerError();

        $this->assertDatabaseCount('receipt_forecast_links', 0);
        $this->assertSame(1, $forecast->fresh()->version);
        $this->assertSame('expected', $forecast->fresh()->status->value);
    }

    private function income(User $user, string $amount): LedgerEntry
    {
        $account = Account::factory()->for($user)->create();

        return LedgerEntry::factory()->for($user)->for($account, 'reference')->create([
            'type' => LedgerEntryType::Income,
            'amount' => $amount,
            'occurred_at' => '2026-09-10 12:00:00',
        ]);
    }

    /** @return array{ledger_entry_id:int,forecast_version:int,operation_id:string} */
    private function payload(LedgerEntry $entry, int $version): array
    {
        return [
            'ledger_entry_id' => $entry->id,
            'forecast_version' => $version,
            'operation_id' => (string) Str::uuid(),
        ];
    }
}
