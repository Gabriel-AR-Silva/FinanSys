<?php

namespace App\Support;

use Carbon\CarbonImmutable;

readonly class OfxTransaction
{
    public function __construct(
        public ?string $externalId,
        public string $bankType,
        public CarbonImmutable $occurredAt,
        public string $amount,
        public string $direction,
        public string $description,
        public ?string $memo,
        public int $sourceIndex,
        public string $fingerprint,
    ) {}
}
