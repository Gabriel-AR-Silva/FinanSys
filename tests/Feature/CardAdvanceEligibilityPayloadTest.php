<?php

namespace Tests\Feature;

use App\Actions\CreateCardPurchase;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CardAdvanceEligibilityPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_future_installment_exposes_purchase_date_for_advance_filter(): void
    {
        $this->travelTo(new \DateTimeImmutable('2026-09-18T15:00:00Z'));
        $user = User::factory()->create();
        $card = CreditCard::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);

        app(CreateCardPurchase::class)->handle($user, [
            'credit_card_id' => $card->id,
            'category_id' => $category->id,
            'description' => 'Fones',
            'planning_type' => 'ordinary',
            'gross_amount' => '150.00',
            'purchased_on' => '2026-09-18',
            'installments_count' => 1,
            'first_due_on' => '2026-10-12',
            'operation_id' => (string) Str::uuid(),
        ]);

        $this->actingAs($user)->get(route('credit-cards.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('CreditCards/Index')
                ->where('cards.0.purchases.0.purchased_on', '2026-09-18')
                ->where('cards.0.purchases.0.installments.0.purchased_on', '2026-09-18')
                ->where('cards.0.purchases.0.installments.0.due_on', '2026-10-12')
                ->where('cards.0.purchases.0.installments.0.status', 'pending'));
    }
}
