<?php

namespace App\Queries;

use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

final class MonthlyDailyPlanningDashboardQuery
{
    public function __construct(
        private DailyFinancialCheckInHistoryQuery $history,
        private DailyBudgetSnapshotQuery $budgets,
    ) {}

    /**
     * @return array{
     *   month:string,
     *   evaluated_at:string,
     *   confirmed_days:int,
     *   pending_days:int,
     *   gross_savings:string,
     *   gross_excess:string,
     *   net_margin:string,
     *   total_spent:string,
     *   current_daily_budget:?string,
     *   daily:list<array{date:string,budget:string,spent:string,margin:string,cumulative_margin:string,revision:int}>,
     *   coverage:string
     * }
     */
    public function forUser(User $user, ?CarbonImmutable $evaluatedAt = null): array
    {
        $now = ($evaluatedAt ?? CarbonImmutable::now('UTC'))->setTimezone('America/Sao_Paulo');
        $month = $now->format('Y-m');
        $confirmed = $this->history->latestForMonth($user, $month);

        $grossSavings = BigDecimal::zero();
        $grossExcess = BigDecimal::zero();
        $netMargin = BigDecimal::zero();
        $totalSpent = BigDecimal::zero();
        $cumulative = BigDecimal::zero();
        $daily = [];

        foreach ($confirmed as $day) {
            $margin = BigDecimal::of($day['margin']);
            $spent = BigDecimal::of($day['spent']);

            if ($margin->isPositive()) {
                $grossSavings = $grossSavings->plus($margin);
            } elseif ($margin->isNegative()) {
                $grossExcess = $grossExcess->plus($margin->negated());
            }

            $netMargin = $netMargin->plus($margin);
            $totalSpent = $totalSpent->plus($spent);
            $cumulative = $cumulative->plus($margin);

            $daily[] = [
                'date' => $day['date'],
                'budget' => $day['budget'],
                'spent' => $day['spent'],
                'margin' => $day['margin'],
                'cumulative_margin' => (string) $cumulative->toScale(2),
                'revision' => $day['revision'],
            ];
        }

        $completedDays = max(0, $now->day - 1);
        $confirmedDays = count($confirmed);
        $currentBudget = $this->budgets->forOpenDay(
            $user,
            $now->toDateString(),
            $now->toIso8601String(),
        );

        return [
            'month' => $month,
            'evaluated_at' => $now->toIso8601String(),
            'confirmed_days' => $confirmedDays,
            'pending_days' => max(0, $completedDays - $confirmedDays),
            'gross_savings' => (string) $grossSavings->toScale(2),
            'gross_excess' => (string) $grossExcess->toScale(2),
            'net_margin' => (string) $netMargin->toScale(2),
            'total_spent' => (string) $totalSpent->toScale(2),
            'current_daily_budget' => $currentBudget['amount'] ?? null,
            'daily' => $daily,
            'coverage' => $confirmedDays === $completedDays
                ? 'all_completed_days_confirmed'
                : 'partial_completed_days_pending',
        ];
    }
}
