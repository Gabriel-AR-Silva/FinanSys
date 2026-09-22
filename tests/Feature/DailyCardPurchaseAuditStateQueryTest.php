<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\ExpensePlanningType;
use App\Models\AuditLog;
use App\Models\CardPurchase;
use App\Models\User;
use App\Queries\DailyCardPurchaseAuditStateQuery;
use App\Support\AuditRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyCardPurchaseAuditStateQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_as_of_revision_moves_purchase_between_days_without_rewriting_earlier_observation(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-22T09:00:00Z'));
        $user = User::factory()->create();
        $purchase = CardPurchase::factory()->create([
            'user_id' => $user->id,
            'purchased_on' => '2026-09-21',
            'gross_amount' => '120.00',
            'planning_type' => ExpensePlanningType::Ordinary,
        ]);
        $recorder = app(AuditRecorder::class);
        $recorder->record($user, AuditAction::Created, $purchase);

        $this->travelTo(CarbonImmutable::parse('2026-09-22T16:00:00Z'));
        $before = $purchase->attributesToArray();
        $purchase->update(['purchased_on' => '2026-09-22', 'gross_amount' => '180.00']);
        $recorder->record($user, AuditAction::Updated, $purchase, $before);

        $query = app(DailyCardPurchaseAuditStateQuery::class);
        $early = CarbonImmutable::parse('2026-09-22T12:00:00Z');
        $late = CarbonImmutable::parse('2026-09-22T18:00:00Z');

        $this->assertSame('120.00', $query->forUserOnDay($user, '2026-09-21', $early)['ordinary_audited_total']);
        $this->assertSame('0.00', $query->forUserOnDay($user, '2026-09-21', $late)['ordinary_audited_total']);
        $this->assertSame('180.00', $query->forUserOnDay($user, '2026-09-22', $late)['ordinary_audited_total']);
        $this->assertSame([$purchase->id], $query->forUserOnDay($user, '2026-09-22', $late)['purchase_ids']);
    }

    public function test_deleted_purchase_is_unverifiable_after_deletion_without_changing_prior_observation(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21T15:00:00Z'));
        $user = User::factory()->create();
        $other = User::factory()->create();
        $purchase = CardPurchase::factory()->create([
            'user_id' => $user->id,
            'purchased_on' => '2026-09-21',
            'gross_amount' => '120.00',
            'planning_type' => ExpensePlanningType::Ordinary,
        ]);
        app(AuditRecorder::class)->record($user, AuditAction::Created, $purchase);
        $otherPurchase = CardPurchase::factory()->create([
            'user_id' => $other->id,
            'purchased_on' => '2026-09-21',
            'gross_amount' => '999.00',
            'planning_type' => ExpensePlanningType::Ordinary,
        ]);
        app(AuditRecorder::class)->record($other, AuditAction::Created, $otherPurchase);

        $this->travelTo(CarbonImmutable::parse('2026-09-22T15:00:00Z'));
        $purchase->delete();

        $query = app(DailyCardPurchaseAuditStateQuery::class);
        $before = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-21T15:00:01Z'));
        $after = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T15:00:01Z'));
        $otherResult = $query->forUserOnDay($other, '2026-09-21', CarbonImmutable::parse('2026-09-22T15:00:01Z'));

        $this->assertSame('120.00', $before['ordinary_audited_total']);
        $this->assertSame([$purchase->id], $before['purchase_ids']);
        $this->assertSame([], $before['unverifiable_purchase_ids']);
        $this->assertSame('0.00', $after['ordinary_audited_total']);
        $this->assertSame([], $after['purchase_ids']);
        $this->assertSame([$purchase->id], $after['unverifiable_purchase_ids']);
        $this->assertSame('partial_audited_purchase_states', $after['coverage']);
        $this->assertSame('999.00', $otherResult['ordinary_audited_total']);
        $this->assertSame([], $otherResult['unverifiable_purchase_ids']);
    }

    public function test_unknown_update_and_incomplete_snapshot_cannot_be_silently_counted(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $purchase = CardPurchase::factory()->create(['user_id' => $user->id, 'purchased_on' => '2026-09-21']);
        $recorder = app(AuditRecorder::class);
        $created = $recorder->record($user, AuditAction::Created, $purchase);
        AuditLog::query()->whereKey($created->id)->update(['created_at' => '2026-09-22 09:00:00']);
        $broken = AuditLog::query()->create([
            'user_id' => $user->id,
            'auditable_type' => (new CardPurchase)->getMorphClass(),
            'auditable_id' => $purchase->id,
            'action' => AuditAction::Updated->value,
            'after' => ['id' => $purchase->id, 'user_id' => $user->id, 'purchased_on' => '2026-09-21'],
            'created_at' => '2026-09-22 10:00:00',
        ]);
        $foreign = CardPurchase::factory()->create(['user_id' => $other->id, 'purchased_on' => '2026-09-21', 'gross_amount' => '999.00']);
        $recorder->record($other, AuditAction::Created, $foreign);

        $result = app(DailyCardPurchaseAuditStateQuery::class)->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T12:00:00Z'));
        $this->assertSame('0.00', $result['ordinary_audited_total']);
        $this->assertSame([$purchase->id], $result['unverifiable_purchase_ids']);
        $this->assertSame('partial_audited_purchase_states', $result['coverage']);
        $this->assertNotNull($broken->id);
    }

    public function test_detects_current_day_legacy_purchases_including_soft_deleted_and_ignores_other_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $legacy = CardPurchase::factory()->create(['user_id' => $user->id, 'purchased_on' => '2026-09-21', 'gross_amount' => '75.00']);
        $deleted = CardPurchase::factory()->create(['user_id' => $user->id, 'purchased_on' => '2026-09-21', 'gross_amount' => '25.00']);
        $deleted->delete();
        CardPurchase::factory()->create(['user_id' => $other->id, 'purchased_on' => '2026-09-21', 'gross_amount' => '999.00']);
        $audited = CardPurchase::factory()->create(['user_id' => $user->id, 'purchased_on' => '2026-09-21', 'gross_amount' => '30.00']);
        app(AuditRecorder::class)->record($user, AuditAction::Created, $audited);

        $result = app(DailyCardPurchaseAuditStateQuery::class)->forUserOnDay($user, '2026-09-21', CarbonImmutable::now('UTC')->addMinute());
        $this->assertSame('30.00', $result['ordinary_audited_total']);
        $this->assertSame([$audited->id], $result['purchase_ids']);
        $this->assertSame([$legacy->id, $deleted->id], $result['unverifiable_purchase_ids']);
        $this->assertSame('partial_audited_purchase_states', $result['coverage']);
    }

    public function test_unaudited_purchase_moved_to_another_day_keeps_original_day_coverage_partial(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $legacy = CardPurchase::factory()->create(['user_id' => $user->id, 'purchased_on' => '2026-09-21', 'gross_amount' => '75.00']);
        $legacy->update(['purchased_on' => '2026-09-23', 'gross_amount' => '90.00']);
        $audited = CardPurchase::factory()->create(['user_id' => $user->id, 'purchased_on' => '2026-09-21', 'gross_amount' => '30.00']);
        app(AuditRecorder::class)->record($user, AuditAction::Created, $audited);
        CardPurchase::factory()->create(['user_id' => $other->id, 'purchased_on' => '2026-09-21', 'gross_amount' => '999.00']);

        $query = app(DailyCardPurchaseAuditStateQuery::class);
        $result = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::now('UTC')->addMinute());
        $this->assertSame('30.00', $result['ordinary_audited_total']);
        $this->assertSame([$audited->id], $result['purchase_ids']);
        $this->assertSame([$legacy->id], $result['unverifiable_purchase_ids']);
        $this->assertSame('partial_audited_purchase_states', $result['coverage']);

        $otherDay = $query->forUserOnDay($user, '2026-09-23', CarbonImmutable::parse('2026-09-24T04:00:00Z'));
        $this->assertSame([$legacy->id], $otherDay['unverifiable_purchase_ids']);
    }
}
