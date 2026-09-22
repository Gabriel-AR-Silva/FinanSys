<?php

namespace Tests\Unit\Support;

use App\Support\DailyMarginCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DailyMarginCalculatorBoundaryTest extends TestCase
{
    public function test_pending_only_month_does_not_count_any_confirmed_day(): void
    {
        $result = (new DailyMarginCalculator)->calculate('2026-09', [
            ['date' => '2026-09-01', 'status' => 'pending'],
            ['date' => '2026-09-02', 'status' => 'pending'],
        ]);

        self::assertSame(0, $result['confirmed_days']);
        self::assertSame(2, $result['pending_days']);
        self::assertSame([null, null], array_column($result['days'], 'margin'));
        self::assertSame('0.00', $result['net_margin']);
    }

    public function test_chosen_interface_uses_spent_margin_and_excess_names(): void
    {
        $result = (new DailyMarginCalculator)->calculate('2026-09', [
            ['date' => '2026-09-01', 'status' => 'confirmed', 'budget' => '90.00', 'spent' => '110.00'],
        ]);

        self::assertSame(
            ['month', 'days', 'confirmed_days', 'pending_days', 'gross_savings', 'gross_excess', 'net_margin'],
            array_keys($result),
        );
        self::assertSame(['date', 'status', 'margin'], array_keys($result['days'][0]));
        self::assertSame('confirmed', $result['days'][0]['status']);
        self::assertSame('-20.00', $result['days'][0]['margin']);
        self::assertSame('20.00', $result['gross_excess']);
        self::assertSame('-20.00', $result['net_margin']);
    }

    public function test_absent_days_are_not_invented_as_confirmed_or_pending(): void
    {
        $result = (new DailyMarginCalculator)->calculate('2026-09', [
            ['date' => '2026-09-02', 'status' => 'pending'],
        ]);

        self::assertSame(['2026-09-02'], array_column($result['days'], 'date'));
        self::assertSame(0, $result['confirmed_days']);
        self::assertSame(1, $result['pending_days']);
    }

    public function test_zero_budget_with_spending_is_an_excess(): void
    {
        $result = (new DailyMarginCalculator)->calculate('2026-09', [
            ['date' => '2026-09-01', 'status' => 'confirmed', 'budget' => '0.00', 'spent' => '0.01'],
        ]);

        self::assertSame('-0.01', $result['days'][0]['margin']);
        self::assertSame('0.00', $result['gross_savings']);
        self::assertSame('0.01', $result['gross_excess']);
        self::assertSame('-0.01', $result['net_margin']);
    }

    public function test_preserves_input_order_without_claiming_chronological_order(): void
    {
        $result = (new DailyMarginCalculator)->calculate('2026-09', [
            ['date' => '2026-09-30', 'status' => 'pending'],
            ['date' => '2026-09-01', 'status' => 'confirmed', 'budget' => '90.00', 'spent' => '80.00'],
        ]);

        self::assertSame(['2026-09-30', '2026-09-01'], array_column($result['days'], 'date'));
        self::assertSame([null, '10.00'], array_column($result['days'], 'margin'));
    }

    public function test_rejects_pending_day_with_budget_even_if_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DailyMarginCalculator)->calculate('2026-09', [
            ['date' => '2026-09-01', 'status' => 'pending', 'budget' => '0.00'],
        ]);
    }

    public function test_rejects_non_leap_february_29(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DailyMarginCalculator)->calculate('2026-02', [
            ['date' => '2026-02-29', 'status' => 'pending'],
        ]);
    }
}
