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
 * Ordinary card purchase principal on the PURCHASE date. Installment due
 * dates, invoice payments and advances are separate obligation/settlement
 * facts and never create a second consumption event. A recorded full reversal
 * removes the related purchase from the current eligible consumption view while
 * preserving its source id for audit. Historical edits remain conservative.
 */
final class DailyCardPurchaseRecognitionQuery
{
    /** @return array{ordinary_purchase_total:string,purchase_ids:list<int>,reversed_purchase_ids:list<int>,unclassified_count:int,unverifiable_purchase_ids:list<int>,coverage:string} */
    public function forUserOnDay(User $user, string $localDate, ?CarbonImmutable $observedAt = null): array
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $localDate, 'America/Sao_Paulo');
        if ($day === false || $day->format('Y-m-d') !== $localDate) {
            throw new InvalidArgumentException('Informe um dia local válido.');
        }

        $observed = ($observedAt ?? CarbonImmutable::now('UTC'))->utc();
        if ($observed->lessThan($day->startOfDay()->utc())) {
            throw new InvalidArgumentException('Não é possível consultar compras antes do início do dia.');
        }

        // V1 stores created_at, updated_at and deleted_at as offset-free
        // Sao Paulo wall-clock DATETIME; purchased_on is a calendar date.
        $cutoff = $observed->setTimezone('America/Sao_Paulo')->format('Y-m-d H:i:s');
        $purchases = CardPurchase::withTrashed()
            ->where('user_id', $user->getKey())
            ->where('created_at', '<=', $cutoff)
            ->where(function ($query) use ($localDate, $cutoff): void {
                $query->whereDate('purchased_on', $localDate)
                    ->orWhere('updated_at', '>', $cutoff);
            })
            ->orderBy('id')
            ->get();

        $reversedPurchaseIds = CardPurchaseReversal::query()
            ->where('user_id', $user->getKey())
            ->whereIn('card_purchase_id', $purchases->pluck('id'))
            ->where('created_at', '<=', $cutoff)
            ->whereDate('reversed_on', '<=', $observed->setTimezone('America/Sao_Paulo')->toDateString())
            ->pluck('card_purchase_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $total = BigDecimal::zero();
        $ids = [];
        $reversed = [];
        $unclassified = 0;
        $unverifiable = [];

        foreach ($purchases as $purchase) {
            $id = (int) $purchase->getKey();
            $deletedAt = $purchase->getRawOriginal('deleted_at');
            $wasReversed = in_array($id, $reversedPurchaseIds, true);
            if ($deletedAt !== null && $deletedAt <= $cutoff && ! $wasReversed) {
                continue;
            }

            // A later edit might have moved the purchase OFF this date or
            // changed amount/classification: do not invent its prior state.
            $updatedAt = $purchase->getRawOriginal('updated_at');
            if ($updatedAt !== null && $updatedAt > $cutoff) {
                $unverifiable[] = $id;

                continue;
            }
            if ($purchase->purchased_on->toDateString() !== $localDate) {
                continue;
            }
            if ($purchase->planning_type === null) {
                $unclassified++;

                continue;
            }
            if ($purchase->planning_type !== ExpensePlanningType::Ordinary) {
                continue;
            }

            if ($wasReversed) {
                $reversed[] = $id;

                continue;
            }

            $total = $total->plus($purchase->gross_amount);
            $ids[] = $id;
        }

        return [
            'ordinary_purchase_total' => (string) $total->toScale(2),
            'purchase_ids' => $ids,
            'reversed_purchase_ids' => $reversed,
            'unclassified_count' => $unclassified,
            'unverifiable_purchase_ids' => $unverifiable,
            'coverage' => $unverifiable === [] ? 'ordinary_card_purchases_reconciled' : 'partial_card_purchase_unverifiable_edits',
        ];
    }
}
