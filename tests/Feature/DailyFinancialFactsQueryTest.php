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
        $this->assertNull($facts['eligible_spent']);
        $this->assertSame('unreconciled_distinct_financial_views', $facts['reconciliation_status']);
    }
}
