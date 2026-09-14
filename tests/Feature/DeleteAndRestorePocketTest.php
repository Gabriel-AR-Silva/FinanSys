<?php

namespace Tests\Feature;

use App\Actions\DeleteAccount;
use App\Actions\DeletePocket;
use App\Actions\RestoreAccount;
use App\Actions\RestorePocket;
use App\Actions\TransferFunds;
use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\Pocket;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DeleteAndRestorePocketTest extends TestCase
{
    use RefreshDatabase;

    public function test_restores_only_active_entries_deleted_in_the_same_batch(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $pocket = Pocket::factory()->for($user)->for($account)->create();
        $old = LedgerEntry::factory()->for($user)->create(['reference_type' => 'pocket', 'reference_id' => $pocket->id]);
        $active = LedgerEntry::factory()->for($user)->create(['reference_type' => 'pocket', 'reference_id' => $pocket->id]);
        $old->delete();

        app(DeletePocket::class)->handle($user, $pocket->id);
        app(RestorePocket::class)->handle($user, $pocket->id);

        $this->assertNotSoftDeleted($pocket);
        $this->assertNotSoftDeleted($active);
        $this->assertSoftDeleted($old);
    }

    public function test_restore_replay_is_idempotent_and_does_not_duplicate_audit_records(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $pocket = Pocket::factory()->for($user)->for($account)->create();
        LedgerEntry::factory()->for($user)->for($pocket, 'reference')->create();
        app(DeletePocket::class)->handle($user, $pocket->id);

        app(RestorePocket::class)->handle($user, $pocket->id);
        $auditCount = DB::table('audit_logs')->count();
        $replayed = app(RestorePocket::class)->handle($user, $pocket->id);

        $this->assertFalse($replayed->trashed());
        $this->assertSame($auditCount, DB::table('audit_logs')->count());
    }

    public function test_exact_limit_is_allowed_and_one_microsecond_older_is_rejected(): void
    {
        $this->travelTo(now()->setMicrosecond(456789));
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $allowed = Pocket::factory()->for($user)->for($account)->create();
        app(DeletePocket::class)->handle($user, $allowed->id);
        DB::table('pockets')->where('id', $allowed->id)->update(['deleted_at' => now()->subDays(30)->format('Y-m-d H:i:s.u')]);
        app(RestorePocket::class)->handle($user, $allowed->id);
        $this->assertNotSoftDeleted($allowed);
        $expired = Pocket::factory()->for($user)->for($account)->create();
        app(DeletePocket::class)->handle($user, $expired->id);
        DB::table('pockets')->where('id', $expired->id)->update(['deleted_at' => now()->subDays(30)->subMicrosecond()->format('Y-m-d H:i:s.u')]);

        $this->expectException(ValidationException::class);
        app(RestorePocket::class)->handle($user, $expired->id);
    }

    public function test_account_cascade_is_not_individually_restorable(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $pocket = Pocket::factory()->for($user)->for($account)->create();
        app(DeleteAccount::class)->handle($user, $account->id);

        try {
            app(RestorePocket::class)->handle($user, $pocket->id);
            $this->fail('Expected the account cascade to block individual restoration.');
        } catch (ModelNotFoundException) {
            $this->assertSoftDeleted($pocket);
        }
        app(RestoreAccount::class)->handle($user, $account->id);
        $this->assertNotSoftDeleted($pocket);
    }

    public function test_pocket_cascade_deletes_and_restores_both_transfer_legs_without_touching_non_transfer_entry(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $pocket = Pocket::factory()->for($user)->for($account)->create();
        $operationId = fake()->uuid();
        $out = LedgerEntry::factory()->for($user)->for($account, 'reference')->create([
            'type' => LedgerEntryType::TransferOut,
            'operation_id' => $operationId,
        ]);
        $in = LedgerEntry::factory()->for($user)->for($pocket, 'reference')->create([
            'type' => LedgerEntryType::TransferIn,
            'operation_id' => $operationId,
        ]);
        $unrelated = LedgerEntry::factory()->for($user)->for($account, 'reference')->income()->create([
            'operation_id' => $operationId,
        ]);

        app(DeletePocket::class)->handle($user, $pocket->id);

        $batchId = $pocket->fresh()->deletion_batch_id;
        $this->assertNotNull($batchId);
        $this->assertSoftDeleted($out);
        $this->assertSoftDeleted($in);
        $this->assertSame($batchId, $out->fresh()->deletion_batch_id);
        $this->assertSame($batchId, $in->fresh()->deletion_batch_id);
        $this->assertNotSoftDeleted($unrelated);
        $this->assertNull($unrelated->fresh()->deletion_batch_id);

        app(RestorePocket::class)->handle($user, $pocket->id);

        $this->assertNotSoftDeleted($out);
        $this->assertNotSoftDeleted($in);
        $this->assertNull($out->fresh()->deletion_batch_id);
        $this->assertNull($in->fresh()->deletion_batch_id);
        $this->assertNotSoftDeleted($unrelated);
    }

    public function test_restore_rejects_a_transfer_whose_counterparty_was_deleted_later(): void
    {
        $user = User::factory()->create();
        $parent = Account::factory()->for($user)->create();
        $counterparty = Account::factory()->for($user)->create();
        $pocket = Pocket::factory()->for($user)->for($parent)->create();
        LedgerEntry::factory()->openingBalance()->for($user)->for($counterparty, 'reference')->create(['amount' => '100.00']);
        $transfer = app(TransferFunds::class)->handle($user, $counterparty, $pocket, '50.00');

        app(DeletePocket::class)->handle($user, $pocket->id);
        app(DeleteAccount::class)->handle($user, $counterparty->id);

        try {
            app(RestorePocket::class)->handle($user, $pocket->id);
            $this->fail('A contraparte excluída deveria bloquear a restauração.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('pocket', $exception->errors());
        }

        $this->assertSoftDeleted($pocket);
        $this->assertSame(2, LedgerEntry::onlyTrashed()->where('operation_id', $transfer['out']->operation_id)->count());
    }
}
