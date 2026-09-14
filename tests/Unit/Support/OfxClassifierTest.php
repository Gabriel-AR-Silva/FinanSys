<?php

namespace Tests\Unit\Support;

use App\Enums\OfxClassification;
use App\Support\OfxClassifier;
use App\Support\OfxTransaction;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class OfxClassifierTest extends TestCase
{
    public function test_pix_on_credit_pair_requires_human_validation(): void
    {
        $transactions = [
            new OfxTransaction('abc', 'CREDIT', CarbonImmutable::parse('2026-09-01'), '3.00', 'credit', 'Valor adicionado por Pix no Crédito', 'Pix no Crédito', 0, 'a'),
            new OfxTransaction('abc:reversal', 'DEBIT', CarbonImmutable::parse('2026-09-01'), '3.00', 'debit', 'Transferência Pix', 'Transferência Pix', 1, 'b'),
        ];

        $results = (new OfxClassifier)->classify($transactions, ['abc' => [0, 1]]);

        $this->assertSame(OfxClassification::CardCreditPixCandidate, $results[0]->classification);
        $this->assertTrue($results[0]->requiresUserValidation);
        $this->assertSame(OfxClassification::NeedsReview, $results[1]->classification);
        $this->assertTrue($results[1]->requiresUserValidation);
    }

    public function test_bank_direction_alone_never_becomes_income_or_expense(): void
    {
        $transaction = new OfxTransaction('x', 'CREDIT', CarbonImmutable::parse('2026-09-01'), '10.00', 'credit', 'Crédito bancário', null, 0, 'x');

        $result = (new OfxClassifier)->classify([$transaction])[0];

        $this->assertSame(OfxClassification::NeedsReview, $result->classification);
    }
}
