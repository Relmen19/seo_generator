<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Util;

/**
 * Minimal inline markdown for richtext paragraphs/list items.
 * Supported: `code`, **bold**, *italic*, [text](url).
 * NOT a full markdown parser. Block-level constructs ignored.
 * XSS-safe: input is escaped, then markers are converted into safe HTML.
 */
class InlineMarkdown
{
    public static function render(string $s): string
    {
        if ($s === '') return '';

        // Tokenize first (extract code spans and links to placeholders),
        // then escape the remaining text, then restore.
        $tokens = [];
        $store = function (string $html) use (&$tokens): string {
            $tokens[] = $html;
            return "\x01TOK" . (count($tokens) - 1) . "\x02";
        };

        // Inline code: `…` (no backticks inside)
        $s = preg_replace_callback('/`([^`\n]+)`/u', function ($m) use ($store) {
            return $store('<code class="lf-ic">' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</code>');
        }, $s) ?? $s;

        // Links: [text](url) — url restricted to http(s)/mailto/relative
        $s = preg_replace_callback('/\[([^\]\n]+)\]\(([^)\s]+)\)/u', function ($m) use ($store) {
            $text = $m[1];
            $url  = $m[2];
            if (!preg_match('#^(https?://|mailto:|/|\#)#i', $url)) {
                $url = '#';
            }
            return $store(
                '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" rel="nofollow noopener" target="_blank">'
                . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
                . '</a>'
            );
        }, $s) ?? $s;

        // Bold: **text**
        $s = preg_replace_callback('/\*\*([^*\n]+)\*\*/u', function ($m) use ($store) {
            return $store('<strong>' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</strong>');
        }, $s) ?? $s;

        // Italic: *text*  (single asterisks, after bold so no conflict)
        $s = preg_replace_callback('/(?<![*\w])\*([^*\n]+)\*(?!\w)/u', function ($m) use ($store) {
            return $store('<em>' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</em>');
        }, $s) ?? $s;

        // Escape rest
        $s = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        // Restore tokens
        $s = preg_replace_callback('/\x01TOK(\d+)\x02/', function ($m) use (&$tokens) {
            $i = (int)$m[1];
            return $tokens[$i] ?? '';
        }, $s) ?? $s;

        return $s;
    }
}
