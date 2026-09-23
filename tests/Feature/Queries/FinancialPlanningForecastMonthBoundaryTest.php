<?php

namespace Tests\Feature\Queries;

use App\Models\MonthlyFinancialSetting;
use App\Models\ReceiptForecast;
use App\Models\User;
use App\Queries\FinancialPlanningOverviewQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialPlanningForecastMonthBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_forecasts_on_last_day_count_in_september_without_leaking_october(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        MonthlyFinancialSetting::factory()->for($user)->create(['month' => '2026-09', 'protection_value' => '0']);
        ReceiptForecast::factory()->for($user)->create(['amount' => '20.00', 'expected_on' => '2026-09-01']);
        ReceiptForecast::factory()->for($user)->create(['amount' => '100.00', 'expected_on' => '2026-09-30']);
        ReceiptForecast::factory()->for($user)->create(['amount' => '400.00', 'expected_on' => '2026-10-01']);
        ReceiptForecast::factory()->for($other)->create(['amount' => '900.00', 'expected_on' => '2026-09-30']);

        $result = app(FinancialPlanningOverviewQuery::class)->forUser(
            $user,
            CarbonImmutable::parse('2026-09-15 12:00:00', 'America/Sao_Paulo'),
        );

        $this->assertSame('0.00', $result['income']['received']);
        $this->assertSame('120.00', $result['income']['pending']);
        $this->assertSame('120.00', $result['income']['projected']);
    }
}
