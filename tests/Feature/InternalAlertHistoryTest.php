<?php

namespace Tests\Feature;

use App\Actions\UpdateInternalAlert;
use App\Models\FinancialEvaluation;
use App\Models\InternalAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InternalAlertHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_alerts_are_filtered_and_isolated_by_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        InternalAlert::factory()->for($user)->create([
            'alert_date' => '2026-09-02',
            'view' => 'current',
            'current_situation' => 'insufficient',
            'deficit_seen' => true,
            'current_deficit' => '100.00',
        ]);
        InternalAlert::factory()->for($other)->create(['alert_date' => '2026-09-02']);

        $this->actingAs($user)->get(route('internal-alerts.index', [
            'view' => 'current', 'from' => '2026-09-01', 'to' => '2026-09-03', 'deficit_only' => 1,
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('InternalAlerts/Index')
            ->where('schemaWarning', null)
            ->where('filters.deficit_only', true)
            ->where('alerts.data.0.current_situation', 'insufficient')
            ->has('alerts.data', 1));
    }

    public function test_alerts_can_be_filtered_by_recovery_state_and_empty_period(): void
    {
        $user = User::factory()->create();
        InternalAlert::factory()->for($user)->create(['recovered_at' => now(), 'alert_date' => '2026-09-02']);

        $this->actingAs($user)->get(route('internal-alerts.index', [
            'status' => 'active', 'from' => '2026-09-01', 'to' => '2026-09-03',
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('InternalAlerts/Index')
            ->has('alerts.data', 0));
    }

    public function test_alert_page_degrades_safely_when_alert_migration_is_pending(): void
    {
        $user = User::factory()->create();
        Schema::drop('internal_alerts');

        $this->actingAs($user)->get(route('internal-alerts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('InternalAlerts/Index')
                ->where('alerts.data', [])
                ->where('alerts.current_page', 1)
                ->where('schemaWarning', 'Os avisos financeiros ainda não foram sincronizados neste ambiente. Execute as migrations pendentes antes de validar este módulo.'));
    }

    public function test_alert_update_is_a_safe_noop_when_alert_migration_is_pending(): void
    {
        $user = User::factory()->create();
        Schema::drop('internal_alerts');
        $evaluation = new FinancialEvaluation([
            'user_id' => $user->id,
            'evaluation_date' => '2026-09-13',
            'view' => 'current',
            'source' => 'recorded',
            'evaluated_at' => now(),
            'result' => ['situation' => 'outside_plan', 'deficit' => '10.00'],
        ]);

        $this->assertNull(app(UpdateInternalAlert::class)->handle($user, $evaluation));
    }
}
