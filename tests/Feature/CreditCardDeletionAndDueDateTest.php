<?php

namespace Tests\Feature;

use App\Models\CardPurchase;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreditCardDeletionAndDueDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_delete_an_empty_credit_card(): void
    {
        $user = User::factory()->create();
        $card = CreditCard::factory()->for($user)->create();

        $this->actingAs($user)
            ->delete(route('credit-cards.destroy', $card))
            ->assertRedirect(route('credit-cards.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted(CreditCard::class, ['id' => $card->id]);
    }

    public function test_credit_card_with_financial_history_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $card = CreditCard::factory()->for($user)->create();
        CardPurchase::factory()->for($user)->for($card, 'creditCard')->create();

        $this->actingAs($user)
            ->delete(route('credit-cards.destroy', $card))
            ->assertRedirect(route('credit-cards.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('credit_cards', [
            'id' => $card->id,
            'deleted_at' => null,
        ]);
    }

    public function test_user_cannot_delete_another_users_credit_card(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $card = CreditCard::factory()->for($owner)->create();

        $this->actingAs($other)
            ->delete(route('credit-cards.destroy', $card))
            ->assertNotFound();
    }

    public function test_first_due_date_cannot_be_before_purchase_date(): void
    {
        $user = User::factory()->create();
        $card = CreditCard::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('card-purchases.store'), [
                'credit_card_id' => $card->id,
                'category_id' => $category->id,
                'description' => 'Compra de teste',
                'planning_type' => 'ordinary',
                'gross_amount' => '100.00',
                'purchased_on' => '2026-09-24',
                'installments_count' => 1,
                'first_due_on' => '2026-09-23',
                'operation_id' => (string) Str::uuid(),
            ])
            ->assertSessionHasErrors('first_due_on');
    }
}
