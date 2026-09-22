<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\ExpensePlanningType;
use App\Models\CardPurchase;
use App\Models\User;
use App\Queries\DailyCardPurchaseAuditStateQuery;
use App\Support\AuditRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyCardPurchaseUnauditedEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_unaudited_purchase_edit_invalidates_only_later_observations_for_its_owner(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21T15:00:00Z'));
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $purchase = CardPurchase::factory()->create([
            'user_id' => $owner->id,
            'purchased_on' => '2026-09-21',
            'gross_amount' => '80.00',
            'planning_type' => ExpensePlanningType::Ordinary,
        ]);
        app(AuditRecorder::class)->record($owner, AuditAction::Created, $purchase);
        $otherPurchase = CardPurchase::factory()->create([
            'user_id' => $other->id,
            'purchased_on' => '2026-09-21',
            'gross_amount' => '30.00',
            'planning_type' => ExpensePlanningType::Ordinary,
        ]);
        app(AuditRecorder::class)->record($other, AuditAction::Created, $otherPurchase);

        $this->travelTo(CarbonImmutable::parse('2026-09-22T16:00:00Z'));
        // A legacy writer bypasses AuditRecorder while moving and resizing a purchase.
        $purchase->update(['purchased_on' => '2026-09-22', 'gross_amount' => '180.00']);

        $query = app(DailyCardPurchaseAuditStateQuery::class);
        $before = $query->forUserOnDay($owner, '2026-09-21', CarbonImmutable::parse('2026-09-21T15:00:01Z'));
        $after = $query->forUserOnDay($owner, '2026-09-21', CarbonImmutable::parse('2026-09-22T16:00:01Z'));
        $movedDay = $query->forUserOnDay($owner, '2026-09-22', CarbonImmutable::parse('2026-09-22T16:00:01Z'));
        $otherResult = $query->forUserOnDay($other, '2026-09-21', CarbonImmutable::parse('2026-09-22T16:00:01Z'));

        $this->assertSame('80.00', $before['ordinary_audited_total']);
        $this->assertSame([$purchase->id], $before['purchase_ids']);
        $this->assertSame([], $before['unverifiable_purchase_ids']);
        $this->assertSame('0.00', $after['ordinary_audited_total']);
        $this->assertSame([], $after['purchase_ids']);
        $this->assertSame([$purchase->id], $after['unverifiable_purchase_ids']);
        $this->assertSame('partial_audited_purchase_states', $after['coverage']);
        $this->assertSame([$purchase->id], $movedDay['unverifiable_purchase_ids']);
        $this->assertSame('30.00', $otherResult['ordinary_audited_total']);
        $this->assertSame([], $otherResult['unverifiable_purchase_ids']);
    }
}
