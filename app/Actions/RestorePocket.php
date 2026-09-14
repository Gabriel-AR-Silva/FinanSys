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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestorePocket
{
    public function __construct(
        private AuditRecorder $auditRecorder,
        private RefreshCurrentInternalAlert $refreshAlert,
    ) {}

    public function handle(User $user, int $pocketId): Pocket
    {
        return DB::transaction(function () use ($user, $pocketId): Pocket {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $accountId = Pocket::withTrashed()->whereBelongsTo($user)->whereKey($pocketId)->value('account_id');
            $account = Account::query()->whereBelongsTo($user)->where('status', RecordStatus::Active)->lockForUpdate()->findOrFail($accountId);
            $pocket = Pocket::withTrashed()->whereBelongsTo($user)->whereBelongsTo($account)->lockForUpdate()->findOrFail($pocketId);
            if (! $pocket->trashed() || $pocket->deletion_batch_id === null) {
                return $pocket;
            }
            if ($pocket->deleted_at->lt(now()->subDays((int) config('finansys.soft_delete_retention_days')))) {
                throw ValidationException::withMessages(['pocket' => 'O prazo de restauração expirou.']);
            }
            $batchId = $pocket->deletion_batch_id;
            $entries = LedgerEntry::onlyTrashed()->whereBelongsTo($user)->where('deletion_batch_id', $batchId)
                ->orderBy('id')->lockForUpdate()->get();
            $this->ensureReferencesAreAvailable($user, $pocket, $entries);
            $affectsPlanning = $entries->contains(fn (LedgerEntry $entry): bool => in_array(
                $entry->type,
                [LedgerEntryType::Income, LedgerEntryType::Expense, LedgerEntryType::Refund],
                true,
            ));
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

            if ($affectsPlanning) {
                $this->refreshAlert->handle($user);
            }

            return $pocket;
        }, 3);
    }

    /** @param Collection<int, LedgerEntry> $entries */
    private function ensureReferencesAreAvailable(User $user, Pocket $restoringPocket, Collection $entries): void
    {
        $references = $entries->map(fn (LedgerEntry $entry): array => [
            'type' => $entry->reference_type,
            'id' => (int) $entry->reference_id,
        ])->unique(fn (array $reference): string => $reference['type'].':'.$reference['id'])
            ->sortBy(fn (array $reference): string => $reference['type'].':'.str_pad((string) $reference['id'], 20, '0', STR_PAD_LEFT));

        foreach ($references as $reference) {
            if ($reference['type'] === $restoringPocket->getMorphClass() && $reference['id'] === $restoringPocket->id) {
                continue;
            }
            $available = match ($reference['type']) {
                'account' => Account::query()->whereBelongsTo($user)->where('status', RecordStatus::Active)
                    ->whereKey($reference['id'])->lockForUpdate()->first() !== null,
                'pocket' => Pocket::query()->whereBelongsTo($user)->where('status', RecordStatus::Active)
                    ->whereHas('account', fn ($query) => $query->whereBelongsTo($user)->where('status', RecordStatus::Active))
                    ->whereKey($reference['id'])->lockForUpdate()->first() !== null,
                default => false,
            };
            if (! $available) {
                throw ValidationException::withMessages(['pocket' => 'Restaure primeiro as outras contas ou caixinhas ligadas às transferências deste lote.']);
            }
        }
    }
}
