<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\Account;
use App\Models\Pocket;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreatePocket
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    public function handle(User $user, int $accountId, string $name, ?string $operationId = null): Pocket
    {
        $operationId ??= (string) Str::uuid();
        if (! Str::isUuid($operationId)) {
            throw ValidationException::withMessages(['operation_id' => 'Informe uma chave de operação UUID válida.']);
        }

        try {
            return DB::transaction(function () use ($user, $accountId, $name, $operationId): Pocket {
                $account = Account::query()->whereBelongsTo($user)->where('status', RecordStatus::Active)->lockForUpdate()->findOrFail($accountId);
                $existing = $user->pockets()->withTrashed()->where('operation_id', $operationId)->first();
                if ($existing !== null) {
                    return $this->validateExistingOperation($existing, $accountId, $name);
                }

                $pocket = $account->pockets()->create(['user_id' => $user->id, 'name' => $name, 'operation_id' => $operationId]);
                $this->auditRecorder->record($user, AuditAction::Created, $pocket);

                return $pocket;
            });
        } catch (UniqueConstraintViolationException) {
            $existing = $user->pockets()->withTrashed()->where('operation_id', $operationId)->first();
            if ($existing === null) {
                throw ValidationException::withMessages(['operation_id' => 'Não foi possível recuperar a operação concorrente.']);
            }

            return $this->validateExistingOperation($existing, $accountId, $name);
        }
    }

    private function validateExistingOperation(Pocket $pocket, int $accountId, string $name): Pocket
    {
        if ($pocket->account_id !== $accountId || $pocket->name !== $name) {
            throw ValidationException::withMessages(['operation_id' => 'Esta chave de operação já foi usada com dados diferentes.']);
        }

        return $pocket;
    }
}
