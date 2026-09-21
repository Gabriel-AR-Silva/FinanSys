<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Calculadora pura D1a. A seleção de despesas e a confirmação dos dias são
 * responsabilidades do adaptador futuro; não lê banco, relógio ou saldo.
 */
final class DailyMarginCalculator
{
    /**
     * @param  list<array{date:string,status:string,budget?:string,spent?:string}>  $days
     * @return array{month:string,days:list<array{date:string,status:string,margin:?string}>,confirmed_days:int,pending_days:int,gross_savings:string,gross_excess:string,net_margin:string}
     */
    public function calculate(string $month, array $days): array
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month) !== 1) {
            throw new InvalidArgumentException('Month must use YYYY-MM.');
        }

        $seen = [];
        $results = [];
        $savings = BigDecimal::zero()->toScale(2);
        $excess = BigDecimal::zero()->toScale(2);
        $confirmed = 0;
        $pending = 0;

        foreach ($days as $day) {
            if (is_array($day) === false || isset($day['date'], $day['status']) === false || is_string($day['date']) === false || is_string($day['status']) === false) {
                throw new InvalidArgumentException('Each day requires a date and status.');
            }

            $date = $day['date'];
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if ($parsed === false || $parsed->format('Y-m-d') !== $date || str_starts_with($date, $month.'-') === false || isset($seen[$date])) {
                throw new InvalidArgumentException('Dates must be valid, unique and belong to the requested month.');
            }

            $seen[$date] = true;

            if ($day['status'] === 'pending') {
                if (array_key_exists('budget', $day) || array_key_exists('spent', $day)) {
                    throw new InvalidArgumentException('Pending days cannot carry confirmed amounts.');
                }

                $pending++;
                $results[] = ['date' => $date, 'status' => 'pending', 'margin' => null];

                continue;
            }

            if ($day['status'] !== 'confirmed' || isset($day['budget'], $day['spent']) === false || is_string($day['budget']) === false || is_string($day['spent']) === false) {
                throw new InvalidArgumentException('Confirmed days require decimal budget and spent amounts.');
            }

            $budget = $this->money($day['budget']);
            $spent = $this->money($day['spent']);
            $margin = $budget->minus($spent);

            if ($margin->isNegative()) {
                $excess = $excess->plus($margin->abs());
            } else {
                $savings = $savings->plus($margin);
            }

            $confirmed++;
            $results[] = ['date' => $date, 'status' => 'confirmed', 'margin' => (string) $margin];
        }

        return [
            'month' => $month,
            'days' => $results,
            'confirmed_days' => $confirmed,
            'pending_days' => $pending,
            'gross_savings' => (string) $savings,
            'gross_excess' => (string) $excess,
            'net_margin' => (string) $savings->minus($excess),
        ];
    }

    private function money(string $amount): BigDecimal
    {
        if (preg_match('/^(0|[1-9]\d*)(\.\d{1,2})?$/D', $amount) !== 1) {
            throw new InvalidArgumentException('Amounts must be non-negative decimal strings with at most two decimal places.');
        }

        return BigDecimal::of($amount)->toScale(2);
    }
}
