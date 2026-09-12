<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\ReceiptForecast;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ReceiptForecastTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_any_forecast_operation(): void
    {
        $this->get(route('receipt-forecasts.index'))->assertRedirect(route('login'));
        $this->post(route('receipt-forecasts.store'), [])->assertRedirect(route('login'));
        $this->put(route('receipt-forecasts.cancel', 1))->assertRedirect(route('login'));
        $this->assertDatabaseCount('receipt_forecasts', 0);
    }

    public function test_create_persists_exact_amount_with_audit_but_no_ledger_entry(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['type' => 'income']);
        $payload = $this->payload($category, ['amount' => '9999999999.99', 'user_id' => 999, 'status' => 'cancelled']);

        $this->actingAs($user)->post(route('receipt-forecasts.store'), $payload)
            ->assertRedirect(route('receipt-forecasts.index', ['month' => '2026-09']))->assertSessionHas('success');

        $forecast = ReceiptForecast::sole();
        $this->assertSame($user->id, $forecast->user_id);
        $this->assertSame('9999999999.99', $forecast->amount);
        $this->assertSame('expected', $forecast->status->value);
        $this->assertSame('2026-09-15', $forecast->expected_on->toDateString());
        $this->assertDatabaseCount('ledger_entries', 0);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertSame('9999999999.99', AuditLog::sole()->after['amount']);
    }

    public function test_list_filters_owner_and_month_with_brasilia_overdue_dates(): void
    {
        $user = User::factory()->create();
        $this->travelTo(new \DateTimeImmutable('2026-10-01T01:00:00Z'));
        ReceiptForecast::factory()->for($user)->create(['expected_on' => '2026-09-29', 'amount' => '10.01']);
        ReceiptForecast::factory()->for($user)->create(['expected_on' => '2026-09-30']);
        ReceiptForecast::factory()->for($user)->create(['expected_on' => '2026-10-01']);
        ReceiptForecast::factory()->create(['expected_on' => '2026-09-01']);

        $this->actingAs($user)->get(route('receipt-forecasts.index'))
            ->assertInertia(fn (Assert $page) => $page->component('ReceiptForecasts/Index')
                ->where('month', '2026-09')->has('forecasts.data', 2)
                ->where('forecasts.data.0.amount', '10.01')->where('forecasts.data.0.is_overdue', true)
                ->where('forecasts.data.1.is_overdue', false));
    }

    public function test_replay_creates_only_one_forecast_and_rejects_changed_payload(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['type' => 'income']);
        $payload = $this->payload($category);

        $this->actingAs($user)->post(route('receipt-forecasts.store'), $payload)->assertRedirect();
        $this->post(route('receipt-forecasts.store'), array_replace($payload, ['amount' => '1000.00']))->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('receipt-forecasts.store'), array_replace($payload, ['amount' => '1200']))->assertSessionHasErrors('operation_id');

        $this->assertDatabaseCount('receipt_forecasts', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertSame('1000.00', ReceiptForecast::sole()->amount);
    }

    public function test_monthly_recurrence_preserves_original_day_and_replay_does_not_duplicate_series(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['type' => 'income']);
        $payload = $this->payload($category, ['expected_on' => '2027-01-31', 'recurrence_count' => 3]);

        $this->actingAs($user)->post(route('receipt-forecasts.store'), $payload)->assertRedirect();
        $this->post(route('receipt-forecasts.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();

        $forecasts = ReceiptForecast::query()->orderBy('series_position')->get();
        $this->assertCount(3, $forecasts);
        $this->assertSame(['2027-01-31', '2027-02-28', '2027-03-31'], $forecasts->pluck('expected_on')->map->toDateString()->all());
        $this->assertSame([31, 31, 31], $forecasts->pluck('original_day')->all());
        $this->assertSame([$payload['operation_id']], $forecasts->pluck('series_id')->unique()->values()->all());
        $this->assertCount(3, $forecasts->pluck('operation_id')->unique());
        $this->assertDatabaseCount('audit_logs', 3);
    }

    public function test_recurrence_beyond_supported_calendar_rolls_back_the_whole_series(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['type' => 'income']);

        $this->actingAs($user)->post(route('receipt-forecasts.store'), $this->payload($category, [
            'expected_on' => '9999-12-31', 'recurrence_count' => 2,
        ]))->assertSessionHasErrors('expected_on');

        $this->assertDatabaseCount('receipt_forecasts', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_recurring_edit_can_change_this_and_future_occurrences_while_preserving_monthly_day_rule(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['type' => 'income']);
        $this->actingAs($user)->post(route('receipt-forecasts.store'), $this->payload($category, [
            'expected_on' => '2027-01-31', 'recurrence_count' => 3,
        ]))->assertRedirect();
        $first = ReceiptForecast::query()->orderBy('series_position')->firstOrFail();

        $this->put(route('receipt-forecasts.update', $first), $this->editPayload($first, [
            'amount' => '1200.00', 'expected_on' => '2027-01-30', 'edit_scope' => 'future',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $series = ReceiptForecast::query()->orderBy('series_position')->get();
        $this->assertSame(['2027-01-30', '2027-02-28', '2027-03-30'], $series->pluck('expected_on')->map->toDateString()->all());
        $this->assertSame(['1200.00', '1200.00', '1200.00'], $series->pluck('amount')->all());
        $this->assertSame([30, 30, 30], $series->pluck('original_day')->all());
        $this->assertSame([2, 2, 2], $series->pluck('version')->all());
        $this->assertDatabaseCount('audit_logs', 6);
    }

    public function test_recurring_edit_can_change_only_selected_occurrence(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['type' => 'income']);
        $this->actingAs($user)->post(route('receipt-forecasts.store'), $this->payload($category, ['recurrence_count' => 3]))->assertRedirect();
        $second = ReceiptForecast::query()->where('series_position', 1)->firstOrFail();

        $this->put(route('receipt-forecasts.update', $second), $this->editPayload($second, [
            'amount' => '777.00', 'edit_scope' => 'this',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(['1000.00', '777.00', '1000.00'], ReceiptForecast::query()->orderBy('series_position')->pluck('amount')->all());
        $this->assertSame([1, 2, 1], ReceiptForecast::query()->orderBy('series_position')->pluck('version')->all());
        $this->assertDatabaseCount('audit_logs', 4);
    }

    public function test_cancellation_preserves_record_and_is_repeatable_without_duplicate_audit(): void
    {
        $forecast = ReceiptForecast::factory()->create(['expected_on' => '2026-09-15']);

        $this->actingAs($forecast->user)->put(route('receipt-forecasts.cancel', $forecast->id), ['version' => 1])->assertRedirect();
        $this->put(route('receipt-forecasts.cancel', $forecast->id), ['version' => 1])->assertRedirect();

        $this->assertSame('cancelled', $forecast->fresh()->status->value);
        $this->assertDatabaseCount('receipt_forecasts', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertSame('expected', AuditLog::sole()->before['status']);
        $this->assertSame('cancelled', AuditLog::sole()->after['status']);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_other_user_cannot_cancel_forecast(): void
    {
        $forecast = ReceiptForecast::factory()->create();

        $this->actingAs(User::factory()->create())->put(route('receipt-forecasts.cancel', $forecast->id), ['version' => 1])->assertNotFound();

        $this->assertSame('expected', $forecast->fresh()->status->value);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_foreign_inactive_and_expense_categories_are_rejected(): void
    {
        $user = User::factory()->create();
        $categories = [Category::factory()->create(['type' => 'income']),
            Category::factory()->for($user)->create(['type' => 'income', 'status' => 'inactive']),
            Category::factory()->for($user)->create(['type' => 'expense'])];

        foreach ($categories as $category) {
            $this->actingAs($user)->post(route('receipt-forecasts.store'), $this->payload($category))->assertSessionHasErrors('category_id');
        }

        $this->assertDatabaseCount('receipt_forecasts', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_payload_does_not_write(array $changes, string $field): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['type' => 'income']);

        $this->actingAs($user)->post(route('receipt-forecasts.store'), $this->payload($category, $changes))->assertSessionHasErrors($field);

        $this->assertDatabaseCount('receipt_forecasts', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public static function invalidPayloads(): array
    {
        return [
            'zero' => [['amount' => '0'], 'amount'],
            'negative' => [['amount' => '-1'], 'amount'],
            'precision' => [['amount' => '1.001'], 'amount'],
            'overflow' => [['amount' => '10000000000'], 'amount'],
            'localized' => [['amount' => '1,00'], 'amount'],
            'exponent' => [['amount' => '1e3'], 'amount'],
            'invalid date' => [['expected_on' => '2026-02-30'], 'expected_on'],
            'missing key' => [['operation_id' => null], 'operation_id'],
        ];
    }

    public function test_creation_rolls_back_when_audit_fails(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['type' => 'income']);
        $this->mock(AuditRecorder::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('audit unavailable'));

        $this->actingAs($user)->post(route('receipt-forecasts.store'), $this->payload($category))->assertServerError();

        $this->assertDatabaseCount('receipt_forecasts', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_pagination_preserves_month_and_cancelled_forecasts_remain_visible(): void
    {
        $user = User::factory()->create();
        ReceiptForecast::factory()->count(21)->for($user)->create(['expected_on' => '2026-09-01']);
        $last = ReceiptForecast::orderByDesc('id')->firstOrFail();
        $this->actingAs($user)->put(route('receipt-forecasts.cancel', $last->id), ['version' => 1])->assertRedirect();

        $this->get(route('receipt-forecasts.index', ['month' => '2026-09', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('forecasts.data', 1)
                ->where('forecasts.total', 21)->where('month', '2026-09')
                ->where('forecasts.data.0.id', $last->id)
                ->where('forecasts.data.0.status', 'cancelled')->where('forecasts.data.0.is_overdue', false));
    }

    public function test_same_operation_key_is_independent_between_users(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $categoryA = Category::factory()->for($first)->create(['type' => 'income']);
        $categoryB = Category::factory()->for($second)->create(['type' => 'income']);
        $key = (string) Str::uuid();

        $this->actingAs($first)->post(route('receipt-forecasts.store'), $this->payload($categoryA, ['operation_id' => $key]))->assertRedirect();
        $this->actingAs($second)->post(route('receipt-forecasts.store'), $this->payload($categoryB, ['operation_id' => $key]))->assertRedirect();

        $this->assertDatabaseHas('receipt_forecasts', ['user_id' => $first->id, 'operation_id' => $key]);
        $this->assertDatabaseHas('receipt_forecasts', ['user_id' => $second->id, 'operation_id' => $key]);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_replaying_creation_does_not_reopen_cancelled_forecast(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['type' => 'income']);
        $payload = $this->payload($category);
        $this->actingAs($user)->post(route('receipt-forecasts.store'), $payload)->assertRedirect();
        $this->put(route('receipt-forecasts.cancel', ReceiptForecast::sole()->id), ['version' => 1])->assertRedirect();

        $this->post(route('receipt-forecasts.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('cancelled', ReceiptForecast::sole()->status->value);
        $this->assertDatabaseCount('receipt_forecasts', 1);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_cancellation_rolls_back_when_audit_fails(): void
    {
        $forecast = ReceiptForecast::factory()->create();
        $this->mock(AuditRecorder::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('audit unavailable'));

        $this->actingAs($forecast->user)->put(route('receipt-forecasts.cancel', $forecast->id), ['version' => 1])->assertServerError();

        $this->assertSame('expected', $forecast->fresh()->status->value);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function payload(Category $category, array $changes = []): array
    {
        return array_replace(['category_id' => $category->id, 'amount' => '1000', 'expected_on' => '2026-09-15', 'operation_id' => (string) Str::uuid()], $changes);
    }

    public function test_edit_and_reschedule_preserve_audited_history_without_moving_money(): void
    {
        $forecast = ReceiptForecast::factory()->create(['expected_on' => '2026-09-15']);
        $forecast->refresh();

        $this->actingAs($forecast->user)->put(route('receipt-forecasts.update', $forecast->id), $this->editPayload($forecast, ['amount' => '1200.01', 'expected_on' => '2026-10-20']))
            ->assertRedirect(route('receipt-forecasts.index', ['month' => '2026-10']))->assertSessionHas('success');

        $this->assertSame('1200.01', $forecast->fresh()->amount);
        $this->assertSame(2, $forecast->fresh()->version);
        $this->assertSame('2026-10-20', $forecast->fresh()->expected_on->toDateString());
        $audit = AuditLog::sole();
        $this->assertSame('1000.00', $audit->before['amount']);
        $this->assertSame('1200.01', $audit->after['amount']);
        $this->assertDatabaseCount('ledger_entries', 0);
        $this->get(route('receipt-forecasts.index', ['month' => '2026-09']))->assertInertia(fn (Assert $page) => $page->has('forecasts.data', 0));
        $this->get(route('receipt-forecasts.index', ['month' => '2026-10']))->assertInertia(fn (Assert $page) => $page->has('forecasts.data', 1)->where('forecasts.data.0.version', 2));
    }

    public function test_stale_edit_does_not_overwrite_newer_changes(): void
    {
        $forecast = ReceiptForecast::factory()->create();
        $data = $this->editPayload($forecast);
        $this->actingAs($forecast->user)->put(route('receipt-forecasts.update', $forecast->id), array_replace($data, ['amount' => '1200']))->assertRedirect();

        $this->put(route('receipt-forecasts.update', $forecast->id), array_replace($data, ['amount' => '900']))
            ->assertSessionHasErrors(['version' => 'Esta previsão mudou em outra aba. Recarregue antes de editar.']);

        $this->assertSame('1200.00', $forecast->fresh()->amount);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_no_change_edit_does_not_generate_audit_or_version(): void
    {
        $forecast = ReceiptForecast::factory()->create();

        $this->actingAs($forecast->user)->put(route('receipt-forecasts.update', $forecast->id), $this->editPayload($forecast))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, $forecast->fresh()->version);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_cancelled_forecast_cannot_be_reopened_by_editing(): void
    {
        $forecast = ReceiptForecast::factory()->create(['status' => 'cancelled']);

        $this->actingAs($forecast->user)->put(route('receipt-forecasts.update', $forecast->id), $this->editPayload($forecast, ['status' => 'expected']))
            ->assertSessionHasErrors(['forecast' => 'Uma previsão concluída ou cancelada não pode ser editada.']);

        $this->assertSame('cancelled', $forecast->fresh()->status->value);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_guest_and_other_user_cannot_edit_forecast(): void
    {
        $forecast = ReceiptForecast::factory()->create();
        $data = $this->editPayload($forecast);

        $this->put(route('receipt-forecasts.update', $forecast->id), $data)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->put(route('receipt-forecasts.update', $forecast->id), $data)->assertNotFound();

        $this->assertSame(1, $forecast->fresh()->version);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_edit_retains_own_inactive_category_but_cannot_select_other_inactive_or_foreign_category(): void
    {
        $forecast = ReceiptForecast::factory()->create();
        $forecast->category->update(['status' => 'inactive']);
        $this->actingAs($forecast->user)->put(route('receipt-forecasts.update', $forecast->id), $this->editPayload($forecast, ['amount' => '1200']))->assertRedirect()->assertSessionHasNoErrors();
        $categories = [Category::factory()->for($forecast->user)->create(['type' => 'income', 'status' => 'inactive']), Category::factory()->create(['type' => 'income']), Category::factory()->for($forecast->user)->create(['type' => 'expense'])];

        foreach ($categories as $category) {
            $this->put(route('receipt-forecasts.update', $forecast->id), $this->editPayload($forecast->fresh(), ['category_id' => $category->id, 'version' => 2]))
                ->assertSessionHasErrors(['category_id' => 'Escolha uma categoria de receita disponível.']);
        }

        $this->assertSame('1200.00', $forecast->fresh()->amount);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_edit_audit_failure_rolls_back_amount_date_and_version(): void
    {
        $forecast = ReceiptForecast::factory()->create(['expected_on' => '2026-09-15']);
        $this->mock(AuditRecorder::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('audit unavailable'));

        $this->actingAs($forecast->user)->put(route('receipt-forecasts.update', $forecast->id), $this->editPayload($forecast, ['amount' => '50', 'expected_on' => '2026-10-01']))->assertServerError();

        $this->assertSame('1000.00', $forecast->fresh()->amount);
        $this->assertSame('2026-09-15', $forecast->fresh()->expected_on->toDateString());
        $this->assertSame(1, $forecast->fresh()->version);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_invalid_edit_payload_preserves_forecast(): void
    {
        $forecast = ReceiptForecast::factory()->create();

        $this->actingAs($forecast->user)->put(route('receipt-forecasts.update', $forecast->id), $this->editPayload($forecast, ['amount' => '0.001', 'expected_on' => '2026-02-30', 'version' => 0]))
            ->assertSessionHasErrors(['amount', 'expected_on', 'version']);

        $this->assertSame('1000.00', $forecast->fresh()->amount);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function editPayload(ReceiptForecast $forecast, array $changes = []): array
    {
        return array_replace(['category_id' => $forecast->category_id, 'amount' => $forecast->amount, 'expected_on' => $forecast->expected_on->toDateString(), 'version' => $forecast->version ?? 1], $changes);
    }

    public function test_stale_cancellation_does_not_cancel_rescheduled_forecast(): void
    {
        $forecast = ReceiptForecast::factory()->create(['expected_on' => '2026-09-15']);
        $this->actingAs($forecast->user)->put(route('receipt-forecasts.update', $forecast->id), $this->editPayload($forecast, ['amount' => '2000', 'expected_on' => '2026-10-20']))->assertRedirect();

        $this->put(route('receipt-forecasts.cancel', $forecast->id), ['version' => 1])
            ->assertSessionHasErrors(['version' => 'Esta previsão mudou em outra aba. Recarregue antes de cancelar.']);

        $this->assertSame('expected', $forecast->fresh()->status->value);
        $this->assertSame('2000.00', $forecast->fresh()->amount);
        $this->assertSame(2, $forecast->fresh()->version);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->put(route('receipt-forecasts.cancel', $forecast->id), ['version' => 2])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $forecast->fresh()->status->value);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_cancellation_without_version_is_rejected(): void
    {
        $forecast = ReceiptForecast::factory()->create();

        $this->actingAs($forecast->user)->put(route('receipt-forecasts.cancel', $forecast->id))
            ->assertSessionHasErrors(['version' => 'Recarregue a previsão antes de cancelar.']);

        $this->assertSame('expected', $forecast->fresh()->status->value);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
