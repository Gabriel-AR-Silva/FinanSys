<?php

namespace Tests\Feature;

use App\Models\CardPurchase;
use App\Models\CardPurchaseReversal;
use App\Models\User;
use App\Queries\DailyCardPurchaseReversalQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class DailyCardPurchaseReversalQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_distinguishes_cancelled_obligations_from_credited_payments_and_excludes_later_records(): void
    {
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
