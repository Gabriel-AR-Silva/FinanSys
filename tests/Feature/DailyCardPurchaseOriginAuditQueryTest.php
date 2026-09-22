<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\ExpensePlanningType;
use App\Models\AuditLog;
use App\Models\CardPurchase;
use App\Models\User;
use App\Queries\DailyCardPurchaseOriginAuditQuery;
use App\Support\AuditRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class DailyCardPurchaseOriginAuditQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_original_purchase_date_and_amount_survive_later_edits_to_current_record(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $purchase = CardPurchase::factory()->create([
            'user_id' => $user->id,
            'purchased_on' => '2026-09-21',
            'gross_amount' => '120.00',
            'planning_type' => ExpensePlanningType::Ordinary,
        ]);
        app(AuditRecorder::class)->record($user, AuditAction::Created, $purchase);
        $purchase->update([
            'purchased_on' => '2026-09-22',
            'gross_amount' => '180.00',
            'planning_type' => ExpensePlanningType::Extraordinary,
        ]);

        $anotherPurchase = CardPurchase::factory()->create([
            'user_id' => $other->id,
            'purchased_on' => '2026-09-21',
            'gross_amount' => '999.00',
        ]);
        app(AuditRecorder::class)->record($other, AuditAction::Created, $anotherPurchase);

        $query = app(DailyCardPurchaseOriginAuditQuery::class);
        $result = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::now('UTC')->addMinute());

        $this->assertSame('120.00', $result['ordinary_original_total']);
        $this->assertSame([$purchase->id], $result['purchase_ids']);
        $this->assertSame([], $result['unverifiable_audit_ids']);
        $this->assertSame('audited_card_purchase_creations_only', $result['coverage']);
        $this->assertSame('0.00', $query->forUserOnDay($user, '2026-09-22', CarbonImmutable::now('UTC')->addMinute())['ordinary_original_total']);
    }

    public function test_future_recorded_audit_is_not_backdated_and_nonordinary_is_not_added(): void
    {
        $user = User::factory()->create();
        $purchase = CardPurchase::factory()->create(['user_id' => $user->id, 'purchased_on' => '2026-09-21']);
        $audit = app(AuditRecorder::class)->record($user, AuditAction::Created, $purchase);
        AuditLog::query()->whereKey($audit->id)->update(['created_at' => '2026-09-23 10:00:00']);

        $query = app(DailyCardPurchaseOriginAuditQuery::class);
        $before = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T22:00:00Z'));
        $after = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-23T11:00:00Z'));

        $this->assertSame('0.00', $before['ordinary_original_total']);
        $this->assertSame($purchase->gross_amount, $after['ordinary_original_total']);
    }

    public function test_incomplete_snapshot_is_flagged_instead_of_inventing_amount(): void
    {
        $user = User::factory()->create();
        $audit = AuditLog::query()->create([
            'user_id' => $user->id,
            'action' => AuditAction::Created->value,
            'auditable_type' => (new CardPurchase)->getMorphClass(),
            'auditable_id' => 100,
            'after' => ['purchased_on' => '2026-09-21', 'id' => 100, 'user_id' => $user->id],
            'created_at' => '2026-09-22 10:00:00',
        ]);

        $result = app(DailyCardPurchaseOriginAuditQuery::class)->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T11:00:00Z'));
        $this->assertSame('0.00', $result['ordinary_original_total']);
        $this->assertSame([$audit->id], $result['unverifiable_audit_ids']);
        $this->assertSame('partial_audited_card_purchase_creations', $result['coverage']);
    }

    public function test_duplicate_creation_audits_are_unverifiable_without_double_counting_or_cross_tenant_leakage(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $purchase = CardPurchase::factory()->create([
            'user_id' => $user->id,
            'purchased_on' => '2026-09-21',
            'gross_amount' => '120.00',
        ]);
        $recorder = app(AuditRecorder::class);
        $first = $recorder->record($user, AuditAction::Created, $purchase);
        $second = $recorder->record($user, AuditAction::Created, $purchase);
        $otherPurchase = CardPurchase::factory()->create([
            'user_id' => $other->id,
            'purchased_on' => '2026-09-21',
            'gross_amount' => '50.00',
        ]);
        $recorder->record($other, AuditAction::Created, $otherPurchase);
        $query = app(DailyCardPurchaseOriginAuditQuery::class);
        $observed = CarbonImmutable::now('UTC')->addMinute();

        $result = $query->forUserOnDay($user, '2026-09-21', $observed);
        $this->assertSame('0.00', $result['ordinary_original_total']);
        $this->assertSame([], $result['purchase_ids']);
        $this->assertSame([$first->id, $second->id], $result['unverifiable_audit_ids']);
        $this->assertSame('partial_audited_card_purchase_creations', $result['coverage']);
        $this->assertSame('50.00', $query->forUserOnDay($other, '2026-09-21', $observed)['ordinary_original_total']);
    }

    public function test_rejects_invalid_local_date_and_observation_before_midnight(): void
    {
        $query = app(DailyCardPurchaseOriginAuditQuery::class);
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
