<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\ExpenseCommitment;
use App\Models\ExpenseCommitmentPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExpenseCommitmentProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_linked_payment_cannot_be_edited_deleted_reversed_or_refunded_independently(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        $this->actingAs($user)->post(route('expense-commitments.store'), [
            'account_id' => $account->id, 'category_id' => $category->id,
            'description' => 'Parcela programada', 'amount' => '100.00',
            'due_on' => now()->addDays(10)->toDateString(), 'planning_type' => 'fixed',
            'operation_id' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $commitment = ExpenseCommitment::query()->sole();
        $this->actingAs($user)->post(route('expense-commitments.pay', $commitment->id), [
            'amount' => '40.00', 'paid_on' => now()->toDateString(), 'operation_id' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $payment = ExpenseCommitmentPayment::query()->sole();
        $entry = $payment->ledgerEntry;
        $this->actingAs($user)->patch(route('ledger-entries.correction.update', $entry->id), [
            'category_id' => $category->id, 'amount' => '20.00',
            'occurred_at' => now()->toDateString(), 'description' => 'Adulterado', 'planning_type' => 'fixed',
        ])->assertSessionHasErrors('ledger_entry');
        $this->actingAs($user)->delete(route('ledger-entries.destroy', $entry->id))->assertSessionHasErrors('ledger_entry');
        $this->actingAs($user)->post(route('ledger-entries.reversals.store', $entry->id), [
            'operation_id' => (string) Str::uuid(),
        ])->assertSessionHasErrors('ledger_entry');
        $this->actingAs($user)->post(route('expense-refunds.store'), [
            'expense_ledger_entry_id' => $entry->id, 'destination_account_id' => $account->id,
            'amount' => '10.00', 'occurred_at' => now()->toDateString(), 'operation_id' => (string) Str::uuid(),
        ])->assertSessionHasErrors('expense_ledger_entry_id');
        $this->assertSame('40.00', $commitment->fresh()->paid_amount);
        $this->assertSame('40.00', $entry->fresh()->amount);
        $this->assertDatabaseCount('ledger_entries', 1);
        $this->assertDatabaseCount('expense_commitment_payments', 1);
    }
}
