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
use App\Queries\DailyFinancialFactsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DailyCardSettlementAllocationGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_divergent_or_later_edited_allocations_block_settlement_without_becoming_spending(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-22T12:00:00Z'));
        $user = User::factory()->create();
        $other = User::factory()->create();
        $card = CreditCard::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
        $account = Account::factory()->for($user)->create();
        LedgerEntry::factory()->openingBalance()->for($user)->for($account, 'reference')->create(['amount' => '500.00']);
        app(CreateCardPurchase::class)->handle($user, [
            'credit_card_id' => $card->id,
            'category_id' => $category->id,
            'description' => 'Compra parcelada',
            'planning_type' => ExpensePlanningType::Ordinary->value,
            'gross_amount' => '200.00',
            'purchased_on' => '2026-08-01',
            'installments_count' => 2,
            'first_due_on' => '2026-08-12',
            'operation_id' => (string) Str::uuid(),
        ]);
        $payment = app(PayCreditCard::class)->handle($user, [
            'credit_card_id' => $card->id,
            'source_account_id' => $account->id,
            'amount' => '150.00',
            'paid_on' => '2026-09-09',
            'operation_id' => (string) Str::uuid(),
        ]);
        $query = app(DailyFinancialFactsQuery::class);
        $observed = CarbonImmutable::parse('2026-09-22T18:00:00Z');

        $verified = $query->forUserOnDay($user, '2026-09-09', $observed);
        $this->assertSame('150.00', $verified['settlement']['settled_total']);
        $this->assertSame([$payment->id], $verified['settlement']['payment_ids']);
        $this->assertNull($verified['eligible_spent']);

        $allocationId = $payment->allocations->first()->id;
        DB::table('card_payment_allocations')->where('id', $allocationId)->update([
            'amount' => '99.00', 'updated_at' => '2026-09-22 12:30:00',
        ]);
        $mismatch = $query->forUserOnDay($user, '2026-09-09', $observed);
        $this->assertSame('0.00', $mismatch['settlement']['settled_total']);
        $this->assertSame([], $mismatch['settlement']['payment_ids']);
        $this->assertSame([$payment->id], $mismatch['settlement']['unverifiable_payment_ids']);
        $this->assertContains('settlement_unverifiable', $mismatch['coverage_blockers']);
        $this->assertSame('0.00', $mismatch['ledger']['ordinary_total']);
        $this->assertNull($mismatch['eligible_spent']);

        DB::table('card_payment_allocations')->where('id', $allocationId)->update([
            'amount' => '100.00', 'updated_at' => '2026-09-23 12:30:00',
        ]);
        $historical = $query->forUserOnDay($user, '2026-09-09', $observed);
        $this->assertSame([$payment->id], $historical['settlement']['unverifiable_payment_ids']);
        $this->assertSame('0.00', $historical['settlement']['settled_total']);
        $this->assertNull($historical['eligible_spent']);

        $otherFacts = $query->forUserOnDay($other, '2026-09-09', $observed);
        $this->assertSame([], $otherFacts['settlement']['unverifiable_payment_ids']);
        $this->assertSame('0.00', $otherFacts['settlement']['settled_total']);
    }
}
