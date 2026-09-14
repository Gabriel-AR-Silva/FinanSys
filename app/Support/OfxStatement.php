<?php

namespace App\Support;

use Carbon\CarbonImmutable;

readonly class OfxStatement
{
    /**
     * @param  list<OfxTransaction>  $transactions
     */
    public function __construct(
        public string $institution,
        public ?string $institutionId,
        public string $currency,
        public string $accountHash,
        public string $accountSuffix,
        public string $accountType,
        public CarbonImmutable $periodStart,
        public CarbonImmutable $periodEnd,
        public ?string $ledgerBalance,
        public ?CarbonImmutable $ledgerBalanceAt,
        public array $transactions,
    ) {}
}
