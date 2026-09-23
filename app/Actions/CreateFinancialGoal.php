<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\FinancialGoal;
use App\Models\Pocket;
use App\Models\User;
use App\Support\AuditRecorder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateFinancialGoal
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    public function handle(
        User $user,
        string $name,
        string $targetAmount,
        string $targetDate,
        ?int $pocketId,
        string $operationId,
    ): FinancialGoal {
        $name = trim($name);
        $operationId = strtolower($operationId);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Informe um nome para a meta.']);
        }

        $this->validateDate($targetDate);

        if (! Str::isUuid($operationId)) {
            throw ValidationException::withMessages(['operation_id' => 'Informe uma chave de operação UUID válida.']);
        }

        try {
            return DB::transaction(function () use ($user, $name, $targetAmount, $targetDate, $pocketId, $operationId): FinancialGoal {
                $pocket = $this->resolvePocket($user, $pocketId);

                $existing = FinancialGoal::withTrashed()
                    ->where('user_id', $user->getKey())
                    ->where('operation_id', $operationId)
                    ->first();

                if ($existing !== null) {
                    return $this->validateReplay($existing, $name, $targetAmount, $targetDate, $pocket?->getKey());
                }

                $goal = FinancialGoal::query()->create([
                    'user_id' => $user->getKey(),
                    'pocket_id' => $pocket?->getKey(),
                    'name' => $name,
                    'target_amount' => $targetAmount,
                    'target_date' => $targetDate,
                    'operation_id' => $operationId,
                ]);

                $this->auditRecorder->record($user, AuditAction::Created, $goal);

                return $goal;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $existing = FinancialGoal::withTrashed()
                ->where('user_id', $user->getKey())
                ->where('operation_id', $operationId)
                ->first();

            if ($existing === null) {
                throw ValidationException::withMessages(['pocket_id' => 'Esta caixinha já está vinculada a outra meta.']);
            }

            return $this->validateReplay($existing, $name, $targetAmount, $targetDate, $pocketId);
        }
    }

    private function resolvePocket(User $user, ?int $pocketId): ?Pocket
    {
        if ($pocketId === null) {
            return null;
        }

        $pocket = Pocket::query()->whereBelongsTo($user)->find($pocketId);
        if ($pocket === null) {
            throw ValidationException::withMessages(['pocket_id' => 'Selecione uma caixinha válida da sua conta.']);
        }

        $alreadyLinked = FinancialGoal::query()->where('pocket_id', $pocket->getKey())->exists();
        if ($alreadyLinked) {
            throw ValidationException::withMessages(['pocket_id' => 'Esta caixinha já está vinculada a outra meta.']);
        }

        return $pocket;
    }

    private function validateDate(string $targetDate): void
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $targetDate, 'America/Sao_Paulo');
        if ($date === false || $date->format('Y-m-d') !== $targetDate || $date->isBefore(today('America/Sao_Paulo'))) {
            throw ValidationException::withMessages(['target_date' => 'Escolha hoje ou uma data futura para a meta.']);
        }
    }

    private function validateReplay(FinancialGoal $goal, string $name, string $targetAmount, string $targetDate, ?int $pocketId): FinancialGoal
    {
        if (
            $goal->name !== $name
            || $goal->target_amount !== (string) BigDecimal::of($targetAmount)->toScale(2, RoundingMode::Unnecessary)
            || $goal->target_date->toDateString() !== $targetDate
            || $goal->pocket_id !== $pocketId
        ) {
            throw ValidationException::withMessages(['operation_id' => 'Esta chave já foi usada com dados diferentes.']);
        }

        return $goal;
    }
}
