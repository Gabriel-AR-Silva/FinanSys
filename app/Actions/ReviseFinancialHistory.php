<?php

namespace App\Actions;

use App\Models\FinancialEvaluation;
use App\Models\User;
use App\Queries\FinancialPlanningOverviewQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ReviseFinancialHistory
{
    public function __construct(
        private FinancialPlanningOverviewQuery $planning,
        private UpdateInternalAlert $updateAlert,
    ) {}

    /**
     * Recalcula somente dias que já possuem fechamento persistido nos meses afetados.
     * O fechamento antigo permanece imutável e a correção vira uma nova revisão.
     *
     * @param  list<string>  $months  Meses no formato Y-m.
     * @return Collection<int, FinancialEvaluation>
     */
    public function handle(User $user, array $months): Collection
    {
        $months = collect($months)
            ->filter(fn (mixed $month): bool => is_string($month) && preg_match('/\A\d{4}-\d{2}\z/', $month) === 1)
            ->unique()
            ->values();

        if ($months->isEmpty()) {
            return new Collection;
        }

        $rulesVersion = (string) config('finansys.planning_rules_version');
        $dates = FinancialEvaluation::query()
            ->whereBelongsTo($user)
            ->where('rules_version', $rulesVersion)
            ->orderBy('evaluation_date')
            ->pluck('evaluation_date')
            ->map(fn ($date): string => CarbonImmutable::parse($date, 'America/Sao_Paulo')->toDateString())
            ->filter(fn (string $date): bool => $months->contains(substr($date, 0, 7)))
            ->unique()
            ->values();

        $created = new Collection;

        foreach ($dates as $date) {
            $evaluationDate = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'America/Sao_Paulo');
            if ($evaluationDate === false) {
                continue;
            }
            $overview = $this->planning->forUser($user, $evaluationDate->endOfDay());
            if (! ($overview['configured'] ?? false)) {
                continue;
            }

            foreach (['current', 'projected'] as $view) {
                $latest = FinancialEvaluation::query()
                    ->whereBelongsTo($user)
                    ->whereDate('evaluation_date', $date)
                    ->where('view', $view)
                    ->where('rules_version', $rulesVersion)
                    ->orderByDesc('revision')
                    ->lockForUpdate()
                    ->first();
                if (! $latest) {
                    continue;
                }

                $result = [
                    'month' => $overview['month'],
                    'view' => $view,
                    'evaluated_at' => $overview['evaluated_at'],
                    'reasons' => $overview['reasons'],
                    ...$overview[$view],
                ];
                if ($latest->result === $result) {
                    continue;
                }

                $revision = FinancialEvaluation::query()->create([
                    'user_id' => $user->id,
                    'evaluation_date' => $date,
                    'view' => $view,
                    'rules_version' => $rulesVersion,
                    'revision' => $latest->revision + 1,
                    'supersedes_id' => $latest->id,
                    'source' => 'correction',
                    'evaluated_at' => now('America/Sao_Paulo'),
                    'result' => $result,
                ]);
                $this->updateAlert->handle($user, $revision);
                $created->push($revision);
            }
        }

        return $created;
    }
}
