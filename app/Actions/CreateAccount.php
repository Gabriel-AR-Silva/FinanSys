<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\User;
use App\Support\AuditRecorder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CreateAccount
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    public function handle(User $user, string $name, string $openingBalance, ?string $operationId = null): Account
    {
        $amount = BigDecimal::of($openingBalance)->toScale(2, RoundingMode::Unnecessary);

        if ($amount->isNegative()) {
            throw new InvalidArgumentException('O saldo inicial deve ser maior ou igual a zero.');
        }

        $operationId ??= (string) Str::uuid();

        try {
            return DB::transaction(function () use ($user, $name, $amount, $operationId): Account {
                $existingAccount = $user->accounts()->withTrashed()->where('operation_id', $operationId)->first();
                if ($existingAccount !== null) {
                    return $this->validateExistingOperation($existingAccount, $name, $amount);
                }

                $account = $user->accounts()->create(['name' => $name, 'operation_id' => $operationId]);
                $this->auditRecorder->record($user, AuditAction::Created, $account);

                if (! $amount->isZero()) {
                    $entry = $account->ledgerEntries()->create([
                        'user_id' => $user->getKey(),
                        'type' => LedgerEntryType::OpeningBalance,
                        'amount' => (string) $amount,
                        'operation_id' => $operationId,
                        'occurred_at' => now(),
                    ]);
                    $this->auditRecorder->record($user, AuditAction::Created, $entry);
                }

                return $account;
            });
        } catch (UniqueConstraintViolationException) {
            $existingAccount = $user->accounts()->withTrashed()->where('operation_id', $operationId)->first();

            if ($existingAccount === null) {
                throw ValidationException::withMessages(['operation_id' => 'Não foi possível recuperar a operação concorrente.']);
            }

            return $this->validateExistingOperation($existingAccount, $name, $amount);
        }
    }

    private function validateExistingOperation(Account $account, string $name, BigDecimal $amount): Account
    {
        $openingEntry = $account->ledgerEntries()
            ->withTrashed()
            ->where('type', LedgerEntryType::OpeningBalance)
            ->where('operation_id', $account->operation_id)
            ->first();
        $matchesOpeningBalance = $amount->isZero()
            ? $openingEntry === null
            : $openingEntry !== null && $openingEntry->amount === (string) $amount;

        if ($account->name !== $name || ! $matchesOpeningBalance) {
            throw ValidationException::withMessages([
                'operation_id' => 'Esta chave de operação já foi usada com dados diferentes.',
            ]);
        }

        return $account;
    }
}
