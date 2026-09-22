<?php

namespace Tests\Feature;

use App\Actions\CreateManualLedgerEntry;
use App\Enums\CategoryType;
use App\Enums\ExpensePlanningType;
use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use App\Queries\DailyOrdinaryLedgerAuditStateQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DailyLedgerHardDeleteCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_persisted_entry_does_not_appear_as_verified_historical_spending(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21T15:00:00+00:00'));
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $account = Account::factory()->for($owner)->create();
        $category = Category::factory()->for($owner)->create(['type' => CategoryType::Expense]);
        $entry = app(CreateManualLedgerEntry::class)->handle(
            $owner, $account->id, $category->id, LedgerEntryType::Expense, '80.00',
            '2026-09-21', 'Histórico sem linha original', (string) Str::uuid(), ExpensePlanningType::Ordinary,
        );
        $query = app(DailyOrdinaryLedgerAuditStateQuery::class);
        $observation = CarbonImmutable::parse('2026-09-21T15:00:01+00:00');
        $before = $query->forUserOnDay($owner, '2026-09-21', $observation);
        $this->assertSame('80.00', $before['ordinary_audited_total']);
        $this->assertSame([$entry->id], $before['entry_ids']);

        $this->travelTo(CarbonImmutable::parse('2026-09-22T15:00:00+00:00'));
        $entry->forceDelete();

        // There is no deletion event to date the disappearance. Even earlier
        // observations are conservatively incomplete after a hard deletion.
        $historical = $query->forUserOnDay($owner, '2026-09-21', $observation);
        $otherResult = $query->forUserOnDay($other, '2026-09-21', $observation);
        $this->assertSame('0.00', $historical['ordinary_audited_total']);
        $this->assertSame([], $historical['entry_ids']);
        $this->assertSame([$entry->id], $historical['unverifiable_entry_ids']);
        $this->assertSame('partial_audited_ledger_creation_updates', $historical['coverage']);
        $this->assertNotContains($entry->id, $otherResult['unverifiable_entry_ids']);
    }
}
