<?php

namespace Tests\Feature;

use App\Actions\AdvanceCardInstallments;
use App\Actions\CreateCardPurchase;
use App\Enums\CardInstallmentStatus;
use App\Enums\CategoryType;
use App\Enums\ExpensePlanningType;
use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\LedgerEntry;
use App\Models\MonthlyFinancialSetting;
use App\Models\User;
use App\Queries\AccountBalanceQuery;
use App\Queries\FinancialPlanningOverviewQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CardAdvanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_future_installments_are_advanced_for_the_net_amount_and_replay_is_idempotent(): void
    {
        [$user, $card, $category, $account] = $this->context();
        $purchase = $this->purchase($user, $card, $category);
        $payload = $this->payload($card, $account, $purchase->installments->pluck('id')->all());

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
        $this->assertSame('0.00', $october['variable']['realized']);
        $this->assertSame('0.00', $october['variable']['projected']);
        $this->assertSame('0.00', $november['variable']['projected']);
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
