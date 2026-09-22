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
use Tests\TestCase;

class DailyCardReversalMovedDateCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_moved_reversal_date_is_unverifiable_on_both_days_before_edit_and_current_day_remains_separate(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21T15:00:00Z'));
        $user = User::factory()->create();
        $other = User::factory()->create();

        $moved = $this->reversal(CardPurchase::factory()->create(['user_id' => $user->id]), '2026-09-21', '30.00', '10.00');
        $stable = $this->reversal(CardPurchase::factory()->create(['user_id' => $user->id]), '2026-09-21', '5.00', '2.00');
        $foreign = $this->reversal(CardPurchase::factory()->create(['user_id' => $other->id]), '2026-09-22', '999.00', '999.00');

        $query = app(DailyCardPurchaseReversalQuery::class);
        $beforeEdit = CarbonImmutable::parse('2026-09-22T15:00:00Z');
        $original = $query->forUserOnDay($user, '2026-09-21', $beforeEdit);
        $this->assertSame('35.00', $original['cancelled_pending_total']);
        $this->assertSame('12.00', $original['credited_paid_total']);
        $this->assertSame([$moved->id, $stable->id], $original['reversal_ids']);

        // Legacy write: the current date is not a reliable historical date.
        DB::table('card_purchase_reversals')->where('id', $moved->id)->update([
            'reversed_on' => '2026-09-22',
            'updated_at' => '2026-09-22 16:00:00',
        ]);

        $oldDay = $query->forUserOnDay($user, '2026-09-21', $beforeEdit);
        $this->assertSame('5.00', $oldDay['cancelled_pending_total']);
        $this->assertSame('2.00', $oldDay['credited_paid_total']);
        $this->assertSame([$stable->id], $oldDay['reversal_ids']);
        $this->assertSame([$moved->id], $oldDay['unverifiable_reversal_ids']);
        $this->assertSame('partial_card_purchase_reversal_events', $oldDay['coverage']);

        $newDay = $query->forUserOnDay($user, '2026-09-22', $beforeEdit);
        $this->assertSame('0.00', $newDay['cancelled_pending_total']);
        $this->assertSame('0.00', $newDay['credited_paid_total']);
        $this->assertSame([$moved->id], $newDay['unverifiable_reversal_ids']);
        $this->assertSame('partial_card_purchase_reversal_events', $newDay['coverage']);

        $afterEdit = $query->forUserOnDay($user, '2026-09-22', CarbonImmutable::parse('2026-09-22T16:00:01Z'));
        $this->assertSame('30.00', $afterEdit['cancelled_pending_total']);
        $this->assertSame('10.00', $afterEdit['credited_paid_total']);
        $this->assertSame([$moved->id], $afterEdit['reversal_ids']);
        $this->assertSame([], $afterEdit['unverifiable_reversal_ids']);
        $this->assertSame('card_purchase_reversal_events_only', $afterEdit['coverage']);

        $otherResult = $query->forUserOnDay($other, '2026-09-22', $beforeEdit);
        $this->assertSame('999.00', $otherResult['cancelled_pending_total']);
        $this->assertSame([$foreign->id], $otherResult['reversal_ids']);
    }

    private function reversal(CardPurchase $purchase, string $day, string $cancelled, string $credited): CardPurchaseReversal
    {
        return CardPurchaseReversal::query()->create([
            'user_id' => $purchase->user_id,
            'credit_card_id' => $purchase->credit_card_id,
            'card_purchase_id' => $purchase->id,
            'reversed_on' => $day,
            'cancelled_pending_amount' => $cancelled,
            'credited_paid_amount' => $credited,
            'operation_id' => (string) Str::uuid(),
        ]);
    }
}
