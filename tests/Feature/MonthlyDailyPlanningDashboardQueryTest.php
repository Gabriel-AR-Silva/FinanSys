<?php

namespace Tests\Feature;

use App\Actions\RecordDailyFinancialCheckIn;
use App\Actions\SetDailyBudget;
use App\Enums\ExpensePlanningType;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Queries\MonthlyDailyPlanningDashboardQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonthlyDailyPlanningDashboardQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_composes_only_confirmed_days_into_v2_margin_indicators(): void
    {
        $user = User::factory()->create();

        $this->travelTo(CarbonImmutable::parse('2026-09-20 09:00:00', 'America/Sao_Paulo'));
        app(SetDailyBudget::class)->handle($user, '90.00', (string) Str::uuid());

        LedgerEntry::factory()->expense()->create([
            'user_id' => $user->id,
            'planning_type' => ExpensePlanningType::Ordinary,
            'amount' => '60.00',
            'occurred_at' => '2026-09-21 12:00:00',
        ]);
        LedgerEntry::factory()->expense()->create([
            'user_id' => $user->id,
            'planning_type' => ExpensePlanningType::Ordinary,
            'amount' => '110.00',
            'occurred_at' => '2026-09-22 12:00:00',
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-09-23 08:00:00', 'America/Sao_Paulo'));
        $recorder = app(RecordDailyFinancialCheckIn::class);
        $recorder->confirm($user, '2026-09-21', (string) Str::uuid());
        $recorder->confirm($user, '2026-09-22', (string) Str::uuid());

        $result = app(MonthlyDailyPlanningDashboardQuery::class)->forUser(
            $user,
            CarbonImmutable::parse('2026-09-23 08:00:00', 'America/Sao_Paulo'),
        );

        $this->assertSame('2026-09', $result['month']);
        $this->assertSame(2, $result['confirmed_days']);
        $this->assertSame(1, $result['pending_days']);
        $this->assertSame(3, $result['tracked_completed_days']);
        $this->assertSame('30.00', $result['gross_savings']);
        $this->assertSame('20.00', $result['gross_excess']);
        $this->assertSame('10.00', $result['net_margin']);
        $this->assertSame('170.00', $result['total_spent']);
        $this->assertSame('90.00', $result['current_daily_budget']);
        $this->assertSame('partial_tracked_days_pending', $result['coverage']);
        $this->assertSame(['30.00', '10.00'], array_column($result['daily'], 'cumulative_margin'));
    }

    public function test_latest_correction_replaces_previous_revision_in_dashboard_without_double_counting(): void
    {
        $user = User::factory()->create();

        $this->travelTo(CarbonImmutable::parse('2026-09-21 09:00:00', 'America/Sao_Paulo'));
        app(SetDailyBudget::class)->handle($user, '90.00', (string) Str::uuid());
        LedgerEntry::factory()->expense()->create([
            'user_id' => $user->id,
            'planning_type' => ExpensePlanningType::Ordinary,
            'amount' => '80.00',
            'occurred_at' => '2026-09-21 12:00:00',
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-09-22 08:00:00', 'America/Sao_Paulo'));
        $recorder = app(RecordDailyFinancialCheckIn::class);
        $recorder->confirm($user, '2026-09-21', (string) Str::uuid());

        LedgerEntry::factory()->expense()->create([
            'user_id' => $user->id,
            'planning_type' => ExpensePlanningType::Ordinary,
            'amount' => '20.00',
            'occurred_at' => '2026-09-21 18:00:00',
        ]);
        $recorder->correct($user, '2026-09-21', (string) Str::uuid(), 'Despesa esquecida.');

        $result = app(MonthlyDailyPlanningDashboardQuery::class)->forUser(
            $user,
            CarbonImmutable::parse('2026-09-22 09:00:00', 'America/Sao_Paulo'),
        );

        $this->assertSame(1, $result['confirmed_days']);
        $this->assertSame('100.00', $result['total_spent']);
        $this->assertSame('0.00', $result['gross_savings']);
        $this->assertSame('10.00', $result['gross_excess']);
        $this->assertSame('-10.00', $result['net_margin']);
        $this->assertSame(2, $result['daily'][0]['revision']);
    }

    public function test_empty_month_never_invents_confirmed_zero_days(): void
    {
        $user = User::factory()->create();

        $this->travelTo(CarbonImmutable::parse('2026-09-23 08:00:00', 'America/Sao_Paulo'));
        app(SetDailyBudget::class)->handle($user, '75.00', (string) Str::uuid());

        $result = app(MonthlyDailyPlanningDashboardQuery::class)->forUser($user);

        $this->assertSame(0, $result['confirmed_days']);
        $this->assertSame(0, $result['pending_days']);
        $this->assertSame(0, $result['tracked_completed_days']);
        $this->assertSame([], $result['daily']);
        $this->assertSame('0.00', $result['net_margin']);
        $this->assertSame('75.00', $result['current_daily_budget']);
        $this->assertSame('all_tracked_completed_days_confirmed', $result['coverage']);
    }
}
