<?php

namespace Tests\Feature;

use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\BankStatementImport;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\OfxImportItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OfxImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_prepares_review_without_creating_financial_fact(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('ofx-imports.store'), [
            'account_id' => $account->getKey(),
            'file' => UploadedFile::fake()->createWithContent('nubank.ofx', $this->ofx([
                $this->transaction('3.00', 'Valor adicionado por Pix no Crédito', 'fake-id'),
                $this->transaction('-3.00', 'Transferência Pix', 'fake-id:reversal'),
            ])),
        ]);

        $response->assertSessionHasNoErrors();
        $import = BankStatementImport::query()->sole();

        $this->assertCount(2, $import->items);
        $this->assertDatabaseCount('ledger_entries', 0);
        $this->assertSame('card_credit_pix_candidate', $import->items->first()->classification->value);
    }

    public function test_reviewed_ordinary_item_is_only_posted_after_explicit_confirmation(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);

        $this->actingAs($user)->post(route('ofx-imports.store'), [
            'account_id' => $account->getKey(),
            'file' => UploadedFile::fake()->createWithContent('statement.ofx', $this->ofx([
                $this->transaction('-12.50', 'Mercado', 'expense-1'),
            ])),
        ])->assertSessionHasNoErrors();

        $import = BankStatementImport::query()->sole();
        $item = OfxImportItem::query()->sole();

        $this->actingAs($user)->patch(route('ofx-imports.items.update', $item), [
            'classification' => 'expense',
            'category_id' => $category->getKey(),
            'planning_type' => 'ordinary',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('ledger_entries', 0);

        $this->actingAs($user)->post(route('ofx-imports.confirm', $import), [
            'item_ids' => [$item->getKey()],
        ])->assertSessionHasNoErrors();

        $entry = LedgerEntry::query()->sole();
        $this->assertSame('12.50', $entry->amount);
        $this->assertSame('expense', $entry->type->value);
        $this->assertSame('confirmed', $item->fresh()->review_status->value);
    }

    /** @param  list<string>  $transactions */
    private function ofx(array $transactions): string
    {
        $body = implode('', $transactions);

        return "OFXHEADER:100\nDATA:OFXSGML\nVERSION:102\nSECURITY:NONE\nENCODING:UTF-8\n<OFX><SIGNONMSGSRSV1><SONRS><FI><ORG>NU PAGAMENTOS S.A.</ORG><FID>260</FID></FI></SONRS></SIGNONMSGSRSV1><BANKMSGSRSV1><STMTTRNRS><STMTRS><CURDEF>BRL</CURDEF><BANKACCTFROM><BANKID>0260</BANKID><ACCTID>00012345</ACCTID><ACCTTYPE>CHECKING</ACCTTYPE></BANKACCTFROM><BANKTRANLIST><DTSTART>20260901000000[-3:BRT]</DTSTART><DTEND>20260902000000[-3:BRT]</DTEND>{$body}</BANKTRANLIST><LEDGERBAL><BALAMT>1.00</BALAMT><DTASOF>20260902000000[-3:BRT]</DTASOF></LEDGERBAL></STMTRS></STMTTRNRS></BANKMSGSRSV1></OFX>\n";
    }

    private function transaction(string $amount, string $memo, string $fitId): string
    {
        $type = str_starts_with($amount, '-') ? 'DEBIT' : 'CREDIT';

        return "<STMTTRN><TRNTYPE>{$type}</TRNTYPE><DTPOSTED>20260901120000[-3:BRT]</DTPOSTED><TRNAMT>{$amount}</TRNAMT><FITID>{$fitId}</FITID><MEMO>{$memo}</MEMO></STMTTRN>";
    }
}
