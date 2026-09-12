<?php

namespace Tests\Unit\Support;

use App\Support\CardPaymentAllocator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CardPaymentAllocatorTest extends TestCase
{
    public function test_payment_is_allocated_to_oldest_obligation_then_by_stable_id(): void
    {
        $result = (new CardPaymentAllocator)->allocate('150.00', [
            ['id' => 30, 'due_on' => '2026-10-10', 'remaining' => '100.00'],
            ['id' => 20, 'due_on' => '2026-09-10', 'remaining' => '100.00'],
            ['id' => 10, 'due_on' => '2026-09-10', 'remaining' => '100.00'],
        ]);

        $this->assertSame([
            ['installment_id' => 10, 'amount' => '100.00'],
            ['installment_id' => 20, 'amount' => '50.00'],
        ], $result);
    }

    public function test_cent_values_are_conserved_without_floating_point(): void
    {
        $result = (new CardPaymentAllocator)->allocate('0.03', [
            ['id' => 1, 'due_on' => '2026-09-01', 'remaining' => '0.01'],
            ['id' => 2, 'due_on' => '2026-09-02', 'remaining' => '0.02'],
        ]);

        $this->assertSame([
            ['installment_id' => 1, 'amount' => '0.01'],
            ['installment_id' => 2, 'amount' => '0.02'],
        ], $result);
    }

    #[DataProvider('invalidCases')]
    public function test_invalid_or_ambiguous_allocation_is_rejected(string $payment, array $obligations): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new CardPaymentAllocator)->allocate($payment, $obligations);
    }

    public static function invalidCases(): array
    {
        return [
            'overpayment' => ['200.01', [['id' => 1, 'due_on' => '2026-09-01', 'remaining' => '200.00']]],
            'zero payment' => ['0', [['id' => 1, 'due_on' => '2026-09-01', 'remaining' => '1.00']]],
            'fraction overflow' => ['1.001', [['id' => 1, 'due_on' => '2026-09-01', 'remaining' => '2.00']]],
            'duplicate id' => ['1.00', [
                ['id' => 1, 'due_on' => '2026-09-01', 'remaining' => '1.00'],
                ['id' => 1, 'due_on' => '2026-09-02', 'remaining' => '1.00'],
            ]],
            'invalid date' => ['1.00', [['id' => 1, 'due_on' => '2026-02-30', 'remaining' => '1.00']]],
            'empty debt' => ['1.00', []],
        ];
    }
}
