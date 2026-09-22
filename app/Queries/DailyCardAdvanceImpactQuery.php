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
 * An observation cutoff excludes records created later and flags subsequent
 * allocation/advance/purchase edits. This is NOT a historical snapshot or
 * ordinary daily spending. Never add to a check-in.
 */
final class DailyCardAdvanceImpactQuery
{
    /** @return array{ordinary_net_advanced:string,ordinary_future_gross_released:string,allocation_ids:list<int>,unverifiable_allocation_ids:list<int>,unclassified_count:int,coverage:string} */
    public function forUserOnDay(User $user, string $localDate, ?CarbonImmutable $observedAt = null): array
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $localDate, 'America/Sao_Paulo');
        if ($day === false || $day->format('Y-m-d') !== $localDate) {
            throw new InvalidArgumentException('Informe um dia local válido.');
        }

        $observed = ($observedAt ?? CarbonImmutable::now('UTC'))->utc();
        if ($observed->lessThan($day->startOfDay()->utc())) {
            throw new InvalidArgumentException('Não é possível consultar antecipações antes do início do dia.');
        }

        $allocations = CardAdvanceAllocation::query()
            ->where('user_id', $user->getKey())
            ->where('created_at', '<=', $observed)
            ->whereHas('advance', fn ($query) => $query
                ->where('user_id', $user->getKey())
                ->where('created_at', '<=', $observed)
                ->where(fn ($dated) => $dated
                    ->whereDate('advanced_on', $localDate)
                    // The current day may have changed after observation.
                    // Without a dated origin, retain uncertainty rather than
                    // silently dropping a moved advance from its old day.
                    ->orWhere('updated_at', '>', $observed)))
            ->whereHas('installment', fn ($query) => $query->where('user_id', $user->getKey())->whereHas('purchase', fn ($purchase) => $purchase->where('user_id', $user->getKey())))
            ->with(['advance', 'installment.purchase'])
            ->orderBy('id')
            ->get();

        $net = BigDecimal::zero();
        $released = BigDecimal::zero();
        $ids = [];
        $unverifiable = [];
        $unclassified = 0;

        foreach ($allocations as $allocation) {
            // All three rows can change after an observation. In particular,
            // current purchase planning_type cannot classify an earlier
            // advance if the purchase was edited later. DATETIME stores raw
            // UTC values without an offset: do not use cast timezones here.
            $allocationUpdated = $allocation->getRawOriginal('updated_at');
            $advanceUpdated = $allocation->advance->getRawOriginal('updated_at');
            $purchaseUpdated = $allocation->installment->purchase->getRawOriginal('updated_at');
            if (($allocationUpdated !== null && CarbonImmutable::parse((string) $allocationUpdated, 'UTC')->greaterThan($observed))
                || ($advanceUpdated !== null && CarbonImmutable::parse((string) $advanceUpdated, 'UTC')->greaterThan($observed))
                || ($purchaseUpdated !== null && CarbonImmutable::parse((string) $purchaseUpdated, 'UTC')->greaterThan($observed))) {
                $unverifiable[] = (int) $allocation->getKey();

                continue;
            }

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
            'unverifiable_allocation_ids' => $unverifiable,
            'unclassified_count' => $unclassified,
            'coverage' => $unverifiable === [] ? 'current_card_advance_timing_only' : 'partial_card_advance_timing_unverifiable_edits',
        ];
    }
}
