<?php

namespace App\Support;

use InvalidArgumentException;

class OfxParser
{
    public const MAX_BYTES = 2097152;

    public const MAX_TRANSACTIONS = 5000;

    public function __construct(
        private OfxTextReader $reader,
        private OfxDateParser $dates,
        private OfxTransactionNormalizer $transactions,
    ) {}

    public function parse(string $contents): OfxStatement
    {
        if ($contents === '' || strlen($contents) > self::MAX_BYTES) {
            throw new InvalidArgumentException('Arquivo OFX vazio ou acima do limite permitido.');
        }

        if (! mb_check_encoding($contents, 'UTF-8')) {
            throw new InvalidArgumentException('O arquivo OFX deve estar em UTF-8.');
        }

        if (! str_contains(strtoupper($contents), 'OFXSGML') || ! str_contains(strtoupper($contents), 'VERSION:102')) {
            throw new InvalidArgumentException('Formato OFX não suportado.');
        }

        $statement = $this->reader->block($contents, 'STMTRS')
            ?? throw new InvalidArgumentException('Extrato bancário OFX não encontrado.');
        $account = $this->reader->block($statement, 'BANKACCTFROM')
            ?? throw new InvalidArgumentException('Conta bancária OFX não encontrada.');
        $list = $this->reader->block($statement, 'BANKTRANLIST')
            ?? throw new InvalidArgumentException('Lista de transações OFX não encontrada.');
        $transactionBlocks = $this->reader->blocks($list, 'STMTTRN');

        if (count($transactionBlocks) > self::MAX_TRANSACTIONS) {
            throw new InvalidArgumentException('O arquivo OFX excede o limite de transações.');
        }

        $accountId = $this->required($account, 'ACCTID');
        $normalizedTransactions = [];
        foreach ($transactionBlocks as $index => $block) {
            $normalizedTransactions[] = $this->transactions->normalize($block, $index);
        }

        $ledger = $this->reader->block($statement, 'LEDGERBAL');
        $fi = $this->reader->block($contents, 'FI');

        return new OfxStatement(
            institution: $fi === null ? 'Instituição não informada' : ($this->reader->value($fi, 'ORG') ?? 'Instituição não informada'),
            institutionId: $fi === null ? null : $this->reader->value($fi, 'FID'),
            currency: $this->reader->value($statement, 'CURDEF') ?? 'BRL',
            accountHash: hash('sha256', $accountId),
            accountSuffix: substr(preg_replace('/\D+/', '', $accountId) ?: $accountId, -4),
            accountType: $this->reader->value($account, 'ACCTTYPE') ?? 'UNKNOWN',
            periodStart: $this->dates->parse($this->required($list, 'DTSTART')),
            periodEnd: $this->dates->parse($this->required($list, 'DTEND')),
            ledgerBalance: $ledger === null ? null : $this->reader->value($ledger, 'BALAMT'),
            ledgerBalanceAt: $ledger === null || $this->reader->value($ledger, 'DTASOF') === null ? null : $this->dates->parse($this->reader->value($ledger, 'DTASOF')),
            transactions: $normalizedTransactions,
        );
    }

    private function required(string $contents, string $tag): string
    {
        return $this->reader->value($contents, $tag)
            ?? throw new InvalidArgumentException("Tag OFX obrigatória ausente: {$tag}.");
    }
}
