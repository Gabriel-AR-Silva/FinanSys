<?php

namespace Tests\Feature\Queries;

use App\Actions\CreateCardCharge;
use App\Actions\CreateCardPurchase;
use App\Actions\CreateExpenseRefund;
use App\Actions\PayCreditCard;
use App\Enums\ExpensePlanningType;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\EssentialBudget;
use App\Models\LedgerEntry;
use App\Models\MonthlyFinancialSetting;
use App\Models\ReceiptForecast;
use App\Models\User;
use App\Queries\FinancialPlanningOverviewQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialPlanningOverviewQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adapts_owned_monthly_facts_without_double_counting_exclusive_sets(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $incomeCategory = Category::factory()->for($user)->create(['type' => 'income']);
        $essentialCategory = Category::factory()->for($user)->create(['type' => 'expense']);
        $fixedCategory = Category::factory()->for($user)->create(['type' => 'expense']);
        $settings = MonthlyFinancialSetting::factory()->for($user)->create(['month' => '2026-09']);
        EssentialBudget::factory()->for($settings)->for($user)->for($essentialCategory)->create(['amount' => '600.00']);
        $this->entry($user, $account, $incomeCategory, 'income', null, '3000.00', '2026-09-01 10:00:00');
        $this->entry($user, $account, $fixedCategory, 'expense', ExpensePlanningType::Fixed, '2000.00', '2026-09-01 11:00:00');
        $this->entry($user, $account, $essentialCategory, 'expense', ExpensePlanningType::Ordinary, '100.00', '2026-09-01 12:00:00');
        $this->entry($user, $account, $essentialCategory, 'expense', ExpensePlanningType::Ordinary, '100.00', '2026-09-02 12:00:00');
        $this->entry($user, $account, $essentialCategory, 'expense', ExpensePlanningType::Ordinary, '150.00', '2026-09-03 08:00:00');
        $this->entry($user, $account, $essentialCategory, 'expense', ExpensePlanningType::Extraordinary, '300.00', '2026-09-03 09:00:00');
        ReceiptForecast::factory()->for($user)->for($incomeCategory)->create(['amount' => '500.00', 'expected_on' => '2026-09-20']);

        $other = User::factory()->create();
        $this->entry($other, Account::factory()->for($other)->create(), Category::factory()->for($other)->create(['type' => 'expense']), 'expense', ExpensePlanningType::Fixed, '9999.00', '2026-09-01 10:00:00');

        $result = app(FinancialPlanningOverviewQuery::class)->forUser($user, CarbonImmutable::parse('2026-09-03 12:00:00', 'America/Sao_Paulo'));

        $this->assertTrue($result['configured']);
        $this->assertTrue($result['complete']);
        $this->assertSame(['received' => '3000.00', 'pending' => '500.00', 'projected' => '3500.00'], $result['income']);
        $this->assertSame('2000.00', $result['fixed']);
        $this->assertSame(['realized' => '650.00', 'projected' => '3350.00'], $result['variable']);
        $this->assertSame(['base' => '1000.00', 'deficit' => null, 'percentage' => '65.00', 'situation' => 'under_control', 'diagnostic_available' => true], $result['current']);
        $this->assertSame('1500.00', $result['projected']['base']);
        $this->assertSame('223.33', $result['projected']['percentage']);
        $this->assertSame('outside_plan', $result['projected']['situation']);
        $this->assertSame('3350.00', $result['essential_categories'][0]['projected']);
        $this->assertSame('350.00', $result['free_margin']);
        $this->assertSame([
            'available' => '350.00',
            'amount' => '12.50',
            'remainder' => '0.00',
            'remaining_days' => 28,
        ], $result['daily']);
    }

    public function test_it_marks_an_absent_month_configuration_instead_of_assuming_zeroes(): void
    {
        $user = User::factory()->create();
        $result = app(FinancialPlanningOverviewQuery::class)->forUser($user, CarbonImmutable::parse('2026-09-03', 'America/Sao_Paulo'));

        $this->assertFalse($result['configured']);
        $this->assertSame('2026-09', $result['month']);
        $this->assertNotEmpty($result['reasons']);
        $this->assertArrayNotHasKey('current', $result);
    }

    public function test_it_withholds_rhythm_diagnosis_during_the_first_two_days(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $incomeCategory = Category::factory()->for($user)->create(['type' => 'income']);
        $settings = MonthlyFinancialSetting::factory()->for($user)->create(['month' => '2026-09']);
        EssentialBudget::factory()->for($settings)->for($user)->for(Category::factory()->for($user)->create(['type' => 'expense']))->create(['amount' => '600.00']);
        $this->entry($user, $account, $incomeCategory, 'income', null, '1000.00', '2026-09-02 08:00:00');

        $result = app(FinancialPlanningOverviewQuery::class)->forUser($user, CarbonImmutable::parse('2026-09-02 12:00:00', 'America/Sao_Paulo'));

        $this->assertFalse($result['current']['diagnostic_available']);
        $this->assertFalse($result['projected']['diagnostic_available']);
        $this->assertSame('under_control', $result['current']['situation']);
        $this->assertSame('under_control', $result['projected']['situation']);
    }

    public function test_same_month_refund_reduces_variable_spending_without_becoming_income(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $incomeCategory = Category::factory()->for($user)->create(['type' => 'income']);
        $expenseCategory = Category::factory()->for($user)->create(['type' => 'expense']);
        MonthlyFinancialSetting::factory()->for($user)->create(['month' => '2026-09']);
        $this->entry($user, $account, $incomeCategory, 'income', null, '1000.00', '2026-09-01 08:00:00');
        $expense = $this->entry($user, $account, $expenseCategory, 'expense', ExpensePlanningType::Ordinary, '100.00', '2026-09-01 10:00:00');
        app(CreateExpenseRefund::class)->handle($user, [
            'expense_ledger_entry_id' => $expense->id,
            'destination_account_id' => $account->id,
            'amount' => '40.00',
            'occurred_at' => '2026-09-02',
            'operation_id' => fake()->uuid(),
        ]);

        $result = app(FinancialPlanningOverviewQuery::class)->forUser($user, CarbonImmutable::parse('2026-09-03 12:00:00', 'America/Sao_Paulo'));

        $this->assertSame('1000.00', $result['income']['received']);
        $this->assertSame('60.00', $result['variable']['realized']);
        $this->assertSame('900.00', $result['variable']['projected']);
        $this->assertSame('6.00', $result['current']['percentage']);
        $this->assertSame('90.00', $result['projected']['percentage']);
    }

    public function test_card_payment_substitutes_the_installment_obligation_without_duplicating_spending(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        $card = CreditCard::factory()->for($user)->create();
        MonthlyFinancialSetting::factory()->for($user)->create(['month' => '2026-09', 'protection_value' => '0']);
        LedgerEntry::factory()->openingBalance()->for($user)->for($account, 'reference')->create(['amount' => '1000.00']);
        $purchase = app(CreateCardPurchase::class)->handle($user, [
            'credit_card_id' => $card->id,
            'category_id' => $category->id,
            'description' => 'Compra parcelada',
            'planning_type' => ExpensePlanningType::Extraordinary->value,
            'gross_amount' => '200.00',
            'purchased_on' => '2026-09-01',
            'installments_count' => 2,
            'first_due_on' => '2026-09-12',
            'operation_id' => (string) Str::uuid(),
        ]);

        $before = app(FinancialPlanningOverviewQuery::class)->forUser($user, CarbonImmutable::parse('2026-09-09', 'America/Sao_Paulo'));
        app(PayCreditCard::class)->handle($user, [
            'credit_card_id' => $card->id,
            'source_account_id' => $account->id,
            'amount' => '60.00',
            'paid_on' => '2026-09-09',
            'operation_id' => (string) Str::uuid(),
        ]);
        $after = app(FinancialPlanningOverviewQuery::class)->forUser($user, CarbonImmutable::parse('2026-09-09', 'America/Sao_Paulo'));

        $this->assertSame('0.00', $before['variable']['realized']);
        $this->assertSame('100.00', $before['variable']['projected']);
        $this->assertSame('60.00', $after['variable']['realized']);
        $this->assertSame('100.00', $after['variable']['projected']);
        $this->assertSame('60.00', $purchase->installments()->oldest('due_on')->first()->fresh()->paid_amount);
    }

    public function test_confirmed_card_charge_is_a_new_expense_and_payment_only_realizes_it(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        $card = CreditCard::factory()->for($user)->create();
        MonthlyFinancialSetting::factory()->for($user)->create(['month' => '2026-09', 'protection_value' => '0']);
        LedgerEntry::factory()->openingBalance()->for($user)->for($account, 'reference')->create(['amount' => '1000.00']);
        $charge = app(CreateCardCharge::class)->handle($user, [
            'credit_card_id' => $card->id, 'category_id' => $category->id, 'type' => 'late_fee',
            'description' => 'Multa confirmada', 'planning_type' => ExpensePlanningType::Extraordinary->value,
            'amount' => '15.00', 'charged_on' => '2026-09-03', 'due_on' => '2026-09-12', 'operation_id' => (string) Str::uuid(),
        ]);

        $before = app(FinancialPlanningOverviewQuery::class)->forUser($user, CarbonImmutable::parse('2026-09-09', 'America/Sao_Paulo'));
        app(PayCreditCard::class)->handle($user, [
            'credit_card_id' => $card->id, 'source_account_id' => $account->id, 'amount' => '15.00',
            'paid_on' => '2026-09-09', 'card_charge_ids' => [$charge->id], 'operation_id' => (string) Str::uuid(),
        ]);
        $after = app(FinancialPlanningOverviewQuery::class)->forUser($user, CarbonImmutable::parse('2026-09-09', 'America/Sao_Paulo'));

        $this->assertSame('0.00', $before['variable']['realized']);
        $this->assertSame('15.00', $before['variable']['projected']);
        $this->assertSame('15.00', $after['variable']['realized']);
        $this->assertSame('15.00', $after['variable']['projected']);
    }

    public function test_overdue_card_debt_keeps_its_opening_commitment_after_a_partial_payment(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $incomeCategory = Category::factory()->for($user)->create(['type' => 'income']);
        $expenseCategory = Category::factory()->for($user)->create(['type' => 'expense']);
        $card = CreditCard::factory()->for($user)->create();
        MonthlyFinancialSetting::factory()->for($user)->create(['month' => '2026-09', 'protection_value' => '0']);
        LedgerEntry::factory()->openingBalance()->for($user)->for($account, 'reference')->create(['amount' => '1000.00']);
        $this->entry($user, $account, $incomeCategory, 'income', null, '1000.00', '2026-09-01 08:00:00');
        app(CreateCardPurchase::class)->handle($user, [
            'credit_card_id' => $card->id,
            'category_id' => $expenseCategory->id,
            'description' => 'Dívida anterior',
            'planning_type' => ExpensePlanningType::Extraordinary->value,
            'gross_amount' => '200.00',
            'purchased_on' => '2026-08-01',
            'installments_count' => 1,
            'first_due_on' => '2026-08-12',
            'operation_id' => (string) Str::uuid(),
        ]);
        app(PayCreditCard::class)->handle($user, [
            'credit_card_id' => $card->id,
            'source_account_id' => $account->id,
            'amount' => '80.00',
            'paid_on' => '2026-09-09',
            'operation_id' => (string) Str::uuid(),
        ]);

        $result = app(FinancialPlanningOverviewQuery::class)->forUser($user, CarbonImmutable::parse('2026-09-09', 'America/Sao_Paulo'));

        $this->assertSame(['total' => '200.00', 'paid_this_month' => '80.00', 'pending' => '120.00'], $result['previous_commitments']);
        $this->assertSame('800.00', $result['current']['base']);
        $this->assertSame('0', $result['variable']['realized']);
    }

    public function test_month_boundaries_follow_the_application_timezone(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $incomeCategory = Category::factory()->for($user)->create(['type' => 'income']);
        MonthlyFinancialSetting::factory()->for($user)->create(['month' => '2026-09', 'protection_value' => '0']);
        $this->entry($user, $account, $incomeCategory, 'income', null, '100.00', '2026-09-01 00:00:00');
        $this->entry($user, $account, $incomeCategory, 'income', null, '200.00', '2026-09-30 23:59:59');
        $this->entry($user, $account, $incomeCategory, 'income', null, '400.00', '2026-10-01 00:00:00');

        $result = app(FinancialPlanningOverviewQuery::class)->forUser(
            $user,
            CarbonImmutable::parse('2026-09-30 23:59:59', 'America/Sao_Paulo'),
        );

        $this->assertSame('300.00', $result['income']['received']);
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
