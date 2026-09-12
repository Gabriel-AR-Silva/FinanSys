<?php

namespace Tests\Feature;

use App\Actions\CloseFinancialEvaluation;
use App\Actions\CreateCardPurchase;
use App\Actions\CreateExpenseRefund;
use App\Actions\CreateManualLedgerEntry;
use App\Actions\PayCreditCard;
use App\Actions\RefreshCurrentInternalAlert;
use App\Actions\UpdateInternalAlert;
use App\Enums\ExpensePlanningType;
use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\EssentialBudget;
use App\Models\FinancialEvaluation;
use App\Models\LedgerEntry;
use App\Models\MonthlyFinancialSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_close_is_idempotent_and_preserves_the_presented_result(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $incomeCategory = Category::factory()->for($user)->create(['type' => 'income']);
        $expenseCategory = Category::factory()->for($user)->create(['type' => 'expense']);
        $settings = MonthlyFinancialSetting::factory()->for($user)->create(['month' => '2026-09']);
        EssentialBudget::factory()->for($settings)->for($user)->for($expenseCategory)->create(['amount' => '600.00']);
        $this->entry($user, $account, $incomeCategory, 'income', null, '1000.00', '2026-09-01 08:00:00');

        $date = CarbonImmutable::parse('2026-09-02', 'America/Sao_Paulo');
        $first = app(CloseFinancialEvaluation::class)->handle($user, $date);
        $firstResults = $first->pluck('result')->all();
        $second = app(CloseFinancialEvaluation::class)->handle($user, $date);

        $this->assertCount(2, $first);
        $this->assertSame($first->pluck('id')->all(), $second->pluck('id')->all());
        $this->assertSame($firstResults, $second->pluck('result')->all());
        $this->assertDatabaseCount('financial_evaluations', 2);

        $this->entry($user, $account, $expenseCategory, 'expense', ExpensePlanningType::Ordinary, '100.00', '2026-09-03 08:00:00');
        app(CloseFinancialEvaluation::class)->handle($user, CarbonImmutable::parse('2026-09-03', 'America/Sao_Paulo'));

        $this->assertDatabaseCount('financial_evaluations', 4);
        $this->assertSame($firstResults[0], $first->first()->fresh()->result);
    }

    public function test_rebuild_fills_missing_days_without_replacing_a_registered_snapshot(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $incomeCategory = Category::factory()->for($user)->create(['type' => 'income']);
        $expenseCategory = Category::factory()->for($user)->create(['type' => 'expense']);
        $settings = MonthlyFinancialSetting::factory()->for($user)->create(['month' => '2026-09']);
        EssentialBudget::factory()->for($settings)->for($user)->for($expenseCategory)->create(['amount' => '600.00']);
        $this->entry($user, $account, $incomeCategory, 'income', null, '1000.00', '2026-09-01 08:00:00');

        $registered = app(CloseFinancialEvaluation::class)->handle($user, CarbonImmutable::parse('2026-09-02', 'America/Sao_Paulo'));
        $registeredResult = $registered->first()->result;

        $this->artisan('finansys:rebuild-financial-days', ['--from' => '2026-09-01', '--to' => '2026-09-03'])
            ->assertSuccessful()
            ->expectsOutput('Avaliacoes processadas: 6.');

        $this->assertDatabaseCount('financial_evaluations', 6);
        $this->assertSame($registeredResult, $registered->first()->fresh()->result);
        $this->assertSame(4, FinancialEvaluation::query()->where('source', 'reconstructed')->count());
    }

    public function test_rebuild_rejects_an_invalid_or_oversized_interval(): void
    {
        $this->artisan('finansys:rebuild-financial-days', ['--from' => '2026-09-03', '--to' => '2026-09-02'])
            ->assertExitCode(2);
        $this->artisan('finansys:rebuild-financial-days', ['--from' => '2026-01-01', '--to' => '2027-01-03'])
            ->assertExitCode(2);
    }

    public function test_official_close_creates_a_revision_after_reconstruction(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $incomeCategory = Category::factory()->for($user)->create(['type' => 'income']);
        $expenseCategory = Category::factory()->for($user)->create(['type' => 'expense']);
        $settings = MonthlyFinancialSetting::factory()->for($user)->create(['month' => '2026-09']);
        EssentialBudget::factory()->for($settings)->for($user)->for($expenseCategory)->create(['amount' => '600.00']);
        $this->entry($user, $account, $incomeCategory, 'income', null, '1000.00', '2026-09-02 08:00:00');

        $date = CarbonImmutable::parse('2026-09-02', 'America/Sao_Paulo');
        $reconstructed = app(CloseFinancialEvaluation::class)->handle($user, $date, true);
        $official = app(CloseFinancialEvaluation::class)->handle($user, $date);
        $replay = app(CloseFinancialEvaluation::class)->handle($user, $date);

        $this->assertSame([1, 1], $reconstructed->pluck('revision')->all());
        $this->assertSame([2, 2], $official->pluck('revision')->all());
        $this->assertSame($official->pluck('id')->all(), $replay->pluck('id')->all());
        $this->assertSame($reconstructed->pluck('id')->all(), $official->pluck('supersedes_id')->all());
        $this->assertSame(['reconstructed', 'reconstructed', 'recorded', 'recorded'], FinancialEvaluation::query()->orderBy('id')->pluck('source')->all());
    }

    public function test_adverse_official_close_creates_two_deduplicated_alerts_and_rebuild_stays_silent(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $incomeCategory = Category::factory()->for($user)->create(['type' => 'income']);
        $expenseCategory = Category::factory()->for($user)->create(['type' => 'expense']);
        $settings = MonthlyFinancialSetting::factory()->for($user)->create(['month' => '2026-09']);
        EssentialBudget::factory()->for($settings)->for($user)->for($expenseCategory)->create(['amount' => '600.00']);
        $this->entry($user, $account, $incomeCategory, 'income', null, '1000.00', '2026-09-02 08:00:00');
        $this->entry($user, $account, $expenseCategory, 'expense', ExpensePlanningType::Fixed, '1100.00', '2026-09-02 09:00:00');

        $date = CarbonImmutable::parse('2026-09-02', 'America/Sao_Paulo');
        app(CloseFinancialEvaluation::class)->handle($user, $date);
        app(CloseFinancialEvaluation::class)->handle($user, $date);
        app(CloseFinancialEvaluation::class)->handle($user, $date, true);

        $this->assertDatabaseCount('internal_alerts', 2);
        $this->assertSame(2, $user->internalAlerts()->count());
        $this->assertTrue($user->internalAlerts()->where('current_situation', 'insufficient')->exists());
    }

    public function test_alert_recovery_preserves_worst_situation_and_deficit_history(): void
    {
        $user = User::factory()->create();
        $adverse = FinancialEvaluation::factory()->for($user)->create([
            'evaluation_date' => '2026-09-02',
            'view' => 'current',
            'source' => 'recorded',
            'result' => ['situation' => 'outside_plan', 'deficit' => '100.00', 'base' => '100.00', 'reasons' => []],
        ]);
        $recovered = FinancialEvaluation::factory()->for($user)->create([
            'evaluation_date' => '2026-09-02',
            'view' => 'current',
            'revision' => 2,
            'supersedes_id' => $adverse->id,
            'source' => 'recorded',
            'result' => ['situation' => 'under_control', 'deficit' => null, 'base' => '1000.00', 'reasons' => []],
        ]);

        app(UpdateInternalAlert::class)->handle($user, $adverse);
        $alert = app(UpdateInternalAlert::class)->handle($user, $recovered);
        $replayed = app(UpdateInternalAlert::class)->handle($user, $recovered);

        $this->assertSame('under_control', $alert->current_situation);
        $this->assertSame('outside_plan', $alert->worst_situation);
        $this->assertTrue($alert->deficit_seen);
        $this->assertNull($alert->current_deficit);
        $this->assertNotNull($alert->recovered_at);
        $this->assertSame($alert->id, $replayed->id);
        $this->assertDatabaseCount('internal_alerts', 1);
    }

    public function test_manual_entry_refreshes_alerts_before_daily_close(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $incomeCategory = Category::factory()->for($user)->create(['type' => 'income']);
        $expenseCategory = Category::factory()->for($user)->create(['type' => 'expense']);
        MonthlyFinancialSetting::factory()->for($user)->create(['month' => now('America/Sao_Paulo')->format('Y-m')]);
        $action = app(CreateManualLedgerEntry::class);

        $action->handle($user, $account->id, $incomeCategory->id, LedgerEntryType::Income, '1000.00', now('America/Sao_Paulo')->toDateString(), null, fake()->uuid());
        $action->handle($user, $account->id, $expenseCategory->id, LedgerEntryType::Expense, '1100.00', now('America/Sao_Paulo')->toDateString(), null, fake()->uuid(), ExpensePlanningType::Fixed);

        $this->assertDatabaseCount('financial_evaluations', 0);
        $this->assertDatabaseCount('internal_alerts', 2);
        $this->assertTrue($user->internalAlerts()->where('deficit_seen', true)->exists());
    }

    public function test_refund_refreshes_the_existing_alert_without_creating_a_snapshot(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $incomeCategory = Category::factory()->for($user)->create(['type' => 'income']);
        $expenseCategory = Category::factory()->for($user)->create(['type' => 'expense']);
        MonthlyFinancialSetting::factory()->for($user)->create(['month' => now('America/Sao_Paulo')->format('Y-m')]);
        $this->entry($user, $account, $incomeCategory, 'income', null, '1000.00', now('America/Sao_Paulo')->toDateString());
        $expense = $this->entry($user, $account, $expenseCategory, 'expense', ExpensePlanningType::Fixed, '1100.00', now('America/Sao_Paulo')->toDateString());
        app(RefreshCurrentInternalAlert::class)->handle($user);
        $alertId = $user->internalAlerts()->where('view', 'current')->value('id');

        app(CreateExpenseRefund::class)->handle($user, [
            'expense_ledger_entry_id' => $expense->id,
            'destination_account_id' => $account->id,
            'amount' => '200.00',
            'occurred_at' => now('America/Sao_Paulo')->toDateString(),
            'operation_id' => fake()->uuid(),
        ]);

        $this->assertSame($alertId, $user->internalAlerts()->where('view', 'current')->value('id'));
        $this->assertSame('under_control', $user->internalAlerts()->where('view', 'current')->value('current_situation'));
        $this->assertDatabaseCount('financial_evaluations', 0);
    }

    public function test_card_purchase_and_payment_refresh_projected_and_current_alerts_without_snapshot(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $incomeCategory = Category::factory()->for($user)->create(['type' => 'income']);
        $expenseCategory = Category::factory()->for($user)->create(['type' => 'expense']);
        $card = CreditCard::factory()->for($user)->create();
        MonthlyFinancialSetting::factory()->for($user)->create([
            'month' => now('America/Sao_Paulo')->format('Y-m'),
            'protection_value' => '0',
        ]);
        LedgerEntry::factory()->openingBalance()->for($user)->for($account, 'reference')->create(['amount' => '1200.00']);
        $this->entry($user, $account, $incomeCategory, 'income', null, '1000.00', now('America/Sao_Paulo')->toDateString());

        app(CreateCardPurchase::class)->handle($user, [
            'credit_card_id' => $card->id,
            'category_id' => $expenseCategory->id,
            'description' => 'Compra acima da verba',
            'planning_type' => ExpensePlanningType::Extraordinary->value,
            'gross_amount' => '1100.00',
            'purchased_on' => now('America/Sao_Paulo')->toDateString(),
            'installments_count' => 1,
            'first_due_on' => now('America/Sao_Paulo')->toDateString(),
            'operation_id' => (string) Str::uuid(),
        ]);

        $this->assertSame('outside_plan', $user->internalAlerts()->where('view', 'projected')->value('current_situation'));
        $this->assertFalse($user->internalAlerts()->where('view', 'current')->exists());

        app(PayCreditCard::class)->handle($user, [
            'credit_card_id' => $card->id,
            'source_account_id' => $account->id,
            'amount' => '1100.00',
            'paid_on' => now('America/Sao_Paulo')->toDateString(),
            'operation_id' => (string) Str::uuid(),
        ]);

        $this->assertSame('outside_plan', $user->internalAlerts()->where('view', 'current')->value('current_situation'));
        $this->assertDatabaseCount('internal_alerts', 2);
        $this->assertDatabaseCount('financial_evaluations', 0);
    }

    private function entry(User $user, Account $account, Category $category, string $type, ?ExpensePlanningType $planningType, string $amount, string $occurredAt): LedgerEntry
    {
        return LedgerEntry::factory()->for($user)->for($account, 'reference')->for($category)->create([
            'type' => $type,
            'planning_type' => $planningType,
            'amount' => $amount,
            'occurred_at' => $occurredAt,
        ]);
    }
}
