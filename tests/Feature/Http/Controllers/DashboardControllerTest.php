<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Pocket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->where('overview.monthly_expense', 125)
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
                ->where('overview.period_summary.savings_rate', '0')
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

    private function entry(User $user, Account|Pocket $reference, LedgerEntryType $type, string $amount, string $occurredAt, ?Category $category = null): void
    {
        $reference->ledgerEntries()->create([
            'user_id' => $user->id,
            'category_id' => $category?->id,
            'type' => $type,
            'amount' => $amount,
            'operation_id' => fake()->uuid(),
            'occurred_at' => $occurredAt,
        ]);
    }
}
