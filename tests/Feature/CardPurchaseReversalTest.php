<?php

namespace Tests\Feature;

use App\Actions\AdvanceCardInstallments;
use App\Actions\ApplyCardCredit;
use App\Actions\CreateCardPurchase;
use App\Actions\PayCreditCard;
use App\Actions\ReverseCardPurchase;
use App\Enums\CardInstallmentStatus;
use App\Enums\CategoryType;
use App\Enums\ExpensePlanningType;
use App\Models\Account;
use App\Models\CardCredit;
use App\Models\CardPurchase;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\LedgerEntry;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CardPurchaseReversalTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpaid_purchase_reversal_cancels_pending_without_creating_credit(): void
    {
        [$user, $card, $category] = $this->cardContext();
        $purchase = app(CreateCardPurchase::class)->handle($user, $this->purchasePayload($card, $category, '300.00'));

        $reversal = app(ReverseCardPurchase::class)->handle($user, $this->reversalPayload($purchase));

        $this->assertSame('300.00', $reversal->cancelled_pending_amount);
        $this->assertSame('0.00', $reversal->credited_paid_amount);
        $this->assertNull($reversal->credit);
        $this->assertSoftDeleted('card_purchases', ['id' => $purchase->id]);
        $this->assertSame(
            [CardInstallmentStatus::Reversed],
            $purchase->installments()->get()->pluck('status')->unique()->values()->all(),
        );
        $this->assertDatabaseCount('card_credits', 0);
    }

    public function test_partial_payment_reversal_cancels_residual_and_credits_only_amount_already_paid(): void
    {
        [$user, $card, $category] = $this->cardContext();
        $account = $this->fundedAccount($user, '500.00');
        $purchase = app(CreateCardPurchase::class)->handle($user, $this->purchasePayload($card, $category, '300.00'));
        app(PayCreditCard::class)->handle($user, $this->paymentPayload($card, $account, '100.00'));

        $reversal = app(ReverseCardPurchase::class)->handle($user, $this->reversalPayload($purchase));

        $this->assertSame('200.00', $reversal->cancelled_pending_amount);
        $this->assertSame('100.00', $reversal->credited_paid_amount);
        $this->assertSame('100.00', $reversal->credit->amount);
        $this->assertSame('0.00', $reversal->credit->applied_amount);
        $this->assertDatabaseCount('ledger_entries', 2);
    }

    public function test_fully_paid_reversal_credit_can_be_applied_partially_without_creating_cash_entry(): void
    {
        [$user, $card, $category] = $this->cardContext();
        $account = $this->fundedAccount($user, '700.00');
        $purchase = app(CreateCardPurchase::class)->handle($user, $this->purchasePayload($card, $category, '300.00'));
        app(PayCreditCard::class)->handle($user, $this->paymentPayload($card, $account, '300.00'));
        $reversal = app(ReverseCardPurchase::class)->handle($user, $this->reversalPayload($purchase));

        $target = app(CreateCardPurchase::class)->handle($user, $this->purchasePayload($card, $category, '200.00', [
            'description' => 'Compra de destino',
            'operation_id' => (string) Str::uuid(),
        ]));
        $targetInstallment = $target->installments()->firstOrFail();
        $ledgerCount = LedgerEntry::query()->count();
        $payload = [
            'card_credit_id' => $reversal->credit->id,
            'target_type' => 'installment',
            'target_id' => $targetInstallment->id,
            'amount' => '180.00',
            'applied_on' => '2026-09-13',
            'operation_id' => (string) Str::uuid(),
        ];

        $allocation = app(ApplyCardCredit::class)->handle($user, $payload);
        $replay = app(ApplyCardCredit::class)->handle($user, $payload);

        $this->assertSame($allocation->id, $replay->id);
        $this->assertSame('180.00', $allocation->amount);
        $this->assertSame('180.00', $targetInstallment->fresh()->paid_amount);
        $this->assertSame('180.00', $reversal->credit->fresh()->applied_amount);
        $this->assertSame('120.00', (string) BigDecimal::of($reversal->credit->amount)->minus($reversal->credit->fresh()->applied_amount));
        $this->assertSame($ledgerCount, LedgerEntry::query()->count());
        $this->assertDatabaseCount('card_credit_allocations', 1);
    }

    public function test_discounted_advance_reversal_credits_only_net_cash_paid(): void
    {
        [$user, $card, $category] = $this->cardContext();
        $account = $this->fundedAccount($user, '500.00');
        $purchase = app(CreateCardPurchase::class)->handle($user, $this->purchasePayload($card, $category, '100.00', [
            'first_due_on' => '2026-10-12',
        ]));
        $installment = $purchase->installments()->firstOrFail();

        app(AdvanceCardInstallments::class)->handle($user, [
            'credit_card_id' => $card->id,
            'source_account_id' => $account->id,
            'installment_ids' => [$installment->id],
            'discount_amount' => '5.00',
            'expected_gross_amount' => '100.00',
            'advanced_on' => '2026-09-09',
            'operation_id' => (string) Str::uuid(),
        ]);

        $reversal = app(ReverseCardPurchase::class)->handle($user, $this->reversalPayload($purchase));

        $this->assertSame('0.00', $reversal->cancelled_pending_amount);
        $this->assertSame('95.00', $reversal->credited_paid_amount);
        $this->assertSame('95.00', $reversal->credit->amount);
    }

    public function test_reversal_replay_is_idempotent_and_changed_payload_is_rejected(): void
    {
        [$user, $card, $category] = $this->cardContext();
        $purchase = app(CreateCardPurchase::class)->handle($user, $this->purchasePayload($card, $category, '300.00'));
        $payload = $this->reversalPayload($purchase);

        $first = app(ReverseCardPurchase::class)->handle($user, $payload);
        $replay = app(ReverseCardPurchase::class)->handle($user, $payload);

        $this->assertSame($first->id, $replay->id);
        $this->assertDatabaseCount('card_purchase_reversals', 1);

        $this->expectException(ValidationException::class);
        app(ReverseCardPurchase::class)->handle($user, [...$payload, 'reason' => 'Outro motivo']);
    }

    public function test_reversal_rejects_another_users_purchase_without_partial_writes(): void
    {
        [$user] = $this->cardContext();
        [$other, $otherCard, $otherCategory] = $this->cardContext();
        $purchase = app(CreateCardPurchase::class)->handle($other, $this->purchasePayload($otherCard, $otherCategory, '300.00'));

        try {
            app(ReverseCardPurchase::class)->handle($user, $this->reversalPayload($purchase));
            $this->fail('A compra de outro usuário deveria ser rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('card_purchase_id', $exception->errors());
        }

        $this->assertDatabaseCount('card_purchase_reversals', 0);
        $this->assertDatabaseCount('card_credits', 0);
        $this->assertDatabaseHas('card_purchases', ['id' => $purchase->id, 'deleted_at' => null]);
    }

    public function test_credit_application_rejects_cross_user_target_without_partial_writes(): void
    {
        [$user, $card, $category] = $this->cardContext();
        $account = $this->fundedAccount($user, '500.00');
        $purchase = app(CreateCardPurchase::class)->handle($user, $this->purchasePayload($card, $category, '100.00'));
        app(PayCreditCard::class)->handle($user, $this->paymentPayload($card, $account, '100.00'));
        $credit = app(ReverseCardPurchase::class)->handle($user, $this->reversalPayload($purchase))->credit;

        [$other, $otherCard, $otherCategory] = $this->cardContext();
        $otherPurchase = app(CreateCardPurchase::class)->handle($other, $this->purchasePayload($otherCard, $otherCategory, '100.00'));
        $payload = [
            'card_credit_id' => $credit->id,
            'target_type' => 'installment',
            'target_id' => $otherPurchase->installments()->firstOrFail()->id,
            'amount' => '50.00',
            'applied_on' => '2026-09-13',
            'operation_id' => (string) Str::uuid(),
        ];

        try {
            app(ApplyCardCredit::class)->handle($user, $payload);
            $this->fail('A obrigação de outro usuário deveria ser rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('target_id', $exception->errors());
        }

        $this->assertDatabaseCount('card_credit_allocations', 0);
        $this->assertSame('0.00', CardCredit::query()->findOrFail($credit->id)->applied_amount);
    }

    /** @return array{User,CreditCard,Category} */
    private function cardContext(): array
    {
        $user = User::factory()->create();

        return [$user, CreditCard::factory()->for($user)->create(), Category::factory()->for($user)->create(['type' => CategoryType::Expense])];
    }

    private function fundedAccount(User $user, string $balance): Account
    {
        $account = Account::factory()->for($user)->create();
        LedgerEntry::factory()->openingBalance()->for($user)->for($account, 'reference')->create(['amount' => $balance]);

        return $account;
    }

    /** @return array<string, mixed> */
    private function purchasePayload(CreditCard $card, Category $category, string $amount, array $overrides = []): array
    {
        return [...[
            'credit_card_id' => $card->id,
            'category_id' => $category->id,
            'description' => 'Compra para estorno',
            'planning_type' => ExpensePlanningType::Ordinary->value,
            'gross_amount' => $amount,
            'purchased_on' => '2026-09-01',
            'installments_count' => 1,
            'first_due_on' => '2026-09-12',
            'operation_id' => (string) Str::uuid(),
        ], ...$overrides];
    }

    /** @return array<string, mixed> */
    private function paymentPayload(CreditCard $card, Account $account, string $amount): array
    {
        return [
            'credit_card_id' => $card->id,
            'source_account_id' => $account->id,
            'amount' => $amount,
            'paid_on' => '2026-09-09',
            'operation_id' => (string) Str::uuid(),
        ];
    }

    /** @return array<string, mixed> */
    private function reversalPayload(CardPurchase $purchase): array
    {
        return [
            'card_purchase_id' => $purchase->id,
            'reversed_on' => '2026-09-13',
            'reason' => 'Compra cancelada',
            'operation_id' => (string) Str::uuid(),
        ];
    }
}
