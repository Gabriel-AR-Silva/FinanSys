<?php

namespace Tests\Feature\Http\Controllers;

use App\Actions\CreateCardPurchase;
use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CreditCardAdvanceEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_page_exposes_purchase_date_to_identify_future_installments_for_advance(): void
    {
        $user = User::factory()->create();
        $card = CreditCard::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
        $purchasedOn = today('America/Sao_Paulo')->toDateString();
        $nextDueOn = today('America/Sao_Paulo')->startOfMonth()->addMonth()->day(12)->toDateString();

        app(CreateCardPurchase::class)->handle($user, [
            'credit_card_id' => $card->id,
            'category_id' => $category->id,
            'description' => 'Compra com parcela futura',
            'planning_type' => 'ordinary',
            'gross_amount' => '150.00',
            'purchased_on' => $purchasedOn,
            'installments_count' => 1,
            'first_due_on' => $nextDueOn,
            'operation_id' => (string) Str::uuid(),
        ]);

        $this->actingAs($user)->get(route('credit-cards.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CreditCards/Index')
                ->has('cards', 1)
                ->where('cards.0.purchases.0.purchased_on', $purchasedOn)
                ->where('cards.0.purchases.0.installments.0.purchased_on', $purchasedOn)
                ->where('cards.0.purchases.0.installments.0.due_on', $nextDueOn));
    }
}
