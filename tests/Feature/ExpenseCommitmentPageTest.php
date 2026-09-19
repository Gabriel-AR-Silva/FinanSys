<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\ExpenseCommitment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExpenseCommitmentPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_date_only_values_and_never_exposes_other_users_commitments(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);
        ExpenseCommitment::query()->create([
            'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $category->id,
            'description' => 'Conta prevista', 'amount' => '90.00', 'paid_amount' => '0.00',
            'due_on' => '2026-10-03', 'planning_type' => 'fixed', 'status' => 'pending',
            'operation_id' => (string) Str::uuid(),
        ]);
        $otherAccount = Account::factory()->for($other)->create();
        $otherCategory = Category::factory()->for($other)->create(['type' => 'expense']);
        ExpenseCommitment::query()->create([
            'user_id' => $other->id, 'account_id' => $otherAccount->id, 'category_id' => $otherCategory->id,
            'description' => 'Segredo', 'amount' => '200.00', 'paid_amount' => '0.00',
            'due_on' => '2026-10-04', 'planning_type' => 'fixed', 'status' => 'pending',
            'operation_id' => (string) Str::uuid(),
        ]);

        $this->actingAs($user)->get(route('expense-commitments.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('ExpenseCommitments/Index')
                ->has('commitments', 1)
                ->where('commitments.0.description', 'Conta prevista')
                ->where('commitments.0.due_on', '2026-10-03')
                ->where('commitments.0.version', 1));
    }
}
