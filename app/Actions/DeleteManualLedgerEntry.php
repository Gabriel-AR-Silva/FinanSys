<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DeleteManualLedgerEntry
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    public function handle(User $user, int $entryId): void
    {
        DB::transaction(function () use ($user, $entryId): void {
            $entry = LedgerEntry::query()->whereBelongsTo($user)->lockForUpdate()->findOrFail($entryId);
            $belongsToReversalChain = $entry->reversal_of_operation_id !== null
                || LedgerEntry::query()->whereBelongsTo($user)->where('reversal_of_operation_id', $entry->operation_id)->exists();
            if ($belongsToReversalChain || ! in_array($entry->type, [LedgerEntryType::Income, LedgerEntryType::Expense], true)) {
                throw new NotFoundHttpException;
            }
            $before = $entry->attributesToArray();
            $entry->update(['deletion_batch_id' => null]);
            $entry->delete();
            $this->auditRecorder->record($user, AuditAction::Deleted, $entry, $before);
        });
    }
}
