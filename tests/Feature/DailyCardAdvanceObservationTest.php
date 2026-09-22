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
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class DailyCardAdvanceObservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_later_recorded_advance_or_allocation_does_not_change_an_earlier_read(): void
    {
        $user = User::factory()->create();
        $purchase = CardPurchase::factory()->create([
            'user_id' => $user->id,
            'planning_type' => ExpensePlanningType::Ordinary,
        ]);
        $first = CardInstallment::factory()->create([
            'user_id' => $user->id,
            'card_purchase_id' => $purchase->id,
            'status' => CardInstallmentStatus::Advanced,
            'gross_amount' => '100.00',
            'due_on' => '2026-11-12',
        ]);
        $second = CardInstallment::factory()->create([
            'user_id' => $user->id,
            'card_purchase_id' => $purchase->id,
            'installment_number' => 2,
            'status' => CardInstallmentStatus::Advanced,
            'gross_amount' => '100.00',
            'due_on' => '2026-12-12',
        ]);

        $lateAdvance = CardAdvance::query()->create([
            'user_id' => $user->id,
            'credit_card_id' => $purchase->credit_card_id,
            'gross_amount' => '100.00',
            'discount_amount' => '5.00',
            'net_amount' => '95.00',
            'advanced_on' => '2026-09-21',
            'selected_installment_ids' => [$first->id],
            'operation_id' => (string) Str::uuid(),
        ]);
        $lateAdvance->forceFill(['created_at' => '2026-09-23 10:00:00'])->save();
        $firstAllocation = CardAdvanceAllocation::query()->create([
            'user_id' => $user->id,
            'card_advance_id' => $lateAdvance->id,
            'card_installment_id' => $first->id,
            'gross_amount' => '100.00',
            'discount_amount' => '5.00',
            'net_amount' => '95.00',
            'original_due_on' => '2026-11-12',
        ]);
        $firstAllocation->forceFill(['created_at' => '2026-09-21 17:00:00'])->save();

        $earlyAdvance = CardAdvance::query()->create([
            'user_id' => $user->id,
            'credit_card_id' => $purchase->credit_card_id,
            'gross_amount' => '100.00',
            'discount_amount' => '5.00',
            'net_amount' => '95.00',
            'advanced_on' => '2026-09-21',
            'selected_installment_ids' => [$second->id],
            'operation_id' => (string) Str::uuid(),
        ]);
        $earlyAdvance->forceFill(['created_at' => '2026-09-21 17:00:00'])->save();
        $secondAllocation = CardAdvanceAllocation::query()->create([
            'user_id' => $user->id,
            'card_advance_id' => $earlyAdvance->id,
            'card_installment_id' => $second->id,
            'gross_amount' => '100.00',
            'discount_amount' => '5.00',
            'net_amount' => '95.00',
            'original_due_on' => '2026-12-12',
        ]);
        $secondAllocation->forceFill(['created_at' => '2026-09-23 10:00:00'])->save();

        $query = app(DailyCardAdvanceImpactQuery::class);
        $before = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T22:00:00Z'));
        $after = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-23T11:00:00Z'));

        $this->assertSame('0.00', $before['ordinary_net_advanced']);
        $this->assertSame([], $before['allocation_ids']);
        $this->assertSame('190.00', $after['ordinary_net_advanced']);
        $this->assertSame('200.00', $after['ordinary_future_gross_released']);
        $this->assertSame([$firstAllocation->id, $secondAllocation->id], $after['allocation_ids']);
    }

    public function test_rejects_observation_before_the_local_day(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(DailyCardAdvanceImpactQuery::class)->forUserOnDay(
            User::factory()->create(),
            '2026-09-21',
            CarbonImmutable::parse('2026-09-21T02:59:59Z'),
        );
    }
}
