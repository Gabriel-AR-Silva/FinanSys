<?php

namespace App\Queries;

use App\Models\User;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class DailyCheckInCalendarQuery
{
    public function __construct(
        private DailyFinancialCheckInHistoryQuery $history,
        private DailyEligibleSpendReconciliationQuery $spending,
    ) {}

    /**
     * Completed local days only. Pending never means zero confirmed.
     *
     * @return list<array{date:string,status:string,budget?:string,spent?:string,margin?:string,revision?:int,preview_spent:?string,blockers:list<string>}>
     */
    public function forMonth(User $user, string $month, ?CarbonImmutable $observedAt = null): array
    {
        if (preg_match('/^(?!0000)\d{4}-(0[1-9]|1[0-2])$/D', $month) !== 1) {
            throw new InvalidArgumentException('Month must use YYYY-MM.');
        }

        $observed = ($observedAt ?? CarbonImmutable::now('UTC'))->utc();
        $localNow = $observed->setTimezone('America/Sao_Paulo');
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01', 'America/Sao_Paulo');
        if ($start === false) {
            throw new InvalidArgumentException('Invalid month.');
        }

        $lastCompleted = $localNow->startOfDay()->subDay();
        $monthEnd = $start->endOfMonth()->startOfDay();
        $end = $lastCompleted->lessThan($monthEnd) ? $lastCompleted : $monthEnd;
        if ($end->lessThan($start)) {
            return [];
        }

        $confirmed = collect($this->history->latestForMonth($user, $month))->keyBy('date');
        $days = [];
        for ($day = $start; $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            $date = $day->toDateString();
            $stored = $confirmed->get($date);
            if ($stored !== null) {
                $days[] = [
                    'date' => $date,
                    'status' => 'confirmed',
                    'budget' => $stored['budget'],
                    'spent' => $stored['spent'],
                    'margin' => $stored['margin'],
                    'revision' => $stored['revision'],
                    'preview_spent' => $stored['spent'],
                    'blockers' => [],
                ];

                continue;
            }

            $preview = $this->spending->forUserOnDay($user, $date, $observed);
            $days[] = [
                'date' => $date,
                'status' => 'pending',
                'preview_spent' => $preview['eligible_spent'],
                'blockers' => $preview['blockers'],
            ];
        }

        return $days;
    }
}
