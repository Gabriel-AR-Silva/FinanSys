<?php

namespace Tests\Feature;

use App\Models\CardPurchase;
use App\Models\CardPurchaseReversal;
use App\Models\User;
use App\Queries\DailyCardPurchaseReversalQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DailyCardReversalProvenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reversal_totals_reject_a_different_card_and_accept_deleted_original(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21T15:00:00Z'));
        $user = User::factory()->create();
        $other = User::factory()->create();
        $validPurchase = CardPurchase::factory()->create(['user_id' => $user->id]);
        $mismatchedPurchase = CardPurchase::factory()->create(['user_id' => $user->id]);
        $otherCardPurchase = CardPurchase::factory()->create(['user_id' => $user->id]);
        $foreignPurchase = CardPurchase::factory()->create(['user_id' => $other->id]);

        $valid = $this->reversal($user, $validPurchase, $validPurchase->credit_card_id, '20.00', '10.00');
        $mismatched = $this->reversal($user, $mismatchedPurchase, $otherCardPurchase->credit_card_id, '888.00', '888.00');
        $validPurchase->delete();

        $result = app(DailyCardPurchaseReversalQuery::class)->forUserOnDay(
            $user,
            '2026-09-21',
            CarbonImmutable::parse('2026-09-21T15:00:01Z'),
        );

        $this->assertSame('20.00', $result['cancelled_pending_total']);
        $this->assertSame('10.00', $result['credited_paid_total']);
        $this->assertSame([$valid->id], $result['reversal_ids']);
        $this->assertSame([$mismatched->id], $result['unverifiable_reversal_ids']);
        $this->assertSame('partial_card_purchase_reversal_events', $result['coverage']);
        $this->assertSame('0.00', app(DailyCardPurchaseReversalQuery::class)->forUserOnDay(
            $other,
            '2026-09-21',
            CarbonImmutable::parse('2026-09-21T15:00:01Z'),
        )['cancelled_pending_total']);
        $this->assertNotSame($foreignPurchase->user_id, $user->id);
    }

    public function test_composite_foreign_key_rejects_a_reversal_of_another_users_purchase(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $ownCardPurchase = CardPurchase::factory()->create(['user_id' => $user->id]);
        $foreignPurchase = CardPurchase::factory()->create(['user_id' => $other->id]);

        $this->expectException(QueryException::class);
        $this->reversal($user, $foreignPurchase, $ownCardPurchase->credit_card_id, '999.00', '999.00');
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
