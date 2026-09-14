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

    public function value(string $contents, string $tag): ?string
    {
        $pattern = '/<'.preg_quote($tag, '/').'\b[^>]*>\s*([^<\r\n]*)/i';

        if (! preg_match($pattern, $contents, $match)) {
            return null;
        }

        $value = trim($match[1]);

        return $value === '' ? null : $value;
    }
}
