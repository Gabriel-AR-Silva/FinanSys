<?php

namespace Tests\Feature;

use App\Enums\CardInstallmentStatus;
use App\Enums\ExpensePlanningType;
use App\Models\CardCharge;
use App\Models\CardInstallment;
use App\Models\CardPurchase;
use App\Models\User;
use App\Queries\DailyCardDueCommitmentQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class DailyCardDueCommitmentQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_only_current_ordinary_obligations_due_on_the_requested_day(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $ordinary = CardPurchase::factory()->create(['user_id' => $user->id, 'planning_type' => ExpensePlanningType::Ordinary]);
        $first = CardInstallment::factory()->create(['user_id' => $user->id, 'card_purchase_id' => $ordinary->id, 'gross_amount' => '100.00', 'paid_amount' => '100.00', 'status' => CardInstallmentStatus::Paid, 'due_on' => '2026-09-21']);
        $second = CardInstallment::factory()->create(['user_id' => $user->id, 'card_purchase_id' => $ordinary->id, 'installment_number' => 2, 'gross_amount' => '60.00', 'paid_amount' => '20.00', 'due_on' => '2026-09-21']);
        CardInstallment::factory()->create(['user_id' => $user->id, 'card_purchase_id' => $ordinary->id, 'installment_number' => 3, 'gross_amount' => '90.00', 'status' => CardInstallmentStatus::Advanced, 'due_on' => '2026-09-21']);
        CardInstallment::factory()->create(['user_id' => $user->id, 'card_purchase_id' => $ordinary->id, 'installment_number' => 4, 'gross_amount' => '90.00', 'status' => CardInstallmentStatus::Reversed, 'due_on' => '2026-09-21']);
        CardInstallment::factory()->create(['user_id' => $user->id, 'card_purchase_id' => $ordinary->id, 'installment_number' => 5, 'gross_amount' => '70.00', 'due_on' => '2026-09-22']);
        CardInstallment::factory()->create(['user_id' => $other->id, 'gross_amount' => '999.00', 'due_on' => '2026-09-21']);
        $fixed = CardPurchase::factory()->create(['user_id' => $user->id, 'planning_type' => ExpensePlanningType::Fixed]);
        CardInstallment::factory()->create(['user_id' => $user->id, 'card_purchase_id' => $fixed->id, 'due_on' => '2026-09-21']);
        $unknown = CardPurchase::factory()->create(['user_id' => $user->id, 'planning_type' => null]);
        CardInstallment::factory()->create(['user_id' => $user->id, 'card_purchase_id' => $unknown->id, 'due_on' => '2026-09-21']);
        $charge = CardCharge::factory()->create(['user_id' => $user->id, 'planning_type' => ExpensePlanningType::Ordinary, 'amount' => '10.00', 'paid_amount' => '10.00', 'status' => CardInstallmentStatus::Paid, 'due_on' => '2026-09-21']);
        CardCharge::factory()->create(['user_id' => $user->id, 'planning_type' => ExpensePlanningType::Extraordinary, 'amount' => '20.00', 'due_on' => '2026-09-21']);
        CardCharge::factory()->create(['user_id' => $user->id, 'planning_type' => null, 'due_on' => '2026-09-21']);

        $result = app(DailyCardDueCommitmentQuery::class)->forUserOnDay($user, '2026-09-21');

        $this->assertSame('170.00', $result['ordinary_due_total']);
        $this->assertSame([$first->id, $second->id], $result['installment_ids']);
        $this->assertSame([$charge->id], $result['charge_ids']);
        $this->assertSame(2, $result['unclassified_count']);
        $this->assertSame('current_card_due_only', $result['coverage']);
    }

    public function test_rejects_invalid_local_date(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(DailyCardDueCommitmentQuery::class)->forUserOnDay(User::factory()->create(), '2026-02-30');
    }
}
