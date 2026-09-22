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

class DailyOrdinaryLedgerAuditTypeChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_unaudited_type_change_cannot_hide_an_original_expense(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21T15:00:00+00:00'));
        $user = User::factory()->create();
        $other = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
        $entry = app(CreateManualLedgerEntry::class)->handle(
            $user, $account->id, $category->id, LedgerEntryType::Expense, '80.00',
            '2026-09-21', 'Despesa original', (string) Str::uuid(), ExpensePlanningType::Ordinary,
        );
        $query = app(DailyOrdinaryLedgerAuditStateQuery::class);

        $this->travelTo(CarbonImmutable::parse('2026-09-22T15:00:00+00:00'));
        // Simulates an old writer bypassing AuditRecorder, including a type change.
        $entry->forceFill(['type' => LedgerEntryType::Income, 'amount' => '180.00'])->save();

        $before = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-21T15:00:01+00:00'));
        $after = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T15:00:01+00:00'));
        $otherResult = $query->forUserOnDay($other, '2026-09-21', CarbonImmutable::parse('2026-09-22T15:00:01+00:00'));

        $this->assertSame('80.00', $before['ordinary_audited_total']);
        $this->assertSame([$entry->id], $before['entry_ids']);
        $this->assertSame([], $before['unverifiable_entry_ids']);
        $this->assertSame('0.00', $after['ordinary_audited_total']);
        $this->assertSame([], $after['entry_ids']);
        $this->assertContains($entry->id, $after['unverifiable_entry_ids']);
        $this->assertSame('partial_audited_ledger_creation_updates', $after['coverage']);
        $this->assertNotContains($entry->id, $otherResult['unverifiable_entry_ids']);
    }
}
