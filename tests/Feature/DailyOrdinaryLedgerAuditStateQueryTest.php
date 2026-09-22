<?php

namespace Tests\Feature;

use App\Actions\CorrectManualLedgerEntry;
use App\Actions\CreateManualLedgerEntry;
use App\Enums\CategoryType;
use App\Enums\ExpensePlanningType;
use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Queries\DailyOrdinaryLedgerAuditStateQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class DailyOrdinaryLedgerAuditStateQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_create_and_correction_reconstruct_amount_and_day_at_observation(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21T15:00:00+00:00'));
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
        $entry = app(CreateManualLedgerEntry::class)->handle(
            $user, $account->id, $category->id, LedgerEntryType::Expense, '80.00',
            '2026-09-21', 'Antes', (string) Str::uuid(), ExpensePlanningType::Ordinary,
        );
        $query = app(DailyOrdinaryLedgerAuditStateQuery::class);
        $beforeCreation = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-21T14:59:59+00:00'));
        $original = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-21T15:00:01+00:00'));

        $this->travelTo(CarbonImmutable::parse('2026-09-22T15:00:00+00:00'));
        app(CorrectManualLedgerEntry::class)->handle($user, $entry->id, [
            'category_id' => $category->id, 'amount' => '180.00',
            'occurred_at' => '2026-09-22', 'description' => 'Depois', 'planning_type' => 'ordinary',
        ]);
        $historical = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-21T15:00:01+00:00'));
        $moved = $query->forUserOnDay($user, '2026-09-22', CarbonImmutable::parse('2026-09-22T15:00:01+00:00'));
        $oldDayNow = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T15:00:01+00:00'));

        $this->assertSame('0.00', $beforeCreation['ordinary_audited_total']);
        $this->assertSame('80.00', $original['ordinary_audited_total']);
        $this->assertSame('80.00', $historical['ordinary_audited_total']);
        $this->assertSame([$entry->id], $historical['entry_ids']);
        $this->assertSame('180.00', $moved['ordinary_audited_total']);
        $this->assertSame([$entry->id], $moved['entry_ids']);
        $this->assertSame('0.00', $oldDayNow['ordinary_audited_total']);
        $this->assertSame([], $historical['unverifiable_entry_ids']);
        $this->assertSame('audited_ledger_creation_updates_only', $historical['coverage']);
    }

    public function test_deletion_after_observation_preserves_prior_view_but_blocks_later_subtotal(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21T15:00:00+00:00'));
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
        $entry = app(CreateManualLedgerEntry::class)->handle(
            $user, $account->id, $category->id, LedgerEntryType::Expense, '80.00',
            '2026-09-21', 'Despesa excluída depois', (string) Str::uuid(), ExpensePlanningType::Ordinary,
        );
        $other = User::factory()->create();
        $query = app(DailyOrdinaryLedgerAuditStateQuery::class);

        $this->travelTo(CarbonImmutable::parse('2026-09-22T15:00:00+00:00'));
        $entry->delete();

        $before = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-21T15:00:01+00:00'));
        $after = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T15:00:01+00:00'));
        $otherResult = $query->forUserOnDay($other, '2026-09-21', CarbonImmutable::parse('2026-09-22T15:00:01+00:00'));

        $this->assertSame('80.00', $before['ordinary_audited_total']);
        $this->assertSame([$entry->id], $before['entry_ids']);
        $this->assertSame('0.00', $after['ordinary_audited_total']);
        $this->assertSame([], $after['entry_ids']);
        $this->assertContains($entry->id, $after['unverifiable_entry_ids']);
        $this->assertSame('partial_audited_ledger_creation_updates', $after['coverage']);
        $this->assertNotContains($entry->id, $otherResult['unverifiable_entry_ids']);
    }

    public function test_unaudited_moved_and_soft_deleted_entries_are_unverifiable_only_for_their_owner(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-22T16:00:00+00:00'));
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $moved = LedgerEntry::factory()->expense()->create([
            'user_id' => $owner->id, 'occurred_at' => '2026-09-22 12:00:00',
            'created_at' => '2026-09-21 12:00:00', 'updated_at' => '2026-09-22 12:00:00',
        ]);
        $deleted = LedgerEntry::factory()->expense()->create([
            'user_id' => $owner->id, 'occurred_at' => '2026-09-21 12:00:00',
            'created_at' => '2026-09-21 12:00:00', 'updated_at' => '2026-09-21 12:00:00',
        ]);
        $deleted->delete();
        LedgerEntry::factory()->expense()->create([
            'user_id' => $other->id, 'occurred_at' => '2026-09-21 12:00:00',
            'created_at' => '2026-09-21 12:00:00',
        ]);
        $query = app(DailyOrdinaryLedgerAuditStateQuery::class);
        $ownerResult = $query->forUserOnDay($owner, '2026-09-21');
        $otherResult = $query->forUserOnDay($other, '2026-09-21');

        $this->assertSame('0.00', $ownerResult['ordinary_audited_total']);
        $this->assertSame([$moved->id, $deleted->id], $ownerResult['unverifiable_entry_ids']);
        $this->assertSame('partial_audited_ledger_creation_updates', $ownerResult['coverage']);
        $this->assertCount(1, $otherResult['unverifiable_entry_ids']);
        $this->assertNotContains($moved->id, $otherResult['unverifiable_entry_ids']);
    }

    public function test_invalid_dates_and_observation_before_local_day_are_rejected(): void
    {
        $query = app(DailyOrdinaryLedgerAuditStateQuery::class);
        $user = User::factory()->create();
        try {
            $query->forUserOnDay($user, '2026-02-30');
            $this->fail('Invalid date accepted.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }
        $this->expectException(InvalidArgumentException::class);
        $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-21T02:59:59+00:00'));
    }
}
