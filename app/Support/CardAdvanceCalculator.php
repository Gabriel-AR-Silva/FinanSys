<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

class CardAdvanceCalculator
{
    /** @param array<int, string> $remainingByInstallment @return array<int, array{gross:string,discount:string,net:string}> */
    public function allocate(array $remainingByInstallment, string $discountAmount): array
    {
        if ($remainingByInstallment === []) {
            throw new InvalidArgumentException('At least one installment is required.');
        }

        $gross = array_reduce(
            $remainingByInstallment,
            fn (BigDecimal $sum, string $value): BigDecimal => $sum->plus(BigDecimal::of($value)),
            BigDecimal::zero(),
        );
        $discount = BigDecimal::of($discountAmount)->toScale(2, RoundingMode::Unnecessary);
        if ($discount->isNegative() || $discount->isGreaterThan($gross)) {
            throw new InvalidArgumentException('Discount must be between zero and the selected gross amount.');
        }

        $allocations = [];
        $fractions = [];
        $used = BigDecimal::zero();
        ksort($remainingByInstallment);

        foreach ($remainingByInstallment as $id => $value) {
            $remaining = BigDecimal::of($value)->toScale(2, RoundingMode::Unnecessary);
            if (! $remaining->isPositive()) {
                throw new InvalidArgumentException('Selected installments must have a positive remaining amount.');
            }
            $exact = $discount->multipliedBy($remaining)->dividedBy($gross, 8, RoundingMode::Down);
            $floor = $exact->toScale(2, RoundingMode::Down);
            $allocations[$id] = ['gross' => $remaining, 'discount' => $floor];
            $fractions[$id] = $exact->minus($floor);
            $used = $used->plus($floor);
        }

        $remainingCents = $discount->minus($used)->multipliedBy(100)->toInt();
        uksort($fractions, function (int $leftId, int $rightId) use ($fractions): int {
            $comparison = $fractions[$rightId]->compareTo($fractions[$leftId]);
            return $comparison !== 0 ? $comparison : $leftId <=> $rightId;
        });
        $orderedIds = array_keys($fractions);
        for ($i = 0; $i < $remainingCents; $i++) {
            $id = $orderedIds[$i % count($orderedIds)];
            $allocations[$id]['discount'] = $allocations[$id]['discount']->plus('0.01');
        }

        return array_map(fn (array $row): array => [
            'gross' => (string) $row['gross'],
            'discount' => (string) $row['discount'],
            'net' => (string) $row['gross']->minus($row['discount']),
        ], $allocations);
    }
}
