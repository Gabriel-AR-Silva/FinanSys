<?php

namespace Tests\Feature;

use App\Actions\CreateFinancialGoal;
use App\Actions\DeleteFinancialGoal;
use App\Actions\DeletePocket;
use App\Actions\SetDailyBudget;
use App\Models\FinancialGoal;
use App\Models\LedgerEntry;
use App\Models\Pocket;
use App\Models\User;
use App\Queries\FinancialGoalPlanningQuery;
use App\Queries\OnboardingProgressQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialGoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_goal_uses_real_pocket_balance_and_calculates_daily_reference_without_creating_money(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-23 08:00:00', 'America/Sao_Paulo'));
        $user = User::factory()->create();
        $pocket = Pocket::factory()->create(['user_id' => $user->id, 'name' => 'Reserva da meta']);

        LedgerEntry::factory()->openingBalance()->create([
            'user_id' => $user->id,
            'reference_type' => $pocket->getMorphClass(),
            'reference_id' => $pocket->id,
            'amount' => '600.00',
            'occurred_at' => now(),
        ]);
        $entriesBefore = LedgerEntry::query()->count();

        app(CreateFinancialGoal::class)->handle(
            $user,
            'Notebook',
            '3000.00',
            today('America/Sao_Paulo')->addDays(120)->toDateString(),
            $pocket->id,
            (string) Str::uuid(),
        );

        $result = app(FinancialGoalPlanningQuery::class)->forUser($user);
        $goal = $result['goals'][0];

        $this->assertSame('3000.00', $goal['target_amount']);
        $this->assertSame('600.00', $goal['reserved_amount']);
        $this->assertSame('2400.00', $goal['remaining_amount']);
        $this->assertSame(120, $goal['days_remaining']);
        $this->assertSame('20.00', $goal['daily_required']);
        $this->assertSame('20.00', $goal['progress_percentage']);
        $this->assertSame('active', $goal['status']);
        $this->assertSame($entriesBefore, LedgerEntry::query()->count());
    }

    public function test_goal_without_pocket_never_invents_reserved_amount(): void
    {
        $user = User::factory()->create();

        app(CreateFinancialGoal::class)->handle(
            $user,
            'Viagem',
            '1000.00',
            today('America/Sao_Paulo')->addDays(10)->toDateString(),
            null,
            (string) Str::uuid(),
        );

        $goal = app(FinancialGoalPlanningQuery::class)->forUser($user)['goals'][0];

        $this->assertNull($goal['pocket_id']);
        $this->assertSame('0.00', $goal['reserved_amount']);
        $this->assertSame('1000.00', $goal['remaining_amount']);
        $this->assertSame('100.00', $goal['daily_required']);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_goal_creation_is_idempotent_and_same_operation_cannot_change_payload(): void
    {
        $user = User::factory()->create();
        $operation = (string) Str::uuid();
        $date = today('America/Sao_Paulo')->addMonth()->toDateString();
        $action = app(CreateFinancialGoal::class);

        $first = $action->handle($user, 'Reserva', '500.00', $date, null, $operation);
        $replayed = $action->handle($user, 'Reserva', '500.00', $date, null, $operation);

        $this->assertSame($first->id, $replayed->id);
        $this->assertDatabaseCount('financial_goals', 1);

        $this->expectException(ValidationException::class);
        $action->handle($user, 'Outra meta', '500.00', $date, null, $operation);
    }

    public function test_goal_refuses_pocket_owned_by_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreignPocket = Pocket::factory()->create(['user_id' => $other->id]);

        $this->expectException(ValidationException::class);

        app(CreateFinancialGoal::class)->handle(
            $user,
            'Meta privada',
            '500.00',
            today('America/Sao_Paulo')->addMonth()->toDateString(),
            $foreignPocket->id,
            (string) Str::uuid(),
        );
    }

    public function test_same_pocket_cannot_fund_two_active_goals_and_is_released_after_goal_deletion(): void
    {
        $user = User::factory()->create();
        $pocket = Pocket::factory()->create(['user_id' => $user->id]);
        $action = app(CreateFinancialGoal::class);
        $date = today('America/Sao_Paulo')->addMonth()->toDateString();

        $first = $action->handle($user, 'Meta A', '500.00', $date, $pocket->id, (string) Str::uuid());

        try {
            $action->handle($user, 'Meta B', '800.00', $date, $pocket->id, (string) Str::uuid());
            $this->fail('A mesma reserva não pode financiar duas metas ao mesmo tempo.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('financial_goals', 1);
        }

        app(DeleteFinancialGoal::class)->handle($user, $first->id);
        $second = $action->handle($user, 'Meta B', '800.00', $date, $pocket->id, (string) Str::uuid());

        $this->assertSame($pocket->id, $second->pocket_id);
    }

    public function test_database_rejects_cross_user_pocket_link_even_outside_action_layer(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreignPocket = Pocket::factory()->create(['user_id' => $other->id]);

        $this->expectException(QueryException::class);

        FinancialGoal::query()->create([
            'user_id' => $user->id,
            'pocket_id' => $foreignPocket->id,
            'name' => 'Ligação inválida',
            'target_amount' => '500.00',
            'target_date' => today('America/Sao_Paulo')->addMonth()->toDateString(),
            'operation_id' => (string) Str::uuid(),
        ]);
    }

    public function test_purging_deleted_pocket_unlinks_goal_without_deleting_the_goal(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-23 09:00:00', 'America/Sao_Paulo'));
        $user = User::factory()->create();
        $pocket = Pocket::factory()->create(['user_id' => $user->id]);

        $goal = app(CreateFinancialGoal::class)->handle(
            $user,
            'Meta persistente',
            '1000.00',
            '2026-12-31',
            $pocket->id,
            (string) Str::uuid(),
        );

        app(DeletePocket::class)->handle($user, $pocket->id);
        $this->assertSame($pocket->id, $goal->fresh()->pocket_id);

        $this->travel(31)->days();
        $this->artisan('finansys:purge-expired')->assertSuccessful();

        $this->assertDatabaseHas('financial_goals', ['id' => $goal->id, 'pocket_id' => null]);
    }

    public function test_goal_endpoints_are_authenticated_and_isolated(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->getJson(route('financial-goals.index'))->assertUnauthorized();

        app(CreateFinancialGoal::class)->handle(
            $other,
            'Meta de outro usuário',
            '500.00',
            today('America/Sao_Paulo')->addMonth()->toDateString(),
            null,
            (string) Str::uuid(),
        );

        $this->actingAs($user)
            ->getJson(route('financial-goals.index'))
            ->assertOk()
            ->assertJsonCount(0, 'goals');
    }

    public function test_tsuki_recommends_daily_budget_and_goal_without_blocking_essential_setup(): void
    {
        $user = User::factory()->create();

        $progress = app(OnboardingProgressQuery::class)->forUser($user);
        $dailyBudget = collect($progress['recommendedSteps'])->firstWhere('key', 'daily_budget');
        $goal = collect($progress['recommendedSteps'])->firstWhere('key', 'goal');

        $this->assertFalse($dailyBudget['completed']);
        $this->assertFalse($goal['completed']);
        $this->assertSame('Definir orçamento diário', $dailyBudget['cta']['label']);
        $this->assertSame('Criar uma meta', $goal['cta']['label']);

        $this->travelTo(CarbonImmutable::parse('2026-09-23 09:00:00', 'America/Sao_Paulo'));
        app(SetDailyBudget::class)->handle($user, '90.00', (string) Str::uuid());
        app(CreateFinancialGoal::class)->handle(
            $user,
            'Reserva',
            '1000.00',
            '2026-12-31',
            null,
            (string) Str::uuid(),
        );

        $updated = app(OnboardingProgressQuery::class)->forUser($user);

        $this->assertTrue(collect($updated['recommendedSteps'])->firstWhere('key', 'daily_budget')['completed']);
        $this->assertTrue(collect($updated['recommendedSteps'])->firstWhere('key', 'goal')['completed']);
        $this->assertFalse($updated['progress']['essentialReady']);
    }
}
