<?php

namespace Tests\Feature;

use App\Actions\ReverseLedgerOperation;
use App\Actions\TransferFunds;
use App\Enums\AuditAction;
use App\Enums\CategoryType;
use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReverseLedgerOperationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_income_is_reversed_once_with_original_date_and_audit(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Income]);
        $original = LedgerEntry::factory()->for($user)->for($account, 'reference')->income()->create([
            'category_id' => $category->id,
            'amount' => '125.30',
            'occurred_at' => '2026-08-20 14:30:00',
        ]);
        $operationId = '29739e3a-1b38-43a1-9039-bfc5a381bc58';

        $first = app(ReverseLedgerOperation::class)->handle($user, $original->id, $operationId)->sole();
        $replayed = app(ReverseLedgerOperation::class)->handle($user, $original->id, $operationId)->sole();

        $this->assertSame($first->id, $replayed->id);
        $this->assertSame(LedgerEntryType::Expense, $first->type);
        $this->assertSame($original->operation_id, $first->reversal_of_operation_id);
        $this->assertSame('2026-08-20 14:30:00', $first->occurred_at->format('Y-m-d H:i:s'));
        $this->assertSame($category->id, $first->category_id);
        $this->assertDatabaseCount('ledger_entries', 2);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::Reversed->value, 'auditable_id' => $first->id]);
    }

    public function test_transfer_reversal_creates_opposite_legs_and_preserves_general_balance(): void
    {
        $user = User::factory()->create();
        $source = Account::factory()->for($user)->create();
        $destination = Account::factory()->for($user)->create();
        LedgerEntry::factory()->for($user)->for($source, 'reference')->income()->create(['amount' => '100.00']);
        $transfer = app(TransferFunds::class)->handle($user, $source, $destination, '60.00', fake()->uuid());

        $reversal = app(ReverseLedgerOperation::class)->handle($user, $transfer['out']->id, fake()->uuid());

        $this->assertCount(2, $reversal);
        $this->assertDatabaseHas('ledger_entries', [
            'reference_type' => $destination->getMorphClass(), 'reference_id' => $destination->id,
            'type' => LedgerEntryType::TransferOut->value, 'amount' => 60, 'reversal_of_operation_id' => $transfer['out']->operation_id,
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'reference_type' => $source->getMorphClass(), 'reference_id' => $source->id,
            'type' => LedgerEntryType::TransferIn->value, 'amount' => 60, 'reversal_of_operation_id' => $transfer['out']->operation_id,
        ]);
        $this->assertSame(2, $reversal->filter(fn (LedgerEntry $entry): bool => $entry->occurred_at->equalTo($transfer['out']->occurred_at))->count());
        $this->assertSame('100.00', $this->generalBalance($user));
    }

    public function test_transfer_reversal_requires_current_available_balance_without_partial_writes(): void
    {
        $user = User::factory()->create();
        $source = Account::factory()->for($user)->create();
        $destination = Account::factory()->for($user)->create();
        LedgerEntry::factory()->for($user)->for($source, 'reference')->income()->create(['amount' => '100.00']);
        $transfer = app(TransferFunds::class)->handle($user, $source, $destination, '60.00', fake()->uuid());
        LedgerEntry::factory()->for($user)->for($destination, 'reference')->expense()->create(['amount' => '10.00']);
        $beforeEntries = LedgerEntry::query()->count();
        $beforeAudits = AuditLog::query()->whereBelongsTo($user)->count();

        try {
            app(ReverseLedgerOperation::class)->handle($user, $transfer['out']->id, fake()->uuid());
            $this->fail('O estorno deveria respeitar o saldo disponível no destino atual.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $this->assertSame($beforeEntries, LedgerEntry::query()->count());
        $this->assertSame($beforeAudits, AuditLog::query()->whereBelongsTo($user)->count());
    }

    public function test_operation_cannot_be_reversed_twice_or_reverse_a_reversal(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $original = LedgerEntry::factory()->for($user)->for($account, 'reference')->income()->create();
        $reversal = app(ReverseLedgerOperation::class)->handle($user, $original->id, fake()->uuid())->sole();

        foreach ([$original, $reversal] as $entry) {
            try {
                app(ReverseLedgerOperation::class)->handle($user, $entry->id, fake()->uuid());
                $this->fail('A operação não deveria aceitar outro estorno.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('ledger_entry', $exception->errors());
            }
        }

        $this->assertDatabaseCount('ledger_entries', 2);
    }

    public function test_another_user_cannot_reverse_the_operation(): void
    {
        $owner = User::factory()->create();
        $original = LedgerEntry::factory()->for($owner)->income()->create();

        $this->expectException(ModelNotFoundException::class);
        app(ReverseLedgerOperation::class)->handle(User::factory()->create(), $original->id, fake()->uuid());
    }

    private function generalBalance(User $user): string
    {
        $positiveTypes = [LedgerEntryType::OpeningBalance->value, LedgerEntryType::Income->value, LedgerEntryType::TransferIn->value];
        $total = LedgerEntry::query()->whereBelongsTo($user)->get()->reduce(
            fn (float $balance, LedgerEntry $entry): float => $balance + (in_array($entry->type->value, $positiveTypes, true) ? (float) $entry->amount : -((float) $entry->amount)),
            0.0,
        );

        return number_format($total, 2, '.', '');
    }
}
