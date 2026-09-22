<?php

namespace Tests\Feature;

use App\Enums\CardInstallmentStatus;
use App\Enums\ExpensePlanningType;
use App\Models\CardAdvance;
use App\Models\CardAdvanceAllocation;
use App\Models\CardInstallment;
use App\Models\CardPurchase;
use App\Models\User;
use App\Queries\DailyCardAdvanceImpactQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DailyCardAdvanceInstallmentObservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reassigned_installment_cannot_silently_reclassify_an_earlier_advance(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21T16:00:00Z'));
        $user = User::factory()->create();
        $other = User::factory()->create();
        $create = function (User $owner, string $amount): array {
            $purchase = CardPurchase::factory()->create([
                'user_id' => $owner->id,
                'planning_type' => ExpensePlanningType::Ordinary,
            ]);
            $installment = CardInstallment::factory()->create([
                'user_id' => $owner->id,
                'card_purchase_id' => $purchase->id,
                'status' => CardInstallmentStatus::Advanced,
                'gross_amount' => $amount,
                'due_on' => '2026-11-12',
            ]);
            $advance = CardAdvance::query()->create([
                'user_id' => $owner->id,
                'credit_card_id' => $purchase->credit_card_id,
                'gross_amount' => $amount,
                'discount_amount' => '0.00',
                'net_amount' => $amount,
                'advanced_on' => '2026-09-21',
                'selected_installment_ids' => [$installment->id],
                'operation_id' => (string) Str::uuid(),
            ]);
            $allocation = CardAdvanceAllocation::query()->create([
                'user_id' => $owner->id,
                'card_advance_id' => $advance->id,
                'card_installment_id' => $installment->id,
                'gross_amount' => $amount,
                'discount_amount' => '0.00',
                'net_amount' => $amount,
                'original_due_on' => '2026-11-12',
            ]);

            return [$installment, $allocation];
        };

        [$changedInstallment, $changedAllocation] = $create($user, '30.00');
        [, $stableAllocation] = $create($user, '5.00');
        [, $foreignAllocation] = $create($other, '999.00');
        $fixedPurchase = CardPurchase::factory()->create([
            'user_id' => $user->id,
            'planning_type' => ExpensePlanningType::Fixed,
        ]);
        $observed = CarbonImmutable::parse('2026-09-22T15:00:00Z');
        $query = app(DailyCardAdvanceImpactQuery::class);
        $this->assertSame('35.00', $query->forUserOnDay($user, '2026-09-21', $observed)['ordinary_net_advanced']);

        DB::table('card_installments')->where('id', $changedInstallment->id)->update([
            'card_purchase_id' => $fixedPurchase->id,
            'updated_at' => '2026-09-22 16:00:00',
        ]);

        $historical = $query->forUserOnDay($user, '2026-09-21', $observed);
        $this->assertSame('5.00', $historical['ordinary_net_advanced']);
        $this->assertSame('5.00', $historical['ordinary_future_gross_released']);
        $this->assertSame([$stableAllocation->id], $historical['allocation_ids']);
        $this->assertSame([$changedAllocation->id], $historical['unverifiable_allocation_ids']);
        $this->assertSame('partial_card_advance_timing_unverifiable_edits', $historical['coverage']);

        $current = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T16:00:01Z'));
        $this->assertSame('5.00', $current['ordinary_net_advanced']);
        $this->assertSame([], $current['unverifiable_allocation_ids']);
        $this->assertSame('999.00', $query->forUserOnDay($other, '2026-09-21', $observed)['ordinary_net_advanced']);
        $this->assertNotContains($foreignAllocation->id, $historical['allocation_ids']);
    }
}
