<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

class OfxDateParser
{
    public function parse(string $value): CarbonImmutable
    {
        if (! preg_match('/^(\d{14})/', trim($value), $match)) {
            throw new InvalidArgumentException('Invalid OFX date.');
        }

        $date = CarbonImmutable::createFromFormat('YmdHis', $match[1], 'America/Sao_Paulo');

        if ($date === false) {
            throw new InvalidArgumentException('Invalid OFX date.');
        }

        return $date;
    }
}
