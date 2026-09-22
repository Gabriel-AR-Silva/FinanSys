<?php

namespace Tests\Feature;

use App\Actions\SetDailyBudget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DailyBudgetPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_budget_page(): void
    {
        $this->get(route('daily-budgets.edit'))->assertRedirect(route('login'));
    }

    public function test_page_has_unknown_budget_until_its_owner_explicitly_configures_one(): void
    {
        $user = User::factory()->create();
        app(SetDailyBudget::class)->handle(User::factory()->create(), '999', (string) Str::uuid());

        $this->actingAs($user)->get(route('daily-budgets.edit'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('DailyBudgets/Edit')
                ->where('currentBudget', null)
                ->has('localDate'));
    }

    public function test_owner_can_update_budget_from_http_then_see_the_selected_amount(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->post(route('daily-budgets.store'), [
                'amount' => '120.00',
                'operation_id' => (string) Str::uuid(),
            ])->assertRedirect(route('daily-budgets.edit'));

        $this->get(route('daily-budgets.edit'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('DailyBudgets/Edit')
                ->where('currentBudget.amount', '120.00')
                ->has('currentBudget.id'));
    }
}
