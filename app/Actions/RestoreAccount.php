<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\Pocket;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestoreAccount
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    public function handle(User $user, int $accountId): Account
    {
        return DB::transaction(function () use ($user, $accountId): Account {
            $account = Account::withTrashed()->whereBelongsTo($user)->lockForUpdate()->findOrFail($accountId);
            if (! $account->trashed() || $account->deletion_batch_id === null) {
                return $account;
            }
            $currentTime = now();
            $restorationThreshold = $currentTime->copy()
                ->subDays((int) config('finansys.soft_delete_retention_days'));

            if ($account->deleted_at->lt($restorationThreshold)) {
                throw ValidationException::withMessages(['account' => 'O prazo de restauração expirou.']);
            }

            $batchId = $account->deletion_batch_id;
            $pockets = Pocket::onlyTrashed()->whereBelongsTo($user)->where('deletion_batch_id', $batchId)->get();
            $entries = LedgerEntry::onlyTrashed()->whereBelongsTo($user)->where('deletion_batch_id', $batchId)->get();

            $before = $account->attributesToArray();
            $account->restore();
            $account->update(['deletion_batch_id' => null]);
            $this->auditRecorder->record($user, AuditAction::Restored, $account, $before);

            foreach ($pockets as $pocket) {
                $before = $pocket->attributesToArray();
                $pocket->restore();
                $pocket->update(['deletion_batch_id' => null]);
                $this->auditRecorder->record($user, AuditAction::Restored, $pocket, $before);
            }
            foreach ($entries as $entry) {
                $before = $entry->attributesToArray();
                $entry->restore();
                $entry->update(['deletion_batch_id' => null]);
                $this->auditRecorder->record($user, AuditAction::Restored, $entry, $before);
            }

            return $account;
        });
    }
}
