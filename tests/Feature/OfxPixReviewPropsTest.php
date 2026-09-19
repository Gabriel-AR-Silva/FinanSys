<?php

namespace Tests\Feature;

use App\Enums\CategoryType;
use App\Http\Controllers\OfxImportController;
use App\Models\Account;
use App\Models\BankStatementImport;
use App\Models\CardPurchase;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OfxPixReviewPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_exposes_one_neutral_pix_pair_and_only_its_owners_purchase(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $card = CreditCard::factory()->for($user)->create(['closing_day' => 5, 'due_day' => 12]);
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
        $ofx = "OFXHEADER:100\nDATA:OFXSGML\nVERSION:102\nSECURITY:NONE\nENCODING:UTF-8\n"
            .'<OFX><SIGNONMSGSRSV1><SONRS><FI><ORG>NU PAGAMENTOS S.A.</ORG><FID>260</FID></FI></SONRS></SIGNONMSGSRSV1><BANKMSGSRSV1><STMTTRNRS><STMTRS><CURDEF>BRL</CURDEF><BANKACCTFROM><BANKID>0260</BANKID><ACCTID>00012345</ACCTID><ACCTTYPE>CHECKING</ACCTTYPE></BANKACCTFROM><BANKTRANLIST><DTSTART>20260901000000[-3:BRT]</DTSTART><DTEND>20260902000000[-3:BRT]</DTEND>'
            .'<STMTTRN><TRNTYPE>CREDIT</TRNTYPE><DTPOSTED>20260901120000[-3:BRT]</DTPOSTED><TRNAMT>3.00</TRNAMT><FITID>review-pix</FITID><MEMO>Valor adicionado por Pix no Crédito</MEMO></STMTTRN>'
            .'<STMTTRN><TRNTYPE>DEBIT</TRNTYPE><DTPOSTED>20260901120000[-3:BRT]</DTPOSTED><TRNAMT>-3.00</TRNAMT><FITID>review-pix:reversal</FITID><MEMO>Transferência Pix</MEMO></STMTTRN>'
            .'</BANKTRANLIST><LEDGERBAL><BALAMT>1.00</BALAMT><DTASOF>20260902000000[-3:BRT]</DTASOF></LEDGERBAL></STMTRS></STMTTRNRS></BANKMSGSRSV1></OFX>'."\n";

        $this->actingAs($user)->post(route('ofx-imports.store'), [
            'account_id' => $account->getKey(),
            'file' => UploadedFile::fake()->createWithContent('nubank.ofx', $ofx),
        ])->assertSessionHasNoErrors();

        $import = BankStatementImport::query()->sole();
        $before = $this->reviewProps($user, $import->getKey());
        $this->assertCount(1, $before['pixPairs']);
        $this->assertSame('0.00', $before['pixPairs'][0]['bank_effect']);
        $this->assertNull($before['pixPairs'][0]['purchase_id']);

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
        $after = $this->reviewProps($user, $import->getKey());
        $this->assertCount(1, $after['pixPairs']);
        $this->assertSame('0.00', $after['pixPairs'][0]['bank_effect']);
        $this->assertSame($purchase->getKey(), $after['pixPairs'][0]['purchase_id']);

        $other = User::factory()->create();
        $foreign = $this->reviewProps($other, $import->getKey());
        $this->assertNull($foreign['review']);
        $this->assertSame([], $foreign['pixPairs']);
        $this->assertDatabaseCount('card_purchases', 1);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    private function reviewProps(User $user, int $importId): array
    {
        $request = Request::create('/importacoes/ofx?review='.$importId, 'GET');
        $request->headers->set('X-Inertia', 'true');
        $request->setUserResolver(fn () => $user);

        return json_decode(app(OfxImportController::class)->index($request)->toResponse($request)->getContent(), true, 512, JSON_THROW_ON_ERROR)['props'];
    }
}
