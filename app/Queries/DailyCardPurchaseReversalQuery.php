<?php

namespace App\Queries;

use App\Models\CardPurchase;
use App\Models\CardPurchaseReversal;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Read-only reversal events recorded by the observation instant. Cancelled
 * obligations and credited settled amounts are distinct, not negative daily
 * consumption or new income. Never add these totals to the purchase, due or
 * advance views. Earlier purchase classification remains unreconstructable.
 */
final class DailyCardPurchaseReversalQuery
{
    /** @return array{cancelled_pending_total:string,credited_paid_total:string,reversal_ids:list<int>,unverifiable_reversal_ids:list<int>,coverage:string} */
    public function forUserOnDay(User $user, string $localDate, ?CarbonImmutable $observedAt = null): array
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $localDate, 'America/Sao_Paulo');
        if ($day === false || $day->format('Y-m-d') !== $localDate) {
            throw new InvalidArgumentException('Informe um dia local válido.');
        }

        $observed = ($observedAt ?? CarbonImmutable::now('UTC'))->utc();
        if ($observed->lessThan($day->startOfDay()->utc())) {
            throw new InvalidArgumentException('Não é possível consultar estornos antes do início do dia.');
        }

        $reversals = CardPurchaseReversal::query()
            ->where('user_id', $user->getKey())
            ->whereDate('reversed_on', $localDate)
            ->where('created_at', '<=', $observed)
            ->orderBy('id')
            ->get();

        // A reversal's user_id alone does not prove that its linked purchase
        // belongs to the same tenant. Include soft-deleted original purchases:
        // legitimate reversal actions can soft-delete them.
        $purchases = CardPurchase::withTrashed()
            ->whereIn('id', $reversals->pluck('card_purchase_id'))
            ->get(['id', 'user_id', 'credit_card_id'])
            ->keyBy('id');

        $cancelled = BigDecimal::zero();
        $credited = BigDecimal::zero();
        $ids = [];
        $unverifiable = [];

        foreach ($reversals as $reversal) {
            $purchase = $purchases->get($reversal->card_purchase_id);
            if ($purchase === null
                || (int) $purchase->user_id !== (int) $user->getKey()
                || (int) $purchase->credit_card_id !== (int) $reversal->credit_card_id) {
                $unverifiable[] = (int) $reversal->getKey();

                continue;
            }

            // Reversal amounts are not versioned. A later edit cannot be
            // reconstructed from the current row for an earlier observation.
            // MySQL stores DATETIME without an offset; inspect its raw UTC value.
            $updatedAt = $reversal->getRawOriginal('updated_at');
            if ($updatedAt !== null && CarbonImmutable::parse((string) $updatedAt, 'UTC')->greaterThan($observed)) {
                $unverifiable[] = (int) $reversal->getKey();

                continue;
            }

            $cancelled = $cancelled->plus($reversal->cancelled_pending_amount);
            $credited = $credited->plus($reversal->credited_paid_amount);
            $ids[] = (int) $reversal->getKey();
        }

        return [
            'cancelled_pending_total' => (string) $cancelled->toScale(2),
            'credited_paid_total' => (string) $credited->toScale(2),
            'reversal_ids' => $ids,
            'unverifiable_reversal_ids' => $unverifiable,
            'coverage' => $unverifiable === [] ? 'card_purchase_reversal_events_only' : 'partial_card_purchase_reversal_events',
        ];
    }
}
