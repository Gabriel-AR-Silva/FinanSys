<?php

namespace Tests\Feature;

use App\Actions\SetDailyBudget;
use App\Enums\ExpensePlanningType;
use App\Models\DailyFinancialCheckIn;
use App\Models\LedgerEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DailyCheckInHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_exposes_pending_completed_days_without_confirming_zero_implicitly(): void
    {
        $user = User::factory()->create();
        $this->travelTo(CarbonImmutable::parse('2026-09-22 09:00:00', 'America/Sao_Paulo'));
        app(SetDailyBudget::class)->handle($user, '90.00', (string) Str::uuid());

        $this->travelTo(CarbonImmutable::parse('2026-09-23 08:00:00', 'America/Sao_Paulo'));

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('dailyCheckIns', 22)
                ->where('dailyCheckIns.21.date', '2026-09-22')
                ->where('dailyCheckIns.21.status', 'pending')
                ->where('dailyCheckIns.21.preview_spent', '0.00')
                ->where('dailyPlanning.month', '2026-09')
                ->where('dailyPlanning.confirmed_days', 0)
                ->where('dailyPlanning.pending_days', 22)
                ->where('dailyPlanning.current_daily_budget', '90.00'));

        $this->assertDatabaseCount('daily_financial_check_ins', 0);
    }

    public function test_authenticated_user_can_confirm_selected_days_in_batch(): void
    {
        $user = User::factory()->create();
        $this->travelTo(CarbonImmutable::parse('2026-09-20 09:00:00', 'America/Sao_Paulo'));
        app(SetDailyBudget::class)->handle($user, '90.00', (string) Str::uuid());

        $this->travelTo(CarbonImmutable::parse('2026-09-23 08:00:00', 'America/Sao_Paulo'));
        $this->actingAs($user)->post(route('daily-check-ins.batch.store'), [
            'days' => [
                ['date' => '2026-09-21', 'operation_id' => (string) Str::uuid(), 'reason' => null],
                ['date' => '2026-09-22', 'operation_id' => (string) Str::uuid(), 'reason' => null],
            ],
        ])->assertRedirect();

        $this->assertDatabaseCount('daily_financial_check_ins', 2);
        $this->assertSame(
            ['0.00', '0.00'],
            DailyFinancialCheckIn::query()->where('user_id', $user->id)->orderBy('local_date')->pluck('eligible_spent')->all(),
        );
    }

    public function test_correction_endpoint_creates_revision_instead_of_overwriting_history(): void
    {
        $user = User::factory()->create();
        $this->travelTo(CarbonImmutable::parse('2026-09-22 09:00:00', 'America/Sao_Paulo'));
        app(SetDailyBudget::class)->handle($user, '90.00', (string) Str::uuid());
        LedgerEntry::factory()->expense()->create([
            'user_id' => $user->id,
            'planning_type' => ExpensePlanningType::Ordinary,
            'amount' => '80.00',
            'occurred_at' => '2026-09-22 12:00:00',
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-09-23 08:00:00', 'America/Sao_Paulo'));
        $this->actingAs($user)->post(route('daily-check-ins.store'), [
            'date' => '2026-09-22',
            'operation_id' => (string) Str::uuid(),
        ])->assertRedirect();

        LedgerEntry::factory()->expense()->create([
            'user_id' => $user->id,
            'planning_type' => ExpensePlanningType::Ordinary,
            'amount' => '20.00',
            'occurred_at' => '2026-09-22 18:00:00',
        ]);

        $this->actingAs($user)->patch(route('daily-check-ins.correct'), [
            'date' => '2026-09-22',
            'operation_id' => (string) Str::uuid(),
            'reason' => 'Despesa esquecida.',
        ])->assertRedirect();

        $revisions = DailyFinancialCheckIn::query()->where('user_id', $user->id)->orderBy('revision')->get();
        $this->assertCount(2, $revisions);
        $this->assertSame(['80.00', '100.00'], $revisions->pluck('eligible_spent')->all());
        $this->assertSame([1, 2], $revisions->pluck('revision')->all());
        $this->assertSame($revisions[0]->id, $revisions[1]->supersedes_id);
    }

    public function test_daily_budget_page_and_write_are_authenticated_and_user_scoped(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->travelTo(CarbonImmutable::parse('2026-09-23 08:00:00', 'America/Sao_Paulo'));

        $this->get(route('daily-budgets.edit'))->assertRedirect(route('login'));

        $this->actingAs($user)->post(route('daily-budgets.store'), [
            'amount' => '75.50',
            'operation_id' => (string) Str::uuid(),
            'reason' => 'Referência inicial.',
        ])->assertRedirect(route('daily-budgets.edit'));

        $this->withoutVite();
        $this->actingAs($user)->get(route('daily-budgets.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('DailyBudgets/Edit')
                ->where('currentBudget.amount', '75.50'));

        $this->assertDatabaseHas('daily_budget_versions', ['user_id' => $user->id, 'amount' => '75.50']);
        $this->assertDatabaseMissing('daily_budget_versions', ['user_id' => $other->id]);
    }
}
