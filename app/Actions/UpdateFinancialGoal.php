<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\FinancialGoal;
use App\Models\Pocket;
use App\Models\User;
use App\Support\AuditRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateFinancialGoal
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    public function handle(
        User $user,
        int $goalId,
        string $name,
        string $targetAmount,
        string $targetDate,
        ?int $pocketId,
    ): FinancialGoal {
        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Informe um nome para a meta.']);
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $targetDate, 'America/Sao_Paulo');
        if ($date === false || $date->format('Y-m-d') !== $targetDate || $date->isBefore(today('America/Sao_Paulo'))) {
            throw ValidationException::withMessages(['target_date' => 'Escolha hoje ou uma data futura para a meta.']);
        }

        try {
            return DB::transaction(function () use ($user, $goalId, $name, $targetAmount, $targetDate, $pocketId): FinancialGoal {
                $goal = FinancialGoal::query()
                    ->whereBelongsTo($user)
                    ->lockForUpdate()
                    ->findOrFail($goalId);

                $pocket = $pocketId === null
                    ? null
                    : Pocket::query()->whereBelongsTo($user)->find($pocketId);

                if ($pocketId !== null && $pocket === null) {
                    throw ValidationException::withMessages(['pocket_id' => 'Selecione uma caixinha válida da sua conta.']);
                }

                if ($pocket !== null && FinancialGoal::query()
                    ->where('pocket_id', $pocket->getKey())
                    ->whereKeyNot($goal->getKey())
                    ->exists()) {
                    throw ValidationException::withMessages(['pocket_id' => 'Esta caixinha já está vinculada a outra meta.']);
                }

                $before = $goal->attributesToArray();
                $goal->fill([
                    'pocket_id' => $pocket?->getKey(),
                    'name' => $name,
                    'target_amount' => $targetAmount,
                    'target_date' => $targetDate,
                ])->save();

                $this->auditRecorder->record($user, AuditAction::Updated, $goal, $before);

                return $goal;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['pocket_id' => 'Esta caixinha já está vinculada a outra meta.']);
        }
    }
}
