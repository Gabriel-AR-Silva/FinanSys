<?php

namespace App\Queries;

use App\Enums\CardInstallmentStatus;
use App\Enums\ExpensePlanningType;
use App\Models\CardCharge;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Confirmed card charges are their own events on charged_on, distinct from
 * purchase principal, invoice due date, payment and card advance. This is a
 * partial read model; unversioned changes cannot feed historical check-ins.
 */
final class DailyCardChargeRecognitionQuery
{
    /** @return array{ordinary_charge_total:string,charge_ids:list<int>,unclassified_count:int,unverifiable_charge_ids:list<int>,coverage:string} */
    public function forUserOnDay(User $user, string $localDate, ?CarbonImmutable $observedAt = null): array
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $localDate, 'America/Sao_Paulo');
        if ($day === false || $day->format('Y-m-d') !== $localDate) {
            throw new InvalidArgumentException('Informe um dia local válido.');
        }
        $observed = ($observedAt ?? CarbonImmutable::now('UTC'))->utc();
        if ($observed->lessThan($day->startOfDay()->utc())) {
            throw new InvalidArgumentException('Não é possível consultar encargos antes do início do dia.');
        }
        // Writers store offset-free Sao Paulo DATETIME; charged_on is DATE.
        $cutoff = $observed->setTimezone('America/Sao_Paulo')->format('Y-m-d H:i:s');
        $charges = CardCharge::query()
            ->where('user_id', $user->getKey())
            ->where('created_at', '<=', $cutoff)
            ->where(function ($query) use ($localDate, $cutoff): void {
                $query->whereDate('charged_on', $localDate)
                    ->orWhere('updated_at', '>', $cutoff);
            })
            ->orderBy('id')
            ->get();

        $total = BigDecimal::zero();
        $ids = [];
        $unclassified = 0;
        $unverifiable = [];
        foreach ($charges as $charge) {
            $id = (int) $charge->getKey();
            // A later edit may have changed charged_on, planning_type or amount;
            // a reversal also needs its original event to be reconstructed.
            if (($charge->getRawOriginal('updated_at') !== null && $charge->getRawOriginal('updated_at') > $cutoff)
                || $charge->status === CardInstallmentStatus::Reversed) {
                $unverifiable[] = $id;

                continue;
            }
            if ($charge->charged_on->toDateString() !== $localDate) {
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
            $ids[] = $id;
        }

        return [
            'ordinary_charge_total' => (string) $total->toScale(2),
            'charge_ids' => $ids,
            'unclassified_count' => $unclassified,
            'unverifiable_charge_ids' => $unverifiable,
            'coverage' => $unverifiable === [] ? 'card_charges_only' : 'partial_card_charge_unverifiable_edits',
        ];
    }
}
