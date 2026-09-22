<?php

namespace App\Queries;

use App\Enums\ExpensePlanningType;
use App\Models\CardPurchase;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Read-only, current-state view of purchases made on a local date. Purchase
 * principal is not the amount due on that day and must not be added to the
 * installment/advance queries or used for confirmed daily spending. Historical
 * reversals and classification edits cannot be reconstructed from this view.
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

        $purchases = CardPurchase::query()
            ->where('user_id', $user->getKey())
            ->whereDate('purchased_on', $localDate)
            ->where('created_at', '<=', $observed)
            ->orderBy('id')
            ->get();

        $total = BigDecimal::zero();
        $ids = [];
        $unclassified = 0;

        foreach ($purchases as $purchase) {
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
            'coverage' => 'current_card_purchases_only',
        ];
    }
}
