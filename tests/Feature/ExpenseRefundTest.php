<?php

namespace Tests\Feature;

use App\Actions\ReverseLedgerOperation;
use App\Models\Account;
use App\Models\ExpenseRefund;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Queries\AccountBalanceQuery;
use App\Queries\FinancialOverviewQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExpenseRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_month_refund_increases_balance_and_reduces_expense_without_becoming_income(): void
    {
        $this->travelTo('2026-09-20 12:00:00');
        [$user, $account, $expense] = $this->expense('100.00', '2026-09-05');

        $this->actingAs($user)->post(route('expense-refunds.store'), $this->payload($expense, $account, '40.00', '2026-09-20'))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('ledger_entries', ['type' => 'refund', 'amount' => 40]);
        $this->assertDatabaseCount('expense_refunds', 1);
        $this->assertEquals(-60, app(AccountBalanceQuery::class)->forUser($user)->sole()->balance);
        $overview = app(FinancialOverviewQuery::class)->forUser($user);
        $this->assertEquals(0, $overview['monthly_income']);
        $this->assertSame('60.00', $overview['monthly_expense']);
    }

    public function test_later_month_refund_affects_cash_but_not_current_income_or_expense(): void
    {
        $this->travelTo('2026-09-20 12:00:00');
        [$user, $account, $expense] = $this->expense('100.00', '2026-08-31');

        $this->actingAs($user)->post(route('expense-refunds.store'), $this->payload($expense, $account, '100.00', '2026-09-01'))->assertRedirect();

        $overview = app(FinancialOverviewQuery::class)->forUser($user);
        $this->assertSame('0.00', $overview['monthly_expense']);
        $this->assertEquals(0, $overview['monthly_income']);
        $this->assertSame('0', $overview['general_balance']);
    }

    public function test_partial_refunds_are_idempotent_and_cannot_exceed_original_expense(): void
    {
        [$user, $account, $expense] = $this->expense('100.00', '2026-09-05');
        $first = $this->payload($expense, $account, '40.00', '2026-09-06');

        $this->actingAs($user)->post(route('expense-refunds.store'), $first)->assertRedirect();
        $this->post(route('expense-refunds.store'), $first)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('expense-refunds.store'), $this->payload($expense, $account, '60.00', '2026-09-07'))->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('expense-refunds.store'), $this->payload($expense, $account, '0.01', '2026-09-08'))->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('expense_refunds', 2);
        $this->assertDatabaseCount('ledger_entries', 3);
    }

    public function test_other_user_cannot_refund_and_reversal_restores_refundable_capacity(): void
    {
        [$user, $account, $expense] = $this->expense('100.00', '2026-09-05');
        $this->actingAs(User::factory()->create())->post(route('expense-refunds.store'), $this->payload($expense, $account, '40.00', '2026-09-06'))->assertNotFound();
        $this->actingAs($user)->post(route('expense-refunds.store'), $this->payload($expense, $account, '100.00', '2026-09-06'))->assertRedirect();
        $refundEntry = ExpenseRefund::with('refundEntry')->sole()->refundEntry;

        app(ReverseLedgerOperation::class)->handle($user, $refundEntry->id, (string) Str::uuid());
        $this->post(route('expense-refunds.store'), $this->payload($expense, $account, '100.00', '2026-09-07'))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('expense_refunds', 2);
    }

    public function test_expense_with_active_refund_cannot_be_deleted(): void
    {
        [$user, $account, $expense] = $this->expense('100.00', '2026-09-05');
        $this->actingAs($user)->post(route('expense-refunds.store'), $this->payload($expense, $account, '40.00', '2026-09-06'))->assertRedirect();

        $this->delete(route('ledger-entries.destroy', $expense))->assertSessionHasErrors('ledger_entry');

        $this->assertNotNull($expense->fresh());
    }

    /** @return array{User,Account,LedgerEntry} */
    private function expense(string $amount, string $date): array
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $expense = LedgerEntry::factory()->expense()->for($user)->for($account, 'reference')->create([
            'amount' => $amount,
            'occurred_at' => $date,
        ]);

        return [$user, $account, $expense];
    }

    private function payload(LedgerEntry $expense, Account $account, string $amount, string $date): array
    {
        return [
            'expense_ledger_entry_id' => $expense->id,
            'destination_account_id' => $account->id,
            'amount' => $amount,
            'occurred_at' => $date,
            'operation_id' => (string) Str::uuid(),
        ];
    }
}
