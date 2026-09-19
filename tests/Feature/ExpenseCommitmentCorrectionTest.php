<?php

namespace Tests\Feature;

use App\Actions\CreateManualLedgerEntry;
use App\Enums\ExpensePlanningType;
use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\Category;
use App\Models\ExpenseCommitment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExpenseCommitmentCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_revision_preserves_payments_and_rejects_stale_version_or_amount_below_paid(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        $this->actingAs($user)->post(route('expense-commitments.store'), [
            'account_id' => $account->id, 'category_id' => $category->id, 'description' => 'Boleto',
            'amount' => '100.00', 'due_on' => now()->addDays(10)->toDateString(),
            'planning_type' => 'fixed', 'operation_id' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $commitment = ExpenseCommitment::query()->sole();
        $this->actingAs($user)->post(route('expense-commitments.pay', $commitment->id), [
            'amount' => '40.00', 'paid_on' => now()->toDateString(), 'operation_id' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $commitment->fresh()->version);
        $revision = ['description' => 'Boleto revisado', 'amount' => '120.00', 'due_on' => now()->addDays(12)->toDateString(), 'version' => 2];
        $this->actingAs($user)->patch(route('expense-commitments.update', $commitment->id), $revision)->assertSessionHasNoErrors();
        $this->assertSame('120.00', $commitment->fresh()->amount);
        $this->assertSame('40.00', $commitment->fresh()->paid_amount);
        $this->assertSame(3, $commitment->fresh()->version);
        $this->actingAs($user)->patch(route('expense-commitments.update', $commitment->id), $revision)->assertSessionHasErrors('version');
        $this->actingAs($user)->patch(route('expense-commitments.update', $commitment->id), array_replace($revision, ['amount' => '39.99', 'version' => 3]))->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('ledger_entries', 1);
    }

    public function test_payment_cannot_claim_an_existing_manual_ledger_entry_even_when_fields_match(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        $operationId = (string) Str::uuid();
        app(CreateManualLedgerEntry::class)->handle(
            $user, $account->id, $category->id, LedgerEntryType::Expense, '40.00', now()->toDateString(), 'Boleto', $operationId, ExpensePlanningType::Fixed,
        );
        $this->actingAs($user)->post(route('expense-commitments.store'), [
            'account_id' => $account->id, 'category_id' => $category->id, 'description' => 'Boleto',
            'amount' => '100.00', 'due_on' => now()->addDays(10)->toDateString(),
            'planning_type' => 'fixed', 'operation_id' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $commitment = ExpenseCommitment::query()->sole();
        $this->actingAs($user)->post(route('expense-commitments.pay', $commitment->id), [
            'amount' => '40.00', 'paid_on' => now()->toDateString(), 'operation_id' => $operationId,
        ])->assertSessionHasErrors('operation_id');
        $this->assertSame('0.00', $commitment->fresh()->paid_amount);
        $this->assertDatabaseCount('expense_commitment_payments', 0);
        $this->assertDatabaseCount('ledger_entries', 1);
    }
}
