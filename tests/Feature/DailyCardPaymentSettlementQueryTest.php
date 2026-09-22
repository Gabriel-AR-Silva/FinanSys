<?php

namespace Tests\Feature;

use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\CardPayment;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Queries\DailyCardPaymentSettlementQuery;
use App\Queries\DailyFinancialFactsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class DailyCardPaymentSettlementQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_settlements_are_separate_from_ordinary_expenses_and_broken_or_edited_links_are_flagged(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21T12:00:00Z'));
        $user = User::factory()->create();
        $other = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $foreignAccount = Account::factory()->create(['user_id' => $other->id]);

        $expense = LedgerEntry::factory()->expense()->create([
            'user_id' => $user->id,
            'reference_type' => $account->getMorphClass(),
            'reference_id' => $account->id,
            'amount' => '12.00',
            'occurred_at' => '2026-09-21 09:00:00',
        ]);
        $stableEntry = LedgerEntry::factory()->create([
            'user_id' => $user->id,
            'reference_type' => $account->getMorphClass(),
            'reference_id' => $account->id,
            'type' => LedgerEntryType::CardPayment,
            'planning_type' => null,
            'amount' => '30.00',
            'occurred_at' => '2026-09-21 09:00:00',
        ]);
        $stable = CardPayment::factory()->create([
            'user_id' => $user->id,
            'source_account_id' => $account->id,
            'ledger_entry_id' => $stableEntry->id,
            'amount' => '30.00',
            'paid_on' => '2026-09-21',
            'operation_id' => $stableEntry->operation_id,
        ]);
        $editedEntry = LedgerEntry::factory()->create([
            'user_id' => $user->id,
            'reference_type' => $account->getMorphClass(),
            'reference_id' => $account->id,
            'type' => LedgerEntryType::CardPayment,
            'planning_type' => null,
            'amount' => '40.00',
            'occurred_at' => '2026-09-21 09:00:00',
        ]);
        $edited = CardPayment::factory()->create([
            'user_id' => $user->id,
            'source_account_id' => $account->id,
            'ledger_entry_id' => $editedEntry->id,
            'amount' => '40.00',
            'paid_on' => '2026-09-21',
            'operation_id' => $editedEntry->operation_id,
        ]);
        $brokenEntry = LedgerEntry::factory()->create([
            'user_id' => $user->id,
            'reference_type' => $account->getMorphClass(),
            'reference_id' => $account->id,
            'type' => LedgerEntryType::CardPayment,
            'planning_type' => null,
            'amount' => '99.00',
            'occurred_at' => '2026-09-21 09:00:00',
        ]);
        $broken = CardPayment::factory()->create([
            'user_id' => $user->id,
            'source_account_id' => $account->id,
            'ledger_entry_id' => $brokenEntry->id,
            'amount' => '15.00',
            'paid_on' => '2026-09-21',
            'operation_id' => $brokenEntry->operation_id,
        ]);
        $foreignEntry = LedgerEntry::factory()->create([
            'user_id' => $other->id,
            'reference_type' => $foreignAccount->getMorphClass(),
            'reference_id' => $foreignAccount->id,
            'type' => LedgerEntryType::CardPayment,
            'planning_type' => null,
            'amount' => '999.00',
            'occurred_at' => '2026-09-21 09:00:00',
        ]);
        CardPayment::factory()->create([
            'user_id' => $other->id,
            'source_account_id' => $foreignAccount->id,
            'ledger_entry_id' => $foreignEntry->id,
            'amount' => '999.00',
            'paid_on' => '2026-09-21',
            'operation_id' => $foreignEntry->operation_id,
        ]);

        DB::table('card_payments')->where('id', $edited->id)->update([
            'paid_on' => '2026-09-22',
            'updated_at' => '2026-09-23 10:00:00',
        ]);

        $observed = CarbonImmutable::parse('2026-09-21T16:00:00Z');
        $facts = app(DailyFinancialFactsQuery::class)->forUserOnDay($user, '2026-09-21', $observed);
        $settlement = $facts['settlement'];

        $this->assertSame('12.00', $facts['ledger']['ordinary_total']);
        $this->assertSame([$expense->id], $facts['ledger']['entry_ids']);
        $this->assertSame('30.00', $settlement['settled_total']);
        $this->assertSame([$stable->id], $settlement['payment_ids']);
        $this->assertSame([$stableEntry->id], $settlement['ledger_entry_ids']);
        $this->assertSame([$edited->id, $broken->id], $settlement['unverifiable_payment_ids']);
        $this->assertSame('partial_card_settlement_unverifiable_links', $settlement['coverage']);
        $this->assertSame(['settlement_unverifiable'], $facts['coverage_blockers']);
        $this->assertNull($facts['eligible_spent']);
    }

    public function test_rejects_invalid_local_date(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(DailyCardPaymentSettlementQuery::class)->forUserOnDay(User::factory()->create(), '2026-02-30');
    }
}
