<?php

namespace App\Actions;

use App\Domain\Ledger\ReferenceResolver;
use App\Enums\AuditAction;
use App\Enums\LedgerEntryReferenceType;
use App\Enums\LedgerEntryType;
use App\Enums\RecordStatus;
use App\Models\Account;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\AuditRecorder;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateManualLedgerEntry
{
    public function __construct(private AuditRecorder $auditRecorder, private ReferenceResolver $referenceResolver) {}

    public function handle(User $user, int $accountId, int $categoryId, LedgerEntryType $type, string $value, string $occurredAt, ?string $description, string $operationId): LedgerEntry
    {
        if (! Str::isUuid($operationId)) {
            throw ValidationException::withMessages(['operation_id' => 'Informe uma chave de operação UUID válida.']);
        }

        if (! in_array($type, [LedgerEntryType::Income, LedgerEntryType::Expense], true)) {
            throw ValidationException::withMessages(['type' => 'Escolha receita ou despesa.']);
        }

        $description = $description === null || trim($description) === '' ? null : trim($description);
        if ($description !== null && mb_strlen($description) > 255) {
            throw ValidationException::withMessages(['description' => 'Informe detalhes com no máximo 255 caracteres.']);
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $occurredAt);
        if ($date === false || $date->format('Y-m-d') !== $occurredAt || $date->isAfter(today())) {
            throw ValidationException::withMessages(['occurred_at' => 'Informe uma data válida que não esteja no futuro.']);
        }

        try {
            $amount = BigDecimal::of($value)->toScale(2, RoundingMode::Unnecessary);
        } catch (MathException) {
            throw ValidationException::withMessages(['amount' => 'Informe um valor monetário válido com no máximo duas casas decimais.']);
        }

        if (! $amount->isPositive()) {
            throw ValidationException::withMessages(['amount' => 'O valor deve ser maior que zero.']);
        }
        if ($amount->isGreaterThan('99999999999999999.99')) {
            throw ValidationException::withMessages(['amount' => 'O valor ultrapassa o limite suportado.']);
        }

        $reference = $this->referenceResolver->resolve($user, LedgerEntryReferenceType::Account, $accountId);
        if (! $reference instanceof Account || $reference->status !== RecordStatus::Active) {
            throw ValidationException::withMessages(['account_id' => 'A conta selecionada não está disponível.']);
        }

        $category = Category::query()->whereBelongsTo($user)->where('type', $type->value)->where('status', RecordStatus::Active)->find($categoryId);
        if ($category === null) {
            throw ValidationException::withMessages(['category_id' => 'A categoria selecionada não está disponível para este tipo de lançamento.']);
        }

        try {
            return DB::transaction(function () use ($user, $accountId, $categoryId, $type, $amount, $occurredAt, $description, $operationId): LedgerEntry {
                $existing = LedgerEntry::withTrashed()->whereBelongsTo($user)->where('operation_id', $operationId)->get();
                if ($existing->isNotEmpty()) {
                    return $this->validateExisting($existing, $type, $accountId, $categoryId, $amount, $occurredAt, $description);
                }

                $account = Account::query()->whereBelongsTo($user)->where('status', RecordStatus::Active)->lockForUpdate()->findOrFail($accountId);
                $entry = $account->ledgerEntries()->create([
                    'user_id' => $user->getKey(), 'category_id' => $categoryId, 'type' => $type, 'amount' => (string) $amount,
                    'operation_id' => $operationId, 'occurred_at' => $occurredAt, 'description' => $description,
                ]);
                $this->auditRecorder->record($user, AuditAction::Created, $entry);

                return $entry;
            });
        } catch (UniqueConstraintViolationException) {
            $existing = LedgerEntry::withTrashed()->whereBelongsTo($user)->where('operation_id', $operationId)->get();
            if ($existing->isEmpty()) {
                throw ValidationException::withMessages(['operation_id' => 'Não foi possível recuperar a operação concorrente.']);
            }

            return $this->validateExisting($existing, $type, $accountId, $categoryId, $amount, $occurredAt, $description);
        }
    }

    /** @param Collection<int, LedgerEntry> $entries */
    private function validateExisting(Collection $entries, LedgerEntryType $type, int $accountId, int $categoryId, BigDecimal $amount, string $occurredAt, ?string $description): LedgerEntry
    {
        $entry = $entries->first(fn (LedgerEntry $candidate): bool => $candidate->type === $type);

        if ($entries->count() !== 1 || $entry === null || $entry->reference_type !== (new Account)->getMorphClass() || (int) $entry->reference_id !== $accountId || (int) $entry->category_id !== $categoryId
            || $entry->amount !== (string) $amount || $entry->occurred_at->toDateString() !== $occurredAt || $entry->description !== $description) {
            throw ValidationException::withMessages(['operation_id' => 'Esta chave de operação já foi usada com dados diferentes.']);
        }

        return $entry;
    }
}
