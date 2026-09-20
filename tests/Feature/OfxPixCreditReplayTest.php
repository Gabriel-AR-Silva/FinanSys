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
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class OfxPixCreditReplayTest extends TestCase
{
    use DatabaseMigrations;

    public function test_confirming_the_same_pix_twice_returns_the_same_purchase(): void
    {
        [$user, $import, $debit, $credit, $data] = $this->pixCreditFixture();

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

    public function test_replaying_a_confirmed_pix_with_different_purchase_data_is_rejected(): void
    {
        [$user, $import, $debit, $credit, $data] = $this->pixCreditFixture();

        $action = app(ConfirmOfxCardCreditPix::class);
        $action->handle($user, $import, $debit, $data);

        try {
            $action->handle($user, $import, $credit, [
                ...$data,
                'installments_count' => 2,
            ]);
            $this->fail('A repetição divergente deveria ser rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Esta confirmação já foi realizada com dados diferentes.',
                $exception->errors()['item'][0] ?? null,
            );
        }

        $this->assertDatabaseCount('card_purchases', 1);
        $this->assertDatabaseCount('card_installments', 1);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_simultaneous_mysql_confirmations_use_distinct_connections_without_duplication(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('A prova de concorrência real exige MySQL.');
        }

        [$user, $import, $debit, $credit, $data] = $this->pixCreditFixture();
        $startAt = microtime(true) + 2;
        $encodedData = base64_encode(json_encode($data, JSON_THROW_ON_ERROR));
        $script = <<<'PHP'
        $basePath = $argv[1];
        require $basePath.'/vendor/autoload.php';
        $app = require $basePath.'/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        Illuminate\Support\Facades\DB::purge();
        $user = App\Models\User::query()->findOrFail((int) $argv[2]);
        $import = App\Models\BankStatementImport::query()->findOrFail((int) $argv[3]);
        $item = App\Models\OfxImportItem::query()->findOrFail((int) $argv[4]);
        $data = json_decode(base64_decode($argv[5]), true, flags: JSON_THROW_ON_ERROR);
        $startAt = (float) $argv[6];
        while (microtime(true) < $startAt) {
            usleep(1000);
        }
        $attemptedAt = microtime(true);
        $connectionId = Illuminate\Support\Facades\DB::selectOne('select connection_id() as id')->id;
        $purchase = $app->make(App\Actions\ConfirmOfxCardCreditPix::class)->handle($user, $import, $item, $data);
        echo json_encode([
            'purchase_id' => $purchase->getKey(),
            'connection_id' => $connectionId,
            'attempted_at' => $attemptedAt,
        ], JSON_THROW_ON_ERROR);
        PHP;

        $first = $this->confirmationProcess($script, $user, $import, $debit, $encodedData, $startAt);
        $second = $this->confirmationProcess($script, $user, $import, $credit, $encodedData, $startAt);

        $first->start();
        $second->start();
        $first->wait();
        $second->wait();

        $this->assertTrue($first->isSuccessful(), $first->getErrorOutput());
        $this->assertTrue($second->isSuccessful(), $second->getErrorOutput());

        $firstResult = json_decode($first->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $secondResult = json_decode($second->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertNotSame($firstResult['connection_id'], $secondResult['connection_id']);
        $this->assertLessThan(0.25, abs($firstResult['attempted_at'] - $secondResult['attempted_at']));
        $this->assertSame($firstResult['purchase_id'], $secondResult['purchase_id']);

        // Descarta a conexão herdada pelo processo pai antes de observar os commits dos filhos.
        $this->app['db']->purge();

        $this->assertDatabaseCount('card_purchases', 1);
        $this->assertDatabaseCount('card_installments', 1);
        $this->assertDatabaseCount('ledger_entries', 0);
        $this->assertSame(2, OfxImportItem::query()->where('review_status', 'confirmed')->count());
    }

    /**
     * @return array{User, BankStatementImport, OfxImportItem, OfxImportItem, array<string, int|string>}
     */
    private function pixCreditFixture(): array
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

        return [
            $user,
            BankStatementImport::query()->sole(),
            OfxImportItem::query()->where('direction', 'debit')->sole(),
            OfxImportItem::query()->where('direction', 'credit')->sole(),
            [
                'credit_card_id' => $card->getKey(),
                'category_id' => $category->getKey(),
                'planning_type' => 'ordinary',
                'installments_count' => 1,
                'first_due_on' => '2026-09-12',
            ],
        ];
    }

    private function confirmationProcess(
        string $script,
        User $user,
        BankStatementImport $import,
        OfxImportItem $item,
        string $encodedData,
        float $startAt,
    ): Process {
        $process = new Process([
            PHP_BINARY,
            '-r',
            $script,
            base_path(),
            (string) $user->getKey(),
            (string) $import->getKey(),
            (string) $item->getKey(),
            $encodedData,
            (string) $startAt,
        ], base_path());
        $process->setTimeout(20);

        return $process;
    }
}
