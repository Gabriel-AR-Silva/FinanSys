<?php

namespace Tests\Unit\Support;

use App\Support\DailyBudgetVersionSelector;
use App\Support\DailyConfirmedDayInput;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DailyBudgetAmountStorageLimitTest extends TestCase
{
    public function test_largest_decimal_19_2_amount_is_accepted_without_rounding(): void
    {
        $versions = [$this->version('99999999999999999.99')];
        $selected = (new DailyBudgetVersionSelector)->resolve(7, '2026-09-21', '2026-09-21T22:00:00-03:00', $versions);
        $input = (new DailyConfirmedDayInput)->build(7, '2026-09-21', '2026-09-21T22:00:00-03:00', '99999999999999999.99', $versions);

        self::assertSame('99999999999999999.99', $selected['amount']);
        self::assertSame('99999999999999999.99', $input['calculator_day']['budget']);
        self::assertSame('99999999999999999.99', $input['calculator_day']['spent']);
    }

    #[DataProvider('amountsOutsideStorageRange')]
    public function test_budget_rejects_amounts_outside_decimal_19_2(string $amount): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DailyBudgetVersionSelector)->resolve(7, '2026-09-21', '2026-09-21T22:00:00-03:00', [$this->version($amount)]);
    }

    #[DataProvider('amountsOutsideStorageRange')]
    public function test_spent_rejects_amounts_outside_decimal_19_2(string $amount): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DailyConfirmedDayInput)->build(7, '2026-09-21', '2026-09-21T22:00:00-03:00', $amount, [$this->version('90.00')]);
    }

    public static function amountsOutsideStorageRange(): iterable
    {
        yield 'eighteen integer digits' => ['100000000000000000'];
        yield 'large amount with cents' => ['999999999999999999.99'];
        yield 'too many cents' => ['90.001'];
        yield 'negative' => ['-0.01'];
        yield 'scientific notation' => ['1e18'];
    }

    /** @return array{id:int,user_id:int,amount:string,effective_at:string,recorded_at:string} */
    private function version(string $amount): array
    {
        return [
            'id' => 1,
            'user_id' => 7,
            'amount' => $amount,
            'effective_at' => '2026-09-21T15:00:00-03:00',
            'recorded_at' => '2026-09-21T15:00:00-03:00',
        ];
    }
}
