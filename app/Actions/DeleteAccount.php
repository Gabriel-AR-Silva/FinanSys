<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\LedgerEntryType;
use App\Enums\ReceiptForecastUnlinkReason;
use App\Models\Account;
use App\Models\ExpenseRefund;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeleteAccount
{
    public function __construct(
        private AuditRecorder $auditRecorder,
        private DetachReceiptForecast $detachReceiptForecast,
    ) {}

    public function handle(User $user, int $accountId): void
    {
        DB::transaction(function () use ($user, $accountId): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $account = Account::withTrashed()->whereBelongsTo($user)->lockForUpdate()->findOrFail($accountId);
            if ($account->trashed()) {
                return;
            }
            $batchId = (string) Str::uuid();
            $pockets = $account->pockets()->get();
            $entries = LedgerEntry::query()->whereBelongsTo($user)
                ->where(function ($query) use ($account, $pockets): void {
                    $query->where(fn ($nested) => $nested->where('reference_type', 'account')->where('reference_id', $account->id));
                    if ($pockets->isNotEmpty()) {
                        $query->orWhere(fn ($nested) => $nested->where('reference_type', 'pocket')->whereIn('reference_id', $pockets->modelKeys()));
                    }
                })->get();

            if (ExpenseRefund::query()->whereBelongsTo($user)->active()
                ->whereIn('expense_ledger_entry_id', $entries->modelKeys())->exists()) {
                throw ValidationException::withMessages(['account' => 'Desfaça os reembolsos ativos antes de excluir esta conta.']);
            }

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
                $this->detachReceiptForecast->handle($user, $entry, ReceiptForecastUnlinkReason::LedgerDeleted);
                $before = $entry->attributesToArray();
                $entry->update(['deletion_batch_id' => $batchId]);
                $entry->delete();
                $this->auditRecorder->record($user, AuditAction::Deleted, $entry, $before);
            }

            foreach ($pockets as $pocket) {
                $before = $pocket->attributesToArray();
                $pocket->update(['deletion_batch_id' => $batchId]);
                $pocket->delete();
                $this->auditRecorder->record($user, AuditAction::Deleted, $pocket, $before);
            }

            $before = $account->attributesToArray();
            $account->update(['deletion_batch_id' => $batchId]);
            $account->delete();
            $this->auditRecorder->record($user, AuditAction::Deleted, $account, $before);
        });
    }
}
