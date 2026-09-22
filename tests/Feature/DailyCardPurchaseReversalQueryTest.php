<?php

namespace Tests\Feature;

use App\Models\CardPurchase;
use App\Models\CardPurchaseReversal;
use App\Models\User;
use App\Queries\DailyCardPurchaseReversalQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class DailyCardPurchaseReversalQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_distinguishes_cancelled_obligations_from_credited_payments_and_excludes_later_records(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21T16:00:00Z'));
        $user = User::factory()->create();
        $other = User::factory()->create();
        $earlyPurchase = CardPurchase::factory()->create(['user_id' => $user->id]);
        $latePurchase = CardPurchase::factory()->create(['user_id' => $user->id]);
        $otherPurchase = CardPurchase::factory()->create(['user_id' => $other->id]);

        $early = $this->reversal($earlyPurchase, '2026-09-21', '200.00', '100.00');
        $late = $this->reversal($latePurchase, '2026-09-21', '50.00', '25.00');
        $this->reversal($otherPurchase, '2026-09-21', '999.00', '999.00');
        $this->reversal(CardPurchase::factory()->create(['user_id' => $user->id]), '2026-09-22', '900.00', '900.00');

        $early->timestamps = false;
        $early->created_at = '2026-09-21 17:00:00';
        $early->save();
        $late->timestamps = false;
        $late->created_at = '2026-09-23 10:00:00';
        $late->updated_at = '2026-09-23 10:00:00';
        $late->save();

        $query = app(DailyCardPurchaseReversalQuery::class);
        $before = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T22:00:00Z'));
        $after = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-23T11:00:00Z'));

        $this->assertSame('200.00', $before['cancelled_pending_total']);
        $this->assertSame('100.00', $before['credited_paid_total']);
        $this->assertSame([$early->id], $before['reversal_ids']);
        $this->assertSame('250.00', $after['cancelled_pending_total']);
        $this->assertSame('125.00', $after['credited_paid_total']);
        $this->assertSame([$early->id, $late->id], $after['reversal_ids']);
        $this->assertSame('card_purchase_reversal_events_only', $after['coverage']);
    }

    public function test_later_edit_marks_historical_reversal_unverifiable_without_affecting_other_events_or_users(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21T15:00:00Z'));
        $user = User::factory()->create();
        $other = User::factory()->create();
        $edited = $this->reversal(CardPurchase::factory()->create(['user_id' => $user->id]), '2026-09-21', '30.00', '10.00');
        $stable = $this->reversal(CardPurchase::factory()->create(['user_id' => $user->id]), '2026-09-21', '5.00', '2.00');
        $foreign = $this->reversal(CardPurchase::factory()->create(['user_id' => $other->id]), '2026-09-21', '999.00', '999.00');

        $query = app(DailyCardPurchaseReversalQuery::class);
        $observedBeforeEdit = CarbonImmutable::parse('2026-09-21T15:00:30Z');
        $original = $query->forUserOnDay($user, '2026-09-21', $observedBeforeEdit);
        $this->assertSame('35.00', $original['cancelled_pending_total']);
        $this->assertSame('12.00', $original['credited_paid_total']);
        $this->assertSame([$edited->id, $stable->id], $original['reversal_ids']);

        // Simulate a legacy write with a deterministic UTC timestamp. Reversal
        // edits have no versioned amounts to reconstruct for an earlier view.
        DB::table('card_purchase_reversals')->where('id', $edited->id)->update([
            'cancelled_pending_amount' => '80.00',
            'credited_paid_amount' => '40.00',
            'updated_at' => '2026-09-21 15:02:00',
        ]);

        $historical = $query->forUserOnDay($user, '2026-09-21', $observedBeforeEdit);
        $this->assertSame('5.00', $historical['cancelled_pending_total']);
        $this->assertSame('2.00', $historical['credited_paid_total']);
        $this->assertSame([$stable->id], $historical['reversal_ids']);
        $this->assertSame([$edited->id], $historical['unverifiable_reversal_ids']);
        $this->assertSame('partial_card_purchase_reversal_events', $historical['coverage']);

        $current = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-21T15:02:01Z'));
        $this->assertSame('85.00', $current['cancelled_pending_total']);
        $this->assertSame('42.00', $current['credited_paid_total']);
        $this->assertSame('card_purchase_reversal_events_only', $current['coverage']);
        $this->assertSame('999.00', $query->forUserOnDay($other, '2026-09-21', $observedBeforeEdit)['credited_paid_total']);
        $this->assertNotContains($foreign->id, $historical['reversal_ids']);
    }

    public function test_rejects_an_invalid_local_day_and_observation_before_midnight(): void
    {
        $query = app(DailyCardPurchaseReversalQuery::class);
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

    private function reversal(CardPurchase $purchase, string $reversedOn, string $cancelled, string $credited): CardPurchaseReversal
    {
        return CardPurchaseReversal::query()->create([
            'user_id' => $purchase->user_id,
            'credit_card_id' => $purchase->credit_card_id,
            'card_purchase_id' => $purchase->id,
            'reversed_on' => $reversedOn,
            'cancelled_pending_amount' => $cancelled,
            'credited_paid_amount' => $credited,
            'operation_id' => (string) Str::uuid(),
        ]);
    }
}
