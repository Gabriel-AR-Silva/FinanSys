<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\Pocket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurgeExpiredFinancialRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_purges_an_expired_account_batch_and_preserves_audit_evidence(): void
    {
        CarbonImmutable::setTestNow('2026-09-02 12:00:00');
        $user = User::factory()->create();
        $batchId = (string) Str::uuid();
        $account = Account::factory()->for($user)->create();
        $pocket = Pocket::factory()->for($user)->for($account)->create();
        $entry = LedgerEntry::factory()->for($user)->create([
            'reference_type' => $pocket->getMorphClass(),
            'reference_id' => $pocket->id,
        ]);
        foreach ([$entry, $pocket, $account] as $model) {
            $model->update(['deletion_batch_id' => $batchId]);
            $model->delete();
            $model->forceFill(['deleted_at' => now()->subDays(30)->subSecond()])->saveQuietly();
        }

        $this->artisan('finansys:purge-expired')->assertSuccessful();

        $this->assertNull(Account::withTrashed()->find($account->id));
        $this->assertNull(Pocket::withTrashed()->find($pocket->id));
        $this->assertNull(LedgerEntry::withTrashed()->find($entry->id));
        $this->assertSame(3, AuditLog::query()->where('action', AuditAction::Purged->value)->count());
    }

    public function test_it_keeps_records_at_the_exact_retention_boundary_and_is_repeatable(): void
    {
        CarbonImmutable::setTestNow('2026-09-02 12:00:00');
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $entry = LedgerEntry::factory()->for($user)->income()->create([
            'reference_type' => $account->getMorphClass(),
            'reference_id' => $account->id,
        ]);
        $entry->delete();
        $entry->forceFill(['deleted_at' => now()->subDays(30)])->saveQuietly();

        $this->artisan('finansys:purge-expired')->assertSuccessful();
        $this->assertNotNull(LedgerEntry::withTrashed()->find($entry->id));

        $entry->forceFill(['deleted_at' => now()->subDays(30)->subSecond()])->saveQuietly();
        $this->artisan('finansys:purge-expired')->assertSuccessful();
        $this->artisan('finansys:purge-expired')->assertSuccessful();

        $this->assertNull(LedgerEntry::withTrashed()->find($entry->id));
        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::Purged->value)->count());
    }
}
