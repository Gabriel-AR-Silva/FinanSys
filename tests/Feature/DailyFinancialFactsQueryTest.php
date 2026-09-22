<?php

namespace Tests\Feature;

use App\Enums\CardInstallmentStatus;
use App\Enums\ExpensePlanningType;
use App\Models\CardInstallment;
use App\Models\CardPurchase;
use App\Models\User;
use App\Queries\DailyFinancialFactsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DailyFinancialFactsQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_and_due_installment_are_separate_views_not_a_combined_expense(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-22T16:00:00Z'));
        $user = User::factory()->create();
        $other = User::factory()->create();
        $purchase = CardPurchase::factory()->create([
            'user_id' => $user->id,
            'gross_amount' => '120.00',
            'purchased_on' => '2026-09-22',
            'planning_type' => ExpensePlanningType::Ordinary,
        ]);
        $installment = CardInstallment::factory()->create([
            'user_id' => $user->id,
            'card_purchase_id' => $purchase->id,
            'gross_amount' => '40.00',
            'due_on' => '2026-09-22',
            'status' => CardInstallmentStatus::Pending,
        ]);
        CardPurchase::factory()->create([
            'user_id' => $other->id,
            'gross_amount' => '999.00',
            'purchased_on' => '2026-09-22',
        ]);

        $facts = app(DailyFinancialFactsQuery::class)->forUserOnDay($user, '2026-09-22', CarbonImmutable::parse('2026-09-22T16:00:01Z'));

        $this->assertSame('120.00', $facts['purchase']['ordinary_purchase_total']);
        $this->assertSame([$purchase->id], $facts['purchase']['purchase_ids']);
        $this->assertSame('40.00', $facts['due']['ordinary_due_total']);
        $this->assertSame([$installment->id], $facts['due']['installment_ids']);
        $this->assertSame('0.00', $facts['ledger']['ordinary_total']);
        $this->assertSame('0.00', $facts['advance']['ordinary_net_advanced']);
        $this->assertSame('0.00', $facts['reversal']['credited_paid_total']);
        $this->assertSame('2026-09-22T16:00:01+00:00', $facts['observed_at']);
        $this->assertSame([], $facts['coverage_blockers']);
        $this->assertSame(['due'], $facts['as_of_unsupported_views']);
        $this->assertNull($facts['eligible_spent']);
        $this->assertSame('unreconciled_distinct_financial_views', $facts['reconciliation_status']);
    }

    public function test_unclassified_and_later_edited_purchase_block_reconciliation_without_leaking_another_user(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-22T16:00:00Z'));
        $user = User::factory()->create();
        $other = User::factory()->create();
        $stable = CardPurchase::factory()->create([
            'user_id' => $user->id, 'gross_amount' => '10.00',
            'purchased_on' => '2026-09-22', 'planning_type' => ExpensePlanningType::Ordinary,
        ]);
        CardPurchase::factory()->create([
            'user_id' => $user->id, 'gross_amount' => '20.00',
            'purchased_on' => '2026-09-22', 'planning_type' => null,
        ]);
        $changed = CardPurchase::factory()->create([
            'user_id' => $user->id, 'gross_amount' => '30.00',
            'purchased_on' => '2026-09-22', 'planning_type' => ExpensePlanningType::Ordinary,
        ]);
        $foreign = CardPurchase::factory()->create([
            'user_id' => $other->id, 'gross_amount' => '900.00',
            'purchased_on' => '2026-09-22', 'planning_type' => ExpensePlanningType::Ordinary,
        ]);
        DB::table('card_purchases')->where('id', $changed->id)->update([
            'gross_amount' => '300.00', 'updated_at' => '2026-09-23 10:00:00',
        ]);
        DB::table('card_purchases')->where('id', $foreign->id)->update([
            'gross_amount' => '999.00', 'updated_at' => '2026-09-23 10:00:00',
        ]);

        $facts = app(DailyFinancialFactsQuery::class)->forUserOnDay(
            $user, '2026-09-22', CarbonImmutable::parse('2026-09-22T16:00:01Z'),
        );

        $this->assertSame('10.00', $facts['purchase']['ordinary_purchase_total']);
        $this->assertSame([$stable->id], $facts['purchase']['purchase_ids']);
        $this->assertSame([$changed->id], $facts['purchase']['unverifiable_purchase_ids']);
        $this->assertSame(1, $facts['purchase']['unclassified_count']);
        $this->assertSame(['purchase_unclassified', 'purchase_unverifiable'], $facts['coverage_blockers']);
        $this->assertSame(['due'], $facts['as_of_unsupported_views']);
        $this->assertNull($facts['eligible_spent']);
    }
}
