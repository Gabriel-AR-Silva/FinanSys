<?php

namespace Tests\Unit\Support;

use App\Enums\FinancialSituation;
use App\Enums\ProtectionType;
use App\Support\FinancialPlanningMath;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FinancialPlanningMathTest extends TestCase
{
    public function test_variable_base_does_not_subtract_variable_spending_twice(): void
    {
        $math = new FinancialPlanningMath;

        $this->assertSame('1000.00', $math->variableBase('3000', '0', '2000', '0'));
        $this->assertSame(FinancialSituation::UnderControl, $math->financialSituation('1000', '850')['situation']);
        $this->assertSame('85.00', $math->financialSituation('1000', '850')['percentage']);
    }

    public function test_ordinary_projection_uses_only_completed_brasilia_days_without_rounding_rate_early(): void
    {
        $math = new FinancialPlanningMath;

        $this->assertSame([
            'diagnostic_available' => true,
            'daily_rate' => '100.00',
            'projected_total' => '3050.00',
        ], $math->ordinaryProjection('200', 2, '150', 27));
        $this->assertSame('33.33', $math->ordinaryProjection('100', 3, '0', 3)['daily_rate']);
        $this->assertSame('3000.00', $math->ordinaryProjection('200', 2, '0', 27)['projected_total']);
        $this->assertSame('0.05', $math->ordinaryProjection('0.02', 3, '0', 3)['projected_total']);
    }

    public function test_first_two_days_and_non_positive_bases_do_not_invent_percentages(): void
    {
        $math = new FinancialPlanningMath;

        $this->assertSame(['diagnostic_available' => false, 'daily_rate' => null, 'projected_total' => '10.00'], $math->ordinaryProjection('0', 0, '10', 30));
        $this->assertSame(['situation' => FinancialSituation::NoBasis, 'percentage' => null], $math->financialSituation('0', '0'));
        $this->assertSame(['situation' => FinancialSituation::Insufficient, 'percentage' => null], $math->financialSituation('-1', '0'));
    }

    #[DataProvider('situationBoundaries')]
    public function test_situation_boundaries_are_compared_before_display_rounding(string $spending, FinancialSituation $expected): void
    {
        $this->assertSame($expected, (new FinancialPlanningMath)->financialSituation('100', $spending)['situation']);
    }

    public static function situationBoundaries(): array
    {
        return [
            ['90', FinancialSituation::UnderControl],
            ['90.01', FinancialSituation::Balanced],
            ['100', FinancialSituation::Balanced],
            ['100.01', FinancialSituation::OutsidePlan],
        ];
    }

    #[DataProvider('essentialProjectionCases')]
    public function test_essential_projection_uses_exclusive_sets_without_double_counting(array $input, string $expected): void
    {
        $result = (new FinancialPlanningMath)->essentialProjection(...$input);

        $this->assertSame($expected, $result['projected_total']);
    }

    public static function essentialProjectionCases(): array
    {
        return [
            'budget covers ordinary rhythm' => [['600', '450', '0', '0'], '600.00'],
            'extraordinary is counted once' => [['600', '450', '300', '0'], '750.00'],
            'outstanding obligation stays reserved' => [['600', '450', '100', '200'], '750.00'],
            'cent values remain exact' => [['0.03', '0.01', '0.01', '0.02'], '0.04'],
        ];
    }

    public function test_essential_remaining_and_free_margin_preserve_reserve_without_double_consumption(): void
    {
        $math = new FinancialPlanningMath;

        $this->assertSame('500.00', $math->essentialRemaining('600', '100'));
        $this->assertSame('0.00', $math->essentialRemaining('600', '700'));
        $this->assertSame('400.00', $math->freeMargin('900', ['500']));
        $this->assertSame('-100.00', $math->freeMargin('400', ['500']));
    }

    #[DataProvider('receiptProgressCases')]
    public function test_receipts_replace_the_forecast_without_duplicating_excess(string $expected, string $received, array $progress): void
    {
        $this->assertSame($progress, (new FinancialPlanningMath)->receiptProgress($expected, $received));
    }

    public static function receiptProgressCases(): array
    {
        return [
            'not received' => ['1000', '0', ['received' => '0.00', 'pending' => '1000.00', 'excess' => '0.00', 'fulfilled' => false]],
            'partial' => ['1000', '600', ['received' => '600.00', 'pending' => '400.00', 'excess' => '0.00', 'fulfilled' => false]],
            'exact' => ['1000', '1000', ['received' => '1000.00', 'pending' => '0.00', 'excess' => '0.00', 'fulfilled' => true]],
            'excess' => ['1000', '1200', ['received' => '1200.00', 'pending' => '0.00', 'excess' => '200.00', 'fulfilled' => true]],
            'one cent pending' => ['1000', '999.99', ['received' => '999.99', 'pending' => '0.01', 'excess' => '0.00', 'fulfilled' => false]],
            'one cent excess' => ['1000', '1000.01', ['received' => '1000.01', 'pending' => '0.00', 'excess' => '0.01', 'fulfilled' => true]],
            'large aggregate' => ['99999999999999999.99', '100000000000000000.00', ['received' => '100000000000000000.00', 'pending' => '0.00', 'excess' => '0.01', 'fulfilled' => true]],
        ];
    }

    #[DataProvider('invalidForecastAmounts')]
    public function test_invalid_forecast_amounts_are_rejected(string $expected, string $received): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FinancialPlanningMath)->receiptProgress($expected, $received);
    }

    public static function invalidForecastAmounts(): array
    {
        return [['0', '0'], ['-1', '0'], ['1', '-1'], ['1.001', '0'], ['1', '0.001']];
    }

    public function test_percentage_rounds_up_once_after_aggregating_receipts(): void
    {
        $result = (new FinancialPlanningMath)->protection(['0.01', '0.01'], ProtectionType::Percentage, '50');

        $this->assertSame('0.01', $result);
    }

    #[DataProvider('protectionCases')]
    public function test_protection_preserves_exact_cents(array $receipts, ProtectionType $type, string $value, string $expected): void
    {
        $this->assertSame($expected, (new FinancialPlanningMath)->protection($receipts, $type, $value));
    }

    public static function protectionCases(): array
    {
        return [
            'fixed without income' => [[], ProtectionType::Fixed, '300', '300.00'],
            'zero percentage' => [['900.25'], ProtectionType::Percentage, '0', '0.00'],
            'all income' => [['900.25'], ProtectionType::Percentage, '100', '900.25'],
            'empty receipts' => [[], ProtectionType::Percentage, '50', '0.00'],
            'fractional cent' => [['0.01'], ProtectionType::Percentage, '0.01', '0.01'],
            'ordinary income' => [['1000', '2000'], ProtectionType::Percentage, '10', '300.00'],
            'aggregate beyond integer cents' => [['99999999999999999.99', '0.01'], ProtectionType::Percentage, '50', '50000000000000000.00'],
        ];
    }

    #[DataProvider('allocationCases')]
    public function test_daily_allocation_preserves_the_undistributed_remainder(string $amount, int $days, string $daily, string $remainder): void
    {
        $this->assertSame(['daily_amount' => $daily, 'remainder' => $remainder], (new FinancialPlanningMath)->dailyAllocation($amount, $days));
    }

    public static function allocationCases(): array
    {
        return [
            ['100', 3, '33.33', '0.01'],
            ['0.01', 31, '0.00', '0.01'],
            ['0', 28, '0.00', '0.00'],
            ['12.34', 1, '12.34', '0.00'],
            ['100', 30, '3.33', '0.10'],
        ];
    }

    #[DataProvider('calendarCases')]
    public function test_remaining_days_use_brasilia_and_include_today(string $instant, int $expected): void
    {
        $this->assertSame($expected, (new FinancialPlanningMath)->remainingDaysInCurrentMonth(new DateTimeImmutable($instant)));
    }

    public static function calendarCases(): array
    {
        return [
            ['2026-02-01T12:00:00Z', 28],
            ['2028-02-01T12:00:00Z', 29],
            ['2026-04-01T12:00:00Z', 30],
            ['2026-01-01T12:00:00Z', 31],
            ['2026-03-01T02:59:59Z', 1],
            ['2026-03-01T03:00:00Z', 31],
            ['2027-01-01T02:59:59Z', 1],
            ['2026-09-08T12:00:00Z', 23],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_amounts_are_rejected_without_silent_rounding(string $amount): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FinancialPlanningMath)->dailyAllocation($amount, 3);
    }

    public static function invalidAmounts(): array
    {
        return [['-0.01'], ['0.001'], ['1e3'], ['1,00'], [' 10'], ["10\n"], [''], ['NaN']];
    }

    public function test_percentage_above_one_hundred_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FinancialPlanningMath)->protection(['100'], ProtectionType::Percentage, '100.01');
    }

    public function test_float_receipts_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FinancialPlanningMath)->protection([0.01], ProtectionType::Percentage, '50');
    }

    public function test_invalid_receipt_is_rejected_even_for_fixed_protection(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FinancialPlanningMath)->protection(['-1'], ProtectionType::Fixed, '50');
    }

    #[DataProvider('invalidDays')]
    public function test_invalid_divisors_are_rejected(int $days): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FinancialPlanningMath)->dailyAllocation('100', $days);
    }

    public static function invalidDays(): array
    {
        return [[0], [-1], [32]];
    }
}
