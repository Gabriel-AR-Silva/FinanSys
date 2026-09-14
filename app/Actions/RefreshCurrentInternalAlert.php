<?php

namespace App\Actions;

use App\Models\FinancialEvaluation;
use App\Models\User;
use App\Queries\FinancialPlanningOverviewQuery;
use Carbon\CarbonImmutable;

class RefreshCurrentInternalAlert
{
    public function __construct(
        private FinancialPlanningOverviewQuery $planning,
        private UpdateInternalAlert $updateAlert,
    ) {}

    public function handle(User $user, ?CarbonImmutable $evaluatedAt = null): void
    {
        $evaluatedAt ??= CarbonImmutable::now('America/Sao_Paulo');
        $overview = $this->planning->forUser($user, $evaluatedAt);
        if (! $overview['configured']) {
            return;
        }

        foreach (['current', 'projected'] as $view) {
            $evaluation = new FinancialEvaluation([
                'user_id' => $user->id,
                'evaluation_date' => $evaluatedAt->toDateString(),
                'view' => $view,
                'source' => 'recorded',
                'evaluated_at' => $evaluatedAt,
                'result' => [
                    'month' => $overview['month'],
                    'view' => $view,
                    'evaluated_at' => $overview['evaluated_at'],
                    'reasons' => $overview['reasons'],
                    ...$overview[$view],
                ],
            ]);
            $this->updateAlert->handle($user, $evaluation);
        }
    }
}
