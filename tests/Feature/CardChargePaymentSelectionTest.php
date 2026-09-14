<?php

namespace Tests\Feature;

use App\Actions\CreateCardCharge;
use App\Actions\CreateCardPurchase;
use App\Actions\PayCreditCard;
use App\Enums\CategoryType;
use App\Enums\ExpensePlanningType;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CardChargePaymentSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_rejects_a_charge_from_another_card_owned_by_the_same_user_without_moving_cash(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
        $payingCard = CreditCard::factory()->for($user)->create(['name' => 'Cartão pago']);
        $otherCard = CreditCard::factory()->for($user)->create(['name' => 'Outro cartão']);
        $account = $this->fundedAccount($user);

        app(CreateCardPurchase::class)->handle($user, $this->purchasePayload($payingCard, $category));
        $foreignCharge = app(CreateCardCharge::class)->handle($user, $this->chargePayload($otherCard, $category));

        try {
            app(PayCreditCard::class)->handle($user, $this->paymentPayload($payingCard, $account, [$foreignCharge->id]));
            $this->fail('Um encargo de outro cartão deveria ser rejeitado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('card_charge_ids', $exception->errors());
        }

        $this->assertDatabaseCount('card_payments', 0);
        $this->assertDatabaseCount('card_charge_payment_allocations', 0);
        $this->assertDatabaseCount('card_payment_allocations', 0);
        $this->assertDatabaseCount('ledger_entries', 1);
    }

    public function test_payment_rejects_duplicate_charge_selection_before_any_financial_write(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
        $card = CreditCard::factory()->for($user)->create();
        $account = $this->fundedAccount($user);
        $charge = app(CreateCardCharge::class)->handle($user, $this->chargePayload($card, $category));

        try {
            app(PayCreditCard::class)->handle($user, $this->paymentPayload($card, $account, [$charge->id, $charge->id]));
            $this->fail('O mesmo encargo não pode ser selecionado duas vezes.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('card_charge_ids', $exception->errors());
        }

        $this->assertDatabaseCount('card_payments', 0);
        $this->assertDatabaseCount('card_charge_payment_allocations', 0);
        $this->assertDatabaseCount('ledger_entries', 1);
        $this->assertSame('0.00', $charge->fresh()->paid_amount);
    }

    private function fundedAccount(User $user): Account
    {
        $account = Account::factory()->for($user)->create();
        LedgerEntry::factory()->openingBalance()->for($user)->for($account, 'reference')->create(['amount' => '500.00']);

        return $account;
    }

    /** @return array<string, mixed> */
    private function chargePayload(CreditCard $card, Category $category): array
    {
        return [
            'credit_card_id' => $card->id,
            'category_id' => $category->id,
            'type' => 'interest',
            'description' => 'Juros confirmados',
            'planning_type' => ExpensePlanningType::Extraordinary->value,
            'amount' => '15.00',
            'charged_on' => '2026-09-09',
            'due_on' => '2026-09-12',
            'operation_id' => (string) Str::uuid(),
        ];
    }

    /** @return array<string, mixed> */
    private function purchasePayload(CreditCard $card, Category $category): array
    {
        return [
            'credit_card_id' => $card->id,
            'category_id' => $category->id,
            'description' => 'Compra',
            'planning_type' => ExpensePlanningType::Extraordinary->value,
            'gross_amount' => '100.00',
            'purchased_on' => '2026-09-01',
            'installments_count' => 1,
            'first_due_on' => '2026-09-12',
            'operation_id' => (string) Str::uuid(),
        ];
    }

    /** @param list<int> $chargeIds */
    private function paymentPayload(CreditCard $card, Account $account, array $chargeIds): array
    {
        return [
            'credit_card_id' => $card->id,
            'source_account_id' => $account->id,
            'amount' => '10.00',
            'paid_on' => '2026-09-10',
            'card_charge_ids' => $chargeIds,
            'operation_id' => (string) Str::uuid(),
        ];
    }
}
