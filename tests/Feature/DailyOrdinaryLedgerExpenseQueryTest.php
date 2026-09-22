<?php

namespace Tests\Feature;

use App\Enums\ExpensePlanningType;
use App\Enums\LedgerEntryType;
use App\Models\ExpenseRefund;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Queries\DailyOrdinaryLedgerExpenseQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class DailyOrdinaryLedgerExpenseQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_only_ordinary_ledger_expenses_for_user_and_local_day(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $entry = LedgerEntry::factory()->expense()->create([
            'user_id' => $user->id,
            'amount' => '80.00',
            'occurred_at' => '2026-09-22 02:59:59', // 21/09 23:59:59 in Sao Paulo
        ]);
        LedgerEntry::factory()->expense()->create(['user_id' => $user->id, 'amount' => '9.00', 'planning_type' => ExpensePlanningType::Fixed, 'occurred_at' => '2026-09-21 18:00:00']);
        LedgerEntry::factory()->expense()->create(['user_id' => $user->id, 'amount' => '12.00', 'planning_type' => ExpensePlanningType::Extraordinary, 'occurred_at' => '2026-09-21 18:00:00']);
        LedgerEntry::factory()->expense()->create(['user_id' => $other->id, 'amount' => '999.00', 'occurred_at' => '2026-09-21 18:00:00']);
        LedgerEntry::factory()->expense()->create(['user_id' => $user->id, 'amount' => '99.00', 'occurred_at' => '2026-09-22 03:00:00']);
        LedgerEntry::factory()->income()->create(['user_id' => $user->id, 'amount' => '50.00', 'occurred_at' => '2026-09-21 18:00:00']);
        LedgerEntry::factory()->expense()->create(['user_id' => $user->id, 'amount' => '30.00', 'planning_type' => null, 'occurred_at' => '2026-09-21 18:00:00']);

        $result = app(DailyOrdinaryLedgerExpenseQuery::class)->forUserOnDay($user, '2026-09-21', CarbonImmutable::now('UTC')->addMinute());

        $this->assertSame('80.00', $result['ordinary_total']);
        $this->assertSame([$entry->id], $result['entry_ids']);
        $this->assertSame(1, $result['unclassified_count']);
        $this->assertSame('ledger_only', $result['coverage']);
    }

    public function test_reversals_and_linked_refunds_are_not_double_counted(): void
    {
        $user = User::factory()->create();
        $expense = LedgerEntry::factory()->expense()->create(['user_id' => $user->id, 'amount' => '80.00', 'occurred_at' => '2026-09-21 15:00:00']);
        LedgerEntry::factory()->create([
            'user_id' => $user->id,
            'type' => LedgerEntryType::Refund,
            'planning_type' => null,
            'amount' => '20.00',
            'reversal_of_operation_id' => $expense->operation_id,
            'occurred_at' => '2026-09-21 16:00:00',
        ]);
        $active = LedgerEntry::factory()->expense()->create(['user_id' => $user->id, 'amount' => '50.00', 'occurred_at' => '2026-09-21 15:00:00']);
        $refund = LedgerEntry::factory()->create(['user_id' => $user->id, 'type' => LedgerEntryType::Refund, 'planning_type' => null, 'amount' => '15.00', 'occurred_at' => '2026-09-22 14:00:00']);
        ExpenseRefund::factory()->create(['user_id' => $user->id, 'expense_ledger_entry_id' => $active->id, 'refund_ledger_entry_id' => $refund->id, 'operation_id' => (string) Str::uuid()]);
        $query = app(DailyOrdinaryLedgerExpenseQuery::class);

        $before = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T12:00:00Z'));
        $after = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-23T00:00:00Z'));

        $this->assertSame('50.00', $before['ordinary_total']);
        $this->assertSame('35.00', $after['ordinary_total']);
        $this->assertSame([$active->id], $after['entry_ids']);
    }

    public function test_refund_link_created_after_observation_does_not_rewrite_earlier_read(): void
    {
        $user = User::factory()->create();
        $expense = LedgerEntry::factory()->expense()->create([
            'user_id' => $user->id,
            'amount' => '50.00',
            'occurred_at' => '2026-09-21 15:00:00',
            'created_at' => '2026-09-21 15:00:00',
        ]);
        $refund = LedgerEntry::factory()->create([
            'user_id' => $user->id,
            'type' => LedgerEntryType::Refund,
            'planning_type' => null,
            'amount' => '15.00',
            'occurred_at' => '2026-09-21 16:00:00',
            'created_at' => '2026-09-21 16:00:00',
        ]);
        ExpenseRefund::factory()->create([
            'user_id' => $user->id,
            'expense_ledger_entry_id' => $expense->id,
            'refund_ledger_entry_id' => $refund->id,
            'operation_id' => (string) Str::uuid(),
            'created_at' => '2026-09-23 10:00:00',
        ]);

        $query = app(DailyOrdinaryLedgerExpenseQuery::class);
        $beforeLink = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T22:00:00Z'));
        $afterLink = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-23T11:00:00Z'));

        $this->assertSame('50.00', $beforeLink['ordinary_total']);
        $this->assertSame('35.00', $afterLink['ordinary_total']);
        $this->assertSame([$expense->id], $beforeLink['entry_ids']);
    }

    public function test_future_dated_reversal_only_applies_after_its_effective_time(): void
    {
        $user = User::factory()->create();
        $expense = LedgerEntry::factory()->expense()->create([
            'user_id' => $user->id,
            'amount' => '50.00',
            'occurred_at' => '2026-09-21 15:00:00',
            'created_at' => '2026-09-21 15:00:00',
        ]);
        LedgerEntry::factory()->create([
            'user_id' => $user->id,
            'type' => LedgerEntryType::Refund,
            'planning_type' => null,
            'amount' => '50.00',
            'reversal_of_operation_id' => $expense->operation_id,
            'occurred_at' => '2026-09-23 12:00:00',
            'created_at' => '2026-09-22 12:00:00',
        ]);
        $query = app(DailyOrdinaryLedgerExpenseQuery::class);

        $before = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T22:00:00Z'));
        $after = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-23T13:00:00Z'));

        $this->assertSame('50.00', $before['ordinary_total']);
        $this->assertSame([$expense->id], $before['entry_ids']);
        $this->assertSame('0.00', $after['ordinary_total']);
        $this->assertSame([], $after['entry_ids']);
    }

    public function test_invalid_day_and_observation_before_day_are_rejected(): void
    {
        $user = User::factory()->create();
        $query = app(DailyOrdinaryLedgerExpenseQuery::class);

        try {
            $query->forUserOnDay($user, '2026-02-30');
            $this->fail('Invalid date accepted.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);
        $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-21T00:00:00Z'));
    }
}
