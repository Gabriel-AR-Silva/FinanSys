<?php

namespace App\Queries;

use App\Enums\CardInstallmentStatus;
use App\Enums\ExpensePlanningType;
use App\Models\CardCharge;
use App\Models\CardInstallment;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Current-state card obligations due on one local day. This is NOT the daily
 * spending total: payment does not create a second expense, and due date alone
 * cannot establish the day of ordinary consumption. Do not feed a check-in
 * until the card recognition/advance contract and as-of history are settled.
 */
final class DailyCardDueCommitmentQuery
{
    /** @return array{ordinary_due_total:string,installment_ids:list<int>,charge_ids:list<int>,unclassified_count:int,coverage:string} */
    public function forUserOnDay(User $user, string $localDate): array
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $localDate, 'America/Sao_Paulo');
        if ($day === false || $day->format('Y-m-d') !== $localDate) {
            throw new InvalidArgumentException('Informe um dia local válido.');
        }

        $excludedStatuses = [CardInstallmentStatus::Advanced, CardInstallmentStatus::Reversed];
        $installments = CardInstallment::query()
            ->where('user_id', $user->getKey())
            ->whereDate('due_on', $localDate)
            ->whereNotIn('status', $excludedStatuses)
            ->whereHas('purchase', fn ($query) => $query->where('user_id', $user->getKey()))
            ->with('purchase')
            ->orderBy('id')
            ->get();
        $charges = CardCharge::query()
            ->where('user_id', $user->getKey())
            ->whereDate('due_on', $localDate)
            ->whereNotIn('status', $excludedStatuses)
            ->orderBy('id')
            ->get();

        $total = BigDecimal::zero();
        $installmentIds = [];
        $chargeIds = [];
        $unclassified = 0;

        foreach ($installments as $installment) {
            if ($installment->purchase->planning_type === null) {
                $unclassified++;

                continue;
            }
            if ($installment->purchase->planning_type !== ExpensePlanningType::Ordinary) {
                continue;
            }

            $total = $total->plus($installment->gross_amount);
            $installmentIds[] = (int) $installment->getKey();
        }

        foreach ($charges as $charge) {
            if ($charge->planning_type === null) {
                $unclassified++;

                continue;
            }
            if ($charge->planning_type !== ExpensePlanningType::Ordinary) {
                continue;
            }

            $total = $total->plus($charge->amount);
            $chargeIds[] = (int) $charge->getKey();
        }

        return [
            'ordinary_due_total' => (string) $total->toScale(2),
            'installment_ids' => $installmentIds,
            'charge_ids' => $chargeIds,
            'unclassified_count' => $unclassified,
            'coverage' => 'current_card_due_only',
        ];
    }
}
