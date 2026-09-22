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
use App\Queries\DailyCardPaymentAllocationIntegrityQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class DailyCardPaymentAllocationIntegrityQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_payment_allocations_reconcile_without_becoming_daily_spending_and_later_edits_are_not_historical_facts(): void
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
        $observed = CarbonImmutable::parse('2026-09-22T18:00:00Z');
        $query = app(DailyCardPaymentAllocationIntegrityQuery::class);
        $verified = $query->forPayment($user, $payment, $observed);

        $this->assertSame('150.00', $verified['allocated_total']);
        $this->assertCount(2, $verified['installment_allocation_ids']);
        $this->assertSame([], $verified['charge_allocation_ids']);
        $this->assertSame('card_allocation_amount_verified', $verified['coverage']);

        $allocationId = $payment->allocations->first()->id;
        DB::table('card_payment_allocations')->where('id', $allocationId)->update([
            'amount' => '99.00', 'updated_at' => '2026-09-22 12:30:00',
        ]);
        $mismatch = $query->forPayment($user, $payment, $observed);
        $this->assertSame('149.00', $mismatch['allocated_total']);
        $this->assertSame('card_allocation_amount_mismatch', $mismatch['coverage']);

        DB::table('card_payment_allocations')->where('id', $allocationId)->update(['updated_at' => '2026-09-23 12:30:00']);
        $historical = $query->forPayment($user, $payment, $observed);
        $this->assertSame('50.00', $historical['allocated_total']);
        $this->assertSame(['installment:'.$allocationId], $historical['unverifiable_allocation_ids']);
        $this->assertSame('partial_card_allocation_unverifiable', $historical['coverage']);

        $this->expectException(InvalidArgumentException::class);
        $query->forPayment($other, $payment, $observed);
    }
}
