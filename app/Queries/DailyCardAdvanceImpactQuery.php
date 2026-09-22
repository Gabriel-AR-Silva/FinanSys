<?php

namespace App\Queries;

use App\Enums\ExpensePlanningType;
use App\Models\CardAdvanceAllocation;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Read-only, current-state view of advances executed on one local date.
 * This is a commitment-timing adjustment, NOT ordinary daily spending and
 * NOT a historical as-of snapshot. Never add it to a check-in automatically.
 */
final class DailyCardAdvanceImpactQuery
{
    /** @return array{ordinary_net_advanced:string,ordinary_future_gross_released:string,allocation_ids:list<int>,unclassified_count:int,coverage:string} */
    public function forUserOnDay(User $user, string $localDate): array
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $localDate, 'America/Sao_Paulo');
        if ($day === false || $day->format('Y-m-d') !== $localDate) {
            throw new InvalidArgumentException('Informe um dia local válido.');
        }

        $allocations = CardAdvanceAllocation::query()
            ->where('user_id', $user->getKey())
            ->whereHas('advance', fn ($query) => $query->where('user_id', $user->getKey())->whereDate('advanced_on', $localDate))
            ->whereHas('installment', fn ($query) => $query->where('user_id', $user->getKey())->whereHas('purchase', fn ($purchase) => $purchase->where('user_id', $user->getKey())))
            ->with('installment.purchase')
            ->orderBy('id')
            ->get();

        $net = BigDecimal::zero();
        $released = BigDecimal::zero();
        $ids = [];
        $unclassified = 0;

        foreach ($allocations as $allocation) {
            $type = $allocation->installment->purchase->planning_type;
            if ($type === null) {
                $unclassified++;

                continue;
            }
            if ($type !== ExpensePlanningType::Ordinary) {
                continue;
            }

            $net = $net->plus($allocation->net_amount);
            $released = $released->plus($allocation->gross_amount);
            $ids[] = (int) $allocation->getKey();
        }

        return [
            'ordinary_net_advanced' => (string) $net->toScale(2),
            'ordinary_future_gross_released' => (string) $released->toScale(2),
            'allocation_ids' => $ids,
            'unclassified_count' => $unclassified,
            'coverage' => 'current_card_advance_timing_only',
        ];
    }
}
