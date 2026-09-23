<?php

namespace Tests\Feature;

use App\Enums\ExpensePlanningType;
use App\Models\CardCharge;
use App\Models\CardInstallment;
use App\Models\CardPurchase;
use App\Models\User;
use App\Queries\DailyCardDueCommitmentQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyCardDueSliceTest extends TestCase
{
    use RefreshDatabase;

    public function test_separates_card_obligations_by_planning_type_and_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $purchase = CardPurchase::factory()->create([
            'user_id' => $user->id,
            'planning_type' => ExpensePlanningType::Extraordinary,
            'gross_amount' => '300.00',
            'installments_count' => 10,
        ]);
        $installment = CardInstallment::factory()->create([
            'user_id' => $user->id,
            'card_purchase_id' => $purchase->id,
            'gross_amount' => '30.00',
            'due_on' => '2026-09-21',
        ]);
        CardCharge::factory()->create([
            'user_id' => $user->id,
            'planning_type' => ExpensePlanningType::Fixed,
            'amount' => '20.00',
            'due_on' => '2026-09-21',
        ]);
        CardCharge::factory()->create([
            'user_id' => $other->id,
            'planning_type' => ExpensePlanningType::Ordinary,
            'amount' => '999.00',
            'due_on' => '2026-09-21',
        ]);

        $result = app(DailyCardDueCommitmentQuery::class)->forUserOnDay($user, '2026-09-21');

        $this->assertSame('0.00', $result['ordinary_due_total']);
        $this->assertSame('20.00', $result['fixed_due_total']);
        $this->assertSame('30.00', $result['extraordinary_due_total']);
        $this->assertSame([$installment->id], $result['extraordinary_installment_ids']);
        $this->assertSame('current_card_due_only', $result['coverage']);
    }
}
