<?php

namespace App\Actions;

use App\Domain\Ledger\ReferenceResolver;
use App\Enums\AuditAction;
use App\Enums\LedgerEntryReferenceType;
use App\Enums\LedgerEntryType;
use App\Enums\RecordStatus;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\Pocket;
use App\Models\User;
use App\Support\AuditRecorder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReverseLedgerOperation
{
    public function __construct(
        private AuditRecorder $auditRecorder,
        private ReferenceResolver $referenceResolver,
    ) {}

    /** @return Collection<int, LedgerEntry> */
    public function handle(User $user, int $entryId, string $operationId): Collection
    {
        if (! Str::isUuid($operationId)) {
            throw ValidationException::withMessages(['operation_id' => 'Informe uma chave de operação UUID válida.']);
        }

        try {
            return DB::transaction(function () use ($user, $entryId, $operationId): Collection {
                $selected = LedgerEntry::query()->whereBelongsTo($user)->lockForUpdate()->findOrFail($entryId);
                $originals = LedgerEntry::query()->whereBelongsTo($user)->where('operation_id', $selected->operation_id)->lockForUpdate()->get();
                $existingReplay = LedgerEntry::query()->whereBelongsTo($user)->where('operation_id', $operationId)->get();
                if ($existingReplay->isNotEmpty()) {
                    return $this->validateReplay($existingReplay, $originals);
                }

                $this->ensureReversible($selected, $originals);
                if (LedgerEntry::query()->whereBelongsTo($user)->where('reversal_of_operation_id', $selected->operation_id)->exists()) {
                    throw ValidationException::withMessages(['ledger_entry' => 'Esta operação já foi estornada.']);
                }

                return $this->isManual($originals)
                    ? collect([$this->reverseManual($user, $originals->sole(), $operationId)])
                    : $this->reverseTransfer($user, $originals, $operationId);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $original = LedgerEntry::query()->whereBelongsTo($user)->find($entryId);
            if ($original === null) {
                throw new NotFoundHttpException;
            }

            $originals = LedgerEntry::query()->whereBelongsTo($user)->where('operation_id', $original->operation_id)->get();
            $existingReplay = LedgerEntry::query()->whereBelongsTo($user)->where('operation_id', $operationId)->get();
            if ($existingReplay->isNotEmpty()) {
                return $this->validateReplay($existingReplay, $originals);
            }

            throw ValidationException::withMessages(['ledger_entry' => 'Esta operação já foi estornada.']);
        }
    }

    /** @param Collection<int, LedgerEntry> $originals */
    private function ensureReversible(LedgerEntry $selected, Collection $originals): void
    {
        if ($selected->reversal_of_operation_id !== null) {
            throw ValidationException::withMessages(['ledger_entry' => 'Um estorno não pode ser estornado novamente.']);
        }

        if ($this->isManual($originals)) {
            return;
        }

        $types = $originals->pluck('type')->map(fn (LedgerEntryType $type): string => $type->value)->sort()->values()->all();
        if ($originals->count() !== 2 || $types !== [LedgerEntryType::TransferIn->value, LedgerEntryType::TransferOut->value]) {
            throw ValidationException::withMessages(['ledger_entry' => 'Esta operação não pode ser estornada.']);
        }
    }

    /** @param Collection<int, LedgerEntry> $originals */
    private function isManual(Collection $originals): bool
    {
        return $originals->count() === 1
            && in_array($originals->sole()->type, [LedgerEntryType::Income, LedgerEntryType::Expense], true);
    }

    private function reverseManual(User $user, LedgerEntry $original, string $operationId): LedgerEntry
    {
        $reference = $this->referenceResolver->resolveActive($user, LedgerEntryReferenceType::from($original->reference_type), (int) $original->reference_id);
        if (! $reference instanceof Account) {
            throw ValidationException::withMessages(['ledger_entry' => 'A conta do lançamento não está disponível.']);
        }

        $entry = $reference->ledgerEntries()->create([
            'user_id' => $user->getKey(),
            'category_id' => $original->category_id,
            'type' => $original->type === LedgerEntryType::Income ? LedgerEntryType::Expense : LedgerEntryType::Income,
            'amount' => $original->amount,
            'operation_id' => $operationId,
            'reversal_of_operation_id' => $original->operation_id,
            'occurred_at' => $original->occurred_at,
            'description' => $original->description,
        ]);
        $this->auditRecorder->record($user, AuditAction::Reversed, $entry);

        return $entry;
    }

    /**
     * @param  Collection<int, LedgerEntry>  $originals
     * @return Collection<int, LedgerEntry>
     */
    private function reverseTransfer(User $user, Collection $originals, string $operationId): Collection
    {
        $originalOut = $originals->firstWhere('type', LedgerEntryType::TransferOut);
        $originalIn = $originals->firstWhere('type', LedgerEntryType::TransferIn);
        if (! $originalOut instanceof LedgerEntry || ! $originalIn instanceof LedgerEntry) {
            throw ValidationException::withMessages(['ledger_entry' => 'Esta transferência não pode ser estornada.']);
        }

        $source = $this->referenceResolver->resolveActive($user, LedgerEntryReferenceType::from($originalIn->reference_type), (int) $originalIn->reference_id, 'source_id');
        $destination = $this->referenceResolver->resolveActive($user, LedgerEntryReferenceType::from($originalOut->reference_type), (int) $originalOut->reference_id, 'destination_id');
        [$source, $destination] = $this->lockReferences($user, $source, $destination);
        $amount = BigDecimal::of($originalOut->amount)->toScale(2, RoundingMode::Unnecessary);
        if ($this->balanceOf($source)->isLessThan($amount)) {
            throw ValidationException::withMessages(['amount' => 'O saldo da origem é insuficiente para estornar esta transferência.']);
        }

        $common = [
            'user_id' => $user->getKey(),
            'amount' => (string) $amount,
            'operation_id' => $operationId,
            'reversal_of_operation_id' => $originalOut->operation_id,
            'occurred_at' => $originalOut->occurred_at,
        ];
        $out = $source->ledgerEntries()->create($common + ['type' => LedgerEntryType::TransferOut]);
        $in = $destination->ledgerEntries()->create($common + ['type' => LedgerEntryType::TransferIn]);
        $this->auditRecorder->record($user, AuditAction::Reversed, $out);
        $this->auditRecorder->record($user, AuditAction::Reversed, $in);

        return collect([$out, $in]);
    }

    /** @return array{Account|Pocket, Account|Pocket} */
    private function lockReferences(User $user, Account|Pocket $source, Account|Pocket $destination): array
    {
        $references = collect([$source, $destination])
            ->sortBy(fn (Account|Pocket $reference): string => $reference->getMorphClass().':'.str_pad((string) $reference->getKey(), 20, '0', STR_PAD_LEFT))
            ->mapWithKeys(function (Account|Pocket $reference) use ($user): array {
                $query = $reference::query()->whereBelongsTo($user)->where('status', RecordStatus::Active);
                if ($reference instanceof Pocket) {
                    $query->whereHas('account', fn ($account) => $account->whereBelongsTo($user)->where('status', RecordStatus::Active));
                }
                $locked = $query->lockForUpdate()->find($reference->getKey());
                if ($locked === null) {
                    throw ValidationException::withMessages(['ledger_entry' => 'Uma referência da transferência não está disponível.']);
                }

                return [$reference->getMorphClass().':'.$reference->getKey() => $locked];
            });

        return [
            $references->get($source->getMorphClass().':'.$source->getKey()),
            $references->get($destination->getMorphClass().':'.$destination->getKey()),
        ];
    }

    private function balanceOf(Account|Pocket $reference): BigDecimal
    {
        $positiveTypes = [LedgerEntryType::OpeningBalance->value, LedgerEntryType::Income->value, LedgerEntryType::TransferIn->value];
        $placeholders = implode(', ', array_fill(0, count($positiveTypes), '?'));
        $balance = LedgerEntry::query()
            ->where('user_id', $reference->user_id)
            ->where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->getKey())
            ->selectRaw("COALESCE(SUM(CASE WHEN type IN ({$placeholders}) THEN amount ELSE -amount END), 0) AS balance", $positiveTypes)
            ->value('balance');

        return BigDecimal::of((string) $balance)->toScale(2, RoundingMode::Unnecessary);
    }

    /**
     * @param  Collection<int, LedgerEntry>  $existing
     * @param  Collection<int, LedgerEntry>  $originals
     * @return Collection<int, LedgerEntry>
     */
    private function validateReplay(Collection $existing, Collection $originals): Collection
    {
        $original = $originals->first();
        if (! $original instanceof LedgerEntry
            || $existing->contains(fn (LedgerEntry $entry): bool => $entry->reversal_of_operation_id !== $original->operation_id)
            || $existing->count() !== ($this->isManual($originals) ? 1 : 2)) {
            throw ValidationException::withMessages(['operation_id' => 'Esta chave de operação já foi usada com dados diferentes.']);
        }

        return $existing;
    }
}
