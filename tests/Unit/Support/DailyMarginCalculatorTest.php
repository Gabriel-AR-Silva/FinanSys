<?php

namespace Tests\Unit\Support;

use App\Support\DailyMarginCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DailyMarginCalculatorTest extends TestCase
{
    #[DataProvider('approvedDailyMarginExamples')]
    public function test_calculates_approved_daily_margin_examples(
        string $budget,
        string $spent,
        string $margin,
        string $grossSavings,
        string $grossExcess,
    ): void {
        $result = (new DailyMarginCalculator)->calculate('2026-09', [
            ['date' => '2026-09-01', 'status' => 'confirmed', 'budget' => $budget, 'spent' => $spent],
        ]);

        self::assertSame($margin, $result['days'][0]['margin']);
        self::assertSame($grossSavings, $result['gross_savings']);
        self::assertSame($grossExcess, $result['gross_excess']);
        self::assertSame($margin, $result['net_margin']);
    }

    public static function approvedDailyMarginExamples(): iterable
    {
        yield 'saves ten' => ['90.00', '80.00', '10.00', '10.00', '0.00'];
        yield 'exceeds by twenty' => ['90.00', '110.00', '-20.00', '0.00', '20.00'];
    }

    public function test_savings_and_excess_are_separate_and_do_not_redistribute_budget(): void
    {
        $result = (new DailyMarginCalculator)->calculate('2026-09', [
            ['date' => '2026-09-01', 'status' => 'confirmed', 'budget' => '90.00', 'spent' => '60.00'],
            ['date' => '2026-09-02', 'status' => 'confirmed', 'budget' => '90.00', 'spent' => '70.00'],
            ['date' => '2026-09-03', 'status' => 'confirmed', 'budget' => '90.00', 'spent' => '200.00'],
        ]);

        self::assertSame('50.00', $result['gross_savings']);
        self::assertSame('110.00', $result['gross_excess']);
        self::assertSame('-60.00', $result['net_margin']);
        self::assertSame(['30.00', '20.00', '-110.00'], array_column($result['days'], 'margin'));
        self::assertSame(3, $result['confirmed_days']);
    }

    public function test_pending_day_is_not_zero_confirmed_and_zero_spend_requires_confirmation(): void
    {
        $result = (new DailyMarginCalculator)->calculate('2026-09', [
            ['date' => '2026-09-01', 'status' => 'pending'],
            ['date' => '2026-09-02', 'status' => 'confirmed', 'budget' => '90.00', 'spent' => '0.00'],
        ]);

        self::assertNull($result['days'][0]['margin']);
        self::assertSame('90.00', $result['days'][1]['margin']);
        self::assertSame(1, $result['confirmed_days']);
        self::assertSame(1, $result['pending_days']);
        self::assertSame('90.00', $result['net_margin']);
    }

    public function test_decimal_cents_and_empty_month_are_exact(): void
    {
        $calculator = new DailyMarginCalculator;
        $result = $calculator->calculate('2026-02', [
            ['date' => '2026-02-28', 'status' => 'confirmed', 'budget' => '0.30', 'spent' => '0.20'],
            ['date' => '2026-02-01', 'status' => 'confirmed', 'budget' => '0.01', 'spent' => '0.02'],
        ]);

        self::assertSame('0.10', $result['gross_savings']);
        self::assertSame('0.01', $result['gross_excess']);
        self::assertSame('0.09', $result['net_margin']);

        $emptyMonth = $calculator->calculate('2026-02', []);
        self::assertSame([], $emptyMonth['days']);
        self::assertSame(0, $emptyMonth['confirmed_days']);
        self::assertSame(0, $emptyMonth['pending_days']);
        self::assertSame('0.00', $emptyMonth['gross_savings']);
        self::assertSame('0.00', $emptyMonth['gross_excess']);
        self::assertSame('0.00', $emptyMonth['net_margin']);
    }

    public function test_accepts_leap_day_and_returns_the_same_result_for_the_same_input(): void
    {
        $days = [
            ['date' => '2028-02-29', 'status' => 'confirmed', 'budget' => '10.00', 'spent' => '9.99'],
        ];
        $calculator = new DailyMarginCalculator;

        $firstResult = $calculator->calculate('2028-02', $days);
        $secondResult = $calculator->calculate('2028-02', $days);

        self::assertSame('0.01', $firstResult['net_margin']);
        self::assertSame($firstResult, $secondResult);
    }

    #[DataProvider('invalidInputs')]
    public function test_rejects_invalid_or_ambiguous_inputs(string $month, array $days): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new DailyMarginCalculator)->calculate($month, $days);
    }

    public static function invalidInputs(): iterable
    {
        yield 'invalid month' => ['2026-13', []];
        yield 'invalid date' => ['2026-02', [['date' => '2026-02-30', 'status' => 'pending']]];
        yield 'outside month' => ['2026-09', [['date' => '2026-10-01', 'status' => 'pending']]];
        yield 'duplicate day' => ['2026-09', [['date' => '2026-09-01', 'status' => 'pending'], ['date' => '2026-09-01', 'status' => 'pending']]];
        yield 'pending with spent' => ['2026-09', [['date' => '2026-09-01', 'status' => 'pending', 'spent' => '0.00']]];
        yield 'confirmed without spent' => ['2026-09', [['date' => '2026-09-01', 'status' => 'confirmed', 'budget' => '90.00']]];
        yield 'unknown status' => ['2026-09', [['date' => '2026-09-01', 'status' => 'open']]];
        yield 'negative amount' => ['2026-09', [['date' => '2026-09-01', 'status' => 'confirmed', 'budget' => '90.00', 'spent' => '-1.00']]];
        yield 'excess precision' => ['2026-09', [['date' => '2026-09-01', 'status' => 'confirmed', 'budget' => '90.00', 'spent' => '1.001']]];
        yield 'float amount' => ['2026-09', [['date' => '2026-09-01', 'status' => 'confirmed', 'budget' => 90.0, 'spent' => '1.00']]];
    }
}
