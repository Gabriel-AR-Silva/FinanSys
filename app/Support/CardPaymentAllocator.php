<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use InvalidArgumentException;

final class CardPaymentAllocator
{
    /**
     * @param  list<array{id:int,due_on:string,remaining:string}>  $obligations
     * @return list<array{installment_id:int,amount:string}>
     */
    public function allocate(string $payment, array $obligations): array
    {
        $amount = $this->positiveDecimal($payment);
        $normalized = [];
        $seen = [];
        foreach ($obligations as $obligation) {
            if (! isset($obligation['id'], $obligation['due_on'], $obligation['remaining']) || ! is_int($obligation['id']) || $obligation['id'] < 1) {
                throw new InvalidArgumentException('Each obligation must have a positive integer id, due date and remaining amount.');
            }
            if (isset($seen[$obligation['id']])) {
                throw new InvalidArgumentException('Obligation ids must be unique.');
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $obligation['due_on']);
            if ($date === false || $date->format('Y-m-d') !== $obligation['due_on']) {
                throw new InvalidArgumentException('Obligation due dates must use Y-m-d.');
            }
            $remaining = $this->positiveDecimal($obligation['remaining']);
            $seen[$obligation['id']] = true;
            $normalized[] = ['id' => $obligation['id'], 'due_on' => $obligation['due_on'], 'remaining' => $remaining];
        }
        usort($normalized, fn (array $left, array $right): int => [$left['due_on'], $left['id']] <=> [$right['due_on'], $right['id']]);
        $debt = array_reduce($normalized, fn (BigDecimal $total, array $item): BigDecimal => $total->plus($item['remaining']), BigDecimal::zero());
        if ($amount->isGreaterThan($debt)) {
            throw new InvalidArgumentException('Payment must not exceed the selected debt.');
        }

        $unallocated = $amount;
        $allocations = [];
        foreach ($normalized as $obligation) {
            if ($unallocated->isZero()) {
                break;
            }
            $allocated = $unallocated->isGreaterThan($obligation['remaining']) ? $obligation['remaining'] : $unallocated;
            $allocations[] = ['installment_id' => $obligation['id'], 'amount' => (string) $allocated];
            $unallocated = $unallocated->minus($allocated);
        }

        return $allocations;
    }

    private function positiveDecimal(string $value): BigDecimal
    {
        if (! preg_match('/\A\d+(?:\.\d{1,2})?\z/', $value)) {
            throw new InvalidArgumentException('Amounts must be positive decimal strings with at most two decimal places.');
        }
        $amount = BigDecimal::of($value)->toScale(2, RoundingMode::Unnecessary);
        if (! $amount->isPositive()) {
            throw new InvalidArgumentException('Amounts must be positive.');
        }

        return $amount;
    }
}
