<?php

namespace Tests\Feature;

use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\BankStatementImport;
use App\Models\CardPurchase;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OfxPixReviewPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_exposes_a_single_neutral_pix_pair_and_owned_purchase_after_confirmation(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $card = CreditCard::factory()->for($user)->create(['closing_day' => 5, 'due_day' => 12]);
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
        $ofx = 'OFXHEADER:100'."\n".'DATA:OFXSGML'."\n".'VERSION:102'."\n".'SECURITY:NONE'."\n".'ENCODING:UTF-8'."\n"
            .'<OFX><SIGNONMSGSRSV1><SONRS><FI><ORG>NU PAGAMENTOS S.A.</ORG><FID>260</FID></FI></SONRS></SIGNONMSGSRSV1><BANKMSGSRSV1><STMTTRNRS><STMTRS><CURDEF>BRL</CURDEF><BANKACCTFROM><BANKID>0260</BANKID><ACCTID>00012345</ACCTID><ACCTTYPE>CHECKING</ACCTTYPE></BANKACCTFROM><BANKTRANLIST><DTSTART>20260901000000[-3:BRT]</DTSTART><DTEND>20260902000000[-3:BRT]</DTEND>'
            .'<STMTTRN><TRNTYPE>CREDIT</TRNTYPE><DTPOSTED>20260901120000[-3:BRT]</DTPOSTED><TRNAMT>3.00</TRNAMT><FITID>review-pix</FITID><MEMO>Valor adicionado por Pix no Crédito</MEMO></STMTTRN>'
            .'<STMTTRN><TRNTYPE>DEBIT</TRNTYPE><DTPOSTED>20260901120000[-3:BRT]</DTPOSTED><TRNAMT>-3.00</TRNAMT><FITID>review-pix:reversal</FITID><MEMO>Transferência Pix</MEMO></STMTTRN>'
            .'</BANKTRANLIST><LEDGERBAL><BALAMT>1.00</BALAMT><DTASOF>20260902000000[-3:BRT]</DTASOF></LEDGERBAL></STMTRS></STMTTRNRS></BANKMSGSRSV1></OFX>'."\n";

        $this->actingAs($user)->post(route('ofx-imports.store'), [
            'account_id' => $account->getKey(),
            'file' => UploadedFile::fake()->createWithContent('nubank.ofx', $ofx),
        ])->assertSessionHasNoErrors();

        $import = BankStatementImport::query()->sole();
        $this->actingAs($user)->withHeader('X-Inertia', 'true')->get(route('ofx-imports.index', ['review' => $import->getKey()]))
            ->assertInertia(fn (Assert $page) => $page->component('OfxImports/Index')
                ->has('pixPairs', 1)
                ->where('pixPairs.0.bank_effect', '0.00')
                ->where('pixPairs.0.purchase_id', null)
                ->etc());

        $debit = $import->items()->where('direction', 'debit')->sole();
        $this->actingAs($user)->post(route('ofx-imports.pix-credit.confirm', [
            'import' => $import->getKey(), 'item' => $debit->getKey(),
        ]), [
            'credit_card_id' => $card->getKey(),
            'category_id' => $category->getKey(),
            'planning_type' => 'ordinary',
            'installments_count' => 1,
            'first_due_on' => '2026-09-12',
        ])->assertSessionHasNoErrors();

        $purchase = CardPurchase::query()->sole();
        $this->actingAs($user)->withHeader('X-Inertia', 'true')->get(route('ofx-imports.index', ['review' => $import->getKey()]))
            ->assertInertia(fn (Assert $page) => $page->component('OfxImports/Index')
                ->has('pixPairs', 1)
                ->where('pixPairs.0.bank_effect', '0.00')
                ->where('pixPairs.0.purchase_id', $purchase->getKey())
                ->etc());

        $other = User::factory()->create();
        $this->actingAs($other)->withHeader('X-Inertia', 'true')->get(route('ofx-imports.index', ['review' => $import->getKey()]))
            ->assertInertia(fn (Assert $page) => $page->component('OfxImports/Index')
                ->where('review', null)
                ->has('pixPairs', 0)
                ->etc());
        $this->assertDatabaseCount('card_purchases', 1);
        $this->assertDatabaseCount('ledger_entries', 0);
    }
}
