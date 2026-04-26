<?php

declare(strict_types=1);

namespace Seo\Service\Editorial\Rule;

use Seo\Service\Editorial\TextExtractor;

class BrokenLinksRule implements RuleInterface
{
    private int $timeout;
    private int $maxLinks;

    public function __construct(int $timeout = 5, int $maxLinks = 30)
    {
        $this->timeout = $timeout;
        $this->maxLinks = $maxLinks;
    }

    public function run(array $article, array $blocks): array
    {
        $issues = [];
        $checked = 0;
        $cache = [];

        foreach ($blocks as $b) {
            if ($checked >= $this->maxLinks) break;
            $blockId = isset($b['id']) ? (int)$b['id'] : null;
            $content = TextExtractor::blockContent($b);
            $urls = $this->extractUrls($content);
            foreach ($urls as $url) {
                if ($checked >= $this->maxLinks) break;
                if (!isset($cache[$url])) {
                    $cache[$url] = $this->checkUrl($url);
                    $checked++;
                }
                $code = $cache[$url];
                if ($code === null) {
                    $issues[] = [
                        'severity' => 'warn',
                        'code'     => 'broken_link',
                        'message'  => "Ссылка не отвечает: {$url} (блок #{$blockId})",
                        'block_id' => $blockId,
                    ];
                } elseif ($code >= 400) {
                    $issues[] = [
                        'severity' => 'warn',
                        'code'     => 'broken_link',
                        'message'  => "Ссылка вернула {$code}: {$url} (блок #{$blockId})",
                        'block_id' => $blockId,
                    ];
                }
            }
        }
        return $issues;
    }

    private function extractUrls(array $content): array
    {
        $found = [];
        $walker = function ($v) use (&$walker, &$found) {
            if (is_array($v)) {
                foreach ($v as $vv) $walker($vv);
            } elseif (is_string($v)) {
                if (preg_match_all('#https?://[^\s"\'<>]+#u', $v, $m)) {
                    foreach ($m[0] as $u) $found[$u] = true;
                }
                if (preg_match_all('#href=["\']([^"\']+)["\']#u', $v, $m)) {
                    foreach ($m[1] as $u) {
                        if (preg_match('#^https?://#i', $u)) $found[$u] = true;
                    }
                }
            }
        };
        $walker($content);
        return array_keys($found);
    }

    private function checkUrl(string $url): ?int
    {
        if (!function_exists('curl_init')) return null;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_USERAGENT      => 'SeoGenerator-LinkChecker/1.0',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        curl_exec($ch);
        $err = curl_errno($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($err !== 0 || $code === 0) return null;
        return $code;
    }
}
