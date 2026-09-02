<?php

namespace App\Console\Commands;

use App\Enums\AuditAction;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\Pocket;
use App\Support\AuditRecorder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

#[Signature('finansys:purge-expired')]
#[Description('Remove definitivamente registros financeiros cujo prazo de restauração expirou')]
class PurgeExpiredFinancialRecords extends Command
{
    public function __construct(private AuditRecorder $auditRecorder)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('finansys.soft_delete_retention_days'));
        $purged = 0;

        Account::onlyTrashed()->where('deleted_at', '<', $cutoff)->orderBy('id')
            ->eachById(function (Account $account) use (&$purged): void {
                $purged += DB::transaction(fn (): int => $this->purgeAccount($account->id));
            });

        Pocket::onlyTrashed()->where('deleted_at', '<', $cutoff)->orderBy('id')
            ->eachById(function (Pocket $pocket) use (&$purged): void {
                $purged += DB::transaction(fn (): int => $this->purgePocket($pocket->id));
            });

        LedgerEntry::onlyTrashed()->whereNull('deletion_batch_id')->where('deleted_at', '<', $cutoff)->orderBy('id')
            ->eachById(function (LedgerEntry $entry) use (&$purged): void {
                $purged += DB::transaction(fn (): int => $this->purgeManualEntry($entry->id));
            });

        $this->info("Registros removidos definitivamente: {$purged}.");

        return self::SUCCESS;
    }

    private function purgeAccount(int $accountId): int
    {
        $account = Account::onlyTrashed()->lockForUpdate()->find($accountId);
        if ($account === null) {
            return 0;
        }

        $batchId = $this->requiredBatchId($account);
        $entries = LedgerEntry::onlyTrashed()->where('user_id', $account->user_id)->where('deletion_batch_id', $batchId)->lockForUpdate()->get();
        $pockets = Pocket::onlyTrashed()->where('user_id', $account->user_id)->where('deletion_batch_id', $batchId)->lockForUpdate()->get();
        $count = 1 + $entries->count() + $pockets->count();

        foreach ($entries as $entry) {
            $this->purge($entry);
        }
        foreach ($pockets as $pocket) {
            $this->purge($pocket);
        }
        $this->purge($account);

        return $count;
    }

    private function purgePocket(int $pocketId): int
    {
        $pocket = Pocket::onlyTrashed()->lockForUpdate()->find($pocketId);
        if ($pocket === null) {
            return 0;
        }

        $batchId = $this->requiredBatchId($pocket);
        $entries = LedgerEntry::onlyTrashed()->where('user_id', $pocket->user_id)->where('deletion_batch_id', $batchId)->lockForUpdate()->get();
        $count = 1 + $entries->count();
        foreach ($entries as $entry) {
            $this->purge($entry);
        }
        $this->purge($pocket);

        return $count;
    }

    private function purgeManualEntry(int $entryId): int
    {
        $entry = LedgerEntry::onlyTrashed()->whereNull('deletion_batch_id')->lockForUpdate()->find($entryId);
        if ($entry === null) {
            return 0;
        }
        $this->purge($entry);

        return 1;
    }

    private function requiredBatchId(Account|Pocket $model): string
    {
        if (! is_string($model->deletion_batch_id) || $model->deletion_batch_id === '') {
            throw new RuntimeException("Registro {$model->getMorphClass()}:{$model->getKey()} não possui lote de exclusão.");
        }

        return $model->deletion_batch_id;
    }

    private function purge(Model $model): void
    {
        $this->auditRecorder->record($model->user, AuditAction::Purged, $model);
        $model->forceDelete();
    }
}
