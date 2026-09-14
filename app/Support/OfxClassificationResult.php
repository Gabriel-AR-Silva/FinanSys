<?php

namespace App\Support;

use App\Enums\OfxClassification;

readonly class OfxClassificationResult
{
    public function __construct(
        public int $sourceIndex,
        public OfxClassification $classification,
        public string $reason,
        public bool $requiresUserValidation = true,
    ) {}
}
