<?php

namespace Tests\Unit\Support;

use App\Support\CardAdvanceCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CardAdvanceCalculatorTest extends TestCase
{
    public function test_allocates_equal_discount_and_preserves_conservation(): void
    {
        $result = (new CardAdvanceCalculator)->allocate([20 => '100.00', 10 => '100.00'], '10.00');

        $this->assertSame([
            10 => ['gross' => '100.00', 'discount' => '5.00', 'net' => '95.00'],
            20 => ['gross' => '100.00', 'discount' => '5.00', 'net' => '95.00'],
        ], $result);
    }

    public function test_distributes_remaining_cents_by_largest_fraction_then_id(): void
    {
        $result = (new CardAdvanceCalculator)->allocate([3 => '0.01', 2 => '0.01', 1 => '0.01'], '0.02');

        $this->assertSame('0.01', $result[1]['discount']);
        $this->assertSame('0.01', $result[2]['discount']);
        $this->assertSame('0.00', $result[3]['discount']);
        $this->assertSame('0.01', $result[3]['net']);
    }

    public function test_supports_zero_and_full_discount(): void
    {
        $calculator = new CardAdvanceCalculator;

        $this->assertSame('10.01', $calculator->allocate([1 => '10.01'], '0.00')[1]['net']);
        $this->assertSame('0.00', $calculator->allocate([1 => '10.01'], '10.01')[1]['net']);
    }

    #[DataProvider('invalidValues')]
    public function test_rejects_invalid_values(array $installments, string $discount): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new CardAdvanceCalculator)->allocate($installments, $discount);
    }

    public static function invalidValues(): array
    {
        return [
            'empty' => [[], '0.00'],
            'zero installment' => [[1 => '0.00'], '0.00'],
            'negative discount' => [[1 => '10.00'], '-0.01'],
            'excess discount' => [[1 => '10.00'], '10.01'],
            'too many decimals' => [[1 => '10.001'], '0.00'],
        ];
    }
}
