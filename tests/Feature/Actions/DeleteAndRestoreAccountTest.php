<?php

namespace Tests\Feature\Actions;

use App\Actions\DeleteAccount;
use App\Actions\RestoreAccount;
use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\Pocket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DeleteAndRestoreAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_pockets_and_entries_are_deleted_and_restored_as_one_batch(): void
    {
        $user = User::factory()->create();
        $account = Account::query()->create(['user_id' => $user->id, 'name' => 'Principal']);
        $pocket = Pocket::query()->create(['user_id' => $user->id, 'account_id' => $account->id, 'name' => 'Reserva']);
        $accountEntry = $account->ledgerEntries()->create([
            'user_id' => $user->id,
            'type' => LedgerEntryType::Income,
            'amount' => '100.00',
            'operation_id' => fake()->uuid(),
            'occurred_at' => now(),
        ]);
        $pocketEntry = $pocket->ledgerEntries()->create([
            'user_id' => $user->id,
            'type' => LedgerEntryType::Expense,
            'amount' => '10.00',
            'operation_id' => fake()->uuid(),
            'occurred_at' => now(),
        ]);

        app(DeleteAccount::class)->handle($user, $account->id);

        $batchId = Account::withTrashed()->findOrFail($account->id)->deletion_batch_id;
        $this->assertNotNull($batchId);
        $this->assertSame($batchId, Pocket::withTrashed()->findOrFail($pocket->id)->deletion_batch_id);
        $this->assertSame($batchId, LedgerEntry::withTrashed()->findOrFail($accountEntry->id)->deletion_batch_id);
        $this->assertSame($batchId, LedgerEntry::withTrashed()->findOrFail($pocketEntry->id)->deletion_batch_id);

        app(RestoreAccount::class)->handle($user, $account->id);

        $this->assertFalse($account->fresh()->trashed());
        $this->assertFalse($pocket->fresh()->trashed());
        $this->assertFalse($accountEntry->fresh()->trashed());
        $this->assertFalse($pocketEntry->fresh()->trashed());
    }

    public function test_restoration_at_the_exact_thirty_day_limit_with_microseconds_is_allowed(): void
    {
        $this->travelTo(now()->setMicrosecond(456789));
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        app(DeleteAccount::class)->handle($user, $account->id);
        $restorationThreshold = now()->subDays(30);
        DB::table('accounts')->where('id', $account->id)->update([
            'deleted_at' => $restorationThreshold->format('Y-m-d H:i:s.u'),
        ]);

        app(RestoreAccount::class)->handle($user, $account->id);

        $this->assertNotSoftDeleted($account);
    }

    public function test_restoration_one_microsecond_before_the_threshold_is_rejected(): void
    {
        $this->travelTo(now()->setMicrosecond(456789));
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        app(DeleteAccount::class)->handle($user, $account->id);
        $expiredAt = now()->subDays(30)->subMicrosecond();
        DB::table('accounts')->where('id', $account->id)->update([
            'deleted_at' => $expiredAt->format('Y-m-d H:i:s.u'),
        ]);

        $this->expectException(ValidationException::class);

        app(RestoreAccount::class)->handle($user, $account->id);
    }

    public function test_restoration_does_not_revive_entries_deleted_before_the_account_batch(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $entry = LedgerEntry::factory()->for($user)->create([
            'reference_type' => 'account',
            'reference_id' => $account->id,
        ]);
        $entry->delete();

        app(DeleteAccount::class)->handle($user, $account->id);
        app(RestoreAccount::class)->handle($user, $account->id);

        $this->assertSoftDeleted($entry);
        $this->assertNull($entry->fresh()->deletion_batch_id);
    }

    public function test_account_cascade_deletes_and_restores_both_transfer_legs_without_touching_another_user(): void
    {
        $user = User::factory()->create();
        $source = Account::factory()->for($user)->create();
        $destination = Account::factory()->for($user)->create();
        $operationId = fake()->uuid();
        $out = LedgerEntry::factory()->for($user)->for($source, 'reference')->create([
            'type' => LedgerEntryType::TransferOut,
            'operation_id' => $operationId,
        ]);
        $in = LedgerEntry::factory()->for($user)->for($destination, 'reference')->create([
            'type' => LedgerEntryType::TransferIn,
            'operation_id' => $operationId,
        ]);
        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->for($otherUser)->create();
        $foreignEntry = LedgerEntry::factory()->for($otherUser)->for($otherAccount, 'reference')->create([
            'type' => LedgerEntryType::TransferOut,
            'operation_id' => $operationId,
        ]);

        app(DeleteAccount::class)->handle($user, $source->id);

        $batchId = $source->fresh()->deletion_batch_id;
        $this->assertNotNull($batchId);
        $this->assertSoftDeleted($out);
        $this->assertSoftDeleted($in);
        $this->assertSame($batchId, $out->fresh()->deletion_batch_id);
        $this->assertSame($batchId, $in->fresh()->deletion_batch_id);
        $this->assertNotSoftDeleted($foreignEntry);
        $this->assertNull($foreignEntry->fresh()->deletion_batch_id);

        app(RestoreAccount::class)->handle($user, $source->id);

        $this->assertNotSoftDeleted($out);
        $this->assertNotSoftDeleted($in);
        $this->assertNull($out->fresh()->deletion_batch_id);
        $this->assertNull($in->fresh()->deletion_batch_id);
        $this->assertNotSoftDeleted($foreignEntry);
    }
}
