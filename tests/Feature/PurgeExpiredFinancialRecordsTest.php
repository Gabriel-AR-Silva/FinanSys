<?php

namespace Tests\Feature;

use App\Actions\AdvanceCardInstallments;
use App\Actions\CreateCardPurchase;
use App\Actions\DeleteAccount;
use App\Enums\AuditAction;
use App\Enums\CategoryType;
use App\Enums\ExpensePlanningType;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\CardAdvance;
use App\Models\Category;
use App\Models\CreditCard;
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

    public function test_it_purges_an_advance_source_account_without_destroying_the_card_history(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 12:00:00');
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        LedgerEntry::factory()->openingBalance()->for($user)->for($account, 'reference')->create(['amount' => '500.00']);
        $card = CreditCard::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
        $purchase = app(CreateCardPurchase::class)->handle($user, [
            'credit_card_id' => $card->id, 'category_id' => $category->id, 'description' => 'Curso',
            'planning_type' => ExpensePlanningType::Extraordinary->value, 'gross_amount' => '100.00',
            'purchased_on' => '2026-09-01', 'installments_count' => 1, 'first_due_on' => '2026-10-12',
            'operation_id' => (string) Str::uuid(),
        ]);
        $advance = app(AdvanceCardInstallments::class)->handle($user, [
            'credit_card_id' => $card->id, 'source_account_id' => $account->id,
            'installment_ids' => $purchase->installments->pluck('id')->all(),
            'discount_amount' => '5.00', 'expected_gross_amount' => '100.00',
            'advanced_on' => '2026-09-13', 'operation_id' => (string) Str::uuid(),
        ]);

        app(DeleteAccount::class)->handle($user, $account->id);
        foreach ([$account, ...LedgerEntry::onlyTrashed()->where('deletion_batch_id', $account->fresh()->deletion_batch_id)->get()] as $model) {
            $model->forceFill(['deleted_at' => now()->subDays(30)->subSecond()])->saveQuietly();
        }

        $this->artisan('finansys:purge-expired')->assertSuccessful();

        $this->assertNull(Account::withTrashed()->find($account->id));
        $this->assertNotNull($advance = CardAdvance::query()->find($advance->id));
        $this->assertNull($advance->source_account_id);
        $this->assertNull($advance->ledger_entry_id);
        $this->assertDatabaseHas('card_advance_allocations', ['card_advance_id' => $advance->id, 'gross_amount' => 100]);
        $this->assertDatabaseHas('card_installments', ['id' => $purchase->installments->first()->id, 'status' => 'advanced']);
    }
}
