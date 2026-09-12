<?php

namespace App\Actions;

use App\Models\FinancialEvaluation;
use App\Models\InternalAlert;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class UpdateInternalAlert
{
    public function handle(User $user, FinancialEvaluation $evaluation): ?InternalAlert
    {
        if ($evaluation->source === 'reconstructed') {
            return null;
        }

        return DB::transaction(function () use ($user, $evaluation): ?InternalAlert {
            $result = $evaluation->result;
            $situation = (string) ($result['situation'] ?? 'no_basis');
            $deficit = $result['deficit'] ?? null;
            $hasDeficit = is_string($deficit) && $deficit !== '0' && $deficit !== '0.00';
            $adverse = in_array($situation, ['insufficient', 'outside_plan'], true) || $hasDeficit;
            $alert = InternalAlert::query()->whereBelongsTo($user)
                ->whereDate('alert_date', $evaluation->evaluation_date->toDateString())
                ->where('view', $evaluation->view)
                ->lockForUpdate()->first();

            if (! $alert && ! $adverse) {
                return null;
            }

            if (! $alert) {
                return InternalAlert::query()->create([
                    'user_id' => $user->id,
                    'alert_date' => $evaluation->evaluation_date,
                    'view' => $evaluation->view,
                    'financial_evaluation_id' => $evaluation->id,
                    'current_situation' => $situation,
                    'worst_situation' => $situation,
                    'current_deficit' => $hasDeficit ? $deficit : null,
                    'deficit_seen' => $hasDeficit,
                    'payload' => $result,
                ]);
            }

            if ($evaluation->getKey() !== null && $alert->financial_evaluation_id === $evaluation->id) {
                return $alert;
            }

            $wasAdverse = in_array($alert->current_situation, ['insufficient', 'outside_plan'], true)
                || $alert->current_deficit !== null;
            $recoveredAt = $wasAdverse && ! $adverse ? CarbonImmutable::now('America/Sao_Paulo') : $alert->recovered_at;
            $worstSituation = $this->severity($situation) > $this->severity($alert->worst_situation)
                ? $situation
                : $alert->worst_situation;
            $alert->update([
                'financial_evaluation_id' => $evaluation->id,
                'current_situation' => $situation,
                'worst_situation' => $worstSituation,
                'current_deficit' => $hasDeficit ? $deficit : null,
                'deficit_seen' => $alert->deficit_seen || $hasDeficit,
                'recovered_at' => $recoveredAt,
                'payload' => $result,
            ]);

            return $alert->fresh();
        });
    }

    private function severity(string $situation): int
    {
        return match ($situation) {
            'insufficient' => 3,
            'outside_plan' => 2,
            'balanced' => 1,
            default => 0,
        };
    }
}
