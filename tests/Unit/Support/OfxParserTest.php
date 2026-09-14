<?php

namespace Tests\Unit\Support;

use App\Support\OfxDateParser;
use App\Support\OfxParser;
use App\Support\OfxTextReader;
use App\Support\OfxTransactionNormalizer;
use PHPUnit\Framework\TestCase;

class OfxParserTest extends TestCase
{
    public function test_parses_anonymized_nubank_bank_statement(): void
    {
        $ofx = <<<'OFX'
OFXHEADER:100
DATA:OFXSGML
VERSION:102
SECURITY:NONE
ENCODING:UTF-8
CHARSET:NONE
COMPRESSION:NONE
<OFX>
<SIGNONMSGSRSV1><SONRS><FI><ORG>NU PAGAMENTOS S.A.</ORG><FID>260</FID></FI></SONRS></SIGNONMSGSRSV1>
<BANKMSGSRSV1><STMTTRNRS><STMTRS>
<CURDEF>BRL</CURDEF>
<BANKACCTFROM><BANKID>0260</BANKID><BRANCHID>1</BRANCHID><ACCTID>0001234-5</ACCTID><ACCTTYPE>CHECKING</ACCTTYPE></BANKACCTFROM>
<BANKTRANLIST><DTSTART>20260901000000[-3:BRT]</DTSTART><DTEND>20260902000000[-3:BRT]</DTEND>
<STMTTRN><TRNTYPE>CREDIT</TRNTYPE><DTPOSTED>20260901120000[-3:BRT]</DTPOSTED><TRNAMT>3.00</TRNAMT><FITID>fake-id</FITID><MEMO>Valor adicionado por Pix no Crédito</MEMO></STMTTRN>
<STMTTRN><TRNTYPE>DEBIT</TRNTYPE><DTPOSTED>20260901120000[-3:BRT]</DTPOSTED><TRNAMT>-3.00</TRNAMT><FITID>fake-id:reversal</FITID><MEMO>Transferência Pix</MEMO></STMTTRN>
</BANKTRANLIST>
<LEDGERBAL><BALAMT>1.00</BALAMT><DTASOF>20260902000000[-3:BRT]</DTASOF></LEDGERBAL>
</STMTRS></STMTTRNRS></BANKMSGSRSV1>
</OFX>
OFX;

        $reader = new OfxTextReader;
        $parser = new OfxParser($reader, new OfxDateParser, new OfxTransactionNormalizer($reader, new OfxDateParser));
        $statement = $parser->parse($ofx);

        $this->assertSame('NU PAGAMENTOS S.A.', $statement->institution);
        $this->assertSame('260', $statement->institutionId);
        $this->assertSame('2345', $statement->accountSuffix);
        $this->assertSame('1.00', $statement->ledgerBalance);
        $this->assertCount(2, $statement->transactions);
        $this->assertSame('credit', $statement->transactions[0]->direction);
        $this->assertSame('debit', $statement->transactions[1]->direction);
    }
}
