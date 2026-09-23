<?php

namespace Tests\Feature;

use App\Actions\CreateCardPurchase;
use App\Actions\PayCreditCard;
use App\Enums\CategoryType;
use App\Enums\ExpensePlanningType;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Queries\CreditCardLimitQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CreditCardLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_configured_limit_tracks_outstanding_and_restores_after_payment(): void
    {
        $user = User::factory()->create();
        $card = CreditCard::factory()->for($user)->create(['credit_limit' => '300.00']);
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);

        app(CreateCardPurchase::class)->handle($user, $this->purchasePayload($card, $category, '200.00'));

        $limit = app(CreditCardLimitQuery::class)->forCard($card->refresh());
        $this->assertSame('300.00', $limit['total']);
        $this->assertSame('200.00', $limit['outstanding']);
        $this->assertSame('100.00', $limit['available']);
        $this->assertSame('0.00', $limit['over_limit']);

        try {
            app(CreateCardPurchase::class)->handle($user, $this->purchasePayload($card, $category, '100.01'));
            $this->fail('Compra acima do limite disponível deveria ser rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('gross_amount', $exception->errors());
        }

        $account = Account::factory()->for($user)->create();
        LedgerEntry::factory()->openingBalance()->for($user)->for($account, 'reference')->create(['amount' => '500.00']);

        app(PayCreditCard::class)->handle($user, [
            'credit_card_id' => $card->id,
            'source_account_id' => $account->id,
            'amount' => '100.00',
            'paid_on' => '2026-09-09',
            'operation_id' => (string) Str::uuid(),
        ]);

        $afterPayment = app(CreditCardLimitQuery::class)->forCard($card->refresh());
        $this->assertSame('100.00', $afterPayment['outstanding']);
        $this->assertSame('200.00', $afterPayment['available']);
    }

    public function test_card_without_limit_preserves_existing_unrestricted_purchase_behavior(): void
    {
        $user = User::factory()->create();
        $card = CreditCard::factory()->for($user)->create(['credit_limit' => null]);
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);

        app(CreateCardPurchase::class)->handle($user, $this->purchasePayload($card, $category, '9999.99'));

        $limit = app(CreditCardLimitQuery::class)->forCard($card->refresh());
        $this->assertNull($limit['total']);
        $this->assertNull($limit['available']);
        $this->assertSame('9999.99', $limit['outstanding']);
    }

    public function test_limit_can_be_updated_and_foreign_card_is_not_exposed(): void
    {
        $user = User::factory()->create();
        $card = CreditCard::factory()->for($user)->create();
        $foreignCard = CreditCard::factory()->create();

        $this->actingAs($user)
            ->patch(route('credit-cards.limit.update', $card->id), ['credit_limit' => '500.00'])
            ->assertRedirect(route('credit-cards.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('credit_cards', ['id' => $card->id, 'credit_limit' => 500]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'auditable_type' => $card->getMorphClass(),
            'auditable_id' => $card->id,
            'action' => 'updated',
        ]);

        $this->actingAs($user)
            ->patch(route('credit-cards.limit.update', $foreignCard->id), ['credit_limit' => '900.00'])
            ->assertNotFound();

        $this->assertDatabaseHas('credit_cards', ['id' => $foreignCard->id, 'credit_limit' => null]);
    }

    public function test_card_index_exposes_total_outstanding_available_and_over_limit(): void
    {
        $user = User::factory()->create();
        $card = CreditCard::factory()->for($user)->create(['credit_limit' => '150.00']);
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
        app(CreateCardPurchase::class)->handle($user, $this->purchasePayload($card, $category, '120.00'));

        $this->withoutVite();

        $this->actingAs($user)->get(route('credit-cards.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('cards.0.limit.total', '150.00')
                ->where('cards.0.limit.outstanding', '120.00')
                ->where('cards.0.limit.available', '30.00')
                ->where('cards.0.limit.over_limit', '0.00'));
    }

    private function purchasePayload(CreditCard $card, Category $category, string $amount): array
    {
        return [
            'credit_card_id' => $card->id,
            'category_id' => $category->id,
            'description' => 'Compra de teste',
            'planning_type' => ExpensePlanningType::Ordinary->value,
            'gross_amount' => $amount,
            'purchased_on' => '2026-09-01',
            'installments_count' => 1,
            'first_due_on' => '2026-09-12',
            'operation_id' => (string) Str::uuid(),
        ];
    }
}
