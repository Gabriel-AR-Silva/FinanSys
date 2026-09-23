<?php

namespace App\Queries;

use App\Models\DailyFinancialCheckIn;
use App\Models\User;
use InvalidArgumentException;

final class DailyFinancialCheckInHistoryQuery
{
    /**
     * @return list<array{id:int,date:string,revision:int,status:string,budget:string,spent:string,margin:string,rules_version:string,source:string,confirmed_at:string,reason:?string}>
     */
    public function latestForMonth(User $user, string $month): array
    {
        if (preg_match('/^(?!0000)\d{4}-(0[1-9]|1[0-2])$/D', $month) !== 1) {
            throw new InvalidArgumentException('Month must use YYYY-MM.');
        }

        $latestIds = DailyFinancialCheckIn::query()
            ->where('user_id', $user->getKey())
            ->where('local_date', '>=', $month.'-01')
            ->where('local_date', '<', $this->nextMonth($month).'-01')
            ->selectRaw('MAX(id) AS id')
            ->groupBy('local_date');

        return DailyFinancialCheckIn::query()
            ->where('user_id', $user->getKey())
            ->whereIn('id', $latestIds)
            ->orderBy('local_date')
            ->get()
            ->map(fn (DailyFinancialCheckIn $checkIn): array => $this->serialize($checkIn))
            ->all();
    }

    /**
     * @return list<array{id:int,date:string,revision:int,status:string,budget:string,spent:string,margin:string,rules_version:string,source:string,confirmed_at:string,reason:?string}>
     */
    public function revisionsForDay(User $user, string $localDate): array
    {
        return DailyFinancialCheckIn::query()
            ->where('user_id', $user->getKey())
            ->whereDate('local_date', $localDate)
            ->orderBy('revision')
            ->get()
            ->map(fn (DailyFinancialCheckIn $checkIn): array => $this->serialize($checkIn))
            ->all();
    }

    /** @return array{id:int,date:string,revision:int,status:string,budget:string,spent:string,margin:string,rules_version:string,source:string,confirmed_at:string,reason:?string} */
    private function serialize(DailyFinancialCheckIn $checkIn): array
    {
        return [
            'id' => (int) $checkIn->getKey(),
            'date' => $checkIn->local_date->toDateString(),
            'revision' => $checkIn->revision,
            'status' => 'confirmed',
            'budget' => $checkIn->budget_amount,
            'spent' => $checkIn->eligible_spent,
            'margin' => $checkIn->margin,
            'rules_version' => $checkIn->rules_version,
            'source' => $checkIn->source,
            'confirmed_at' => $checkIn->confirmed_at->utc()->toIso8601String(),
            'reason' => $checkIn->reason,
        ];
    }

    private function nextMonth(string $month): string
    {
        [$year, $number] = array_map('intval', explode('-', $month));
        $number++;
        if ($number === 13) {
            $number = 1;
            $year++;
        }

        return sprintf('%04d-%02d', $year, $number);
    }
}
