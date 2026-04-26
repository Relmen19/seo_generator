<?php

declare(strict_types=1);

namespace Seo\Service\Editorial;

class TextExtractor
{
    public static function blockContent(array $block): array
    {
        $c = $block['content'] ?? [];
        if (is_string($c)) {
            $decoded = json_decode($c, true);
            $c = is_array($decoded) ? $decoded : [];
        }
        return is_array($c) ? $c : [];
    }

    /**
     * Recursively collect human-readable text from block content.
     */
    public static function collectText(array $content): string
    {
        $out = [];
        self::walk($content, $out);
        $text = implode(' ', $out);
        $text = strip_tags($text);
        return preg_replace('/\s+/u', ' ', $text) ?? '';
    }

    private static function walk($node, array &$out): void
    {
        if (is_string($node)) {
            $node = trim($node);
            if ($node !== '') $out[] = $node;
            return;
        }
        if (is_array($node)) {
            foreach ($node as $v) {
                self::walk($v, $out);
            }
        }
    }

    public static function wordCount(string $text): int
    {
        $text = trim($text);
        if ($text === '') return 0;
        $tokens = preg_split('/\s+/u', $text) ?: [];
        return count(array_filter($tokens, fn($t) => $t !== ''));
    }
}
