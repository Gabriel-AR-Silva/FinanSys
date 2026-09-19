<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\ExpenseCommitment;
use App\Models\User;
use App\Queries\AccountBalanceQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExpenseCommitmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_future_commitment_keeps_balance_untouched_until_partial_and_full_payment(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        $payload = $this->schedulePayload($account, $category);
        $this->actingAs($user)->post(route('expense-commitments.store'), $payload)->assertSessionHasNoErrors();
        $commitment = ExpenseCommitment::query()->sole();
        $this->assertSame('0.00', $commitment->paid_amount);
        $this->assertSame('0', (string) app(AccountBalanceQuery::class)->forUser($user)->sole()->balance);
        $this->assertDatabaseCount('ledger_entries', 0);

        $first = ['amount' => '40.00', 'paid_on' => now()->toDateString(), 'operation_id' => (string) Str::uuid()];
        $this->actingAs($user)->post(route('expense-commitments.pay', $commitment->id), $first)->assertSessionHasNoErrors();
        $this->assertSame('40.00', $commitment->fresh()->paid_amount);
        $this->assertSame('pending', $commitment->fresh()->status);
        $this->assertEquals(-40, app(AccountBalanceQuery::class)->forUser($user)->sole()->balance);
        $this->assertDatabaseCount('ledger_entries', 1);

        $this->actingAs($user)->post(route('expense-commitments.pay', $commitment->id), $first)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('expense_commitment_payments', 1);
        $this->assertDatabaseCount('ledger_entries', 1);
        $this->actingAs($user)->post(route('expense-commitments.pay', $commitment->id), array_replace($first, ['amount' => '41.00']))->assertSessionHasErrors('operation_id');
        $this->actingAs($user)->post(route('expense-commitments.pay', $commitment->id), ['amount' => '61.00', 'paid_on' => now()->toDateString(), 'operation_id' => (string) Str::uuid()])->assertSessionHasErrors('amount');

        $this->actingAs($user)->post(route('expense-commitments.pay', $commitment->id), ['amount' => '60.00', 'paid_on' => now()->toDateString(), 'operation_id' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->assertSame('100.00', $commitment->fresh()->paid_amount);
        $this->assertSame('paid', $commitment->fresh()->status);
        $this->assertEquals(-100, app(AccountBalanceQuery::class)->forUser($user)->sole()->balance);
        $this->assertDatabaseCount('ledger_entries', 2);
    }

    public function test_other_user_cannot_schedule_on_foreign_account_or_pay_foreign_commitment(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $account = Account::factory()->for($owner)->create();
        $category = Category::factory()->for($owner)->create(['type' => 'expense']);
        $this->actingAs($stranger)->post(route('expense-commitments.store'), $this->schedulePayload($account, $category))->assertSessionHasErrors();
        $this->assertDatabaseCount('expense_commitments', 0);
        $this->actingAs($owner)->post(route('expense-commitments.store'), $this->schedulePayload($account, $category))->assertSessionHasNoErrors();
        $commitment = ExpenseCommitment::query()->sole();
        $this->actingAs($stranger)->post(route('expense-commitments.pay', $commitment->id), [
            'amount' => '10.00', 'paid_on' => now()->toDateString(), 'operation_id' => (string) Str::uuid(),
        ])->assertNotFound();
        $this->assertDatabaseCount('expense_commitment_payments', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_schedule_replay_and_cancel_preserve_history_without_balance_change(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        $payload = $this->schedulePayload($account, $category);
        $this->actingAs($user)->post(route('expense-commitments.store'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('expense-commitments.store'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('expense-commitments.store'), array_replace($payload, ['amount' => '200.00']))->assertSessionHasErrors('operation_id');
        $commitment = ExpenseCommitment::query()->sole();
        $this->actingAs($user)->post(route('expense-commitments.cancel', $commitment->id))->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('expense-commitments.cancel', $commitment->id))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $commitment->fresh()->status);
        $this->assertDatabaseCount('expense_commitments', 1);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    private function schedulePayload(Account $account, Category $category): array
    {
        return [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Boleto futuro',
            'amount' => '100.00',
            'due_on' => now()->addDays(10)->toDateString(),
            'planning_type' => 'fixed',
            'operation_id' => (string) Str::uuid(),
        ];
    }
}
