<?php

declare(strict_types=1);

namespace App\Services;

class MarkdownParserService
{
    public function extractTitle(string $content, string $fallback = 'Documento sem título'): string
    {
        if (preg_match('/^#\s+(.+)$/m', $content, $matches) === 1) {
            return trim($matches[1]);
        }

        return $fallback;
    }
}
