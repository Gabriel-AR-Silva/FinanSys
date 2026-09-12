<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CreditCardWorkflowControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_index_contains_only_the_authenticated_users_cards(): void
    {
        $this->get(route('credit-cards.index'))->assertRedirect(route('login'));
        $user = User::factory()->create();
        CreditCard::factory()->for($user)->create(['name' => 'Meu cartão']);
        CreditCard::factory()->create(['name' => 'Cartão alheio']);

        $this->actingAs($user)->get(route('credit-cards.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('CreditCards/Index')
            ->has('cards', 1)
            ->where('cards.0.name', 'Meu cartão')
            ->has('categories')
            ->has('accounts'));
    }

    public function test_authenticated_user_can_complete_the_basic_card_workflow(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
        $account = Account::factory()->for($user)->create();
        LedgerEntry::factory()->openingBalance()->for($user)->for($account, 'reference')->create(['amount' => '500.00']);

        $this->actingAs($user)->post(route('credit-cards.store'), [
            'name' => 'Nubank', 'closing_day' => 5, 'due_day' => 12, 'operation_id' => (string) Str::uuid(),
        ])->assertRedirect(route('credit-cards.index'))->assertSessionHas('success');
        $card = CreditCard::query()->whereBelongsTo($user)->sole();

        $this->post(route('card-purchases.store'), [
            'credit_card_id' => $card->id, 'category_id' => $category->id, 'description' => 'Mercado',
            'planning_type' => 'ordinary', 'gross_amount' => '120.00', 'purchased_on' => '2026-09-01',
            'installments_count' => 1, 'first_due_on' => '2026-09-12', 'operation_id' => (string) Str::uuid(),
        ])->assertRedirect(route('credit-cards.index'))->assertSessionHas('success');

        $this->post(route('card-payments.store'), [
            'credit_card_id' => $card->id, 'source_account_id' => $account->id, 'amount' => '120.00',
            'paid_on' => '2026-09-10', 'operation_id' => (string) Str::uuid(),
        ])->assertRedirect(route('credit-cards.index'))->assertSessionHas('success');

        $this->assertDatabaseHas('card_installments', ['gross_amount' => 120, 'paid_amount' => 120, 'status' => 'paid']);
        $this->assertDatabaseHas('ledger_entries', ['type' => 'card_payment', 'amount' => 120]);
    }

    public function test_card_endpoints_validate_input_and_reject_foreign_resources(): void
    {
        $user = User::factory()->create();
        $foreignCard = CreditCard::factory()->create();
        $foreignCategory = Category::factory()->create(['type' => CategoryType::Expense]);

        $this->actingAs($user)->post(route('credit-cards.store'), [
            'name' => '', 'closing_day' => 0, 'due_day' => 32, 'operation_id' => 'invalid',
        ])->assertSessionHasErrors(['name', 'closing_day', 'due_day', 'operation_id']);

        $this->post(route('card-purchases.store'), [
            'credit_card_id' => $foreignCard->id, 'category_id' => $foreignCategory->id, 'description' => 'Não pode',
            'planning_type' => 'ordinary', 'gross_amount' => '10.00', 'purchased_on' => '2026-09-01',
            'installments_count' => 1, 'first_due_on' => '2026-09-12', 'operation_id' => (string) Str::uuid(),
        ])->assertSessionHasErrors('credit_card_id');

        $this->assertDatabaseCount('card_purchases', 0);
    }
}
