<?php

namespace Tests\Feature;

use App\Actions\CreateCardPurchase;
use App\Enums\CategoryType;
use App\Enums\ExpensePlanningType;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CardPurchaseDetailAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_link_shows_only_the_authenticated_users_purchase_and_installments(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $card = CreditCard::factory()->for($owner)->create();
        $category = Category::factory()->for($owner)->create(['type' => CategoryType::Expense]);
        $purchase = app(CreateCardPurchase::class)->handle($owner, [
            'credit_card_id' => $card->id,
            'category_id' => $category->id,
            'description' => 'Compra Pix no Crédito',
            'planning_type' => ExpensePlanningType::Ordinary->value,
            'gross_amount' => '30.00',
            'purchased_on' => '2026-09-01',
            'installments_count' => 2,
            'first_due_on' => '2026-09-12',
            'operation_id' => (string) Str::uuid(),
        ]);

        $this->actingAs($owner)->get(route('credit-cards.index', ['purchase' => $purchase->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CreditCards/Purchase')
                ->where('card.id', $card->id)
                ->where('purchase.id', $purchase->id)
                ->where('purchase.description', 'Compra Pix no Crédito')
                ->has('purchase.installments', 2));

        $this->actingAs($other)->get(route('credit-cards.index', ['purchase' => $purchase->id]))->assertNotFound();
        $this->actingAs($owner)->get(route('credit-cards.index', ['purchase' => 'invalid']))->assertNotFound();
        $this->actingAs($owner)->get(route('credit-cards.index', ['purchase' => '0']))->assertNotFound();
    }
}
