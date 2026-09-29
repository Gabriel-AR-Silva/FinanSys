<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\InvestmentMovement;
use App\Models\InvestmentPosition;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvestmentFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_investment_position_is_manual_history_and_increases_patrimony_without_changing_cash(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        LedgerEntry::factory()->openingBalance()->for($user)->for($account, 'reference')->create(['amount' => '1000.00']);

        $entriesBefore = LedgerEntry::query()->count();

        $this->actingAs($user)->post(route('investments.store'), [
            'asset_type' => 'Renda variável',
            'ticker' => 'ABCD3',
            'name' => 'Empresa ABCD',
            'purchased_on' => '2026-09-01',
            'quantity' => '10',
            'average_cost' => '20.0000',
            'current_value' => '250.00',
            'valued_on' => '2026-09-29',
        ])->assertRedirect(route('investments.index'));

        $this->assertSame($entriesBefore, LedgerEntry::query()->count());
        $this->assertDatabaseHas('investment_positions', [
            'user_id' => $user->id,
            'ticker' => 'ABCD3',
            'total_invested' => '200.00',
            'current_value' => '250.00',
        ]);
        $this->assertDatabaseHas('investment_movements', [
            'user_id' => $user->id,
            'type' => 'buy',
            'amount' => '200.00',
        ]);

        $this->actingAs($user)->get(route('investments.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Investments/Index')
                ->where('investments.summary.total_invested', '200.00')
                ->where('investments.summary.current_value', '250.00')
                ->where('investments.summary.unrealized_result', '50.00'));

        $this->actingAs($user)->get(route('patrimony.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('patrimony.summary.financial_balance', '1000.00')
                ->where('patrimony.summary.investments_total', '250.00')
                ->where('patrimony.summary.estimated_net_worth', '1250.00'));
    }

    public function test_investments_are_isolated_by_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        InvestmentPosition::factory()->for($user)->create(['total_invested' => '100.00', 'current_value' => '120.00']);
        InvestmentPosition::factory()->for($other)->create(['total_invested' => '900.00', 'current_value' => '999.00']);

        $this->actingAs($user)->get(route('investments.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('investments.positions', 1)
                ->where('investments.summary.current_value', '120.00'));

        $this->assertSame(0, InvestmentMovement::query()->count());
    }
}
