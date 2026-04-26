<?php

declare(strict_types=1);

namespace Seo\Service;

use RuntimeException;

/**
 * Brave Search API wrapper. Activated when BRAVE_SEARCH_API_KEY is set.
 * Falls back to disabled() = true if no key — caller should detect and skip.
 */
class WebSearchClient
{
    private string $apiKey;
    private int $timeout;

    public function __construct(?string $apiKey = null, int $timeout = 8)
    {
        $this->apiKey = $apiKey ?? (defined('BRAVE_SEARCH_API_KEY') ? BRAVE_SEARCH_API_KEY : '');
        $this->timeout = $timeout;
    }

    public function disabled(): bool
    {
        return $this->apiKey === '';
    }

    /**
     * Returns array of {title, url, description} entries (max $count).
     */
    public function search(string $query, int $count = 5): array
    {
        if ($this->disabled()) return [];
        $count = max(1, min($count, 10));

        $url = 'https://api.search.brave.com/res/v1/web/search?'
            . http_build_query(['q' => $query, 'count' => $count, 'safesearch' => 'moderate']);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'X-Subscription-Token: ' . $this->apiKey,
            ],
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $code >= 400) {
            throw new RuntimeException("WebSearch: HTTP {$code} {$err}");
        }
        $data = json_decode((string)$body, true);
        if (!is_array($data)) return [];

        $out = [];
        $items = $data['web']['results'] ?? [];
        if (!is_array($items)) return [];
        foreach ($items as $r) {
            if (!is_array($r)) continue;
            $out[] = [
                'title'       => (string)($r['title'] ?? ''),
                'url'         => (string)($r['url'] ?? ''),
                'description' => strip_tags((string)($r['description'] ?? '')),
            ];
            if (count($out) >= $count) break;
        }
        return $out;
    }
}
