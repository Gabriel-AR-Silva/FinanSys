<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\LedgerEntryType;
use App\Enums\RecordStatus;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\Pocket;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeletePocket
{
    /**
     * Create a new class instance.
     */
    public function __construct(private AuditRecorder $auditRecorder) {}

    public function handle(User $user, int $pocketId): void
    {
        DB::transaction(function () use ($user, $pocketId): void {
            $accountId = Pocket::query()->whereBelongsTo($user)->whereKey($pocketId)->value('account_id');
            $account = Account::query()->whereBelongsTo($user)->where('status', RecordStatus::Active)->lockForUpdate()->findOrFail($accountId);
            $pocket = Pocket::query()->whereBelongsTo($user)->whereBelongsTo($account)->lockForUpdate()->findOrFail($pocketId);
            $batchId = (string) Str::uuid();
            $entries = LedgerEntry::query()
                ->whereBelongsTo($user)
                ->where('reference_type', $pocket->getMorphClass())
                ->where('reference_id', $pocket->id)
                ->get();
            $transferOperationIds = $entries
                ->filter(fn (LedgerEntry $entry): bool => in_array($entry->type, [LedgerEntryType::TransferIn, LedgerEntryType::TransferOut], true))
                ->pluck('operation_id')
                ->unique();

            if ($transferOperationIds->isNotEmpty()) {
                $transferEntries = LedgerEntry::query()
                    ->whereBelongsTo($user)
                    ->whereIn('operation_id', $transferOperationIds)
                    ->whereIn('type', [LedgerEntryType::TransferIn, LedgerEntryType::TransferOut])
                    ->get();
                $entries = $entries->merge($transferEntries)->unique('id');
            }

            $entries = LedgerEntry::query()
                ->whereBelongsTo($user)
                ->whereKey($entries->modelKeys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            foreach ($entries as $entry) {
                $before = $entry->attributesToArray();
                $entry->update(['deletion_batch_id' => $batchId]);
                $entry->delete();
                $this->auditRecorder->record($user, AuditAction::Deleted, $entry, $before);
            }
            $before = $pocket->attributesToArray();
            $pocket->update(['deletion_batch_id' => $batchId]);
            $pocket->delete();
            $this->auditRecorder->record($user, AuditAction::Deleted, $pocket, $before);
        });
    }
}
