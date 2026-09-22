<?php

namespace Tests\Feature;

use App\Enums\ExpensePlanningType;
use App\Models\CardPurchase;
use App\Models\User;
use App\Queries\DailyCardPurchaseRecognitionQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class DailyCardPurchaseRecognitionQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_purchase_principal_only_on_purchase_day_and_excludes_other_users_and_types(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $purchase = CardPurchase::factory()->create([
            'user_id' => $user->id,
            'gross_amount' => '1200.00',
            'installments_count' => 6,
            'purchased_on' => '2026-09-21',
            'planning_type' => ExpensePlanningType::Ordinary,
        ]);
        CardPurchase::factory()->create(['user_id' => $user->id, 'purchased_on' => '2026-09-21', 'planning_type' => ExpensePlanningType::Fixed]);
        CardPurchase::factory()->create(['user_id' => $user->id, 'purchased_on' => '2026-09-21', 'planning_type' => ExpensePlanningType::Extraordinary]);
        CardPurchase::factory()->create(['user_id' => $user->id, 'purchased_on' => '2026-09-21', 'planning_type' => null]);
        CardPurchase::factory()->create(['user_id' => $other->id, 'purchased_on' => '2026-09-21', 'gross_amount' => '999.00']);
        CardPurchase::factory()->create(['user_id' => $user->id, 'purchased_on' => '2026-09-22', 'gross_amount' => '40.00']);

        $query = app(DailyCardPurchaseRecognitionQuery::class);
        $result = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-23T00:00:00Z'));

        $this->assertSame('1200.00', $result['ordinary_purchase_total']);
        $this->assertSame([$purchase->id], $result['purchase_ids']);
        $this->assertSame(1, $result['unclassified_count']);
        $this->assertSame('current_card_purchases_only', $result['coverage']);
        $this->assertSame('40.00', $query->forUserOnDay($user, '2026-09-22', CarbonImmutable::parse('2026-09-23T00:00:00Z'))['ordinary_purchase_total']);
    }

    public function test_late_recorded_purchase_is_not_visible_before_it_is_registered(): void
    {
        $user = User::factory()->create();
        $purchase = CardPurchase::factory()->create(['user_id' => $user->id, 'purchased_on' => '2026-09-21', 'gross_amount' => '80.00']);
        $purchase->timestamps = false;
        $purchase->created_at = '2026-09-23 10:00:00';
        $purchase->save();

        $query = app(DailyCardPurchaseRecognitionQuery::class);
        $before = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T22:00:00Z'));
        $after = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-23T11:00:00Z'));

        $this->assertSame('0.00', $before['ordinary_purchase_total']);
        $this->assertSame([], $before['purchase_ids']);
        $this->assertSame('80.00', $after['ordinary_purchase_total']);
        $this->assertSame([$purchase->id], $after['purchase_ids']);
    }

    public function test_soft_deleted_purchase_is_excluded_from_current_state(): void
    {
        $user = User::factory()->create();
        $purchase = CardPurchase::factory()->create(['user_id' => $user->id, 'purchased_on' => '2026-09-21']);
        $purchase->delete();

        $this->assertSame('0.00', app(DailyCardPurchaseRecognitionQuery::class)->forUserOnDay($user, '2026-09-21')['ordinary_purchase_total']);
    }

    public function test_rejects_invalid_day_and_observation_before_local_midnight(): void
    {
        $query = app(DailyCardPurchaseRecognitionQuery::class);
        $user = User::factory()->create();
        try {
            $query->forUserOnDay($user, '2026-02-30');
            $this->fail('Invalid date accepted.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);
        $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-21T02:59:59Z'));
    }
}
