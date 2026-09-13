<?php

namespace App\Support;

use Brick\Math\BigInteger;
use InvalidArgumentException;

final class CardAdvanceCalculator
{
    /**
     * @param  array<int, string>  $remainingByInstallment
     * @return array<int, array{gross:string, discount:string, net:string}>
     */
    public function allocate(array $remainingByInstallment, string $discountAmount): array
    {
        if ($remainingByInstallment === []) {
            throw new InvalidArgumentException('At least one installment is required.');
        }

        ksort($remainingByInstallment);
        $grossById = [];
        $gross = BigInteger::zero();
        foreach ($remainingByInstallment as $id => $value) {
            $cents = $this->toCents($value);
            if (! $cents->isPositive()) {
                throw new InvalidArgumentException('Selected installments must have a positive remaining amount.');
            }
            $grossById[(int) $id] = $cents;
            $gross = $gross->plus($cents);
        }

        $discount = $this->toCents($discountAmount);
        if ($discount->isNegative() || $discount->isGreaterThan($gross)) {
            throw new InvalidArgumentException('Discount must be between zero and the selected gross amount.');
        }

        $allocations = [];
        $remainders = [];
        $used = BigInteger::zero();
        foreach ($grossById as $id => $installmentGross) {
            $numerator = $discount->multipliedBy($installmentGross);
            $floor = $numerator->quotient($gross);
            $allocations[$id] = ['gross' => $installmentGross, 'discount' => $floor];
            $remainders[$id] = $numerator->remainder($gross);
            $used = $used->plus($floor);
        }

        uksort($remainders, function (int $leftId, int $rightId) use ($remainders): int {
            $fraction = $remainders[$rightId]->compareTo($remainders[$leftId]);

            return $fraction !== 0 ? $fraction : $leftId <=> $rightId;
        });
        $orderedIds = array_keys($remainders);
        $remainingCents = $discount->minus($used)->toInt();
        for ($index = 0; $index < $remainingCents; $index++) {
            $id = $orderedIds[$index];
            $allocations[$id]['discount'] = $allocations[$id]['discount']->plus(1);
        }

        ksort($allocations);

        return array_map(fn (array $allocation): array => [
            'gross' => $this->fromCents($allocation['gross']),
            'discount' => $this->fromCents($allocation['discount']),
            'net' => $this->fromCents($allocation['gross']->minus($allocation['discount'])),
        ], $allocations);
    }

    private function toCents(string $value): BigInteger
    {
        if (! preg_match('/\A(\d{1,17})(?:\.(\d{1,2}))?\z/', $value, $matches)) {
            throw new InvalidArgumentException('Money must be a non-negative decimal with at most two decimal places.');
        }

        return BigInteger::of(ltrim($matches[1].str_pad($matches[2] ?? '', 2, '0'), '0') ?: '0');
    }

    private function fromCents(BigInteger $cents): string
    {
        $whole = $cents->quotient(100);
        $fraction = $cents->remainder(100)->toInt();

        return $whole.'.'.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }
}
