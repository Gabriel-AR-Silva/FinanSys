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
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransferFunds
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    /** @return array{out: LedgerEntry, in: LedgerEntry} */
    public function handle(User $user, Account|Pocket $source, Account|Pocket $destination, string $value, ?string $operationId = null): array
    {
        if ($source->is($destination)) {
            throw ValidationException::withMessages(['destination' => 'A origem e o destino devem ser diferentes.']);
        }

        if ((int) $source->user_id !== (int) $user->id || (int) $destination->user_id !== (int) $user->id) {
            throw ValidationException::withMessages(['destination' => 'A referência não existe para o usuário autenticado.']);
        }

        try {
            $amount = BigDecimal::of($value)->toScale(2, RoundingMode::Unnecessary);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['amount' => 'Informe um valor válido com no máximo duas casas decimais.']);
        }

        if (! $amount->isPositive()) {
            throw ValidationException::withMessages(['amount' => 'O valor deve ser maior que zero.']);
        }

        $operationId ??= (string) Str::uuid();
        if (! Str::isUuid($operationId)) {
            throw ValidationException::withMessages(['operation_id' => 'Informe uma chave de operação UUID válida.']);
        }

        try {
            return DB::transaction(function () use ($user, $source, $destination, $amount, $operationId): array {
                $existingEntries = LedgerEntry::query()
                    ->withTrashed()
                    ->whereBelongsTo($user)
                    ->where('operation_id', $operationId)
                    ->get()
                    ->keyBy(fn (LedgerEntry $entry): string => $entry->type->value);

                if ($existingEntries->isNotEmpty()) {
                    return $this->validateExistingOperation($existingEntries, $source, $destination, $amount);
                }

                [$lockedSource, $lockedDestination] = $this->lockReferences($user, $source, $destination);
                if ($this->balanceOf($lockedSource)->isLessThan($amount)) {
                    throw ValidationException::withMessages([
                        'amount' => 'O saldo da origem é insuficiente para esta transferência.',
                    ]);
                }

                $common = ['user_id' => $user->id, 'amount' => (string) $amount, 'operation_id' => $operationId, 'occurred_at' => now()];
                $out = $lockedSource->ledgerEntries()->create($common + ['type' => LedgerEntryType::TransferOut]);
                $in = $lockedDestination->ledgerEntries()->create($common + ['type' => LedgerEntryType::TransferIn]);
                $this->auditRecorder->record($user, AuditAction::Created, $out);
                $this->auditRecorder->record($user, AuditAction::Created, $in);

                return ['out' => $out, 'in' => $in];
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $existingEntries = LedgerEntry::query()
                ->withTrashed()
                ->whereBelongsTo($user)
                ->where('operation_id', $operationId)
                ->get()
                ->keyBy(fn (LedgerEntry $entry): string => $entry->type->value);

            return $this->validateExistingOperation($existingEntries, $source, $destination, $amount);
        }
    }

    /** @return array{Account|Pocket, Account|Pocket} */
    private function lockReferences(User $user, Account|Pocket $source, Account|Pocket $destination): array
    {
        $references = collect([$source, $destination])
            ->sortBy(fn (Account|Pocket $reference): string => $reference->getMorphClass().':'.str_pad((string) $reference->getKey(), 20, '0', STR_PAD_LEFT))
            ->mapWithKeys(function (Account|Pocket $reference) use ($user, $source): array {
                $query = $reference::query()
                    ->whereBelongsTo($user)
                    ->where('status', RecordStatus::Active);

                if ($reference instanceof Pocket) {
                    $query->whereHas('account', fn ($account) => $account
                        ->whereBelongsTo($user)
                        ->where('status', RecordStatus::Active));
                }

                $locked = $query->lockForUpdate()->find($reference->getKey());
                if ($locked === null) {
                    throw ValidationException::withMessages([
                        $reference->is($source) ? 'source_id' : 'destination_id' => 'A referência selecionada não está disponível.',
                    ]);
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
        $positiveTypes = [
            LedgerEntryType::OpeningBalance->value,
            LedgerEntryType::Income->value,
            LedgerEntryType::TransferIn->value,
        ];
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
     * @param  Collection<string, LedgerEntry>  $existingEntries
     * @return array{out: LedgerEntry, in: LedgerEntry}
     */
    private function validateExistingOperation($existingEntries, Account|Pocket $source, Account|Pocket $destination, BigDecimal $amount): array
    {
        $out = $existingEntries->get(LedgerEntryType::TransferOut->value);
        $in = $existingEntries->get(LedgerEntryType::TransferIn->value);

        if ($existingEntries->count() !== 2
            || $out === null
            || $in === null
            || $out->reference_type !== $source->getMorphClass()
            || (int) $out->reference_id !== (int) $source->getKey()
            || $in->reference_type !== $destination->getMorphClass()
            || (int) $in->reference_id !== (int) $destination->getKey()
            || $out->amount !== (string) $amount
            || $in->amount !== (string) $amount) {
            throw ValidationException::withMessages([
                'operation_id' => 'Esta chave de operação já foi usada com dados diferentes.',
            ]);
        }

        return ['out' => $out, 'in' => $in];
    }
}
