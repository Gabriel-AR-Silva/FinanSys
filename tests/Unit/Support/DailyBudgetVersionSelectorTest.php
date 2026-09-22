<?php

namespace Tests\Unit\Support;

use App\Support\DailyBudgetVersionSelector;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DailyBudgetVersionSelectorTest extends TestCase
{
    public function test_last_budget_change_before_end_of_day_is_used_at_check_in(): void
    {
        $selected = (new DailyBudgetVersionSelector)->resolve(7, '2026-09-21', '2026-09-22T07:00:00-03:00', [
            $this->version(1, '90.00', '2026-09-20T09:00:00-03:00'),
            $this->version(2, '120.00', '2026-09-21T15:00:00-03:00'),
            $this->version(3, '150.00', '2026-09-22T01:00:00-03:00'),
        ]);

        self::assertSame(['id' => 2, 'amount' => '120.00'], $selected);
    }

    public function test_explicit_check_in_at_22h_uses_only_versions_available_by_then(): void
    {
        $selected = (new DailyBudgetVersionSelector)->resolve(7, '2026-09-21', '2026-09-21T22:00:00-03:00', [
            $this->version(1, '90.00', '2026-09-20T09:00:00-03:00'),
            $this->version(2, '120.00', '2026-09-21T15:00:00-03:00'),
            $this->version(3, '150.00', '2026-09-21T23:00:00-03:00'),
        ]);

        self::assertSame(['id' => 2, 'amount' => '120.00'], $selected);
    }

    public function test_earlier_day_keeps_its_original_budget(): void
    {
        $selected = (new DailyBudgetVersionSelector)->resolve(7, '2026-09-20', '2026-09-22T07:00:00-03:00', [
            $this->version(1, '90.00', '2026-09-20T09:00:00-03:00'),
            $this->version(2, '120.00', '2026-09-21T15:00:00-03:00'),
        ]);

        self::assertSame(['id' => 1, 'amount' => '90.00'], $selected);
    }

    public function test_unknown_budget_remains_unknown_instead_of_becoming_zero(): void
    {
        self::assertNull((new DailyBudgetVersionSelector)->resolve(7, '2026-09-19', '2026-09-22T07:00:00-03:00', [
            $this->version(1, '90.00', '2026-09-20T09:00:00-03:00'),
        ]));
    }

    public function test_backfill_requires_a_record_that_existed_by_the_check_in(): void
    {
        $backfill = $this->version(4, '80.00', '2026-09-19T00:00:00-03:00', '2026-09-23T10:00:00-03:00');
        $selector = new DailyBudgetVersionSelector;

        self::assertNull($selector->resolve(7, '2026-09-19', '2026-09-22T07:00:00-03:00', [$backfill]));
        self::assertSame(['id' => 4, 'amount' => '80.00'], $selector->resolve(7, '2026-09-19', '2026-09-23T11:00:00-03:00', [$backfill]));
    }

    public function test_refuses_check_in_before_the_day_begins_in_sao_paulo(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DailyBudgetVersionSelector)->resolve(7, '2026-09-21', '2026-09-20T23:59:59-03:00', []);
    }

    public function test_refuses_cross_user_version_instead_of_leaking_its_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DailyBudgetVersionSelector)->resolve(7, '2026-09-21', '2026-09-22T07:00:00-03:00', [
            [...$this->version(1, '90.00', '2026-09-20T09:00:00-03:00'), 'user_id' => 8],
        ]);
    }

    public function test_refuses_invalid_money_instead_of_implicitly_rounding(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DailyBudgetVersionSelector)->resolve(7, '2026-09-21', '2026-09-22T07:00:00-03:00', [
            $this->version(1, '90.001', '2026-09-20T09:00:00-03:00'),
        ]);
    }

    public function test_open_day_reacts_immediately_but_excludes_future_versions(): void
    {
        $versions = [
            $this->version(1, '90.00', '2026-09-20T09:00:00-03:00'),
            $this->version(2, '120.00', '2026-09-21T15:00:00-03:00'),
            $this->version(3, '130.00', '2026-09-21T17:00:00-03:00'),
        ];
        $selector = new DailyBudgetVersionSelector;

        self::assertSame(['id' => 1, 'amount' => '90.00'], $selector->resolveOpenDay(7, '2026-09-21', '2026-09-21T14:59:59-03:00', $versions));
        self::assertSame(['id' => 2, 'amount' => '120.00'], $selector->resolveOpenDay(7, '2026-09-21', '2026-09-21T15:00:00-03:00', $versions));
        self::assertSame(['id' => 3, 'amount' => '130.00'], $selector->resolveOpenDay(7, '2026-09-21', '2026-09-21T17:00:00-03:00', $versions));
    }

    public function test_open_day_excludes_backfill_not_yet_recorded(): void
    {
        $selector = new DailyBudgetVersionSelector;
        $versions = [$this->version(4, '80.00', '2026-09-20T00:00:00-03:00', '2026-09-21T16:00:00-03:00')];

        self::assertNull($selector->resolveOpenDay(7, '2026-09-21', '2026-09-21T15:00:00-03:00', $versions));
        self::assertSame(['id' => 4, 'amount' => '80.00'], $selector->resolveOpenDay(7, '2026-09-21', '2026-09-21T16:00:00-03:00', $versions));
    }

    public function test_open_day_uses_sao_paulo_date_even_when_observed_with_utc_offset(): void
    {
        $selector = new DailyBudgetVersionSelector;
        $versions = [$this->version(1, '90.00', '2026-09-21T08:00:00-03:00')];

        self::assertSame(['id' => 1, 'amount' => '90.00'], $selector->resolveOpenDay(7, '2026-09-21', '2026-09-22T02:59:59+00:00', $versions));
    }

    public function test_open_day_rejects_observation_after_local_midnight(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DailyBudgetVersionSelector)->resolveOpenDay(7, '2026-09-21', '2026-09-22T03:00:00+00:00', []);
    }

    public function test_open_day_rejects_observation_before_local_midnight(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DailyBudgetVersionSelector)->resolveOpenDay(7, '2026-09-21', '2026-09-21T02:59:59+00:00', []);
    }

    /** @return array{id:int,user_id:int,amount:string,effective_at:string,recorded_at:string} */
    private function version(int $id, string $amount, string $effectiveAt, ?string $recordedAt = null): array
    {
        return [
            'id' => $id,
            'user_id' => 7,
            'amount' => $amount,
            'effective_at' => $effectiveAt,
            'recorded_at' => $recordedAt ?? $effectiveAt,
        ];
    }
}
