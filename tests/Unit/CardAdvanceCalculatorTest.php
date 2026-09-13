<?php

namespace Tests\Unit;

use App\Support\CardAdvanceCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CardAdvanceCalculatorTest extends TestCase
{
    public function test_it_distributes_discount_proportionally_and_preserves_cents(): void
    {
        $result = (new CardAdvanceCalculator)->allocate([
            10 => '100.00',
            20 => '100.00',
        ], '10.00');

        $this->assertSame('100.00', $result[10]['gross']);
        $this->assertSame('5.00', $result[10]['discount']);
        $this->assertSame('95.00', $result[10]['net']);
        $this->assertSame('5.00', $result[20]['discount']);
        $this->assertSame('95.00', $result[20]['net']);
    }

    public function test_largest_fraction_gets_remaining_cent_with_id_as_tie_breaker(): void
    {
        $result = (new CardAdvanceCalculator)->allocate([
            30 => '33.33',
            10 => '33.33',
            20 => '33.34',
        ], '10.00');

        $this->assertSame('3.33', $result[10]['discount']);
        $this->assertSame('3.34', $result[20]['discount']);
        $this->assertSame('3.33', $result[30]['discount']);
        $this->assertSame('10.00', bcadd(bcadd($result[10]['discount'], $result[20]['discount'], 2), $result[30]['discount'], 2));
    }

    public function test_discount_cannot_exceed_selected_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CardAdvanceCalculator)->allocate([1 => '100.00'], '100.01');
    }
}
