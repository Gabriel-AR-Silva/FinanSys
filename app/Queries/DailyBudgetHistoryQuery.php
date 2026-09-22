<?php

namespace App\Queries;

use App\Models\DailyBudgetVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Read-only bridge between UTC DATETIME storage and the pure D2 selector.
 * Eloquent's datetime cast uses the application timezone for naive values;
 * these database columns are explicitly UTC, so read their raw values.
 */
final class DailyBudgetHistoryQuery
{
    /**
     * @return list<array{id:int,user_id:int,amount:string,effective_at:string,recorded_at:string}>
     */
    public function forUser(User $user): array
    {
        return DailyBudgetVersion::query()
            ->where('user_id', $user->getKey())
            ->orderBy('effective_at')
            ->orderBy('id')
            ->get()
            ->map(fn (DailyBudgetVersion $version): array => [
                'id' => (int) $version->getKey(),
                'user_id' => (int) $version->user_id,
                'amount' => (string) $version->amount,
                'effective_at' => $this->utcInstant($version->getRawOriginal('effective_at')),
                'recorded_at' => $this->utcInstant($version->getRawOriginal('recorded_at')),
            ])
            ->all();
    }

    private function utcInstant(mixed $raw): string
    {
        if (! is_string($raw)) {
            throw new InvalidArgumentException('Budget timestamp must be stored as a UTC datetime string.');
        }

        $instant = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $raw, new DateTimeZone('UTC'));
        if ($instant === false || $instant->format('Y-m-d H:i:s') !== $raw) {
            throw new InvalidArgumentException('Budget timestamp must be a valid UTC datetime.');
        }

        return $instant->format('Y-m-d\TH:i:sP');
    }
}
