<?php

namespace Tests\Feature;

use App\Actions\ConfirmOfxCardCreditPix;
use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\BankStatementImport;
use App\Models\CardPurchase;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\OfxImportItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OfxPixCreditReplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirming_the_same_pix_twice_returns_the_same_purchase(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $card = CreditCard::factory()->for($user)->create(['closing_day' => 5, 'due_day' => 12]);
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
        $ofx = 'OFXHEADER:100'."\n".'DATA:OFXSGML'."\n".'VERSION:102'."\n".'SECURITY:NONE'."\n".'ENCODING:UTF-8'."\n"
            .'<OFX><SIGNONMSGSRSV1><SONRS><FI><ORG>NU PAGAMENTOS S.A.</ORG><FID>260</FID></FI></SONRS></SIGNONMSGSRSV1><BANKMSGSRSV1><STMTTRNRS><STMTRS><CURDEF>BRL</CURDEF><BANKACCTFROM><BANKID>0260</BANKID><ACCTID>00012345</ACCTID><ACCTTYPE>CHECKING</ACCTTYPE></BANKACCTFROM><BANKTRANLIST><DTSTART>20260901000000[-3:BRT]</DTSTART><DTEND>20260902000000[-3:BRT]</DTEND>'
            .'<STMTTRN><TRNTYPE>CREDIT</TRNTYPE><DTPOSTED>20260901120000[-3:BRT]</DTPOSTED><TRNAMT>3.00</TRNAMT><FITID>replay-pix</FITID><MEMO>Valor adicionado por Pix no Crédito</MEMO></STMTTRN>'
            .'<STMTTRN><TRNTYPE>DEBIT</TRNTYPE><DTPOSTED>20260901120000[-3:BRT]</DTPOSTED><TRNAMT>-3.00</TRNAMT><FITID>replay-pix:reversal</FITID><MEMO>Transferência Pix</MEMO></STMTTRN>'
            .'</BANKTRANLIST><LEDGERBAL><BALAMT>1.00</BALAMT><DTASOF>20260902000000[-3:BRT]</DTASOF></LEDGERBAL></STMTRS></STMTTRNRS></BANKMSGSRSV1></OFX>'."\n";

        $this->actingAs($user)->post(route('ofx-imports.store'), [
            'account_id' => $account->getKey(),
            'file' => UploadedFile::fake()->createWithContent('nubank.ofx', $ofx),
        ])->assertSessionHasNoErrors();

        $import = BankStatementImport::query()->sole();
        $debit = OfxImportItem::query()->where('direction', 'debit')->sole();
        $credit = OfxImportItem::query()->where('direction', 'credit')->sole();
        $data = [
            'credit_card_id' => $card->getKey(),
            'category_id' => $category->getKey(),
            'planning_type' => 'ordinary',
            'installments_count' => 1,
            'first_due_on' => '2026-09-12',
        ];

        $action = app(ConfirmOfxCardCreditPix::class);
        $first = $action->handle($user, $import, $debit, $data);
        $second = $action->handle($user, $import, $debit, $data);
        $third = $action->handle($user, $import, $credit, $data);

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame($first->getKey(), $third->getKey());
        $this->assertDatabaseCount('card_purchases', 1);
        $this->assertDatabaseCount('ledger_entries', 0);
        $this->assertSame(2, OfxImportItem::query()->where('review_status', 'confirmed')->count());
        $this->assertSame($first->getKey(), CardPurchase::query()->sole()->getKey());

        // A repeated browser request must remain idempotent at the HTTP boundary, too.
        $this->actingAs($user)->post(route('ofx-imports.pix-credit.confirm', [
            'import' => $import->getKey(),
            'item' => $credit->getKey(),
        ]), $data)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('card_purchases', 1);
        $this->assertDatabaseCount('ledger_entries', 0);
        $this->assertSame($first->getKey(), CardPurchase::query()->sole()->getKey());
    }
}
