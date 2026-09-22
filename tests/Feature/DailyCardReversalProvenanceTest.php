<?php

namespace Tests\Feature;

use App\Models\CardPurchase;
use App\Models\CardPurchaseReversal;
use App\Models\User;
use App\Queries\DailyCardPurchaseReversalQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DailyCardReversalProvenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reversal_totals_exclude_inconsistent_purchase_origin_and_accept_deleted_original(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21T15:00:00Z'));
        $user = User::factory()->create();
        $other = User::factory()->create();
        $validPurchase = CardPurchase::factory()->create(['user_id' => $user->id]);
        $mismatchedPurchase = CardPurchase::factory()->create(['user_id' => $user->id]);
        $foreignPurchase = CardPurchase::factory()->create(['user_id' => $other->id]);

        $valid = $this->reversal($user, $validPurchase, $validPurchase->credit_card_id, '20.00', '10.00');
        $foreign = $this->reversal($user, $foreignPurchase, $foreignPurchase->credit_card_id, '999.00', '999.00');
        $mismatched = $this->reversal($user, $mismatchedPurchase, $foreignPurchase->credit_card_id, '888.00', '888.00');
        $validPurchase->delete();

        $result = app(DailyCardPurchaseReversalQuery::class)->forUserOnDay(
            $user,
            '2026-09-21',
            CarbonImmutable::parse('2026-09-21T15:00:01Z'),
        );

        $this->assertSame('20.00', $result['cancelled_pending_total']);
        $this->assertSame('10.00', $result['credited_paid_total']);
        $this->assertSame([$valid->id], $result['reversal_ids']);
        $this->assertSame([$foreign->id, $mismatched->id], $result['unverifiable_reversal_ids']);
        $this->assertSame('partial_card_purchase_reversal_events', $result['coverage']);
        $this->assertSame('0.00', app(DailyCardPurchaseReversalQuery::class)->forUserOnDay(
            $other,
            '2026-09-21',
            CarbonImmutable::parse('2026-09-21T15:00:01Z'),
        )['cancelled_pending_total']);
    }

    private function reversal(User $user, CardPurchase $purchase, int $cardId, string $cancelled, string $credited): CardPurchaseReversal
    {
        return CardPurchaseReversal::query()->create([
            'user_id' => $user->id,
            'credit_card_id' => $cardId,
            'card_purchase_id' => $purchase->id,
            'reversed_on' => '2026-09-21',
            'cancelled_pending_amount' => $cancelled,
            'credited_paid_amount' => $credited,
            'operation_id' => (string) Str::uuid(),
        ]);
    }
}
