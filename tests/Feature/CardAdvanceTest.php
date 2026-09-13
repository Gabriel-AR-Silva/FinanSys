<?php

namespace Tests\Feature;

use App\Actions\AdvanceCardInstallments;
use App\Actions\CreateCardPurchase;
use App\Enums\CardInstallmentStatus;
use App\Enums\CategoryType;
use App\Enums\ExpensePlanningType;
use App\Enums\LedgerEntryType;
use App\Enums\RecordStatus;
use App\Models\Account;
use App\Models\CardPayment;
use App\Models\CardPaymentAllocation;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\LedgerEntry;
use App\Models\MonthlyFinancialSetting;
use App\Models\User;
use App\Queries\AccountBalanceQuery;
use App\Queries\FinancialPlanningOverviewQuery;
use App\Support\AuditRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class CardAdvanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-13 12:00:00', 'America/Sao_Paulo'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_two_future_installments_are_advanced_for_the_net_amount_and_replay_is_idempotent(): void
    {
        [$user, $card, $category, $account] = $this->context();
        $purchase = $this->purchase($user, $card, $category);
        $payload = $this->payload($card, $account, $purchase->installments->pluck('id')->all());
        $auditCount = DB::table('audit_logs')->count();

        $first = app(AdvanceCardInstallments::class)->handle($user, $payload);
        $replayed = app(AdvanceCardInstallments::class)->handle($user, [...$payload, 'installment_ids' => array_reverse($payload['installment_ids'])]);

        $this->assertSame($first->id, $replayed->id);
        $this->assertSame('200.00', $first->gross_amount);
        $this->assertSame('10.00', $first->discount_amount);
        $this->assertSame('190.00', $first->net_amount);
        $this->assertSame(['95.00', '95.00'], $first->allocations->pluck('net_amount')->all());
        $this->assertSame(['5.00', '5.00'], $first->allocations->pluck('discount_amount')->all());
        $this->assertSame([CardInstallmentStatus::Advanced, CardInstallmentStatus::Advanced], $purchase->installments()->orderBy('id')->get()->pluck('status')->all());
        $this->assertSame(['0.00', '0.00'], $purchase->installments()->orderBy('id')->get()->pluck('paid_amount')->all());
        $this->assertSame(LedgerEntryType::CardAdvance, $first->ledgerEntry->type);
        $this->assertEquals(310, app(AccountBalanceQuery::class)->forUser($user)->sole()->balance);
        $this->assertDatabaseCount('card_advances', 1);
        $this->assertDatabaseCount('card_advance_allocations', 2);
        $this->assertDatabaseCount('ledger_entries', 2);
        $this->assertDatabaseMissing('ledger_entries', ['type' => LedgerEntryType::Expense->value]);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'created')->where('auditable_type', 'ledger_entry')->where('auditable_id', $first->ledger_entry_id)->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'created')->where('auditable_type', 'card_advance')->where('auditable_id', $first->id)->count());
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'created')->where('auditable_type', 'card_advance_allocation')->whereIn('auditable_id', $first->allocations->modelKeys())->count());
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'updated')->where('auditable_type', 'card_installment')->whereIn('auditable_id', $purchase->installments->modelKeys())->count());

        try {
            app(AdvanceCardInstallments::class)->handle($user, [...$payload,
                'installment_ids' => [$payload['installment_ids'][0]],
                'expected_gross_amount' => '100.00',
            ]);
            $this->fail('O replay com outra seleção deveria ser rejeitado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('operation_id', $exception->errors());
        }
        $this->assertDatabaseCount('card_advances', 1);
        $this->assertDatabaseCount('ledger_entries', 2);
        $this->assertSame($auditCount + 6, DB::table('audit_logs')->count());
    }

    public function test_full_discount_releases_the_obligation_without_artificial_cash_entry(): void
    {
        [$user, $card, $category, $account] = $this->context();
        $purchase = $this->purchase($user, $card, $category);

        $advance = app(AdvanceCardInstallments::class)->handle($user, $this->payload(
            $card, $account, [$purchase->installments->first()->id], ['discount_amount' => '100.00', 'expected_gross_amount' => '100.00'],
        ));

        $this->assertSame('0.00', $advance->net_amount);
        $this->assertNull($advance->ledger_entry_id);
        $this->assertSame(CardInstallmentStatus::Advanced, $purchase->installments->first()->fresh()->status);
        $this->assertDatabaseCount('ledger_entries', 1);
    }

    public function test_uses_the_residual_and_rejects_stale_or_ineligible_selection_without_partial_writes(): void
    {
        [$user, $card, $category, $account] = $this->context();
        $purchase = $this->purchase($user, $card, $category);
        $installment = $purchase->installments->first();
        $installment->update(['paid_amount' => '20.00']);

        try {
            app(AdvanceCardInstallments::class)->handle($user, $this->payload($card, $account, [$installment->id], ['expected_gross_amount' => '100.00']));
            $this->fail('Uma prévia obsoleta deveria ser rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('expected_gross_amount', $exception->errors());
        }

        $otherUserInstallment = $this->purchase(...array_slice($this->context(), 0, 3))->installments->first();
        foreach ([[$installment->id, '80.00'], [$otherUserInstallment->id, '100.00']] as [$id, $gross]) {
            try {
                app(AdvanceCardInstallments::class)->handle($user, $this->payload($card, $account, [$id], [
                    'expected_gross_amount' => $gross, 'discount_amount' => '8.00',
                ]));
                if ($id === $otherUserInstallment->id) {
                    $this->fail('Uma parcela de outro usuário deveria ser rejeitada.');
                }
            } catch (ValidationException $exception) {
                if ($id === $otherUserInstallment->id) {
                    $this->assertArrayHasKey('installment_ids', $exception->errors());
                } else {
                    throw $exception;
                }
            }
        }

        $this->assertSame(CardInstallmentStatus::Advanced, $installment->fresh()->status);
        $this->assertSame('72.00', $installment->advanceAllocations()->sole()->net_amount);
        $this->assertDatabaseCount('card_advances', 1);
    }

    public function test_planning_counts_net_now_and_removes_gross_from_future_months(): void
    {
        [$user, $card, $category, $account] = $this->context();
        foreach (['2026-09', '2026-10', '2026-11'] as $month) {
            MonthlyFinancialSetting::factory()->for($user)->create(['month' => $month]);
        }
        $purchase = $this->purchase($user, $card, $category);

        $octoberBefore = app(FinancialPlanningOverviewQuery::class)->forUser($user, CarbonImmutable::parse('2026-10-09', 'America/Sao_Paulo'));
        app(AdvanceCardInstallments::class)->handle($user, $this->payload($card, $account, $purchase->installments->pluck('id')->all()));
        $september = app(FinancialPlanningOverviewQuery::class)->forUser($user, CarbonImmutable::parse('2026-09-09', 'America/Sao_Paulo'));
        $october = app(FinancialPlanningOverviewQuery::class)->forUser($user, CarbonImmutable::parse('2026-10-09', 'America/Sao_Paulo'));
        $november = app(FinancialPlanningOverviewQuery::class)->forUser($user, CarbonImmutable::parse('2026-11-09', 'America/Sao_Paulo'));

        $this->assertSame('100.00', $octoberBefore['variable']['projected']);
        $this->assertSame('190.00', $september['variable']['realized']);
        $this->assertSame('190.00', $september['variable']['projected']);
        $this->assertEquals(0, $october['variable']['realized']);
        $this->assertEquals(0, $october['variable']['projected']);
        $this->assertEquals(0, $november['variable']['projected']);
    }

    public function test_http_endpoint_requires_authentication_and_persists_the_server_recalculation(): void
    {
        [$user, $card, $category, $account] = $this->context();
        $purchase = $this->purchase($user, $card, $category);
        $payload = $this->payload($card, $account, $purchase->installments->pluck('id')->all());

        $this->post(route('card-advances.store'), $payload)->assertRedirect(route('login'));
        $this->actingAs($user)->post(route('card-advances.store'), $payload)
            ->assertRedirect(route('credit-cards.index'))->assertSessionHas('success');

        $this->assertDatabaseHas('card_advances', [
            'user_id' => $user->id, 'gross_amount' => 200, 'discount_amount' => 10, 'net_amount' => 190,
        ]);
    }

    public function test_rejects_current_month_cross_card_and_insufficient_balance_without_partial_writes(): void
    {
        [$user, $card, $category, $account] = $this->context();
        $future = $this->purchase($user, $card, $category)->installments->first();
        $current = app(CreateCardPurchase::class)->handle($user, [
            'credit_card_id' => $card->id, 'category_id' => $category->id, 'description' => 'Compra atual',
            'planning_type' => ExpensePlanningType::Ordinary->value, 'gross_amount' => '100.00',
            'purchased_on' => '2026-09-01', 'installments_count' => 1, 'first_due_on' => '2026-09-20',
            'operation_id' => (string) Str::uuid(),
        ])->installments->first();
        $otherCard = CreditCard::factory()->for($user)->create();
        $otherCardInstallment = $this->purchase($user, $otherCard, $category)->installments->first();
        foreach ([
            $this->payload($card, $account, [$current->id], ['expected_gross_amount' => '100.00', 'discount_amount' => '0.00']),
            $this->payload($card, $account, [$otherCardInstallment->id], ['expected_gross_amount' => '100.00', 'discount_amount' => '0.00']),
        ] as $payload) {
            try {
                app(AdvanceCardInstallments::class)->handle($user, $payload);
                $this->fail('A antecipação inválida deveria ser rejeitada.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('installment_ids', $exception->errors());
            }
        }

        LedgerEntry::query()->whereBelongsTo($user)->where('type', LedgerEntryType::OpeningBalance)->update(['amount' => '50.00']);
        try {
            app(AdvanceCardInstallments::class)->handle($user, $this->payload($card, $account, [$future->id], [
                'expected_gross_amount' => '100.00', 'discount_amount' => '0.00',
            ]));
            $this->fail('Saldo insuficiente deveria impedir a antecipação.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('discount_amount', $exception->errors());
        }

        $this->assertDatabaseCount('card_advances', 0);
        $this->assertDatabaseCount('card_advance_allocations', 0);
        $this->assertDatabaseCount('ledger_entries', 1);
        $this->assertSame(CardInstallmentStatus::Pending, $future->fresh()->status);
        $this->assertSame(CardInstallmentStatus::Pending, $current->fresh()->status);
        $this->assertSame(CardInstallmentStatus::Pending, $otherCardInstallment->fresh()->status);
    }

    public function test_http_validation_rejects_invalid_and_foreign_resources_without_writes(): void
    {
        [$user, $card, $category, $account] = $this->context();
        $installment = $this->purchase($user, $card, $category)->installments->first();
        $auditCount = DB::table('audit_logs')->count();
        $ledgerCount = LedgerEntry::query()->count();
        $this->actingAs($user)->post(route('card-advances.store'), [])->assertSessionHasErrors([
            'credit_card_id', 'source_account_id', 'installment_ids', 'discount_amount',
            'expected_gross_amount', 'advanced_on', 'operation_id',
        ]);
        $this->post(route('card-advances.store'), $this->payload($card, $account, [$installment->id], [
            'installment_ids' => [$installment->id, $installment->id],
            'expected_gross_amount' => '100.00', 'advanced_on' => '2999-01-01', 'operation_id' => 'invalid',
        ]))->assertSessionHasErrors(['installment_ids.1', 'advanced_on', 'operation_id']);

        $foreignCard = CreditCard::factory()->create();
        $foreignAccount = Account::factory()->create();
        $this->post(route('card-advances.store'), $this->payload($foreignCard, $account, [$installment->id], [
            'expected_gross_amount' => '100.00',
        ]))->assertSessionHasErrors('credit_card_id');
        $this->post(route('card-advances.store'), $this->payload($card, $foreignAccount, [$installment->id], [
            'expected_gross_amount' => '100.00',
        ]))->assertSessionHasErrors('source_account_id');
        $this->post(route('card-advances.store'), $this->payload($card, $account, array_fill(0, 201, $installment->id), [
            'discount_amount' => 'invalid', 'expected_gross_amount' => '-1.00',
        ]))->assertSessionHasErrors(['installment_ids', 'discount_amount', 'expected_gross_amount']);
        $card->update(['status' => RecordStatus::Inactive]);
        $this->post(route('card-advances.store'), $this->payload($card, $account, [$installment->id], [
            'expected_gross_amount' => '100.00',
        ]))->assertSessionHasErrors('credit_card_id');

        $this->assertDatabaseCount('card_advances', 0);
        $this->assertDatabaseCount('card_advance_allocations', 0);
        $this->assertSame($ledgerCount, LedgerEntry::query()->count());
        $this->assertSame($auditCount, DB::table('audit_logs')->count());
        $this->assertSame(CardInstallmentStatus::Pending, $installment->fresh()->status);
    }

    public function test_replay_rejects_each_divergent_contract_field(): void
    {
        [$user, $card, $category, $account] = $this->context();
        $purchase = $this->purchase($user, $card, $category);
        $payload = $this->payload($card, $account, $purchase->installments->pluck('id')->all());
        $otherCard = CreditCard::factory()->for($user)->create();
        $otherAccount = Account::factory()->for($user)->create();

        app(AdvanceCardInstallments::class)->handle($user, $payload);

        foreach ([
            ['credit_card_id' => $otherCard->id],
            ['source_account_id' => $otherAccount->id],
            ['discount_amount' => '9.99'],
            ['expected_gross_amount' => '199.99'],
            ['advanced_on' => '2026-09-08'],
            ['installment_ids' => [$payload['installment_ids'][0]], 'expected_gross_amount' => '100.00'],
        ] as $changes) {
            try {
                app(AdvanceCardInstallments::class)->handle($user, [...$payload, ...$changes]);
                $this->fail('O replay divergente deveria ser rejeitado.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('operation_id', $exception->errors());
            }
        }

        $this->assertDatabaseCount('card_advances', 1);
        $this->assertDatabaseCount('card_advance_allocations', 2);
        $this->assertDatabaseCount('ledger_entries', 2);
    }

    public function test_retroactive_advance_is_rejected_when_a_selected_installment_was_paid_later(): void
    {
        [$user, $card, $category, $account] = $this->context();
        $installment = $this->purchase($user, $card, $category)->installments->first();
        $operationId = (string) Str::uuid();
        $ledgerEntry = LedgerEntry::factory()->create([
            'user_id' => $user->id,
            'reference_type' => $account->getMorphClass(),
            'reference_id' => $account->id,
            'type' => LedgerEntryType::CardPayment,
            'planning_type' => null,
            'amount' => '20.00',
            'occurred_at' => '2026-09-10',
            'operation_id' => $operationId,
        ]);
        $payment = CardPayment::factory()->create([
            'user_id' => $user->id,
            'credit_card_id' => $card->id,
            'source_account_id' => $account->id,
            'ledger_entry_id' => $ledgerEntry->id,
            'amount' => '20.00',
            'paid_on' => '2026-09-10',
            'operation_id' => $operationId,
        ]);
        CardPaymentAllocation::factory()->create([
            'user_id' => $user->id,
            'card_payment_id' => $payment->id,
            'card_installment_id' => $installment->id,
            'amount' => '20.00',
        ]);
        $installment->update(['paid_amount' => '20.00']);
        $auditCount = DB::table('audit_logs')->count();
        $ledgerCount = LedgerEntry::query()->count();

        try {
            app(AdvanceCardInstallments::class)->handle($user, $this->payload($card, $account, [$installment->id], [
                'expected_gross_amount' => '80.00',
                'discount_amount' => '8.00',
                'advanced_on' => '2026-09-09',
            ]));
            $this->fail('Uma antecipação anterior a um pagamento já registrado deveria ser rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('advanced_on', $exception->errors());
        }

        $this->assertDatabaseCount('card_advances', 0);
        $this->assertDatabaseCount('card_advance_allocations', 0);
        $this->assertSame($ledgerCount, LedgerEntry::query()->count());
        $this->assertSame($auditCount, DB::table('audit_logs')->count());
        $this->assertSame(CardInstallmentStatus::Pending, $installment->fresh()->status);
        $this->assertSame('20.00', $installment->fresh()->paid_amount);
    }

    public function test_audit_failure_rolls_back_cash_advance_and_installment_status(): void
    {
        [$user, $card, $category, $account] = $this->context();
        $purchase = $this->purchase($user, $card, $category);
        $auditCount = DB::table('audit_logs')->count();
        $auditCalls = 0;
        $realAuditRecorder = new AuditRecorder;
        $this->mock(AuditRecorder::class)->shouldReceive('record')->times(6)->andReturnUsing(function (...$arguments) use (&$auditCalls, $realAuditRecorder) {
            $auditCalls++;
            if ($auditCalls === 6) {
                throw new RuntimeException('audit unavailable');
            }

            return $realAuditRecorder->record(...$arguments);
        });

        try {
            app(AdvanceCardInstallments::class)->handle($user, $this->payload($card, $account, $purchase->installments->pluck('id')->all()));
            $this->fail('A falha de auditoria deveria abortar a operação.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit unavailable', $exception->getMessage());
        }

        $this->assertDatabaseCount('card_advances', 0);
        $this->assertDatabaseCount('card_advance_allocations', 0);
        $this->assertDatabaseCount('ledger_entries', 1);
        $this->assertSame($auditCount, DB::table('audit_logs')->count());
        $this->assertSame([CardInstallmentStatus::Pending, CardInstallmentStatus::Pending], $purchase->installments()->orderBy('id')->get()->pluck('status')->all());
    }

    /** @return array{User,CreditCard,Category,Account} */
    private function context(): array
    {
        $user = User::factory()->create();
        $card = CreditCard::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
        $account = Account::factory()->for($user)->create();
        LedgerEntry::factory()->openingBalance()->for($user)->for($account, 'reference')->create(['amount' => '500.00']);

        return [$user, $card, $category, $account];
    }

    private function purchase(User $user, CreditCard $card, Category $category)
    {
        return app(CreateCardPurchase::class)->handle($user, [
            'credit_card_id' => $card->id, 'category_id' => $category->id, 'description' => 'Curso',
            'planning_type' => ExpensePlanningType::Extraordinary->value, 'gross_amount' => '200.00',
            'purchased_on' => '2026-09-01', 'installments_count' => 2, 'first_due_on' => '2026-10-12',
            'operation_id' => (string) Str::uuid(),
        ]);
    }

    /** @param list<int> $installmentIds */
    private function payload(CreditCard $card, Account $account, array $installmentIds, array $overrides = []): array
    {
        return [...[
            'credit_card_id' => $card->id, 'source_account_id' => $account->id,
            'installment_ids' => $installmentIds, 'discount_amount' => '10.00',
            'expected_gross_amount' => '200.00', 'advanced_on' => '2026-09-09',
            'operation_id' => (string) Str::uuid(),
        ], ...$overrides];
    }
}
