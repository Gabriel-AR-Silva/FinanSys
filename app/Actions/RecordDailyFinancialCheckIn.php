<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\DailyFinancialCheckIn;
use App\Models\User;
use App\Queries\DailyBudgetHistoryQuery;
use App\Queries\DailyEligibleSpendReconciliationQuery;
use App\Support\AuditRecorder;
use App\Support\DailyConfirmedDayInput;
use App\Support\DailyMarginCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class RecordDailyFinancialCheckIn
{
    public function __construct(
        private DailyEligibleSpendReconciliationQuery $spending,
        private DailyBudgetHistoryQuery $budgets,
        private DailyConfirmedDayInput $input,
        private DailyMarginCalculator $margin,
        private AuditRecorder $auditRecorder,
    ) {}

    public function confirm(User $actor, string $localDate, string $operationId, ?string $reason = null): DailyFinancialCheckIn
    {
        return $this->record($actor, $localDate, $operationId, $reason, false);
    }

    public function correct(User $actor, string $localDate, string $operationId, string $reason): DailyFinancialCheckIn
    {
        return $this->record($actor, $localDate, $operationId, $reason, true);
    }

    private function record(User $actor, string $localDate, string $operationId, ?string $reason, bool $correction): DailyFinancialCheckIn
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $localDate, 'America/Sao_Paulo');
        if ($day === false || $day->format('Y-m-d') !== $localDate || ! $day->isBefore(today('America/Sao_Paulo'))) {
            throw ValidationException::withMessages(['local_date' => 'Confirme apenas um dia anterior válido.']);
        }
        if (! Str::isUuid($operationId)) {
            throw ValidationException::withMessages(['operation_id' => 'Informe uma chave de operação UUID válida.']);
        }

        $reason = $reason === null || trim($reason) === '' ? null : trim($reason);
        if ($reason !== null && mb_strlen($reason) > 255) {
            throw ValidationException::withMessages(['reason' => 'Informe um motivo com até 255 caracteres.']);
        }
        if ($correction && $reason === null) {
            throw ValidationException::withMessages(['reason' => 'Informe o motivo da correção do dia confirmado.']);
        }

        try {
            return DB::transaction(function () use ($actor, $localDate, $operationId, $reason, $correction): DailyFinancialCheckIn {
                User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();

                $existingOperation = DailyFinancialCheckIn::query()
                    ->where('user_id', $actor->getKey())
                    ->where('operation_id', strtolower($operationId))
                    ->first();
                if ($existingOperation !== null) {
                    return $this->validateReplay($existingOperation, $localDate, $reason, $correction);
                }

                $latest = DailyFinancialCheckIn::query()
                    ->where('user_id', $actor->getKey())
                    ->whereDate('local_date', $localDate)
                    ->orderByDesc('revision')
                    ->lockForUpdate()
                    ->first();

                if (! $correction && $latest !== null) {
                    throw ValidationException::withMessages(['local_date' => 'Este dia já foi confirmado; use uma correção explícita.']);
                }
                if ($correction && $latest === null) {
                    throw ValidationException::withMessages(['local_date' => 'Confirme o dia antes de registrar uma correção.']);
                }

                $observed = CarbonImmutable::now('UTC');
                $reconciled = $this->spending->forUserOnDay($actor, $localDate, $observed);
                if ($reconciled['eligible_spent'] === null) {
                    throw ValidationException::withMessages([
                        'eligible_spent' => 'O dia possui dados financeiros incompletos: '.implode(', ', $reconciled['blockers']).'.',
                    ]);
                }

                try {
                    $input = $this->input->build(
                        (int) $actor->getKey(),
                        $localDate,
                        $observed->toIso8601String(),
                        $reconciled['eligible_spent'],
                        $this->budgets->forUser($actor),
                    );
                } catch (InvalidArgumentException $exception) {
                    throw ValidationException::withMessages(['daily_budget' => $exception->getMessage()]);
                }

                $calculated = $this->margin->calculate(substr($localDate, 0, 7), [$input['calculator_day']]);
                $snapshot = $calculated['days'][0];
                $revision = $latest === null ? 1 : $latest->revision + 1;

                $checkIn = DailyFinancialCheckIn::query()->create([
                    'user_id' => $actor->getKey(),
                    'actor_id' => $actor->getKey(),
                    'local_date' => $localDate,
                    'revision' => $revision,
                    'supersedes_id' => $latest?->getKey(),
                    'daily_budget_version_id' => $input['budget_version_id'],
                    'budget_amount' => $input['calculator_day']['budget'],
                    'eligible_spent' => $input['calculator_day']['spent'],
                    'margin' => $snapshot['margin'],
                    'rules_version' => 'daily-margin-v1+'.$reconciled['rule_version'],
                    'source' => $correction ? 'corrected' : 'recorded',
                    'confirmed_at' => $observed->format('Y-m-d H:i:s'),
                    'reason' => $reason,
                    'operation_id' => strtolower($operationId),
                ]);

                $this->auditRecorder->record($actor, AuditAction::Created, $checkIn);

                return $checkIn;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $existing = DailyFinancialCheckIn::query()
                ->where('user_id', $actor->getKey())
                ->where('operation_id', strtolower($operationId))
                ->first();
            if ($existing !== null) {
                return $this->validateReplay($existing, $localDate, $reason, $correction);
            }

            throw ValidationException::withMessages(['operation_id' => 'Não foi possível repetir o check-in com segurança.']);
        }
    }

    private function validateReplay(DailyFinancialCheckIn $existing, string $localDate, ?string $reason, bool $correction): DailyFinancialCheckIn
    {
        $expectedSource = $correction ? 'corrected' : 'recorded';
        if ($existing->local_date->toDateString() !== $localDate
            || $existing->reason !== $reason
            || $existing->source !== $expectedSource) {
            throw ValidationException::withMessages(['operation_id' => 'Esta chave já foi usada com dados diferentes.']);
        }

        return $existing;
    }
}
