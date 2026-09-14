<?php

namespace App\Support;

class OfxTextReader
{
    public function block(string $contents, string $tag): ?string
    {
        return preg_match('/<'.preg_quote($tag, '/').'\b[^>]*>(.*?)<\/'.preg_quote($tag, '/').'>/si', $contents, $match)
            ? $match[1]
            : null;
    }
}
