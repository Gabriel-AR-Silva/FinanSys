<?php

namespace App\Queries;

use App\Models\User;
use App\Support\DailyBudgetVersionSelector;

/**
 * Resolves persisted budget history for one authenticated user without
 * confirming a day or inferring expenses. A future check-in writer must call
 * this while holding the same user-row lock as SetDailyBudget and persist the
 * selected version in the same transaction.
 */
final class DailyBudgetSnapshotQuery
{
    public function __construct(
        private DailyBudgetHistoryQuery $history,
        private DailyBudgetVersionSelector $selector,
    ) {}

    /** @return array{id:int,amount:string}|null */
    public function forOpenDay(User $user, string $localDate, string $observedAt): ?array
    {
        return $this->selector->resolveOpenDay(
            (int) $user->getKey(),
            $localDate,
            $observedAt,
            $this->history->forUser($user),
        );
    }

    /** @return array{id:int,amount:string}|null */
    public function forCheckIn(User $user, string $localDate, string $confirmedAt): ?array
    {
        return $this->selector->resolve(
            (int) $user->getKey(),
            $localDate,
            $confirmedAt,
            $this->history->forUser($user),
        );
    }
}
