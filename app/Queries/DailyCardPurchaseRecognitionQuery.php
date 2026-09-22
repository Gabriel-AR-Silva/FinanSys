<?php

namespace App\Queries;

use App\Enums\ExpensePlanningType;
use App\Models\CardPurchase;
use App\Models\CardPurchaseReversal;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Read-only gross purchases made on a local date. Reversed purchases remain
 * visible as original purchases; their reversal is a separate event, never a
 * second expense or an automatic reduction of this gross total. Classification
 * edits cannot be reconstructed here. Do not use this view for a check-in.
 */
final class DailyCardPurchaseRecognitionQuery
{
    /** @return array{ordinary_purchase_total:string,purchase_ids:list<int>,unclassified_count:int,coverage:string} */
    public function forUserOnDay(User $user, string $localDate, ?CarbonImmutable $observedAt = null): array
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $localDate, 'America/Sao_Paulo');
        if ($day === false || $day->format('Y-m-d') !== $localDate) {
            throw new InvalidArgumentException('Informe um dia local válido.');
        }

        $start = $day->startOfDay()->utc();
        $observed = ($observedAt ?? CarbonImmutable::now('UTC'))->utc();
        if ($observed->lessThan($start)) {
            throw new InvalidArgumentException('Não é possível consultar compras antes do início do dia.');
        }

        $purchases = CardPurchase::withTrashed()
            ->where('user_id', $user->getKey())
            ->whereDate('purchased_on', $localDate)
            ->where('created_at', '<=', $observed)
            ->orderBy('id')
            ->get();

        // Only a recorded reversal explains a deleted purchase as an original
        // purchase. Ordinary deletions must not reappear after their deletion.
        $reversedPurchaseIds = CardPurchaseReversal::query()
            ->where('user_id', $user->getKey())
            ->whereIn('card_purchase_id', $purchases->pluck('id'))
            ->where('created_at', '<=', $observed)
            ->whereDate('reversed_on', '<=', $observed->setTimezone('America/Sao_Paulo')->toDateString())
            ->pluck('card_purchase_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $total = BigDecimal::zero();
        $ids = [];
        $unclassified = 0;

        foreach ($purchases as $purchase) {
            if ($purchase->trashed()
                && $purchase->deleted_at->utc()->lessThanOrEqualTo($observed)
                && ! in_array((int) $purchase->getKey(), $reversedPurchaseIds, true)) {
                continue;
            }
            if ($purchase->planning_type === null) {
                $unclassified++;

                continue;
            }
            if ($purchase->planning_type !== ExpensePlanningType::Ordinary) {
                continue;
            }

            $total = $total->plus($purchase->gross_amount);
            $ids[] = (int) $purchase->getKey();
        }

        return [
            'ordinary_purchase_total' => (string) $total->toScale(2),
            'purchase_ids' => $ids,
            'unclassified_count' => $unclassified,
            'coverage' => 'gross_card_purchases_with_recorded_reversals',
        ];
    }
}
