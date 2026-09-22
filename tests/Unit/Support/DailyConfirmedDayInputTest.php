<?php

namespace Tests\Unit\Support;

use App\Support\DailyConfirmedDayInput;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DailyConfirmedDayInputTest extends TestCase
{
    public function test_builds_confirmed_input_with_the_last_budget_and_explicit_version_reference(): void
    {
        $input = (new DailyConfirmedDayInput)->build(7, '2026-09-21', '2026-09-21T22:00:00-03:00', '80', [
            $this->version(1, '90', '2026-09-20T09:00:00-03:00'),
            $this->version(2, '120', '2026-09-21T15:00:00-03:00'),
            $this->version(3, '150', '2026-09-21T23:00:00-03:00'),
        ]);

        self::assertSame([
            'budget_version_id' => 2,
            'calculator_day' => [
                'date' => '2026-09-21',
                'status' => 'confirmed',
                'budget' => '120.00',
                'spent' => '80.00',
            ],
        ], $input);
    }

    public function test_does_not_change_an_earlier_snapshot_when_budget_changes_tomorrow(): void
    {
        $builder = new DailyConfirmedDayInput;
        $original = [$this->version(1, '90.00', '2026-09-20T09:00:00-03:00')];
        $before = $builder->build(7, '2026-09-20', '2026-09-21T08:00:00-03:00', '60.00', $original);
        $after = $builder->build(7, '2026-09-20', '2026-09-22T08:00:00-03:00', '60.00', [
            ...$original,
            $this->version(2, '120.00', '2026-09-21T15:00:00-03:00'),
        ]);

        self::assertSame($before, $after);
    }

    public function test_refuses_to_confirm_unknown_budget_instead_of_substituting_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DailyConfirmedDayInput)->build(7, '2026-09-19', '2026-09-20T08:00:00-03:00', '0.00', [
            $this->version(1, '90.00', '2026-09-20T09:00:00-03:00'),
        ]);
    }

    public function test_refuses_confirmation_before_the_local_day_begins(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DailyConfirmedDayInput)->build(7, '2026-09-21', '2026-09-20T23:00:00-03:00', '0.00', [
            $this->version(1, '90.00', '2026-09-20T09:00:00-03:00'),
        ]);
    }

    public function test_rejects_cross_user_budget_version(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DailyConfirmedDayInput)->build(7, '2026-09-21', '2026-09-22T08:00:00-03:00', '0.00', [
            [...$this->version(1, '90.00', '2026-09-20T09:00:00-03:00'), 'user_id' => 8],
        ]);
    }

    public function test_rejects_invalid_spend_instead_of_rounding_or_guessing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DailyConfirmedDayInput)->build(7, '2026-09-21', '2026-09-22T08:00:00-03:00', '80.001', [
            $this->version(1, '90.00', '2026-09-20T09:00:00-03:00'),
        ]);
    }

    public function test_zero_spend_is_only_a_caller_supplied_explicit_input(): void
    {
        $input = (new DailyConfirmedDayInput)->build(7, '2026-09-21', '2026-09-22T08:00:00-03:00', '0', [
            $this->version(1, '90.00', '2026-09-20T09:00:00-03:00'),
        ]);

        self::assertSame('0.00', $input['calculator_day']['spent']);
        self::assertSame('confirmed', $input['calculator_day']['status']);
    }

    /** @return array{id:int,user_id:int,amount:string,effective_at:string,recorded_at:string} */
    private function version(int $id, string $amount, string $effectiveAt): array
    {
        return [
            'id' => $id,
            'user_id' => 7,
            'amount' => $amount,
            'effective_at' => $effectiveAt,
            'recorded_at' => $effectiveAt,
        ];
    }
}
