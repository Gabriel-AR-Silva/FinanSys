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
 * Card obligations due on one local day, never an additional consumption total.
 * Observation mode is conservative: a later edit or soft deletion can move a
 * due date, hide its purchase, or change its amount, status or classification.
 * Without versions, the previous state cannot be recovered. Neither mode is
 * a check-in input.
 */
final class DailyCardDueCommitmentQuery
{
    /** @return array{ordinary_due_total:string,installment_ids:list<int>,charge_ids:list<int>,unclassified_count:int,unverifiable_installment_ids:list<int>,unverifiable_charge_ids:list<int>,coverage:string} */
    public function forUserOnDay(User $user, string $localDate, ?CarbonImmutable $observedAt = null): array
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $localDate, 'America/Sao_Paulo');
        if ($day === false || $day->format('Y-m-d') !== $localDate) {
            throw new InvalidArgumentException('Informe um dia local válido.');
        }

        $observed = $observedAt?->utc();
        if ($observed !== null && $observed->lessThan($day->startOfDay()->utc())) {
            throw new InvalidArgumentException('Não é possível consultar vencimentos antes do início do dia.');
        }

        $excludedStatuses = [CardInstallmentStatus::Advanced, CardInstallmentStatus::Reversed];
        $installments = CardInstallment::query()
            ->where('user_id', $user->getKey())
            ->whereDate('due_on', $localDate)
            ->whereNotIn('status', $excludedStatuses)
            ->whereHas('purchase', function ($query) use ($user, $observed): void {
                if ($observed !== null) {
                    $query->withTrashed();
                }
                $query->where('user_id', $user->getKey());
            })
            ->with(['purchase' => function ($query) use ($observed): void {
                if ($observed !== null) {
                    $query->withTrashed();
                }
            }])
            ->orderBy('id')
            ->get();
        $charges = CardCharge::query()
            ->where('user_id', $user->getKey())
            ->whereDate('due_on', $localDate)
            ->whereNotIn('status', $excludedStatuses)
            ->orderBy('id')
            ->get();

        $unverifiableInstallments = [];
        $unverifiableCharges = [];
        if ($observed !== null) {
            $cutoff = $observed->format('Y-m-d H:i:s');
            // Search across all dates and statuses: a moved, advanced or
            // purchase-linked deleted item must not silently disappear.
            $unverifiableInstallments = CardInstallment::query()
                ->where('user_id', $user->getKey())
                ->where('created_at', '<=', $cutoff)
                ->where(function ($query) use ($cutoff): void {
                    $query->where('updated_at', '>', $cutoff)
                        ->orWhereHas('purchase', fn ($purchase) => $purchase
                            ->withTrashed()
                            ->where(function ($purchase) use ($cutoff): void {
                                $purchase->where('updated_at', '>', $cutoff)
                                    ->orWhere('deleted_at', '>', $cutoff);
                            }));
                })
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $unverifiableCharges = CardCharge::query()
                ->where('user_id', $user->getKey())
                ->where('created_at', '<=', $cutoff)
                ->where('updated_at', '>', $cutoff)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        $total = BigDecimal::zero();
        $installmentIds = [];
        $chargeIds = [];
        $unclassified = 0;

        foreach ($installments as $installment) {
            if ($observed !== null && (
                in_array((int) $installment->getKey(), $unverifiableInstallments, true)
                || $this->recordedAfter($installment->getRawOriginal('created_at'), $observed)
                || $this->recordedAfter($installment->purchase->getRawOriginal('created_at'), $observed)
                || $this->recordedOnOrBefore($installment->purchase->getRawOriginal('deleted_at'), $observed)
            )) {
                continue;
            }
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
            if ($observed !== null && (
                in_array((int) $charge->getKey(), $unverifiableCharges, true)
                || $this->recordedAfter($charge->getRawOriginal('created_at'), $observed)
            )) {
                continue;
            }
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
            'unverifiable_installment_ids' => $unverifiableInstallments,
            'unverifiable_charge_ids' => $unverifiableCharges,
            'coverage' => $observed === null
                ? 'current_card_due_only'
                : ($unverifiableInstallments === [] && $unverifiableCharges === []
                    ? 'observed_card_due_without_versioned_history'
                    : 'partial_card_due_unverifiable_edits'),
        ];
    }

    private function recordedAfter(mixed $rawTimestamp, CarbonImmutable $observed): bool
    {
        return $rawTimestamp !== null
            && CarbonImmutable::parse((string) $rawTimestamp, 'UTC')->greaterThan($observed);
    }

    private function recordedOnOrBefore(mixed $rawTimestamp, CarbonImmutable $observed): bool
    {
        return $rawTimestamp !== null
            && CarbonImmutable::parse((string) $rawTimestamp, 'UTC')->lessThanOrEqualTo($observed);
    }
}
