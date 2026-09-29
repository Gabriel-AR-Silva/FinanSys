<?php

namespace Tests\Feature\Http\Controllers;

use App\Actions\CreateCardPurchase;
use App\Actions\CreateExpenseRefund;
use App\Actions\PayCreditCard;
use App\Enums\ExpensePlanningType;
use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\ExpenseCommitment;
use App\Models\LedgerEntry;
use App\Models\Pocket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_derives_balances_and_monthly_totals_from_the_authenticated_users_entries(): void
    {
        $this->travelTo('2026-09-01 12:00:00');
        $user = User::factory()->create();
        $account = Account::query()->create(['user_id' => $user->id, 'name' => 'Principal']);
        $pocket = Pocket::query()->create(['user_id' => $user->id, 'account_id' => $account->id, 'name' => 'Reserva']);

        $this->entry($user, $account, LedgerEntryType::OpeningBalance, '1000.00', '2026-08-01 10:00:00');
        $this->entry($user, $account, LedgerEntryType::Income, '300.00', '2026-09-01 09:00:00');
        $this->entry($user, $account, LedgerEntryType::Expense, '125.00', '2026-09-01 10:00:00');
        $this->entry($user, $account, LedgerEntryType::TransferOut, '200.00', '2026-09-01 11:00:00');
        $this->entry($user, $pocket, LedgerEntryType::TransferIn, '200.00', '2026-09-01 11:00:00');

        $otherUser = User::factory()->create();
        $otherAccount = Account::query()->create(['user_id' => $otherUser->id, 'name' => 'Alheia']);
        $this->entry($otherUser, $otherAccount, LedgerEntryType::Income, '9999.00', '2026-09-01 11:30:00');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('filters.period', 30)
            ->where('filters.category_id', null)
            ->where('overview.general_balance', '1175')
            ->where('overview.accounts_balance', '975')
            ->where('overview.pockets_balance', '200')
            ->where('overview.monthly_income', 300)
            ->where('overview.monthly_expense', '125.00')
            ->where('overview.period_summary.income', '300')
            ->where('overview.period_summary.expense', '125')
            ->where('overview.period_summary.net', '175')
            ->where('overview.period_summary.savings_rate', '58.33')
            ->where('overview.period_summary.transaction_count', 2)
            ->where('overview.period_summary.largest_expense', '125')
            ->has('overview.cash_flow.points', 30)
            ->has('overview.recent_entries', 4)
            ->where('overview.recent_entries.0.reference_name', 'Reserva'));
    }

    public function test_dashboard_exposes_card_purchase_as_realized_consumption_without_counting_invoice_as_second_expense(): void
    {
        $this->travelTo('2026-09-28 12:00:00');
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        $card = CreditCard::factory()->for($user)->create(['closing_day' => 5, 'due_day' => 12]);

        app(CreateCardPurchase::class)->handle($user, [
            'credit_card_id' => $card->id,
            'category_id' => $category->id,
            'description' => 'Compra outubro',
            'planning_type' => ExpensePlanningType::Extraordinary->value,
            'gross_amount' => '120.00',
            'purchased_on' => '2026-09-28',
            'installments_count' => 1,
            'first_due_on' => '2026-10-12',
            'operation_id' => (string) Str::uuid(),
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('cardInvoice.month', '2026-10')
                ->where('cardInvoice.pending', '120.00')
                ->where('overview.period_summary.expense', '120.00')
                ->where('overview.period_summary.net', '-120.00')
                ->where('overview.period_summary.transaction_count', 1)
                ->where('overview.period_summary.largest_expense', '120.00')
                ->where('overview.period_summary.savings_rate', null)
                ->has('overview.consumption_flow.points', 30)
                ->where('overview.consumption_flow.points.29.realized', '120.00')
                ->has('overview.category_breakdown', 1)
                ->where('overview.category_breakdown.0.name', $category->name)
                ->where('overview.category_breakdown.0.total', '-120.00')
                ->where('overview.recent_entries.0.type', 'card_purchase'));
    }

    public function test_dashboard_available_now_funds_all_known_commitments_without_leaking_other_users(): void
    {
        $this->travelTo('2026-09-28 12:00:00');
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        $this->entry($user, $account, LedgerEntryType::OpeningBalance, '500.00', '2026-08-01 10:00:00');

        ExpenseCommitment::factory()->for($user)->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => '150.00',
            'paid_amount' => '50.00',
            'status' => 'pending',
            'due_on' => '2026-10-10',
        ]);

        $other = User::factory()->create();
        ExpenseCommitment::factory()->for($other)->create([
            'amount' => '999.00',
            'paid_amount' => '0.00',
            'status' => 'pending',
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('planning.configured', false)
                ->where('planning.indicators.committed', '100.00')
                ->where('planning.indicators.consolidated', '100.00')
                ->where('planning.indicators.available_now', '400.00'));
    }

    public function test_card_payment_is_cash_outflow_but_never_a_second_consumption(): void
    {
        $this->travelTo('2026-09-28 12:00:00');
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        $card = CreditCard::factory()->for($user)->create();
        $this->entry($user, $account, LedgerEntryType::OpeningBalance, '1000.00', '2026-08-01 10:00:00');

        app(CreateCardPurchase::class)->handle($user, [
            'credit_card_id' => $card->id,
            'category_id' => $category->id,
            'description' => 'Compra paga depois',
            'planning_type' => ExpensePlanningType::Ordinary->value,
            'gross_amount' => '100.00',
            'purchased_on' => '2026-09-01',
            'installments_count' => 1,
            'first_due_on' => '2026-09-12',
            'operation_id' => (string) Str::uuid(),
        ]);
        app(PayCreditCard::class)->handle($user, [
            'credit_card_id' => $card->id,
            'source_account_id' => $account->id,
            'amount' => '100.00',
            'paid_on' => '2026-09-28',
            'operation_id' => (string) Str::uuid(),
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('overview.period_summary.expense', '100.00')
                ->where('overview.cash_flow.points.29.expense', '100')
                ->where('planning.indicators.realized', '100.00')
                ->where('planning.indicators.committed', '0')
                ->where('planning.indicators.consolidated', '100.00'));
    }

    public function test_refund_reconciles_period_category_and_consumption_timeline(): void
    {
        $this->travelTo('2026-09-07 12:00:00');
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['name' => 'Mercado', 'type' => 'expense']);
        $expense = $this->entry($user, $account, LedgerEntryType::Expense, '100.00', '2026-09-05 10:00:00', $category);

        app(CreateExpenseRefund::class)->handle($user, [
            'expense_ledger_entry_id' => $expense->id,
            'destination_account_id' => $account->id,
            'amount' => '40.00',
            'occurred_at' => '2026-09-07',
            'operation_id' => (string) Str::uuid(),
        ]);

        $this->actingAs($user)->get(route('dashboard', ['period' => 7]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('overview.period_summary.expense', '60.00')
                ->where('overview.category_breakdown.0.name', 'Mercado')
                ->where('overview.category_breakdown.0.total', '-60.00')
                ->where('overview.consumption_flow.points.4.realized', '60.00'));
    }

    #[DataProvider('periods')]
    public function test_dashboard_accepts_supported_chart_periods(int $period): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard', ['period' => $period]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('overview.chart.period', $period)
            ->has('overview.chart.points', $period));
    }

    public function test_dashboard_falls_back_to_thirty_days_for_an_unsupported_period(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard', ['period' => 999]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('overview.chart.period', 30)
            ->has('overview.chart.points', 30));
    }

    public function test_dashboard_groups_income_and_expense_by_category_for_the_selected_period_without_leaking_users(): void
    {
        $this->travelTo('2026-09-02 12:00:00');
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $salary = Category::factory()->for($user)->create(['name' => 'Salário', 'type' => 'income']);
        $housing = Category::factory()->for($user)->create(['name' => 'Moradia', 'type' => 'expense']);
        $this->entry($user, $account, LedgerEntryType::Income, '500.00', '2026-09-01 10:00:00', $salary);
        $this->entry($user, $account, LedgerEntryType::Expense, '125.00', '2026-09-02 10:00:00', $housing);
        $this->entry($user, $account, LedgerEntryType::Expense, '999.00', '2026-08-01 10:00:00', $housing);

        $other = User::factory()->create();
        $otherAccount = Account::factory()->for($other)->create();
        $otherCategory = Category::factory()->for($other)->create(['name' => 'Alheia', 'type' => 'income']);
        $this->entry($other, $otherAccount, LedgerEntryType::Income, '9000.00', '2026-09-01 10:00:00', $otherCategory);

        $this->actingAs($user)->get(route('dashboard', ['period' => 7]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('overview.category_breakdown', 2)
                ->where('overview.category_breakdown.0.name', 'Salário')
                ->where('overview.category_breakdown.0.total', '500')
                ->where('overview.category_breakdown.1.name', 'Moradia')
                ->where('overview.category_breakdown.1.total', '-125'));
    }

    public function test_dashboard_filters_period_analytics_by_an_owned_category_without_changing_general_balance(): void
    {
        $this->travelTo('2026-09-02 12:00:00');
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $salary = Category::factory()->for($user)->create(['name' => 'Salário', 'type' => 'income']);
        $housing = Category::factory()->for($user)->create(['name' => 'Moradia', 'type' => 'expense']);
        $this->entry($user, $account, LedgerEntryType::Income, '500.00', '2026-09-01 10:00:00', $salary);
        $this->entry($user, $account, LedgerEntryType::Expense, '125.00', '2026-09-02 10:00:00', $housing);

        $this->actingAs($user)->get(route('dashboard', ['period' => 7, 'category_id' => $housing->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.period', 7)
                ->where('filters.category_id', $housing->id)
                ->where('overview.general_balance', '375')
                ->where('overview.period_summary.income', '0')
                ->where('overview.period_summary.expense', '125')
                ->where('overview.period_summary.net', '-125')
                ->where('overview.period_summary.savings_rate', null)
                ->where('overview.period_summary.transaction_count', 1)
                ->has('overview.category_breakdown', 1)
                ->where('overview.category_breakdown.0.name', 'Moradia')
                ->has('overview.recent_entries', 1));
    }

    public function test_dashboard_rejects_a_category_owned_by_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignCategory = Category::factory()->for($otherUser)->create();

        $this->actingAs($user)->get(route('dashboard', ['category_id' => $foreignCategory->id]))
            ->assertRedirect()
            ->assertSessionHasErrors('category_id');
    }

    public static function periods(): array
    {
        return [
            'seven days' => [7],
            'fifteen days' => [15],
            'thirty days' => [30],
            'sixty days' => [60],
            'one year' => [365],
        ];
    }

    private function entry(User $user, Account|Pocket $reference, LedgerEntryType $type, string $amount, string $occurredAt, ?Category $category = null): LedgerEntry
    {
        return $reference->ledgerEntries()->create([
            'user_id' => $user->id,
            'category_id' => $category?->id,
            'type' => $type,
            'amount' => $amount,
            'operation_id' => fake()->uuid(),
            'occurred_at' => $occurredAt,
        ]);
    }
}
