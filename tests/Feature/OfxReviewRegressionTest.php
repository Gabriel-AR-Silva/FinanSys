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

class OfxReviewRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_ordinary_credit_and_debit_are_posted_once_and_foreign_user_cannot_confirm(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $account = Account::factory()->for($owner)->create();
        $income = Category::factory()->for($owner)->create(['type' => CategoryType::Income]);
        $expense = Category::factory()->for($owner)->create(['type' => CategoryType::Expense]);
        $ofx = "OFXHEADER:100\nDATA:OFXSGML\nVERSION:102\nSECURITY:NONE\nENCODING:UTF-8\n<OFX><BANKMSGSRSV1><STMTTRNRS><STMTRS><CURDEF>BRL</CURDEF><BANKACCTFROM><BANKID>260</BANKID><ACCTID>12345</ACCTID><ACCTTYPE>CHECKING</ACCTTYPE></BANKACCTFROM><BANKTRANLIST><DTSTART>20260901000000[-3:BRT]</DTSTART><DTEND>20260902000000[-3:BRT]</DTEND><STMTTRN><TRNTYPE>CREDIT</TRNTYPE><DTPOSTED>20260901120000[-3:BRT]</DTPOSTED><TRNAMT>40.00</TRNAMT><FITID>ordinary-credit-1</FITID><MEMO>Receita comum</MEMO></STMTTRN><STMTTRN><TRNTYPE>DEBIT</TRNTYPE><DTPOSTED>20260901130000[-3:BRT]</DTPOSTED><TRNAMT>-12.50</TRNAMT><FITID>ordinary-debit-1</FITID><MEMO>Despesa comum</MEMO></STMTTRN></BANKTRANLIST><LEDGERBAL><BALAMT>27.50</BALAMT><DTASOF>20260902000000[-3:BRT]</DTASOF></LEDGERBAL></STMTRS></STMTTRNRS></BANKMSGSRSV1></OFX>";

        $this->actingAs($owner)->post(route('ofx-imports.store'), [
            'account_id' => $account->id,
            'file' => UploadedFile::fake()->createWithContent('ordinary.ofx', $ofx),
        ])->assertSessionHasNoErrors();
        $import = BankStatementImport::query()->sole();
        $items = OfxImportItem::query()->get()->keyBy('direction');
        $this->assertDatabaseCount('ledger_entries', 0);

        $this->actingAs($other)->post(route('ofx-imports.confirm', $import), [
            'item_ids' => $items->pluck('id')->all(),
        ])->assertNotFound();
        $this->assertDatabaseCount('ledger_entries', 0);

        foreach (['credit' => [$income, 'income'], 'debit' => [$expense, 'expense']] as $direction => [$category, $type]) {
            $this->actingAs($owner)->patch(route('ofx-imports.items.update', $items[$direction]), [
                'classification' => $type,
                'category_id' => $category->id,
                'planning_type' => $type === 'expense' ? 'ordinary' : null,
            ])->assertSessionHasNoErrors();
        }
        $ids = $items->pluck('id')->all();
        $this->actingAs($owner)->post(route('ofx-imports.confirm', $import), ['item_ids' => $ids])->assertSessionHasNoErrors();
        $this->assertSame('40.00', LedgerEntry::query()->where('type', 'income')->sole()->amount);
        $this->assertSame('12.50', LedgerEntry::query()->where('type', 'expense')->sole()->amount);
        $this->assertDatabaseCount('ledger_entries', 2);

        $this->actingAs($owner)->post(route('ofx-imports.confirm', $import), ['item_ids' => $ids]);
        $this->assertDatabaseCount('ledger_entries', 2);
    }
}
