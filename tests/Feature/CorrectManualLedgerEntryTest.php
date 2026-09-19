<?php

namespace Tests\Feature;

use App\Actions\CorrectManualLedgerEntry;
use App\Enums\CategoryType;
use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Queries\AccountBalanceQuery;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CorrectManualLedgerEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_correcting_unlinked_expense_changes_balance_once_and_audits_before_and_after(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
        $entry = LedgerEntry::factory()->for($user)->create([
            'reference_type' => 'account', 'reference_id' => $account->id,
            'category_id' => $category->id, 'type' => LedgerEntryType::Expense,
            'amount' => '100.00', 'occurred_at' => '2026-09-01',
        ]);
        $before = DB::table('audit_logs')->count();
        $payload = [
            'category_id' => $category->id, 'amount' => '80.00',
            'occurred_at' => '2026-09-01', 'description' => 'Valor corrigido',
            'planning_type' => 'ordinary',
        ];

        app(CorrectManualLedgerEntry::class)->handle($user, $entry->id, $payload);
        app(CorrectManualLedgerEntry::class)->handle($user, $entry->id, $payload);

        $this->assertSame('80.00', $entry->fresh()->amount);
        $this->assertEquals(-80, app(AccountBalanceQuery::class)->forUser($user)->sole()->balance);
        $this->assertSame($before + 1, DB::table('audit_logs')->where('action', 'updated')->where('auditable_type', 'ledger_entry')->count());
        $audit = AuditLog::query()->where('action', 'updated')->where('auditable_type', 'ledger_entry')->sole();
        $this->assertSame('100.00', $audit->before['amount']);
        $this->assertSame('80.00', $audit->after['amount']);
    }

    public function test_user_cannot_correct_foreign_or_linked_entry(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $account = Account::factory()->for($owner)->create();
        $category = Category::factory()->for($owner)->create(['type' => CategoryType::Expense]);
        $entry = LedgerEntry::factory()->for($owner)->create([
            'reference_type' => 'account', 'reference_id' => $account->id,
            'category_id' => $category->id, 'type' => LedgerEntryType::Expense,
            'amount' => '100.00', 'occurred_at' => '2026-09-01',
        ]);
        $payload = [
            'category_id' => $category->id, 'amount' => '1.00',
            'occurred_at' => '2026-09-01', 'description' => 'Tentativa',
            'planning_type' => 'ordinary',
        ];

        try {
            app(CorrectManualLedgerEntry::class)->handle($other, $entry->id, $payload);
            $this->fail('Outro usuário não pode corrigir um lançamento.');
        } catch (ModelNotFoundException $exception) {
            $this->assertSame('100.00', $entry->fresh()->amount);
        }

        $entry->update(['reversal_of_operation_id' => 'existing-related-operation']);
        try {
            app(CorrectManualLedgerEntry::class)->handle($owner, $entry->id, $payload);
            $this->fail('Lançamento vinculado não pode ser corrigido em linha.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ledger_entry', $exception->errors());
        }
        $this->assertSame('100.00', $entry->fresh()->amount);
    }
}
