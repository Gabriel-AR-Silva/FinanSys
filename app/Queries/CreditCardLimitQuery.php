<?php

namespace App\Queries;

use App\Enums\CardInstallmentStatus;
use App\Models\CardCharge;
use App\Models\CardInstallment;
use App\Models\CreditCard;
use Brick\Math\BigDecimal;

class CreditCardLimitQuery
{
    /** @return array{total:?string,outstanding:string,available:?string,over_limit:string} */
    public function forCard(CreditCard $card): array
    {
        $installmentOutstanding = CardInstallment::query()
            ->where('user_id', $card->user_id)
            ->where('status', CardInstallmentStatus::Pending)
            ->whereHas('purchase', fn ($query) => $query->where('credit_card_id', $card->id))
            ->get(['gross_amount', 'paid_amount'])
            ->reduce(
                fn (BigDecimal $total, CardInstallment $installment): BigDecimal => $total
                    ->plus(BigDecimal::of($installment->gross_amount)->minus($installment->paid_amount)),
                BigDecimal::zero(),
            );

        $chargeOutstanding = CardCharge::query()
            ->where('user_id', $card->user_id)
            ->where('credit_card_id', $card->id)
            ->where('status', CardInstallmentStatus::Pending)
            ->get(['amount', 'paid_amount'])
            ->reduce(
                fn (BigDecimal $total, CardCharge $charge): BigDecimal => $total
                    ->plus(BigDecimal::of($charge->amount)->minus($charge->paid_amount)),
                BigDecimal::zero(),
            );

        $outstanding = $installmentOutstanding->plus($chargeOutstanding);

        if ($card->credit_limit === null) {
            return [
                'total' => null,
                'outstanding' => (string) $outstanding,
                'available' => null,
                'over_limit' => '0.00',
            ];
        }

        $total = BigDecimal::of($card->credit_limit);
        $available = $total->minus($outstanding);

        return [
            'total' => (string) $total,
            'outstanding' => (string) $outstanding,
            'available' => (string) ($available->isNegative() ? BigDecimal::zero()->toScale(2) : $available->toScale(2)),
            'over_limit' => (string) ($available->isNegative() ? $available->negated()->toScale(2) : BigDecimal::zero()->toScale(2)),
        ];
    }
}
