<?php

namespace Tests\Feature;

use App\Enums\CardInstallmentStatus;
use App\Enums\ExpensePlanningType;
use App\Models\CardCharge;
use App\Models\CardInstallment;
use App\Models\CardPurchase;
use App\Models\User;
use App\Queries\DailyCardDueCommitmentQuery;
use App\Queries\DailyFinancialFactsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyCardDueClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_keeps_fixed_and_extraordinary_card_obligations_distinct_from_ordinary_consumption(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $purchase = CardPurchase::factory()->create([
            'user_id' => $user->id,
            'planning_type' => ExpensePlanningType::Extraordinary,
            'gross_amount' => '300.00',
            'installments_count' => 10,
        ]);
        $extraordinary = CardInstallment::factory()->create([
            'user_id' => $user->id,
            'card_purchase_id' => $purchase->id,
            'gross_amount' => '30.00',
            'paid_amount' => '30.00',
            'status' => CardInstallmentStatus::Paid,
            'due_on' => '2026-09-21',
        ]);
        $fixedPurchase = CardPurchase::factory()->create(['user_id' => $user->id, 'planning_type' => ExpensePlanningType::Fixed]);
        $fixed = CardInstallment::factory()->create([
            'user_id' => $user->id,
            'card_purchase_id' => $fixedPurchase->id,
            'gross_amount' => '65.00',
            'due_on' => '2026-09-21',
        ]);
        $charge = CardCharge::factory()->create([
            'user_id' => $user->id,
            'planning_type' => ExpensePlanningType::Extraordinary,
            'amount' => '5.00',
            'due_on' => '2026-09-21',
        ]);
        CardInstallment::factory()->create([
            'user_id' => $other->id,
            'gross_amount' => '999.00',
            'due_on' => '2026-09-21',
        ]);
        CardCharge::factory()->create([
            'user_id' => $user->id,
            'planning_type' => ExpensePlanningType::Extraordinary,
            'amount' => '900.00',
            'status' => CardInstallmentStatus::Reversed,
            'due_on' => '2026-09-21',
        ]);

        $result = app(DailyCardDueCommitmentQuery::class)->forUserOnDay($user, '2026-09-21');
        $this->assertSame('0.00', $result['ordinary_due_total']);
        $this->assertSame('65.00', $result['fixed_due_total']);
        $this->assertSame('35.00', $result['extraordinary_due_total']);
        $this->assertSame([], $result['installment_ids']);
        $this->assertSame([$fixed->id], $result['fixed_installment_ids']);
        $this->assertSame([$extraordinary->id], $result['extraordinary_installment_ids']);
        $this->assertSame([$charge->id], $result['extraordinary_charge_ids']);
        $this->assertNull(app(DailyFinancialFactsQuery::class)->forUserOnDay($user, '2026-09-21')['eligible_spent']);
    }
}
