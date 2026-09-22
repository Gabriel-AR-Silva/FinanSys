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
    /** @return array{ordinary_purchase_total:string,purchase_ids:list<int>,unclassified_count:int,unverifiable_purchase_ids:list<int>,coverage:string} */
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

        // A purchase edited after observation may have moved OFF the queried
        // date. Include later-edited candidates even if their current date no
        // longer matches, otherwise the old day looks falsely complete. Since
        // the original date is not versioned, unrelated edits may conservatively
        // mark other days partial; never reconstruct an old amount or date.
        $purchases = CardPurchase::withTrashed()
            ->where('user_id', $user->getKey())
            ->where('created_at', '<=', $observed)
            ->where(function ($query) use ($localDate, $observed): void {
                $query->whereDate('purchased_on', $localDate)
                    ->orWhere('updated_at', '>', $observed);
            })
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
        $unverifiable = [];

        foreach ($purchases as $purchase) {
            // MySQL stores DATETIME without an offset. Eloquent's date cast can
            // reinterpret it in the app timezone, so read the raw UTC value.
            $deletedAt = $purchase->getRawOriginal('deleted_at');
            if ($deletedAt !== null
                && CarbonImmutable::parse((string) $deletedAt, 'UTC')->lessThanOrEqualTo($observed)
                && ! in_array((int) $purchase->getKey(), $reversedPurchaseIds, true)) {
                continue;
            }

            // An edit after the observation may have changed the amount, date or
            // classification. The old state was not versioned, so never pass the
            // current value off as a verified historical amount.
            $updatedAt = $purchase->getRawOriginal('updated_at');
            if ($updatedAt !== null && CarbonImmutable::parse((string) $updatedAt, 'UTC')->greaterThan($observed)) {
                $unverifiable[] = (int) $purchase->getKey();

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
            'unverifiable_purchase_ids' => $unverifiable,
            'coverage' => $unverifiable === [] ? 'gross_card_purchases_with_recorded_reversals' : 'partial_gross_card_purchases_unverifiable_edits',
        ];
    }
}
