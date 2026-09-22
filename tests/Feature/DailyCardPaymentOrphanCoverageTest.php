<?php

namespace Tests\Feature;

use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\CardPayment;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Queries\DailyFinancialFactsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DailyCardPaymentOrphanCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_orphan_cash_outflow_is_reported_as_unverifiable_not_consumption(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21T12:00:00Z'));
        $user = User::factory()->create();
        $other = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $foreign = Account::factory()->create(['user_id' => $other->id]);
        $orphan = LedgerEntry::factory()->create([
            'user_id' => $user->id,
            'reference_type' => $account->getMorphClass(),
            'reference_id' => $account->id,
            'type' => LedgerEntryType::CardPayment,
            'planning_type' => null,
            'amount' => '80.00',
            'occurred_at' => '2026-09-21 09:00:00',
        ]);
        LedgerEntry::factory()->create([
            'user_id' => $other->id,
            'reference_type' => $foreign->getMorphClass(),
            'reference_id' => $foreign->id,
            'type' => LedgerEntryType::CardPayment,
            'planning_type' => null,
            'amount' => '900.00',
            'occurred_at' => '2026-09-21 09:00:00',
        ]);
        $observed = CarbonImmutable::parse('2026-09-21T16:00:00Z');
        $query = app(DailyFinancialFactsQuery::class);
        $before = $query->forUserOnDay($user, '2026-09-21', $observed);

        $this->assertSame('0.00', $before['ledger']['ordinary_total']);
        $this->assertSame('0.00', $before['settlement']['settled_total']);
        $this->assertSame([$orphan->id], $before['settlement']['unmatched_ledger_entry_ids']);
        $this->assertSame(['settlement_unverifiable'], $before['coverage_blockers']);
        $this->assertNull($before['eligible_spent']);

        // A payment created after observation cannot retroactively remove the
        // missing-link warning. A matching row before a later observation can.
        $this->travelTo(CarbonImmutable::parse('2026-09-22T12:00:00Z'));
        CardPayment::factory()->create([
            'user_id' => $user->id,
            'source_account_id' => $account->id,
            'ledger_entry_id' => $orphan->id,
            'amount' => '80.00',
            'paid_on' => '2026-09-21',
            'operation_id' => $orphan->operation_id,
        ]);
        $historical = $query->forUserOnDay($user, '2026-09-21', $observed);
        $later = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T16:00:00Z'));

        $this->assertSame([$orphan->id], $historical['settlement']['unmatched_ledger_entry_ids']);
        $this->assertSame([], $later['settlement']['unmatched_ledger_entry_ids']);
        $this->assertSame('80.00', $later['settlement']['settled_total']);
        $this->assertSame([], $later['coverage_blockers']);
    }

    public function test_later_moved_orphan_does_not_disappear_from_old_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21T12:00:00Z'));
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $orphan = LedgerEntry::factory()->create([
            'user_id' => $user->id,
            'reference_type' => $account->getMorphClass(),
            'reference_id' => $account->id,
            'type' => LedgerEntryType::CardPayment,
            'planning_type' => null,
            'amount' => '20.00',
            'occurred_at' => '2026-09-21 09:00:00',
        ]);
        DB::table('ledger_entries')->where('id', $orphan->id)->update([
            'occurred_at' => '2026-09-22 09:00:00',
            'updated_at' => '2026-09-23 10:00:00',
        ]);

        $facts = app(DailyFinancialFactsQuery::class)->forUserOnDay(
            $user, '2026-09-21', CarbonImmutable::parse('2026-09-21T16:00:00Z'),
        );
        $this->assertSame([$orphan->id], $facts['settlement']['unmatched_ledger_entry_ids']);
        $this->assertSame(['settlement_unverifiable'], $facts['coverage_blockers']);
    }
}
