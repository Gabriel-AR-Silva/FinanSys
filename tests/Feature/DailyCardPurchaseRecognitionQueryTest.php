<?php

namespace Tests\Feature;

use App\Enums\ExpensePlanningType;
use App\Models\CardPurchase;
use App\Models\CardPurchaseReversal;
use App\Models\User;
use App\Queries\DailyCardPurchaseRecognitionQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        CardPurchase::factory()->create(['user_id' => $other->id, 'purchased_on' => '2026-09-21', 'gross_amount' => '999.00']);
        CardPurchase::factory()->create(['user_id' => $user->id, 'purchased_on' => '2026-09-22', 'gross_amount' => '40.00']);

        $query = app(DailyCardPurchaseRecognitionQuery::class);
        $result = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-23T00:00:00Z'));

        $this->assertSame('1200.00', $result['ordinary_purchase_total']);
        $this->assertSame([$purchase->id], $result['purchase_ids']);
        $this->assertSame(0, $result['unclassified_count']);
        $this->assertSame([], $result['unverifiable_purchase_ids']);
        $this->assertSame('gross_card_purchases_with_recorded_reversals', $result['coverage']);
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

    public function test_reversed_purchase_remains_visible_as_original_gross_before_and_after_reversal(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $purchase = CardPurchase::factory()->create([
            'user_id' => $user->id,
            'purchased_on' => '2026-09-21',
            'gross_amount' => '300.00',
        ]);
        $purchase->timestamps = false;
        $purchase->created_at = '2026-09-21 12:00:00';
        $purchase->updated_at = '2026-09-21 12:00:00';
        $purchase->deleted_at = '2026-09-22 16:00:00';
        $purchase->save();

        $reversal = CardPurchaseReversal::query()->create([
            'user_id' => $user->id,
            'credit_card_id' => $purchase->credit_card_id,
            'card_purchase_id' => $purchase->id,
            'reversed_on' => '2026-09-22',
            'cancelled_pending_amount' => '200.00',
            'credited_paid_amount' => '100.00',
            'operation_id' => (string) Str::uuid(),
        ]);
        $reversal->timestamps = false;
        $reversal->created_at = '2026-09-22 16:00:00';
        $reversal->save();
        CardPurchase::factory()->create(['user_id' => $other->id, 'purchased_on' => '2026-09-21', 'gross_amount' => '900.00']);

        $query = app(DailyCardPurchaseRecognitionQuery::class);
        $before = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T15:00:00Z'));
        $after = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T17:00:00Z'));

        $this->assertSame('300.00', $before['ordinary_purchase_total']);
        $this->assertSame([$purchase->id], $before['purchase_ids']);
        $this->assertSame('300.00', $after['ordinary_purchase_total']);
        $this->assertSame([$purchase->id], $after['purchase_ids']);
    }

    public function test_later_edit_is_reported_as_unverifiable_instead_of_rewriting_an_earlier_observation(): void
    {
        $user = User::factory()->create();
        $stable = CardPurchase::factory()->create(['user_id' => $user->id, 'purchased_on' => '2026-09-21', 'gross_amount' => '25.00']);
        $stable->timestamps = false;
        $stable->created_at = '2026-09-21 12:00:00';
        $stable->updated_at = '2026-09-21 12:00:00';
        $stable->save();
        $changed = CardPurchase::factory()->create([
            'user_id' => $user->id,
            'purchased_on' => '2026-09-21',
            'gross_amount' => '70.00',
            'planning_type' => ExpensePlanningType::Fixed,
        ]);
        $changed->timestamps = false;
        $changed->created_at = '2026-09-21 12:00:00';
        $changed->updated_at = '2026-09-23 10:00:00';
        $changed->save();

        $query = app(DailyCardPurchaseRecognitionQuery::class);
        $before = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T17:00:00Z'));
        $after = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-23T11:00:00Z'));

        $this->assertSame('25.00', $before['ordinary_purchase_total']);
        $this->assertSame([$stable->id], $before['purchase_ids']);
        $this->assertSame([$changed->id], $before['unverifiable_purchase_ids']);
        $this->assertSame('partial_gross_card_purchases_unverifiable_edits', $before['coverage']);
        $this->assertSame('25.00', $after['ordinary_purchase_total']);
        $this->assertSame([], $after['unverifiable_purchase_ids']);
        $this->assertSame('gross_card_purchases_with_recorded_reversals', $after['coverage']);
    }

    public function test_purchase_moved_to_another_day_does_not_silently_clear_original_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-22T16:00:00Z'));
        $user = User::factory()->create();
        $other = User::factory()->create();
        $stable = CardPurchase::factory()->create([
            'user_id' => $user->id,
            'purchased_on' => '2026-09-21',
            'gross_amount' => '15.00',
            'planning_type' => ExpensePlanningType::Ordinary,
        ]);
        $moved = CardPurchase::factory()->create([
            'user_id' => $user->id,
            'purchased_on' => '2026-09-21',
            'gross_amount' => '30.00',
            'planning_type' => ExpensePlanningType::Ordinary,
        ]);
        $foreign = CardPurchase::factory()->create([
            'user_id' => $other->id,
            'purchased_on' => '2026-09-21',
            'gross_amount' => '900.00',
        ]);
        DB::table('card_purchases')->where('id', $moved->id)->update([
            'purchased_on' => '2026-09-22',
            'gross_amount' => '300.00',
            'updated_at' => '2026-09-23 10:00:00',
        ]);
        DB::table('card_purchases')->where('id', $foreign->id)->update([
            'purchased_on' => '2026-09-22',
            'updated_at' => '2026-09-23 10:00:00',
        ]);

        $query = app(DailyCardPurchaseRecognitionQuery::class);
        $observed = CarbonImmutable::parse('2026-09-22T16:00:01Z');
        $original = $query->forUserOnDay($user, '2026-09-21', $observed);
        $newDay = $query->forUserOnDay($user, '2026-09-22', $observed);
        $afterEdit = $query->forUserOnDay($user, '2026-09-22', CarbonImmutable::parse('2026-09-23T11:00:00Z'));

        $this->assertSame('15.00', $original['ordinary_purchase_total']);
        $this->assertSame([$stable->id], $original['purchase_ids']);
        $this->assertSame([$moved->id], $original['unverifiable_purchase_ids']);
        $this->assertSame('partial_gross_card_purchases_unverifiable_edits', $original['coverage']);
        $this->assertSame('0.00', $newDay['ordinary_purchase_total']);
        $this->assertSame([$moved->id], $newDay['unverifiable_purchase_ids']);
        $this->assertSame('300.00', $afterEdit['ordinary_purchase_total']);
        $this->assertSame([$moved->id], $afterEdit['purchase_ids']);
        $this->assertSame([], $afterEdit['unverifiable_purchase_ids']);
    }

    public function test_ordinary_soft_deletion_is_visible_before_but_not_after_deletion(): void
    {
        $user = User::factory()->create();
        $purchase = CardPurchase::factory()->create(['user_id' => $user->id, 'purchased_on' => '2026-09-21', 'gross_amount' => '70.00']);
        $purchase->timestamps = false;
        $purchase->created_at = '2026-09-21 12:00:00';
        $purchase->updated_at = '2026-09-21 12:00:00';
        $purchase->deleted_at = '2026-09-22 16:00:00';
        $purchase->save();

        $query = app(DailyCardPurchaseRecognitionQuery::class);
        $this->assertSame('70.00', $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T15:00:00Z'))['ordinary_purchase_total']);
        $this->assertSame('0.00', $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T17:00:00Z'))['ordinary_purchase_total']);
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
