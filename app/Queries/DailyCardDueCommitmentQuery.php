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
    /** @return array{ordinary_due_total:string,fixed_due_total:string,extraordinary_due_total:string,installment_ids:list<int>,charge_ids:list<int>,fixed_installment_ids:list<int>,fixed_charge_ids:list<int>,extraordinary_installment_ids:list<int>,extraordinary_charge_ids:list<int>,unclassified_count:int,unverifiable_installment_ids:list<int>,unverifiable_charge_ids:list<int>,coverage:string} */
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

        $totals = [
            ExpensePlanningType::Ordinary->value => BigDecimal::zero(),
            ExpensePlanningType::Fixed->value => BigDecimal::zero(),
            ExpensePlanningType::Extraordinary->value => BigDecimal::zero(),
        ];
        $installmentIds = [];
        $chargeIds = [];
        $fixedInstallmentIds = [];
        $fixedChargeIds = [];
        $extraordinaryInstallmentIds = [];
        $extraordinaryChargeIds = [];
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
            $type = $installment->purchase->planning_type;
            if ($type === null) {
                $unclassified++;

                continue;
            }
            $totals[$type->value] = $totals[$type->value]->plus($installment->gross_amount);
            match ($type) {
                ExpensePlanningType::Ordinary => $installmentIds[] = (int) $installment->getKey(),
                ExpensePlanningType::Fixed => $fixedInstallmentIds[] = (int) $installment->getKey(),
                ExpensePlanningType::Extraordinary => $extraordinaryInstallmentIds[] = (int) $installment->getKey(),
            };
        }

        foreach ($charges as $charge) {
            if ($observed !== null && (
                in_array((int) $charge->getKey(), $unverifiableCharges, true)
                || $this->recordedAfter($charge->getRawOriginal('created_at'), $observed)
            )) {
                continue;
            }
            $type = $charge->planning_type;
            if ($type === null) {
                $unclassified++;

                continue;
            }
            $totals[$type->value] = $totals[$type->value]->plus($charge->amount);
            match ($type) {
                ExpensePlanningType::Ordinary => $chargeIds[] = (int) $charge->getKey(),
                ExpensePlanningType::Fixed => $fixedChargeIds[] = (int) $charge->getKey(),
                ExpensePlanningType::Extraordinary => $extraordinaryChargeIds[] = (int) $charge->getKey(),
            };
        }

        return [
            'ordinary_due_total' => (string) $totals[ExpensePlanningType::Ordinary->value]->toScale(2),
            'fixed_due_total' => (string) $totals[ExpensePlanningType::Fixed->value]->toScale(2),
            'extraordinary_due_total' => (string) $totals[ExpensePlanningType::Extraordinary->value]->toScale(2),
            'installment_ids' => $installmentIds,
            'charge_ids' => $chargeIds,
            'fixed_installment_ids' => $fixedInstallmentIds,
            'fixed_charge_ids' => $fixedChargeIds,
            'extraordinary_installment_ids' => $extraordinaryInstallmentIds,
            'extraordinary_charge_ids' => $extraordinaryChargeIds,
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
