<?php

namespace App\Actions;

use App\Models\FinancialEvaluation;
use App\Models\User;
use App\Queries\FinancialPlanningOverviewQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CloseFinancialEvaluation
{
    public function __construct(
        private FinancialPlanningOverviewQuery $planning,
        private UpdateInternalAlert $updateAlert,
    ) {}

    /** @return Collection<int, FinancialEvaluation> */
    public function handle(User $user, CarbonImmutable $closedDate, bool $reconstructed = false): Collection
    {
        $closedDate = $closedDate->setTimezone('America/Sao_Paulo');
        $evaluatedAt = $closedDate->endOfDay();

        return DB::transaction(function () use ($user, $closedDate, $evaluatedAt, $reconstructed): Collection {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $overview = $this->planning->forUser($user, $evaluatedAt);

            if (! $overview['configured']) {
                return new Collection;
            }

            $rulesVersion = (string) config('finansys.planning_rules_version');
            $source = $reconstructed ? 'reconstructed' : 'recorded';

            return collect(['current', 'projected'])->map(function (string $view) use ($user, $closedDate, $rulesVersion, $source, $evaluatedAt, $overview): FinancialEvaluation {
                $evaluations = FinancialEvaluation::query()
                    ->whereBelongsTo($user)
                    ->whereDate('evaluation_date', $closedDate->toDateString())
                    ->where('view', $view)
                    ->where('rules_version', $rulesVersion)
                    ->orderByDesc('revision')
                    ->get();
                $latest = $evaluations->first();
                if ($latest && ($source === 'reconstructed' || $latest->source === 'recorded')) {
                    $this->updateAlert->handle($user, $latest);

                    return $latest;
                }

                $evaluation = FinancialEvaluation::query()->create([
                    'user_id' => $user->id,
                    'evaluation_date' => $closedDate->toDateString(),
                    'view' => $view,
                    'rules_version' => $rulesVersion,
                    'revision' => ($latest?->revision ?? 0) + 1,
                    'supersedes_id' => $latest?->id,
                    'source' => $source,
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

                return $evaluation;
            });
        });
    }
}
