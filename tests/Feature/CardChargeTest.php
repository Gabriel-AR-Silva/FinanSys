<?php

namespace Tests\Feature;

use App\Actions\CreateCardCharge;
use App\Actions\CreateCardPurchase;
use App\Actions\PayCreditCard;
use App\Enums\CardInstallmentStatus;
use App\Enums\CategoryType;
use App\Enums\ExpensePlanningType;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Queries\AccountBalanceQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CardChargeTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_charge_is_an_idempotent_expense_obligation_without_moving_cash(): void
    {
        [$user, $card, $category] = $this->context();
        $payload = $this->chargePayload($card, $category);

        $first = app(CreateCardCharge::class)->handle($user, $payload);
        $replayed = app(CreateCardCharge::class)->handle($user, $payload);

        $this->assertSame($first->id, $replayed->id);
        $this->assertSame('15.00', $first->amount);
        $this->assertSame(CardInstallmentStatus::Pending, $first->status);
        $this->assertDatabaseCount('card_charges', 1);
        $this->assertDatabaseCount('ledger_entries', 0);
        $this->assertDatabaseCount('audit_logs', 1);

        $this->expectException(ValidationException::class);
        app(CreateCardCharge::class)->handle($user, [...$payload, 'amount' => '16.00']);
    }

    public function test_charge_rejects_foreign_resources_without_partial_writes(): void
    {
        [$user, $card, $category] = $this->context();

        foreach ([
            [$this->chargePayload(CreditCard::factory()->create(), $category), 'credit_card_id'],
            [$this->chargePayload($card, Category::factory()->create(['type' => CategoryType::Expense])), 'category_id'],
        ] as [$payload, $field]) {
            try {
                app(CreateCardCharge::class)->handle($user, $payload);
                $this->fail('Um recurso de outro usuário deveria ser rejeitado.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }

        $this->assertDatabaseCount('card_charges', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_selected_charge_is_paid_before_installments_with_separate_allocations(): void
    {
        [$user, $card, $category] = $this->context();
        $account = $this->fundedAccount($user);
        $charge = app(CreateCardCharge::class)->handle($user, $this->chargePayload($card, $category));
        $purchase = app(CreateCardPurchase::class)->handle($user, $this->purchasePayload($card, $category));

        $payment = app(PayCreditCard::class)->handle($user, $this->paymentPayload($card, $account, '50.00', [$charge->id]));

        $this->assertSame('15.00', $payment->chargeAllocations->sole()->amount);
        $this->assertSame('35.00', $payment->allocations->sole()->amount);
        $this->assertSame('15.00', $charge->fresh()->paid_amount);
        $this->assertSame(CardInstallmentStatus::Paid, $charge->fresh()->status);
        $this->assertSame('35.00', $purchase->installments()->sole()->paid_amount);
        $this->assertSame(450, app(AccountBalanceQuery::class)->forUser($user)->sole()->balance);
        $this->assertDatabaseCount('ledger_entries', 2);
    }

    public function test_unselected_charge_has_no_implicit_priority(): void
    {
        [$user, $card, $category] = $this->context();
        $account = $this->fundedAccount($user);
        $charge = app(CreateCardCharge::class)->handle($user, $this->chargePayload($card, $category));
        $purchase = app(CreateCardPurchase::class)->handle($user, $this->purchasePayload($card, $category));

        $payment = app(PayCreditCard::class)->handle($user, $this->paymentPayload($card, $account, '50.00'));

        $this->assertTrue($payment->chargeAllocations->isEmpty());
        $this->assertSame('0.00', $charge->fresh()->paid_amount);
        $this->assertSame('50.00', $purchase->installments()->sole()->paid_amount);
    }

    public function test_charge_can_be_paid_partially_and_replay_does_not_duplicate_cash_or_allocations(): void
    {
        [$user, $card, $category] = $this->context();
        $account = $this->fundedAccount($user);
        $charge = app(CreateCardCharge::class)->handle($user, $this->chargePayload($card, $category));
        $payload = $this->paymentPayload($card, $account, '10.01', [$charge->id]);

        $first = app(PayCreditCard::class)->handle($user, $payload);
        $replayed = app(PayCreditCard::class)->handle($user, $payload);

        $this->assertSame($first->id, $replayed->id);
        $this->assertSame('10.01', $charge->fresh()->paid_amount);
        $this->assertSame(CardInstallmentStatus::Pending, $charge->fresh()->status);
        $this->assertDatabaseCount('card_payments', 1);
        $this->assertDatabaseCount('card_charge_payment_allocations', 1);
        $this->assertDatabaseCount('ledger_entries', 2);

        try {
            app(PayCreditCard::class)->handle($user, [...$payload, 'card_charge_ids' => []]);
            $this->fail('O replay com seleção diferente deveria ser rejeitado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('operation_id', $exception->errors());
        }

        $this->assertDatabaseCount('card_payments', 1);
        $this->assertSame('10.01', $charge->fresh()->paid_amount);
    }

    public function test_multiple_selected_charges_preserve_cents_and_stable_order(): void
    {
        [$user, $card, $category] = $this->context();
        $account = $this->fundedAccount($user);
        $later = app(CreateCardCharge::class)->handle($user, [...$this->chargePayload($card, $category),
            'amount' => '10.02', 'due_on' => '2026-09-13', 'operation_id' => (string) Str::uuid(),
        ]);
        $earlier = app(CreateCardCharge::class)->handle($user, [...$this->chargePayload($card, $category),
            'amount' => '10.01', 'operation_id' => (string) Str::uuid(),
        ]);

        $payment = app(PayCreditCard::class)->handle($user, $this->paymentPayload($card, $account, '15.00', [$later->id, $earlier->id]));

        $this->assertSame([$earlier->id, $later->id], $payment->chargeAllocations->pluck('card_charge_id')->all());
        $this->assertSame(['10.01', '4.99'], $payment->chargeAllocations->pluck('amount')->all());
        $this->assertSame('4.99', $later->fresh()->paid_amount);
    }

    public function test_overpayment_and_payment_before_charge_leave_no_partial_financial_write(): void
    {
        [$user, $card, $category] = $this->context();
        $account = $this->fundedAccount($user);
        $charge = app(CreateCardCharge::class)->handle($user, $this->chargePayload($card, $category));

        foreach ([
            $this->paymentPayload($card, $account, '15.01', [$charge->id]),
            [...$this->paymentPayload($card, $account, '10.00', [$charge->id]), 'paid_on' => '2026-09-08'],
        ] as $payload) {
            try {
                app(PayCreditCard::class)->handle($user, $payload);
                $this->fail('O pagamento inválido deveria ser rejeitado.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }

        $this->assertDatabaseCount('card_payments', 0);
        $this->assertDatabaseCount('card_charge_payment_allocations', 0);
        $this->assertDatabaseCount('ledger_entries', 1);
        $this->assertSame('0.00', $charge->fresh()->paid_amount);
    }

    public function test_payment_rejects_a_selected_charge_from_another_user_without_cash_movement(): void
    {
        [$user, $card, $category] = $this->context();
        $account = $this->fundedAccount($user);
        app(CreateCardPurchase::class)->handle($user, $this->purchasePayload($card, $category));
        [$other, $otherCard, $otherCategory] = $this->context();
        $foreignCharge = app(CreateCardCharge::class)->handle($other, $this->chargePayload($otherCard, $otherCategory));

        try {
            app(PayCreditCard::class)->handle($user, $this->paymentPayload($card, $account, '10.00', [$foreignCharge->id]));
            $this->fail('Um encargo de outro usuário deveria ser rejeitado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('card_charge_ids', $exception->errors());
        }

        $this->assertDatabaseCount('card_payments', 0);
        $this->assertDatabaseCount('card_charge_payment_allocations', 0);
        $this->assertDatabaseCount('ledger_entries', 1);
    }

    /** @return array{User,CreditCard,Category} */
    private function context(): array
    {
        $user = User::factory()->create();

        return [$user, CreditCard::factory()->for($user)->create(), Category::factory()->for($user)->create(['type' => CategoryType::Expense])];
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
            'credit_card_id' => $card->id, 'category_id' => $category->id, 'type' => 'interest',
            'description' => 'Juros confirmados', 'planning_type' => ExpensePlanningType::Extraordinary->value,
            'amount' => '15.00', 'charged_on' => '2026-09-09', 'due_on' => '2026-09-12', 'operation_id' => (string) Str::uuid(),
        ];
    }

    /** @return array<string, mixed> */
    private function purchasePayload(CreditCard $card, Category $category): array
    {
        return [
            'credit_card_id' => $card->id, 'category_id' => $category->id, 'description' => 'Compra',
            'planning_type' => ExpensePlanningType::Extraordinary->value, 'gross_amount' => '100.00',
            'purchased_on' => '2026-09-01', 'installments_count' => 1, 'first_due_on' => '2026-09-12',
            'operation_id' => (string) Str::uuid(),
        ];
    }

    /** @param list<int> $chargeIds */
    private function paymentPayload(CreditCard $card, Account $account, string $amount, array $chargeIds = []): array
    {
        return [
            'credit_card_id' => $card->id, 'source_account_id' => $account->id, 'amount' => $amount,
            'paid_on' => '2026-09-09', 'card_charge_ids' => $chargeIds, 'operation_id' => (string) Str::uuid(),
        ];
    }
}
