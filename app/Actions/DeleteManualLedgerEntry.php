<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\LedgerEntryType;
use App\Enums\ReceiptForecastUnlinkReason;
use App\Models\ExpenseCommitmentPayment;
use App\Models\ExpenseRefund;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DeleteManualLedgerEntry
{
    public function __construct(
        private AuditRecorder $auditRecorder,
        private DetachReceiptForecast $detachReceiptForecast,
        private RefreshCurrentInternalAlert $refreshAlert,
    ) {}

    public function handle(User $user, int $entryId): void
    {
        DB::transaction(function () use ($user, $entryId): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $entry = LedgerEntry::query()->whereBelongsTo($user)->lockForUpdate()->findOrFail($entryId);
            $belongsToReversalChain = $entry->reversal_of_operation_id !== null
                || LedgerEntry::query()->whereBelongsTo($user)->where('reversal_of_operation_id', $entry->operation_id)->exists();
            if ($belongsToReversalChain || ! in_array($entry->type, [LedgerEntryType::Income, LedgerEntryType::Expense], true)) {
                throw new NotFoundHttpException;
            }
            if (ExpenseCommitmentPayment::query()->where('user_id', $user->id)->where('ledger_entry_id', $entry->id)->exists()) {
                throw ValidationException::withMessages(['ledger_entry' => 'Este pagamento pertence a um compromisso. Corrija o compromisso pelo fluxo específico.']);
            }
            if ($entry->type === LedgerEntryType::Expense
                && ExpenseRefund::query()->whereBelongsTo($user)->active()->whereBelongsTo($entry, 'expenseEntry')->exists()) {
                throw ValidationException::withMessages(['ledger_entry' => 'Desfaça os reembolsos ativos antes de excluir esta despesa.']);
            }
            $this->detachReceiptForecast->handle($user, $entry, ReceiptForecastUnlinkReason::LedgerDeleted);
            $before = $entry->attributesToArray();
            $entry->update(['deletion_batch_id' => null]);
            $entry->delete();
            $this->auditRecorder->record($user, AuditAction::Deleted, $entry, $before);
            $this->refreshAlert->handle($user);
        });
    }
}
