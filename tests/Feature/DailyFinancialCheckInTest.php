<?php

namespace Tests\Feature;

use App\Actions\ConfirmDailyFinancialCheckInBatch;
use App\Actions\RecordDailyFinancialCheckIn;
use App\Actions\SetDailyBudget;
use App\Enums\ExpensePlanningType;
use App\Models\CardInstallment;
use App\Models\CardPurchase;
use App\Models\DailyFinancialCheckIn;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Queries\DailyCheckInCalendarQuery;
use App\Queries\DailyFinancialCheckInHistoryQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DailyFinancialCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_zero_spend_is_confirmed_only_after_explicit_check_in(): void
    {
        $user = User::factory()->create();

        $this->travelTo(CarbonImmutable::parse('2026-09-22 10:00:00', 'America/Sao_Paulo'));
        $budget = app(SetDailyBudget::class)->handle($user, '90.00', (string) Str::uuid());

        $this->travelTo(CarbonImmutable::parse('2026-09-23 08:00:00', 'America/Sao_Paulo'));
        $before = collect(app(DailyCheckInCalendarQuery::class)->forMonth($user, '2026-09'))->firstWhere('date', '2026-09-22');
        $this->assertSame('pending', $before['status']);
        $this->assertSame('0.00', $before['preview_spent']);

        $checkIn = app(RecordDailyFinancialCheckIn::class)->confirm($user, '2026-09-22', (string) Str::uuid());

        $this->assertSame(1, $checkIn->revision);
        $this->assertSame($budget->id, $checkIn->daily_budget_version_id);
        $this->assertSame('90.00', $checkIn->budget_amount);
        $this->assertSame('0.00', $checkIn->eligible_spent);
        $this->assertSame('90.00', $checkIn->margin);
        $this->assertSame('recorded', $checkIn->source);

        $after = collect(app(DailyCheckInCalendarQuery::class)->forMonth($user, '2026-09'))->firstWhere('date', '2026-09-22');
        $this->assertSame('confirmed', $after['status']);
        $this->assertSame('0.00', $after['spent']);
    }

    public function test_installment_payment_state_does_not_duplicate_purchase_in_check_in(): void
    {
        $user = User::factory()->create();

        $this->travelTo(CarbonImmutable::parse('2026-09-22 09:00:00', 'America/Sao_Paulo'));
        app(SetDailyBudget::class)->handle($user, '1500.00', (string) Str::uuid());
        $purchase = CardPurchase::factory()->create([
            'user_id' => $user->id,
            'purchased_on' => '2026-09-22',
            'gross_amount' => '1200.00',
            'installments_count' => 6,
            'planning_type' => ExpensePlanningType::Ordinary,
        ]);
        CardInstallment::factory()->create([
            'user_id' => $user->id,
            'card_purchase_id' => $purchase->id,
            'gross_amount' => '200.00',
            'paid_amount' => '50.00',
            'due_on' => '2026-09-22',
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-09-23 08:00:00', 'America/Sao_Paulo'));
        $checkIn = app(RecordDailyFinancialCheckIn::class)->confirm($user, '2026-09-22', (string) Str::uuid());

        $this->assertSame('1200.00', $checkIn->eligible_spent);
        $this->assertSame('300.00', $checkIn->margin);
    }

    public function test_replay_is_idempotent_and_a_second_confirmation_requires_explicit_correction(): void
    {
        $user = User::factory()->create();
        $this->travelTo(CarbonImmutable::parse('2026-09-22 09:00:00', 'America/Sao_Paulo'));
        app(SetDailyBudget::class)->handle($user, '90.00', (string) Str::uuid());

        $this->travelTo(CarbonImmutable::parse('2026-09-23 08:00:00', 'America/Sao_Paulo'));
        $operation = (string) Str::uuid();
        $recorder = app(RecordDailyFinancialCheckIn::class);
        $first = $recorder->confirm($user, '2026-09-22', $operation);
        $replayed = $recorder->confirm($user, '2026-09-22', $operation);

        $this->assertSame($first->id, $replayed->id);
        $this->assertDatabaseCount('daily_financial_check_ins', 1);

        $this->expectException(ValidationException::class);
        $recorder->confirm($user, '2026-09-22', (string) Str::uuid());
    }

    public function test_late_expense_creates_a_revision_and_preserves_the_original_snapshot(): void
    {
        $user = User::factory()->create();

        $this->travelTo(CarbonImmutable::parse('2026-09-22 09:00:00', 'America/Sao_Paulo'));
        app(SetDailyBudget::class)->handle($user, '90.00', (string) Str::uuid());
        LedgerEntry::factory()->expense()->create([
            'user_id' => $user->id,
            'planning_type' => ExpensePlanningType::Ordinary,
            'amount' => '80.00',
            'occurred_at' => '2026-09-22 12:00:00',
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-09-23 08:00:00', 'America/Sao_Paulo'));
        $recorder = app(RecordDailyFinancialCheckIn::class);
        $original = $recorder->confirm($user, '2026-09-22', (string) Str::uuid());

        $this->travelTo(CarbonImmutable::parse('2026-09-23 09:00:00', 'America/Sao_Paulo'));
        LedgerEntry::factory()->expense()->create([
            'user_id' => $user->id,
            'planning_type' => ExpensePlanningType::Ordinary,
            'amount' => '20.00',
            'occurred_at' => '2026-09-22 18:00:00',
        ]);
        $corrected = $recorder->correct($user, '2026-09-22', (string) Str::uuid(), 'Despesa esquecida.');

        $this->assertSame('80.00', $original->fresh()->eligible_spent);
        $this->assertSame('10.00', $original->fresh()->margin);
        $this->assertSame(2, $corrected->revision);
        $this->assertSame($original->id, $corrected->supersedes_id);
        $this->assertSame('100.00', $corrected->eligible_spent);
        $this->assertSame('-10.00', $corrected->margin);
        $this->assertSame('corrected', $corrected->source);
        $this->assertSame('Despesa esquecida.', $corrected->reason);

        $history = app(DailyFinancialCheckInHistoryQuery::class)->revisionsForDay($user, '2026-09-22');
        $this->assertSame([1, 2], array_column($history, 'revision'));
        $this->assertSame(['80.00', '100.00'], array_column($history, 'spent'));
    }

    public function test_batch_confirmation_is_atomic_and_never_turns_missing_days_into_zero_silently(): void
    {
        $user = User::factory()->create();

        $this->travelTo(CarbonImmutable::parse('2026-09-20 09:00:00', 'America/Sao_Paulo'));
        app(SetDailyBudget::class)->handle($user, '90.00', (string) Str::uuid());

        $this->travelTo(CarbonImmutable::parse('2026-09-23 08:00:00', 'America/Sao_Paulo'));
        $items = [
            ['date' => '2026-09-21', 'operation_id' => (string) Str::uuid()],
            ['date' => '2026-09-22', 'operation_id' => (string) Str::uuid()],
        ];
        $confirmed = app(ConfirmDailyFinancialCheckInBatch::class)->handle($user, $items);

        $this->assertCount(2, $confirmed);
        $this->assertSame(['0.00', '0.00'], $confirmed->pluck('eligible_spent')->all());

        $other = User::factory()->create();
        $this->assertDatabaseMissing('daily_financial_check_ins', ['user_id' => $other->id]);
    }

    public function test_failed_batch_rolls_back_days_already_processed_in_the_same_selection(): void
    {
        $user = User::factory()->create();

        $this->travelTo(CarbonImmutable::parse('2026-09-20 09:00:00', 'America/Sao_Paulo'));
        app(SetDailyBudget::class)->handle($user, '90.00', (string) Str::uuid());

        $this->travelTo(CarbonImmutable::parse('2026-09-23 08:00:00', 'America/Sao_Paulo'));

        try {
            app(ConfirmDailyFinancialCheckInBatch::class)->handle($user, [
                ['date' => '2026-09-21', 'operation_id' => (string) Str::uuid()],
                ['date' => '2026-09-23', 'operation_id' => (string) Str::uuid()],
            ]);
            $this->fail('The current day must not be confirmed as completed.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('daily_financial_check_ins', 0);
        }
    }

    public function test_month_history_uses_highest_revision_instead_of_highest_id_assumption(): void
    {
        $user = User::factory()->create();
        $this->travelTo(CarbonImmutable::parse('2026-09-21 09:00:00', 'America/Sao_Paulo'));
        $budget = app(SetDailyBudget::class)->handle($user, '90.00', (string) Str::uuid());

        $olderId = DailyFinancialCheckIn::query()->create([
            'user_id' => $user->id,
            'actor_id' => $user->id,
            'local_date' => '2026-09-22',
            'revision' => 2,
            'daily_budget_version_id' => $budget->id,
            'budget_amount' => '90.00',
            'eligible_spent' => '100.00',
            'margin' => '-10.00',
            'rules_version' => 'test',
            'source' => 'corrected',
            'confirmed_at' => '2026-09-23 12:00:00',
            'operation_id' => (string) Str::uuid(),
        ]);
        DailyFinancialCheckIn::query()->create([
            'user_id' => $user->id,
            'actor_id' => $user->id,
            'local_date' => '2026-09-22',
            'revision' => 1,
            'daily_budget_version_id' => $budget->id,
            'budget_amount' => '90.00',
            'eligible_spent' => '80.00',
            'margin' => '10.00',
            'rules_version' => 'test',
            'source' => 'recorded',
            'confirmed_at' => '2026-09-23 11:00:00',
            'operation_id' => (string) Str::uuid(),
        ]);

        $history = app(DailyFinancialCheckInHistoryQuery::class)->latestForMonth($user, '2026-09');

        $this->assertCount(1, $history);
        $this->assertSame($olderId->id, $history[0]['id']);
        $this->assertSame(2, $history[0]['revision']);
        $this->assertSame('100.00', $history[0]['spent']);
    }

    public function test_database_rejects_budget_version_from_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->travelTo(CarbonImmutable::parse('2026-09-22 09:00:00', 'America/Sao_Paulo'));
        $foreignBudget = app(SetDailyBudget::class)->handle($other, '40.00', (string) Str::uuid());

        $this->expectException(QueryException::class);

        DailyFinancialCheckIn::query()->create([
            'user_id' => $user->id,
            'actor_id' => $user->id,
            'local_date' => '2026-09-21',
            'revision' => 1,
            'daily_budget_version_id' => $foreignBudget->id,
            'budget_amount' => '40.00',
            'eligible_spent' => '0.00',
            'margin' => '40.00',
            'rules_version' => 'test',
            'source' => 'recorded',
            'confirmed_at' => '2026-09-22 12:00:00',
            'operation_id' => (string) Str::uuid(),
        ]);
    }

    public function test_history_is_isolated_by_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->travelTo(CarbonImmutable::parse('2026-09-22 09:00:00', 'America/Sao_Paulo'));
        app(SetDailyBudget::class)->handle($user, '90.00', (string) Str::uuid());
        app(SetDailyBudget::class)->handle($other, '40.00', (string) Str::uuid());

        $this->travelTo(CarbonImmutable::parse('2026-09-23 08:00:00', 'America/Sao_Paulo'));
        app(RecordDailyFinancialCheckIn::class)->confirm($user, '2026-09-22', (string) Str::uuid());
        app(RecordDailyFinancialCheckIn::class)->confirm($other, '2026-09-22', (string) Str::uuid());

        $history = app(DailyFinancialCheckInHistoryQuery::class)->latestForMonth($user, '2026-09');

        $this->assertCount(1, $history);
        $this->assertSame('90.00', $history[0]['budget']);
        $this->assertSame(2, DailyFinancialCheckIn::query()->count());
    }
}
