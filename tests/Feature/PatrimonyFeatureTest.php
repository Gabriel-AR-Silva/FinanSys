<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\PatrimonialAsset;
use App\Models\User;
use App\Queries\OnboardingProgressQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PatrimonyFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_patrimony_uses_current_estimated_value_minus_linked_debt_without_changing_cash(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        LedgerEntry::factory()->openingBalance()->create([
            'user_id' => $user->id,
            'reference_type' => $account->getMorphClass(),
            'reference_id' => $account->id,
            'amount' => '6000.00',
        ]);

        $entriesBefore = LedgerEntry::query()->count();

        $this->actingAs($user)->post(route('patrimony.store'), [
            'name' => 'Moto',
            'category' => 'Veículo',
            'estimated_value' => '18000.00',
            'debt_balance' => '11000.00',
            'valued_on' => '2026-09-23',
        ])->assertRedirect(route('patrimony.index'));

        $this->assertSame($entriesBefore, LedgerEntry::query()->count());

        $this->actingAs($user)->get(route('patrimony.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Patrimony/Index')
                ->where('patrimony.summary.financial_balance', '6000.00')
                ->where('patrimony.summary.assets_total', '18000.00')
                ->where('patrimony.summary.debts_total', '11000.00')
                ->where('patrimony.summary.asset_equity', '7000.00')
                ->where('patrimony.summary.estimated_net_worth', '13000.00')
                ->where('patrimony.assets.0.name', 'Moto')
                ->where('patrimony.assets.0.equity', '7000.00'));
    }

    public function test_dashboard_exposes_only_authenticated_users_estimated_patrimony(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        PatrimonialAsset::query()->create([
            'user_id' => $user->id,
            'name' => 'Notebook',
            'category' => 'Eletrônico',
            'estimated_value' => '4000.00',
            'debt_balance' => '500.00',
            'valued_on' => '2026-09-23',
        ]);
        PatrimonialAsset::query()->create([
            'user_id' => $other->id,
            'name' => 'Bem alheio',
            'estimated_value' => '99999.00',
            'debt_balance' => '0.00',
            'valued_on' => '2026-09-23',
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('patrimony.assets_total', '4000.00')
                ->where('patrimony.debts_total', '500.00')
                ->where('patrimony.asset_equity', '3500.00')
                ->where('patrimony.estimated_net_worth', '3500.00'));
    }

    public function test_update_and_delete_are_user_isolated_and_audited(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $asset = PatrimonialAsset::query()->create([
            'user_id' => $user->id,
            'name' => 'Moto',
            'estimated_value' => '18000.00',
            'debt_balance' => '11000.00',
            'valued_on' => '2026-09-23',
        ]);

        $this->actingAs($other)->put(route('patrimony.update', $asset), [
            'name' => 'Invadido',
            'estimated_value' => '1.00',
            'debt_balance' => '0.00',
            'valued_on' => '2026-09-23',
        ])->assertNotFound();

        $this->actingAs($user)->put(route('patrimony.update', $asset), [
            'name' => 'Moto atualizada',
            'category' => 'Veículo',
            'estimated_value' => '17500.00',
            'debt_balance' => '10000.00',
            'valued_on' => '2026-09-23',
        ])->assertRedirect(route('patrimony.index'));

        $this->assertDatabaseHas('patrimonial_assets', ['id' => $asset->id, 'name' => 'Moto atualizada']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'auditable_type' => 'patrimonial_asset', 'auditable_id' => $asset->id, 'action' => 'updated']);

        $this->actingAs($other)->delete(route('patrimony.destroy', $asset))->assertNotFound();
        $this->actingAs($user)->delete(route('patrimony.destroy', $asset))->assertRedirect(route('patrimony.index'));

        $this->assertDatabaseMissing('patrimonial_assets', ['id' => $asset->id]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'auditable_type' => 'patrimonial_asset', 'auditable_id' => $asset->id, 'action' => 'deleted']);
    }

    public function test_tsuki_lists_patrimony_as_optional_recommended_step(): void
    {
        $user = User::factory()->create();

        $progress = app(OnboardingProgressQuery::class)->forUser($user);
        $step = collect($progress['recommendedSteps'])->firstWhere('key', 'patrimony');

        $this->assertFalse($step['completed']);
        $this->assertSame('Adicionar patrimônio', $step['cta']['label']);
        $this->assertFalse($progress['progress']['essentialReady']);

        PatrimonialAsset::query()->create([
            'user_id' => $user->id,
            'name' => 'Moto',
            'estimated_value' => '18000.00',
            'debt_balance' => '11000.00',
            'valued_on' => '2026-09-23',
        ]);

        $updated = app(OnboardingProgressQuery::class)->forUser($user);

        $this->assertTrue(collect($updated['recommendedSteps'])->firstWhere('key', 'patrimony')['completed']);
        $this->assertFalse($updated['progress']['essentialReady']);
    }
}
