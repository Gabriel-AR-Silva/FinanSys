<?php

namespace Tests\Feature;

use App\Enums\CategoryType;
use App\Http\Controllers\OfxImportController;
use App\Models\Account;
use App\Models\BankStatementImport;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OfxPixPairReviewInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_does_not_present_unbalanced_or_different_day_pix_as_neutral_pair(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $ofx = "OFXHEADER:100\nDATA:OFXSGML\nVERSION:102\nSECURITY:NONE\nENCODING:UTF-8\n"
            .'<OFX><SIGNONMSGSRSV1><SONRS><FI><ORG>NU PAGAMENTOS S.A.</ORG><FID>260</FID></FI></SONRS></SIGNONMSGSRSV1><BANKMSGSRSV1><STMTTRNRS><STMTRS><CURDEF>BRL</CURDEF><BANKACCTFROM><BANKID>0260</BANKID><ACCTID>00012345</ACCTID><ACCTTYPE>CHECKING</ACCTTYPE></BANKACCTFROM><BANKTRANLIST><DTSTART>20260901000000[-3:BRT]</DTSTART><DTEND>20260902000000[-3:BRT]</DTEND>'
            .'<STMTTRN><TRNTYPE>CREDIT</TRNTYPE><DTPOSTED>20260901120000[-3:BRT]</DTPOSTED><TRNAMT>3.00</TRNAMT><FITID>invariant-pix</FITID><MEMO>Valor adicionado por Pix no Crédito</MEMO></STMTTRN>'
            .'<STMTTRN><TRNTYPE>DEBIT</TRNTYPE><DTPOSTED>20260901120000[-3:BRT]</DTPOSTED><TRNAMT>-3.00</TRNAMT><FITID>invariant-pix:reversal</FITID><MEMO>Transferência Pix</MEMO></STMTTRN>'
            .'</BANKTRANLIST><LEDGERBAL><BALAMT>1.00</BALAMT><DTASOF>20260902000000[-3:BRT]</DTASOF></LEDGERBAL></STMTRS></STMTTRNRS></BANKMSGSRSV1></OFX>'."\n";

        $this->actingAs($user)->post(route('ofx-imports.store'), [
            'account_id' => $account->getKey(),
            'file' => UploadedFile::fake()->createWithContent('nubank.ofx', $ofx),
        ])->assertSessionHasNoErrors();

        $import = BankStatementImport::query()->sole();
        $this->assertCount(1, $this->pairs($user, $import->getKey()));

        $debit = $import->items()->where('direction', 'debit')->sole();
        $debit->forceFill(['amount' => '4.00'])->save();
        $this->assertSame([], $this->pairs($user, $import->getKey()));

        $debit->forceFill(['amount' => '3.00', 'occurred_at' => '2026-09-02 12:00:00'])->save();
        $this->assertSame([], $this->pairs($user, $import->getKey()));
        $this->assertDatabaseCount('card_purchases', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    private function pairs(User $user, int $importId): array
    {
        $request = Request::create('/importacoes/ofx?review='.$importId, 'GET');
        $request->headers->set('X-Inertia', 'true');
        $request->setUserResolver(fn () => $user);

        return json_decode(app(OfxImportController::class)->index($request)->toResponse($request)->getContent(), true, 512, JSON_THROW_ON_ERROR)['props']['pixPairs'];
    }
}
