<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\Pocket;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RestorePocket
{
    /**
     * Create a new class instance.
     */
    public function __construct(private AuditRecorder $auditRecorder) {}

    public function handle(User $user, int $pocketId): Pocket
    {
        return DB::transaction(function () use ($user, $pocketId): Pocket {
            $accountId = Pocket::onlyTrashed()->whereBelongsTo($user)->whereKey($pocketId)->value('account_id');
            $account = Account::query()->whereBelongsTo($user)->where('status', RecordStatus::Active)->lockForUpdate()->findOrFail($accountId);
            $pocket = Pocket::onlyTrashed()->whereBelongsTo($user)->whereBelongsTo($account)->lockForUpdate()->findOrFail($pocketId);
            if ($pocket->deletion_batch_id === null) {
                throw new NotFoundHttpException;
            }
            if ($pocket->deleted_at->lt(now()->subDays((int) config('finansys.soft_delete_retention_days')))) {
                throw ValidationException::withMessages(['pocket' => 'O prazo de restauração expirou.']);
            }
            $batchId = $pocket->deletion_batch_id;
            $entries = LedgerEntry::onlyTrashed()->whereBelongsTo($user)->where('deletion_batch_id', $batchId)->get();
            $before = $pocket->attributesToArray();
            $pocket->restore();
            $pocket->update(['deletion_batch_id' => null]);
            $this->auditRecorder->record($user, AuditAction::Restored, $pocket, $before);
            foreach ($entries as $entry) {
                $before = $entry->attributesToArray();
                $entry->restore();
                $entry->update(['deletion_batch_id' => null]);
                $this->auditRecorder->record($user, AuditAction::Restored, $entry, $before);
            }

            return $pocket;
        });
    }
}
