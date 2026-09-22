<?php

namespace Tests\Feature;

use App\Actions\CreateManualLedgerEntry;
use App\Enums\AuditAction;
use App\Enums\CategoryType;
use App\Enums\ExpensePlanningType;
use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Queries\DailyOrdinaryLedgerExpenseQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DailyFinancialTimestampRoundTripTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_manual_writer_exposes_timestamp_mismatch_that_blocks_daily_confirmation(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-22T02:59:59+00:00'));
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $category = Category::factory()->create(['user_id' => $user->id, 'type' => CategoryType::Expense]);

        $entry = app(CreateManualLedgerEntry::class)->handle(
            $user,
            $account->id,
            $category->id,
            LedgerEntryType::Expense,
            '80.00',
            '2026-09-21',
            'Teste de virada do dia',
            (string) Str::uuid(),
            ExpensePlanningType::Ordinary,
        );

        $raw = DB::table('ledger_entries')->where('id', $entry->id)->first();
        $audit = AuditLog::query()->where('user_id', $user->id)
            ->where('auditable_type', (new LedgerEntry)->getMorphClass())
            ->where('auditable_id', $entry->id)
            ->where('action', AuditAction::Created->value)->firstOrFail();
        $auditRaw = DB::table('audit_logs')->where('id', $audit->id)->value('created_at');

        // V1 stores the local date at naive 00:00; the V2 query currently
        // compares the column against UTC 03:00. Do not assert that the
        // missing expense is legitimate or enable check-in from this query.
        $this->assertSame('2026-09-21 00:00:00', $raw->occurred_at);
        $this->assertSame('2026-09-21 23:59:59', $raw->created_at);
        $this->assertSame('2026-09-21 23:59:59', $auditRaw);

        $query = app(DailyOrdinaryLedgerExpenseQuery::class);
        $before = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T02:59:58+00:00'));
        $after = $query->forUserOnDay($user, '2026-09-21', CarbonImmutable::parse('2026-09-22T03:00:00+00:00'));

        $this->assertSame('0.00', $before['ordinary_total']);
        $this->assertSame('0.00', $after['ordinary_total']);
        $this->assertSame([], $after['entry_ids']);
        $this->assertDatabaseHas('ledger_entries', ['id' => $entry->id, 'amount' => '80.00']);
    }
}
