<?php

namespace Tests\Feature;

use App\Enums\ExpensePlanningType;
use App\Models\CardCharge;
use App\Models\CardInstallment;
use App\Models\CardPurchase;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Queries\DailyEligibleSpendReconciliationQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DailyEligibleSpendReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciles_known_ordinary_origins_without_recounting_due_installments_or_fees_paid(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-22T02:59:59Z'));
        $user = User::factory()->create();
        $other = User::factory()->create();
        $ledger = LedgerEntry::factory()->expense()->create(['user_id' => $user->id, 'amount' => '80.00', 'occurred_at' => '2026-09-21 18:00:00']);
        LedgerEntry::factory()->income()->create(['user_id' => $user->id, 'amount' => '100.00', 'occurred_at' => '2026-09-21 18:00:00']);
        LedgerEntry::factory()->expense()->create(['user_id' => $other->id, 'amount' => '999.00', 'occurred_at' => '2026-09-21 18:00:00']);
        $charge = CardCharge::factory()->create([
            'user_id' => $user->id,
            'planning_type' => ExpensePlanningType::Ordinary,
            'amount' => '7.35', 'paid_amount' => '7.35',
            'charged_on' => '2026-09-21', 'due_on' => '2026-10-21',
        ]);
        CardPurchase::factory()->create(['user_id' => $user->id, 'gross_amount' => '300.00', 'purchased_on' => '2026-09-21', 'planning_type' => ExpensePlanningType::Extraordinary]);
        $old = CardPurchase::factory()->create(['user_id' => $user->id, 'gross_amount' => '1200.00', 'purchased_on' => '2026-09-01', 'planning_type' => ExpensePlanningType::Ordinary]);
        CardInstallment::factory()->create(['user_id' => $user->id, 'card_purchase_id' => $old->id, 'due_on' => '2026-09-21', 'gross_amount' => '200.00']);
        $result = app(DailyEligibleSpendReconciliationQuery::class)->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T02:59:59Z'));

        $this->assertSame('87.35', $result['eligible_spent']);
        $this->assertSame('reconciled_ordinary_consumption', $result['coverage']);
        $this->assertSame([], $result['blockers']);
        $this->assertSame([$ledger->id], $result['sources']['ledger']['entry_ids']);
        $this->assertSame([$charge->id], $result['sources']['card_charges']['charge_ids']);
        $this->assertSame([], $result['sources']['card_purchases_gross_behavior_only']['purchase_ids']);
    }

    public function test_ordinary_installment_purchase_counts_purchase_once_and_never_recounts_installment_or_payment_state(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21T18:00:00Z'));
        $user = User::factory()->create();
        $purchase = CardPurchase::factory()->create([
            'user_id' => $user->id,
            'purchased_on' => '2026-09-21',
            'gross_amount' => '1200.00',
            'installments_count' => 6,
            'planning_type' => ExpensePlanningType::Ordinary,
        ]);
        CardInstallment::factory()->create([
            'user_id' => $user->id,
            'card_purchase_id' => $purchase->id,
            'due_on' => '2026-09-21',
            'gross_amount' => '200.00',
            'paid_amount' => '50.00',
        ]);
        $result = app(DailyEligibleSpendReconciliationQuery::class)->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-21T19:00:00Z'));

        $this->assertSame('1200.00', $result['eligible_spent']);
        $this->assertSame([], $result['blockers']);
        $this->assertSame('1200.00', $result['sources']['card_purchases_gross_behavior_only']['ordinary_purchase_total']);
    }

    public function test_unclassified_or_later_edited_origin_cannot_be_reported_as_zero_spending(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21T18:00:00Z'));
        $user = User::factory()->create();
        LedgerEntry::factory()->expense()->create(['user_id' => $user->id, 'planning_type' => null, 'occurred_at' => '2026-09-21 15:00:00']);
        $charge = CardCharge::factory()->create(['user_id' => $user->id, 'planning_type' => ExpensePlanningType::Ordinary, 'charged_on' => '2026-09-21']);
        DB::table('card_charges')->where('id', $charge->id)->update(['charged_on' => '2026-09-22', 'updated_at' => '2026-09-23 10:00:00']);
        $result = app(DailyEligibleSpendReconciliationQuery::class)->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T18:00:00Z'));

        $this->assertNull($result['eligible_spent']);
        $this->assertContains('ledger_classification_missing', $result['blockers']);
        $this->assertContains('card_charge_history_unverifiable', $result['blockers']);
    }
}
