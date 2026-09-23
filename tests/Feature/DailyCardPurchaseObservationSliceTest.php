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
use Tests\TestCase;

class DailyCardPurchaseObservationSliceTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_local_midnight_write_is_visible_without_counting_other_users_or_other_dates(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-22T02:59:59Z'));
        $user = User::factory()->create();
        $foreign = User::factory()->create();
        $purchase = CardPurchase::factory()->create([
            'user_id' => $user->id,
            'gross_amount' => '1200.00',
            'installments_count' => 6,
            'purchased_on' => '2026-09-21',
            'planning_type' => ExpensePlanningType::Ordinary,
        ]);
        CardPurchase::factory()->create(['user_id' => $foreign->id, 'gross_amount' => '999.00', 'purchased_on' => '2026-09-21']);
        CardPurchase::factory()->create(['user_id' => $user->id, 'gross_amount' => '40.00', 'purchased_on' => '2026-09-22']);
        CardPurchase::factory()->create(['user_id' => $user->id, 'gross_amount' => '10.00', 'purchased_on' => '2026-09-21', 'planning_type' => ExpensePlanningType::Extraordinary]);

        $this->assertSame('2026-09-21 23:59:59', DB::table('card_purchases')->where('id', $purchase->id)->value('created_at'));
        $result = app(DailyCardPurchaseRecognitionQuery::class)->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T02:59:59Z'));

        $this->assertSame('1200.00', $result['ordinary_purchase_total']);
        $this->assertSame([$purchase->id], $result['purchase_ids']);
        $this->assertSame([], $result['reversed_purchase_ids']);
        $this->assertSame('gross_card_purchases_only', $result['coverage']);
    }

    public function test_full_reversal_is_flagged_but_original_purchase_is_not_automatically_counted_twice(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21T18:00:00Z'));
        $user = User::factory()->create();
        $purchase = CardPurchase::factory()->create([
            'user_id' => $user->id,
            'gross_amount' => '300.00',
            'purchased_on' => '2026-09-21',
            'planning_type' => ExpensePlanningType::Ordinary,
        ]);
        $before = app(DailyCardPurchaseRecognitionQuery::class)->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-21T19:00:00Z'));
        $this->assertSame([], $before['reversed_purchase_ids']);

        $this->travelTo(CarbonImmutable::parse('2026-09-22T18:00:00Z'));
        CardPurchaseReversal::query()->create([
            'user_id' => $user->id,
            'credit_card_id' => $purchase->credit_card_id,
            'card_purchase_id' => $purchase->id,
            'reversed_on' => '2026-09-22',
            'cancelled_pending_amount' => '300.00',
            'credited_paid_amount' => '0.00',
            'operation_id' => (string) Str::uuid(),
        ]);
        $purchase->delete();
        $after = app(DailyCardPurchaseRecognitionQuery::class)->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T19:00:00Z'));

        $this->assertSame('300.00', $after['ordinary_purchase_total']);
        $this->assertSame([$purchase->id], $after['reversed_purchase_ids']);
        $this->assertSame([$purchase->id], $after['purchase_ids']);
    }

    public function test_late_update_marks_prior_purchase_as_unverifiable_instead_of_reconstructing_old_amount(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21T18:00:00Z'));
        $user = User::factory()->create();
        $purchase = CardPurchase::factory()->create(['user_id' => $user->id, 'purchased_on' => '2026-09-21', 'gross_amount' => '30.00']);
        DB::table('card_purchases')->where('id', $purchase->id)->update(['purchased_on' => '2026-09-22', 'updated_at' => '2026-09-23 10:00:00']);

        $result = app(DailyCardPurchaseRecognitionQuery::class)->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T18:00:00Z'));
        $this->assertSame('0.00', $result['ordinary_purchase_total']);
        $this->assertSame([$purchase->id], $result['unverifiable_purchase_ids']);
        $this->assertSame('partial_card_purchase_unverifiable_edits', $result['coverage']);
    }
}
