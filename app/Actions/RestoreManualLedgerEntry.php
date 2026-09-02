<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\LedgerEntryType;
use App\Enums\RecordStatus;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RestoreManualLedgerEntry
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    public function handle(User $user, int $entryId): LedgerEntry
    {
        return DB::transaction(function () use ($user, $entryId): LedgerEntry {
            $entry = LedgerEntry::onlyTrashed()->whereBelongsTo($user)->lockForUpdate()->findOrFail($entryId);
            $belongsToReversalChain = $entry->reversal_of_operation_id !== null
                || LedgerEntry::withTrashed()->whereBelongsTo($user)->where('reversal_of_operation_id', $entry->operation_id)->exists();
            if ($belongsToReversalChain || $entry->deletion_batch_id !== null || ! in_array($entry->type, [LedgerEntryType::Income, LedgerEntryType::Expense], true)) {
                throw new NotFoundHttpException;
            }
            if ($entry->deleted_at->lt(now()->subDays((int) config('finansys.soft_delete_retention_days')))) {
                throw ValidationException::withMessages(['ledger_entry' => 'O prazo de restauração expirou.']);
            }
            $accountExists = Account::query()->whereBelongsTo($user)->where('status', RecordStatus::Active)->whereKey($entry->reference_id)->exists();
            if ($entry->reference_type !== (new Account)->getMorphClass() || ! $accountExists) {
                throw ValidationException::withMessages(['ledger_entry' => 'A conta do lançamento não está disponível.']);
            }
            $before = $entry->attributesToArray();
            $entry->restore();
            $this->auditRecorder->record($user, AuditAction::Restored, $entry, $before);

            return $entry;
        });
    }
}
