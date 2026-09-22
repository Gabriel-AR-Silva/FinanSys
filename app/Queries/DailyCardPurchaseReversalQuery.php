<?php

namespace App\Queries;

use App\Enums\AuditAction;
use App\Models\AuditLog;
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
            ->where('created_at', '<=', $observed)
            ->where(function ($query) use ($localDate, $observed) {
                $query->whereDate('reversed_on', $localDate)
                    // An edit after observation may have moved the reversal
                    // away from this day. Without its original dated audit,
                    // report uncertainty instead of silently omitting it.
                    ->orWhere('updated_at', '>', $observed);
            })
            ->orderBy('id')
            ->get();

        // Include soft-deleted original purchases: legitimate reversals can
        // soft-delete them. The reversal's user_id alone is not provenance.
        $purchases = CardPurchase::withTrashed()
            ->whereIn('id', $reversals->pluck('card_purchase_id'))
            ->get(['id', 'user_id', 'credit_card_id'])
            ->keyBy('id');

        // The official reversal action stores an immutable creation snapshot.
        // Only a single, consistent snapshot can rule out an unrelated day;
        // missing or duplicate audits must never silently erase uncertainty.
        $originAudits = AuditLog::query()
            ->where('user_id', $user->getKey())
            ->where('auditable_type', (new CardPurchaseReversal)->getMorphClass())
            ->where('action', AuditAction::Reversed->value)
            ->where('created_at', '<=', $observed)
            ->whereIn('auditable_id', $reversals->pluck('id'))
            ->orderBy('id')
            ->get()
            ->groupBy('auditable_id');

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

            // MySQL DATETIME has no offset: read the raw value as UTC.
            $updatedAt = $reversal->getRawOriginal('updated_at');
            if ($updatedAt !== null && CarbonImmutable::parse((string) $updatedAt, 'UTC')->greaterThan($observed)) {
                if ($reversal->reversed_on->toDateString() !== $localDate) {
                    $audits = $originAudits->get($reversal->getKey());
                    if ($audits !== null && $audits->count() === 1) {
                        $snapshot = $audits->first()->after;
                        $originalDay = is_array($snapshot) ? ($snapshot['reversed_on'] ?? null) : null;
                        if (is_array($snapshot)
                            && isset($snapshot['id'], $snapshot['user_id'], $snapshot['credit_card_id'], $snapshot['card_purchase_id'])
                            && (int) $snapshot['id'] === (int) $reversal->getKey()
                            && (int) $snapshot['user_id'] === (int) $user->getKey()
                            && (int) $snapshot['credit_card_id'] === (int) $reversal->credit_card_id
                            && (int) $snapshot['card_purchase_id'] === (int) $reversal->card_purchase_id
                            && is_string($originalDay)
                            && preg_match('/\A\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z)?\z/', $originalDay)
                            && substr($originalDay, 0, 10) !== $localDate) {
                            $originDate = CarbonImmutable::createFromFormat('!Y-m-d', substr($originalDay, 0, 10), 'America/Sao_Paulo');
                            if ($originDate !== false && $originDate->format('Y-m-d') === substr($originalDay, 0, 10)) {
                                continue;
                            }
                        }
                    }
                }

                // The original amount/date is not reconstructable from an
                // unversioned row. Do not call its current values historical.
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
