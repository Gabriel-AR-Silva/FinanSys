<?php

namespace Tests\Feature;

use App\Actions\CreateCardPurchase;
use App\Models\CardInstallment;
use App\Models\CardPurchase;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CardPurchaseDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_delete_an_unpaid_card_purchase_and_its_installments(): void
    {
        $user = User::factory()->create();
        $card = CreditCard::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create();
        $purchase = app(CreateCardPurchase::class)->handle($user, [
            'credit_card_id' => $card->id,
            'category_id' => $category->id,
            'description' => 'Compra lançada errada',
            'planning_type' => 'ordinary',
            'gross_amount' => '120.00',
            'purchased_on' => '2026-09-24',
            'installments_count' => 2,
            'first_due_on' => '2026-10-12',
            'operation_id' => (string) Str::uuid(),
        ]);

        $this->actingAs($user)
            ->delete(route('card-purchases.destroy', $purchase))
            ->assertRedirect(route('credit-cards.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted(CardPurchase::class, ['id' => $purchase->id]);
        $this->assertDatabaseMissing(CardInstallment::class, ['card_purchase_id' => $purchase->id]);
    }

    public function test_user_cannot_delete_another_users_card_purchase(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $purchase = CardPurchase::factory()->for($owner)->create();

        $this->actingAs($other)
            ->delete(route('card-purchases.destroy', $purchase))
            ->assertNotFound();
    }
}
