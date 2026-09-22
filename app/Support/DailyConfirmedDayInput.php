<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

/**
 * Pure D2 boundary for an explicit check-in after the local day has ended.
 * The caller must supply a reconciled, eligible spend amount; this class
 * neither selects ledger facts nor records consent, history or a check-in.
 */
final class DailyConfirmedDayInput
{
    /**
     * @param  list<array{id:int,user_id:int,amount:string,effective_at:string,recorded_at:string}>  $versions
     * @return array{budget_version_id:int,calculator_day:array{date:string,status:string,budget:string,spent:string}}
     */
    public function build(int $userId, string $date, string $confirmedAt, string $eligibleSpent, array $versions): array
    {
        if (preg_match('/^(0|[1-9]\d*)(\.\d{1,2})?$/D', $eligibleSpent) !== 1) {
            throw new InvalidArgumentException('Eligible spend must be a non-negative decimal string with at most two decimal places.');
        }

        $selected = (new DailyBudgetVersionSelector)->resolve($userId, $date, $confirmedAt, $versions);
        if ($selected === null) {
            throw new InvalidArgumentException('An explicitly recorded daily budget is required before confirming the day.');
        }

        return [
            'budget_version_id' => $selected['id'],
            'calculator_day' => [
                'date' => $date,
                'status' => 'confirmed',
                'budget' => (string) BigDecimal::of($selected['amount'])->toScale(2),
                'spent' => (string) BigDecimal::of($eligibleSpent)->toScale(2),
            ],
        ];
    }
}
