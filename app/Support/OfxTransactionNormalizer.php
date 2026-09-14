<?php

namespace App\Support;

use InvalidArgumentException;

class OfxTransactionNormalizer
{
    public function __construct(
        private OfxTextReader $reader,
        private OfxDateParser $dates,
    ) {}

    public function normalize(string $block, int $index): OfxTransaction
    {
        $rawAmount = $this->required($block, 'TRNAMT');
        $amount = $this->normalizeAmount($rawAmount);
        $direction = str_starts_with($rawAmount, '-') ? 'debit' : 'credit';
        $memo = $this->reader->value($block, 'MEMO');
        $name = $this->reader->value($block, 'NAME');
        $description = $memo ?? $name ?? 'Movimentação OFX';
        $externalId = $this->reader->value($block, 'FITID');
        $occurredAt = $this->dates->parse($this->required($block, 'DTPOSTED'));
        $bankType = strtoupper($this->reader->value($block, 'TRNTYPE') ?? 'OTHER');
        $fingerprint = hash('sha256', implode('|', [
            $externalId ?? '',
            $occurredAt->format('c'),
            $amount,
            $direction,
            mb_strtolower($description),
        ]));

        return new OfxTransaction(
            externalId: $externalId,
            bankType: $bankType,
            occurredAt: $occurredAt,
            amount: $amount,
            direction: $direction,
            description: $description,
            memo: $memo,
            sourceIndex: $index,
            fingerprint: $fingerprint,
        );
    }

    private function required(string $block, string $tag): string
    {
        return $this->reader->value($block, $tag)
            ?? throw new InvalidArgumentException("Missing OFX tag: {$tag}.");
    }

    private function normalizeAmount(string $amount): string
    {
        $normalized = ltrim(trim($amount), '+-');

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized)) {
            throw new InvalidArgumentException('Invalid OFX amount.');
        }

        [$integer, $decimal] = array_pad(explode('.', $normalized, 2), 2, '');

        return $integer.'.'.str_pad($decimal, 2, '0');
    }
}
