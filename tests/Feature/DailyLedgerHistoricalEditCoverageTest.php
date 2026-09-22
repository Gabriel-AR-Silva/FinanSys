<?php

namespace Tests\Feature;

use App\Actions\CorrectManualLedgerEntry;
use App\Actions\CreateManualLedgerEntry;
use App\Enums\CategoryType;
use App\Enums\ExpensePlanningType;
use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use App\Queries\DailyOrdinaryLedgerExpenseQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DailyLedgerHistoricalEditCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_later_correction_cannot_rewrite_or_silently_erase_an_earlier_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21T12:00:00+00:00'));
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
        $other = User::factory()->create();
        $otherAccount = Account::factory()->for($other)->create();
        $otherCategory = Category::factory()->for($other)->create(['type' => CategoryType::Expense]);
        $writer = app(CreateManualLedgerEntry::class);

        $changed = $writer->handle($user, $account->id, $category->id, LedgerEntryType::Expense, '80.00', '2026-09-21', null, (string) Str::uuid(), ExpensePlanningType::Ordinary);
        $stable = $writer->handle($user, $account->id, $category->id, LedgerEntryType::Expense, '25.00', '2026-09-21', null, (string) Str::uuid(), ExpensePlanningType::Ordinary);
        $writer->handle($other, $otherAccount->id, $otherCategory->id, LedgerEntryType::Expense, '900.00', '2026-09-21', null, (string) Str::uuid(), ExpensePlanningType::Ordinary);
        $query = app(DailyOrdinaryLedgerExpenseQuery::class);
        $atOriginalObservation = CarbonImmutable::parse('2026-09-21T18:00:00+00:00');

        $original = $query->forUserOnDay($user, '2026-09-21', $atOriginalObservation);
        $this->assertSame('105.00', $original['ordinary_total']);
        $this->assertSame('ledger_only', $original['coverage']);
        $this->assertSame([], $original['unverifiable_entry_ids']);

        $this->travelTo(CarbonImmutable::parse('2026-09-22T15:00:00+00:00'));
        app(CorrectManualLedgerEntry::class)->handle($user, $changed->id, [
            'category_id' => $category->id,
            'amount' => '180.00',
            'occurred_at' => '2026-09-22',
            'description' => 'Corrigido no dia seguinte',
            'planning_type' => ExpensePlanningType::Ordinary->value,
        ]);

        $historical = $query->forUserOnDay($user, '2026-09-21', $atOriginalObservation);
        $this->assertSame('25.00', $historical['ordinary_total']);
        $this->assertSame([$stable->id], $historical['entry_ids']);
        $this->assertSame([$changed->id], $historical['unverifiable_entry_ids']);
        $this->assertSame('partial_ledger_unverifiable_edits', $historical['coverage']);

        $current = $query->forUserOnDay($user, '2026-09-22', CarbonImmutable::parse('2026-09-22T16:00:00+00:00'));
        $this->assertSame('180.00', $current['ordinary_total']);
        $this->assertSame([$changed->id], $current['entry_ids']);
        $this->assertSame([], $current['unverifiable_entry_ids']);
        $this->assertSame('ledger_only', $current['coverage']);

        $otherDay = $query->forUserOnDay($other, '2026-09-21', $atOriginalObservation);
        $this->assertSame('900.00', $otherDay['ordinary_total']);
        $this->assertSame([], $otherDay['unverifiable_entry_ids']);
    }
}
