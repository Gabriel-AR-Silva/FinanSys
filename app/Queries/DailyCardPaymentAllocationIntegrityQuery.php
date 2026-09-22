<?php

namespace App\Queries;

use App\Models\CardChargePaymentAllocation;
use App\Models\CardPayment;
use App\Models\CardPaymentAllocation;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Read-only allocation evidence for a single card settlement. A cash outflow
 * and its allocation to obligations are different facts: this query does not
 * create daily consumption or change a payment's ledger amount.
 */
final class DailyCardPaymentAllocationIntegrityQuery
{
    /** @return array{allocated_total:string,installment_allocation_ids:list<int>,charge_allocation_ids:list<int>,unverifiable_allocation_ids:list<int>,coverage:string} */
    public function forPayment(User $user, CardPayment $payment, CarbonImmutable $observedAt): array
    {
        if ((int) $payment->user_id !== (int) $user->getKey()) {
            throw new InvalidArgumentException('Pagamento não pertence ao usuário.');
        }

        $observed = $observedAt->utc();
        $total = BigDecimal::zero();
        $installmentIds = [];
        $chargeIds = [];
        $unverifiable = [];

        foreach ([
            'installment' => CardPaymentAllocation::query(),
            'charge' => CardChargePaymentAllocation::query(),
        ] as $kind => $query) {
            $allocations = $query->where('user_id', $user->getKey())
                ->where('card_payment_id', $payment->getKey())
                ->orderBy('id')
                ->get();

            foreach ($allocations as $allocation) {
                if ($this->recordedAfter($allocation->getRawOriginal('created_at'), $observed)) {
                    continue;
                }
                if ($this->recordedAfter($allocation->getRawOriginal('updated_at'), $observed)) {
                    $unverifiable[] = $kind.':'.$allocation->getKey();

                    continue;
                }

                $amount = BigDecimal::of($allocation->amount);
                if (! $amount->isPositive()) {
                    $unverifiable[] = $kind.':'.$allocation->getKey();

                    continue;
                }

                $total = $total->plus($amount);
                if ($kind === 'installment') {
                    $installmentIds[] = (int) $allocation->getKey();
                } else {
                    $chargeIds[] = (int) $allocation->getKey();
                }
            }
        }

        return [
            'allocated_total' => (string) $total->toScale(2),
            'installment_allocation_ids' => $installmentIds,
            'charge_allocation_ids' => $chargeIds,
            'unverifiable_allocation_ids' => $unverifiable,
            'coverage' => $unverifiable !== []
                ? 'partial_card_allocation_unverifiable'
                : ($total->isEqualTo($payment->amount)
                    ? 'card_allocation_amount_verified'
                    : 'card_allocation_amount_mismatch'),
        ];
    }

    private function recordedAfter(mixed $rawTimestamp, CarbonImmutable $observed): bool
    {
        return $rawTimestamp !== null
            && CarbonImmutable::parse((string) $rawTimestamp, 'UTC')->greaterThan($observed);
    }
}
