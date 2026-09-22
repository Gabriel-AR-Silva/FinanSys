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
        $user = User::factory()->create();
        $purchase = CardPurchase::factory()->create([
            'user_id' => $user->id,
            'purchased_on' => '2026-09-21',
            'gross_amount' => '120.00',
            'planning_type' => ExpensePlanningType::Ordinary,
        ]);
        $recorder = app(AuditRecorder::class);
        $created = $recorder->record($user, AuditAction::Created, $purchase);
        AuditLog::query()->whereKey($created->id)->update(['created_at' => '2026-09-22 09:00:00']);

        $before = $purchase->attributesToArray();
        $purchase->update(['purchased_on' => '2026-09-22', 'gross_amount' => '180.00']);
        $updated = $recorder->record($user, AuditAction::Updated, $purchase, $before);
        AuditLog::query()->whereKey($updated->id)->update(['created_at' => '2026-09-22 16:00:00']);

        $query = app(DailyCardPurchaseAuditStateQuery::class);
        $early = CarbonImmutable::parse('2026-09-22T12:00:00Z');
        $late = CarbonImmutable::parse('2026-09-22T18:00:00Z');

        $this->assertSame('120.00', $query->forUserOnDay($user, '2026-09-21', $early)['ordinary_audited_total']);
        $this->assertSame('0.00', $query->forUserOnDay($user, '2026-09-21', $late)['ordinary_audited_total']);
        $this->assertSame('180.00', $query->forUserOnDay($user, '2026-09-22', $late)['ordinary_audited_total']);
        $this->assertSame([$purchase->id], $query->forUserOnDay($user, '2026-09-22', $late)['purchase_ids']);
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
}
