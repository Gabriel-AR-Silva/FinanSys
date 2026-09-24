<?php

namespace Tests\Feature;

use App\Actions\CreateCardPurchase;
use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OngoingCardPurchaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_only_remaining_installments_and_preserves_original_numbering(): void
    {
        $user = User::factory()->create();
        $card = CreditCard::factory()->for($user)->create(['credit_limit' => '700.00']);
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);

        $purchase = app(CreateCardPurchase::class)->handle($user, [
            'credit_card_id' => $card->id,
            'category_id' => $category->id,
            'description' => 'Compra anterior ao FinanSys',
            'planning_type' => 'ordinary',
            'gross_amount' => '1000.00',
            'purchased_on' => '2026-05-10',
            'installments_count' => 10,
            'paid_installments_count' => 4,
            'first_due_on' => '2026-09-10',
            'operation_id' => (string) Str::uuid(),
        ]);

        $installments = $purchase->installments()->orderBy('installment_number')->get();

        $this->assertCount(6, $installments);
        $this->assertSame([5, 6, 7, 8, 9, 10], $installments->pluck('installment_number')->all());
        $this->assertSame('2026-09-10', $installments->first()->due_on->toDateString());
        $this->assertSame('2027-02-10', $installments->last()->due_on->toDateString());
        $this->assertSame('600.00', $installments->sum(fn ($installment) => (float) $installment->gross_amount) === 600.0 ? '600.00' : 'unexpected');
    }

    public function test_limit_validation_uses_only_the_remaining_balance(): void
    {
        $user = User::factory()->create();
        $card = CreditCard::factory()->for($user)->create(['credit_limit' => '650.00']);
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);

        $purchase = app(CreateCardPurchase::class)->handle($user, [
            'credit_card_id' => $card->id,
            'category_id' => $category->id,
            'description' => 'Compra em andamento',
            'planning_type' => 'ordinary',
            'gross_amount' => '1000.00',
            'purchased_on' => '2026-05-10',
            'installments_count' => 10,
            'paid_installments_count' => 4,
            'first_due_on' => '2026-09-10',
            'operation_id' => (string) Str::uuid(),
        ]);

        $this->assertSame('1000.00', $purchase->gross_amount);
        $this->assertSame(6, $purchase->installments()->count());
    }
}
