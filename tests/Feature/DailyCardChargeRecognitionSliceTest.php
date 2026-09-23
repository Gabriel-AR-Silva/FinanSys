<?php

namespace Tests\Feature;

use App\Enums\CardInstallmentStatus;
use App\Enums\ExpensePlanningType;
use App\Models\CardCharge;
use App\Models\User;
use App\Queries\DailyCardChargeRecognitionQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DailyCardChargeRecognitionSliceTest extends TestCase
{
    use RefreshDatabase;

    public function test_charge_occurs_on_charged_on_not_due_on_or_payment_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-22T02:59:59Z'));
        $user = User::factory()->create();
        $foreign = User::factory()->create();
        $charge = CardCharge::factory()->create([
            'user_id' => $user->id,
            'planning_type' => ExpensePlanningType::Ordinary,
            'amount' => '7.35',
            'paid_amount' => '7.35',
            'status' => CardInstallmentStatus::Paid,
            'charged_on' => '2026-09-21',
            'due_on' => '2026-10-10',
        ]);
        CardCharge::factory()->create(['user_id' => $user->id, 'charged_on' => '2026-09-21', 'planning_type' => ExpensePlanningType::Extraordinary, 'amount' => '100.00']);
        CardCharge::factory()->create(['user_id' => $foreign->id, 'charged_on' => '2026-09-21', 'planning_type' => ExpensePlanningType::Ordinary, 'amount' => '999.00']);
        $query = app(DailyCardChargeRecognitionQuery::class);
        $observed = CarbonImmutable::parse('2026-09-22T02:59:59Z');

        $this->assertSame('2026-09-21 23:59:59', DB::table('card_charges')->where('id', $charge->id)->value('created_at'));
        $result = $query->forUserOnDay($user, '2026-09-21', $observed);
        $this->assertSame('7.35', $result['ordinary_charge_total']);
        $this->assertSame([$charge->id], $result['charge_ids']);
        $this->assertSame('card_charges_only', $result['coverage']);
        $this->assertSame('0.00', $query->forUserOnDay($user, '2026-10-10', CarbonImmutable::parse('2026-10-11T12:00:00Z'))['ordinary_charge_total']);
    }

    public function test_reversed_or_unverifiably_edited_charge_blocks_reconciliation(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21T18:00:00Z'));
        $user = User::factory()->create();
        $reversed = CardCharge::factory()->create(['user_id' => $user->id, 'charged_on' => '2026-09-21', 'planning_type' => ExpensePlanningType::Ordinary, 'status' => CardInstallmentStatus::Reversed]);
        $changed = CardCharge::factory()->create(['user_id' => $user->id, 'charged_on' => '2026-09-21', 'planning_type' => ExpensePlanningType::Ordinary]);
        DB::table('card_charges')->where('id', $changed->id)->update(['charged_on' => '2026-09-22', 'updated_at' => '2026-09-23 10:00:00']);

        $result = app(DailyCardChargeRecognitionQuery::class)->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T18:00:00Z'));
        $this->assertSame('0.00', $result['ordinary_charge_total']);
        $this->assertSame([$reversed->id, $changed->id], $result['unverifiable_charge_ids']);
        $this->assertSame('partial_card_charge_unverifiable_edits', $result['coverage']);
    }
}
