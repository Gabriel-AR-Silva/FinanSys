<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\DailyBudgetVersion;
use App\Models\User;
use App\Support\AuditRecorder;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Records only a voluntary change effective now. Historical backfills and
 * corrections require a separate explicit action; neither is inferred here.
 */
class SetDailyBudget
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    public function handle(User $actor, string $value, string $operationId, ?string $reason = null): DailyBudgetVersion
    {
        if (! Str::isUuid($operationId)) {
            throw ValidationException::withMessages(['operation_id' => 'Informe uma chave de operação UUID válida.']);
        }

        if (preg_match('/^(0|[1-9]\\d{0,16})(\\.\\d{1,2})?$/D', $value) !== 1) {
            throw ValidationException::withMessages(['amount' => 'Informe um orçamento válido de até 17 dígitos e duas casas decimais.']);
        }

        $amount = (string) BigDecimal::of($value)->toScale(2);
        $reason = $reason === null || trim($reason) === '' ? null : trim($reason);
        if ($reason !== null && mb_strlen($reason) > 255) {
            throw ValidationException::withMessages(['reason' => 'Informe um motivo com até 255 caracteres.']);
        }

        try {
            return DB::transaction(function () use ($actor, $amount, $operationId, $reason): DailyBudgetVersion {
                // Future check-in writers must use the same per-user lock to
                // atomically select the budget version and persist its snapshot.
                User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();

                $existing = DailyBudgetVersion::query()
                    ->where('user_id', $actor->getKey())
                    ->where('operation_id', $operationId)
                    ->first();
                if ($existing !== null) {
                    return $this->validateReplay($existing, $amount, $reason);
                }

                $now = CarbonImmutable::now('UTC')->format('Y-m-d H:i:s');
                $version = DailyBudgetVersion::query()->create([
                    'user_id' => $actor->getKey(),
                    'actor_id' => $actor->getKey(),
                    'amount' => $amount,
                    'effective_at' => $now,
                    'recorded_at' => $now,
                    'origin' => 'manual',
                    'reason' => $reason,
                    'operation_id' => $operationId,
                ]);
                $this->auditRecorder->record($actor, AuditAction::Created, $version);

                return $version;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $existing = DailyBudgetVersion::query()
                ->where('user_id', $actor->getKey())
                ->where('operation_id', $operationId)
                ->first();
            if ($existing === null) {
                throw ValidationException::withMessages(['operation_id' => 'Não foi possível recuperar a operação concorrente.']);
            }

            return $this->validateReplay($existing, $amount, $reason);
        }
    }

    private function validateReplay(DailyBudgetVersion $existing, string $amount, ?string $reason): DailyBudgetVersion
    {
        if ($existing->amount !== $amount || $existing->origin !== 'manual' || $existing->reason !== $reason
            || (int) $existing->actor_id !== (int) $existing->user_id) {
            throw ValidationException::withMessages(['operation_id' => 'Esta chave já foi usada com dados diferentes.']);
        }

        return $existing;
    }
}
