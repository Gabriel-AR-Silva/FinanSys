<?php

namespace Tests\Feature;

use App\Actions\CreateCardPurchase;
use App\Actions\PayCreditCard;
use App\Enums\CardInstallmentStatus;
use App\Enums\CategoryType;
use App\Enums\ExpensePlanningType;
use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Queries\AccountBalanceQuery;
use App\Queries\DailyCardPaymentSettlementQuery;
use App\Queries\DailyFinancialFactsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CardPurchaseAndPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_splits_cents_exactly_and_preserves_the_original_due_day(): void
    {
        [$user, $card, $category] = $this->cardContext();
        $purchase = app(CreateCardPurchase::class)->handle($user, $this->purchasePayload($card, $category, [
            'gross_amount' => '200.01', 'installments_count' => 2, 'first_due_on' => '2027-01-31',
        ]));

        $this->assertSame(['100.01', '100.00'], $purchase->installments->pluck('gross_amount')->all());
        $this->assertSame(['2027-01-31', '2027-02-28'], $purchase->installments->map(fn ($installment) => $installment->due_on->toDateString())->all());
        $this->assertSame('200.01', $purchase->installments->reduce(fn (string $total, $installment): string => bcadd($total, $installment->gross_amount, 2), '0.00'));
        $this->assertDatabaseCount('ledger_entries', 0);
        $this->assertDatabaseCount('audit_logs', 3);
    }

    public function test_purchase_replay_is_idempotent_and_rejects_changed_data(): void
    {
        [$user, $card, $category] = $this->cardContext();
        $payload = $this->purchasePayload($card, $category);
        $first = app(CreateCardPurchase::class)->handle($user, $payload);
        $replayed = app(CreateCardPurchase::class)->handle($user, $payload);

        $this->assertSame($first->id, $replayed->id);
        $this->assertDatabaseCount('card_purchases', 1);
        $this->assertDatabaseCount('card_installments', 2);
        $this->assertDatabaseCount('audit_logs', 3);

        $this->expectException(ValidationException::class);
        app(CreateCardPurchase::class)->handle($user, [...$payload, 'gross_amount' => '201.00']);
    }

    public function test_purchase_rejects_resources_from_another_user_without_partial_writes(): void
    {
        [$user, $card, $category] = $this->cardContext();

        foreach ([
            [$this->purchasePayload(CreditCard::factory()->create(), $category), 'credit_card_id'],
            [$this->purchasePayload($card, Category::factory()->create(['type' => CategoryType::Expense])), 'category_id'],
        ] as [$payload, $field]) {
            try {
                app(CreateCardPurchase::class)->handle($user, $payload);
                $this->fail('Um recurso de outro usuário deveria ser rejeitado.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }

        $this->assertDatabaseCount('card_purchases', 0);
        $this->assertDatabaseCount('card_installments', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_payment_uses_cash_once_and_allocates_the_oldest_installments_first(): void
    {
        [$user, $card, $category] = $this->cardContext();
        $account = $this->fundedAccount($user, '500.00');
        $purchase = app(CreateCardPurchase::class)->handle($user, $this->purchasePayload($card, $category, [
            'purchased_on' => '2026-08-01', 'first_due_on' => '2026-08-12',
        ]));

        $payment = app(PayCreditCard::class)->handle($user, $this->paymentPayload($card, $account, '150.00'));
        $installments = $purchase->installments()->orderBy('due_on')->get();
        $settlement = app(DailyCardPaymentSettlementQuery::class)->forUserOnDay($user, '2026-09-09');

        $this->assertSame(['100.00', '50.00'], $payment->allocations->pluck('amount')->all());
        $this->assertSame('100.00', $installments[0]->paid_amount);
        $this->assertSame(CardInstallmentStatus::Paid, $installments[0]->status);
        $this->assertSame('50.00', $installments[1]->paid_amount);
        $this->assertSame(CardInstallmentStatus::Pending, $installments[1]->status);
        $this->assertSame(LedgerEntryType::CardPayment, $payment->ledgerEntry->type);
        $this->assertSame('150.00', $settlement['settled_total']);
        $this->assertSame([$payment->id], $settlement['payment_ids']);
        $this->assertSame([$payment->ledger_entry_id], $settlement['ledger_entry_ids']);
        $this->assertSame([], $settlement['unverifiable_payment_ids']);
        $this->assertEquals(350, app(AccountBalanceQuery::class)->forUser($user)->sole()->balance);
        $this->assertDatabaseMissing('ledger_entries', ['user_id' => $user->id, 'type' => LedgerEntryType::Expense->value]);
    }

    public function test_unmatched_card_payment_ledger_is_flagged_without_duplicate_settlement_or_consumption(): void
    {
        [$user, $card, $category] = $this->cardContext();
        $account = $this->fundedAccount($user, '500.00');
        app(CreateCardPurchase::class)->handle($user, $this->purchasePayload($card, $category, [
            'purchased_on' => '2026-08-01', 'first_due_on' => '2026-08-12',
        ]));
        $payment = app(PayCreditCard::class)->handle($user, $this->paymentPayload($card, $account, '150.00'));
        $orphan = LedgerEntry::factory()->create([
            'user_id' => $user->id,
            'reference_type' => $account->getMorphClass(),
            'reference_id' => $account->id,
            'type' => LedgerEntryType::CardPayment,
            'planning_type' => null,
            'amount' => '7.00',
            'occurred_at' => '2026-09-09 10:00:00',
        ]);

        $facts = app(DailyFinancialFactsQuery::class)->forUserOnDay($user, '2026-09-09');

        $this->assertSame('150.00', $facts['settlement']['settled_total']);
        $this->assertSame([$payment->id], $facts['settlement']['payment_ids']);
        $this->assertSame([$payment->ledger_entry_id], $facts['settlement']['ledger_entry_ids']);
        $this->assertSame([$orphan->id], $facts['settlement']['unmatched_ledger_entry_ids']);
        $this->assertContains('settlement_unverifiable', $facts['coverage_blockers']);
        $this->assertSame('0.00', $facts['ledger']['ordinary_total']);
        $this->assertNull($facts['eligible_spent']);
    }

    public function test_payment_replay_and_rejections_never_duplicate_cash_or_allocations(): void
    {
        [$user, $card, $category] = $this->cardContext();
        $account = $this->fundedAccount($user, '500.00');
        app(CreateCardPurchase::class)->handle($user, $this->purchasePayload($card, $category, [
            'purchased_on' => '2026-08-01', 'first_due_on' => '2026-08-12',
        ]));
        $payload = $this->paymentPayload($card, $account, '150.00');
        $first = app(PayCreditCard::class)->handle($user, $payload);
        $replayed = app(PayCreditCard::class)->handle($user, $payload);

        $this->assertSame($first->id, $replayed->id);
        $this->assertDatabaseCount('card_payments', 1);
        $this->assertDatabaseCount('card_payment_allocations', 2);
        $this->assertDatabaseCount('ledger_entries', 2);

        foreach (['51.00', '351.00'] as $amount) {
            try {
                app(PayCreditCard::class)->handle($user, $this->paymentPayload($card, $account, $amount));
                $this->fail('O pagamento inválido deveria ser rejeitado.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('amount', $exception->errors());
            }
        }

        $this->assertDatabaseCount('card_payments', 1);
        $this->assertDatabaseCount('card_payment_allocations', 2);
        $this->assertDatabaseCount('ledger_entries', 2);
    }

    public function test_payment_rejects_another_users_card_and_account(): void
    {
        [$user, $card, $category] = $this->cardContext();
        $account = $this->fundedAccount($user, '500.00');
        app(CreateCardPurchase::class)->handle($user, $this->purchasePayload($card, $category));

        foreach ([[CreditCard::factory()->create(), $account], [$card, Account::factory()->create()]] as [$attemptCard, $attemptAccount]) {
            try {
                app(PayCreditCard::class)->handle($user, $this->paymentPayload($attemptCard, $attemptAccount, '50.00'));
                $this->fail('Recursos financeiros de outro usuário deveriam ser rejeitados.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }

        $this->assertDatabaseCount('card_payments', 0);
        $this->assertDatabaseCount('card_payment_allocations', 0);
        $this->assertDatabaseCount('ledger_entries', 1);
    }

    public function test_regular_payment_cannot_anticipate_future_installments_silently(): void
    {
        [$user, $card, $category] = $this->cardContext();
        $account = $this->fundedAccount($user, '500.00');
        app(CreateCardPurchase::class)->handle($user, $this->purchasePayload($card, $category, ['first_due_on' => '2026-10-12']));

        $this->expectException(ValidationException::class);
        app(PayCreditCard::class)->handle($user, $this->paymentPayload($card, $account, '50.00'));
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
    private function purchasePayload(CreditCard $card, Category $category, array $overrides = []): array
    {
        return [...[
            'credit_card_id' => $card->id, 'category_id' => $category->id, 'description' => 'Notebook de trabalho',
            'planning_type' => ExpensePlanningType::Ordinary->value, 'gross_amount' => '200.00', 'purchased_on' => '2026-09-01',
            'installments_count' => 2, 'first_due_on' => '2026-09-12', 'operation_id' => (string) Str::uuid(),
        ], ...$overrides];
    }

    /** @return array<string, mixed> */
    private function paymentPayload(CreditCard $card, Account $account, string $amount): array
    {
        return [
            'credit_card_id' => $card->id, 'source_account_id' => $account->id, 'amount' => $amount,
            'paid_on' => '2026-09-09', 'operation_id' => (string) Str::uuid(),
        ];

    }
}
