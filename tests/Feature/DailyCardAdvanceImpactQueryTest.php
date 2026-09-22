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
use InvalidArgumentException;
use Tests\TestCase;

class DailyCardAdvanceImpactQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_net_ordinary_advance_and_original_future_release_separately(): void
    {
        $user = User::factory()->create();
        $ordinary = CardPurchase::factory()->create(['user_id' => $user->id, 'planning_type' => ExpensePlanningType::Ordinary]);
        $first = CardInstallment::factory()->create(['user_id' => $user->id, 'card_purchase_id' => $ordinary->id, 'status' => CardInstallmentStatus::Advanced, 'gross_amount' => '100.00', 'due_on' => '2026-11-12']);
        $second = CardInstallment::factory()->create(['user_id' => $user->id, 'card_purchase_id' => $ordinary->id, 'installment_number' => 2, 'status' => CardInstallmentStatus::Advanced, 'gross_amount' => '100.00', 'due_on' => '2026-12-12']);
        $advance = CardAdvance::query()->create([
            'user_id' => $user->id, 'credit_card_id' => $ordinary->credit_card_id,
            'gross_amount' => '200.00', 'discount_amount' => '10.00', 'net_amount' => '190.00',
            'advanced_on' => '2026-09-21', 'selected_installment_ids' => [$first->id, $second->id],
            'operation_id' => (string) Str::uuid(),
        ]);
        $firstAllocation = CardAdvanceAllocation::query()->create([
            'user_id' => $user->id, 'card_advance_id' => $advance->id, 'card_installment_id' => $first->id,
            'gross_amount' => '100.00', 'discount_amount' => '5.00', 'net_amount' => '95.00', 'original_due_on' => '2026-11-12',
        ]);
        $secondAllocation = CardAdvanceAllocation::query()->create([
            'user_id' => $user->id, 'card_advance_id' => $advance->id, 'card_installment_id' => $second->id,
            'gross_amount' => '100.00', 'discount_amount' => '5.00', 'net_amount' => '95.00', 'original_due_on' => '2026-12-12',
        ]);

        $fixed = CardPurchase::factory()->create(['user_id' => $user->id, 'planning_type' => ExpensePlanningType::Fixed]);
        $fixedInstallment = CardInstallment::factory()->create(['user_id' => $user->id, 'card_purchase_id' => $fixed->id, 'status' => CardInstallmentStatus::Advanced, 'due_on' => '2026-11-12']);
        $fixedAdvance = CardAdvance::query()->create([
            'user_id' => $user->id, 'credit_card_id' => $fixed->credit_card_id,
            'gross_amount' => '100.00', 'discount_amount' => '0.00', 'net_amount' => '100.00',
            'advanced_on' => '2026-09-21', 'selected_installment_ids' => [$fixedInstallment->id],
            'operation_id' => (string) Str::uuid(),
        ]);
        CardAdvanceAllocation::query()->create([
            'user_id' => $user->id, 'card_advance_id' => $fixedAdvance->id, 'card_installment_id' => $fixedInstallment->id,
            'gross_amount' => '100.00', 'discount_amount' => '0.00', 'net_amount' => '100.00', 'original_due_on' => '2026-11-12',
        ]);

        $other = User::factory()->create();
        $otherPurchase = CardPurchase::factory()->create(['user_id' => $other->id]);
        $otherInstallment = CardInstallment::factory()->create(['user_id' => $other->id, 'card_purchase_id' => $otherPurchase->id, 'status' => CardInstallmentStatus::Advanced]);
        $otherAdvance = CardAdvance::query()->create([
            'user_id' => $other->id, 'credit_card_id' => $otherPurchase->credit_card_id,
            'gross_amount' => '100.00', 'discount_amount' => '0.00', 'net_amount' => '100.00',
            'advanced_on' => '2026-09-21', 'selected_installment_ids' => [$otherInstallment->id],
            'operation_id' => (string) Str::uuid(),
        ]);
        CardAdvanceAllocation::query()->create([
            'user_id' => $other->id, 'card_advance_id' => $otherAdvance->id, 'card_installment_id' => $otherInstallment->id,
            'gross_amount' => '100.00', 'discount_amount' => '0.00', 'net_amount' => '100.00', 'original_due_on' => '2026-11-12',
        ]);

        $result = app(DailyCardAdvanceImpactQuery::class)->forUserOnDay($user, '2026-09-21');

        $this->assertSame('190.00', $result['ordinary_net_advanced']);
        $this->assertSame('200.00', $result['ordinary_future_gross_released']);
        $this->assertSame([$firstAllocation->id, $secondAllocation->id], $result['allocation_ids']);
        $this->assertSame(0, $result['unclassified_count']);
        $this->assertSame('current_card_advance_timing_only', $result['coverage']);
        $this->assertSame('0.00', app(DailyCardAdvanceImpactQuery::class)->forUserOnDay($user, '2026-09-22')['ordinary_net_advanced']);
    }

    public function test_purchase_reclassification_after_observation_does_not_rewrite_advance_classification(): void
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

            return [$purchase, $allocation];
        };

        [$changedPurchase, $changedAllocation] = $create($user, '30.00');
        [$stablePurchase, $stableAllocation] = $create($user, '5.00');
        [, $foreignAllocation] = $create($other, '999.00');
        $observed = CarbonImmutable::parse('2026-09-22T15:00:00Z');
        $query = app(DailyCardAdvanceImpactQuery::class);
        $this->assertSame('35.00', $query->forUserOnDay($user, '2026-09-21', $observed)['ordinary_net_advanced']);

        DB::table('card_purchases')->where('id', $changedPurchase->id)->update([
            'planning_type' => ExpensePlanningType::Fixed->value,
            'updated_at' => '2026-09-22 16:00:00',
        ]);

        $historical = $query->forUserOnDay($user, '2026-09-21', $observed);
        $this->assertSame('5.00', $historical['ordinary_net_advanced']);
        $this->assertSame('5.00', $historical['ordinary_future_gross_released']);
        $this->assertSame([$stableAllocation->id], $historical['allocation_ids']);
        $this->assertSame([$changedAllocation->id], $historical['unverifiable_allocation_ids']);
        $this->assertSame('partial_card_advance_timing_unverifiable_edits', $historical['coverage']);

        $afterEdit = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T16:00:01Z'));
        $this->assertSame('5.00', $afterEdit['ordinary_net_advanced']);
        $this->assertSame([], $afterEdit['unverifiable_allocation_ids']);
        $this->assertSame('999.00', $query->forUserOnDay($other, '2026-09-21', $observed)['ordinary_net_advanced']);
        $this->assertNotContains($foreignAllocation->id, $historical['allocation_ids']);
        $this->assertSame(ExpensePlanningType::Ordinary, $stablePurchase->planning_type);
    }

    public function test_soft_deleted_purchase_keeps_advance_coverage_explicit(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21T15:00:00Z'));
        $user = User::factory()->create();
        $other = User::factory()->create();
        $create = function (User $owner, string $amount): array {
            $purchase = CardPurchase::factory()->create(['user_id' => $owner->id, 'planning_type' => ExpensePlanningType::Ordinary]);
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

            return [$purchase, $allocation];
        };

        [$deletedPurchase, $deletedAllocation] = $create($user, '30.00');
        [, $stableAllocation] = $create($user, '5.00');
        [, $foreignAllocation] = $create($other, '999.00');
        $query = app(DailyCardAdvanceImpactQuery::class);
        $observed = CarbonImmutable::parse('2026-09-22T15:00:00Z');
        $this->assertSame('35.00', $query->forUserOnDay($user, '2026-09-21', $observed)['ordinary_net_advanced']);

        $this->travelTo(CarbonImmutable::parse('2026-09-22T16:00:00Z'));
        $deletedPurchase->delete();

        $historical = $query->forUserOnDay($user, '2026-09-21', $observed);
        $this->assertSame('5.00', $historical['ordinary_net_advanced']);
        $this->assertSame('5.00', $historical['ordinary_future_gross_released']);
        $this->assertSame([$stableAllocation->id], $historical['allocation_ids']);
        $this->assertSame([$deletedAllocation->id], $historical['unverifiable_allocation_ids']);
        $this->assertSame('partial_card_advance_timing_unverifiable_edits', $historical['coverage']);
        $this->assertNotContains($foreignAllocation->id, $historical['unverifiable_allocation_ids']);
        $this->assertSame('999.00', $query->forUserOnDay($other, '2026-09-21', $observed)['ordinary_net_advanced']);

        $afterDeletion = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T16:00:01Z'));
        $this->assertSame([$deletedAllocation->id], $afterDeletion['unverifiable_allocation_ids']);
        $this->assertSame('5.00', $afterDeletion['ordinary_net_advanced']);
    }

    public function test_rejects_invalid_local_date(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(DailyCardAdvanceImpactQuery::class)->forUserOnDay(User::factory()->create(), '2026-02-30');
    }
}
