<?php

declare(strict_types=1);

namespace Seo\Service;

use Seo\Database;

/**
 * Aggregates seo_token_usage rows in category=article_research, joined to
 * the article's profile.research_strategy, into a per-strategy cost summary.
 */
class CostReportService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function build(int $days = 30): array
    {
        if ($days < 1) $days = 30;

        $perArticle = $this->db->fetchAll(
            "SELECT u.entity_id AS article_id,
                    COALESCE(p.research_strategy, 'unknown') AS strategy,
                    SUM(u.prompt_tokens)     AS prompt_tokens,
                    SUM(u.completion_tokens) AS completion_tokens,
                    SUM(u.total_tokens)      AS total_tokens,
                    SUM(u.cost_usd)          AS cost_usd
               FROM seo_token_usage u
               LEFT JOIN seo_articles a ON a.id = u.entity_id
               LEFT JOIN seo_site_profiles p ON p.id = a.profile_id
              WHERE u.category = 'article_research'
                AND u.entity_type = 'article'
                AND u.created_at >= (NOW() - INTERVAL ? DAY)
              GROUP BY u.entity_id, p.research_strategy",
            [$days]
        );

        $byStrategy = [];
        foreach ($perArticle as $r) {
            $s = (string)$r['strategy'];
            if (!isset($byStrategy[$s])) {
                $byStrategy[$s] = ['articles' => 0, 'total_tokens' => 0, 'total_cost' => 0.0];
            }
            $byStrategy[$s]['articles']++;
            $byStrategy[$s]['total_tokens'] += (int)$r['total_tokens'];
            $byStrategy[$s]['total_cost']   += (float)$r['cost_usd'];
        }
        foreach ($byStrategy as &$row) {
            $n = max(1, $row['articles']);
            $row['avg_tokens'] = (int)round($row['total_tokens'] / $n);
            $row['avg_cost']   = round($row['total_cost'] / $n, 6);
        }
        unset($row);
        ksort($byStrategy);

        $byOperation = $this->db->fetchAll(
            "SELECT operation,
                    COUNT(*)               AS calls,
                    SUM(prompt_tokens)     AS prompt_tokens,
                    SUM(completion_tokens) AS completion_tokens,
                    SUM(total_tokens)      AS total_tokens,
                    SUM(cost_usd)          AS cost_usd
               FROM seo_token_usage
              WHERE category = 'article_research'
                AND created_at >= (NOW() - INTERVAL ? DAY)
              GROUP BY operation
              ORDER BY total_tokens DESC",
            [$days]
        );

        return [
            'days'         => $days,
            'by_strategy'  => $byStrategy,
            'by_operation' => array_map(fn($r) => [
                'operation'         => $r['operation'] ?? null,
                'calls'             => (int)$r['calls'],
                'prompt_tokens'     => (int)$r['prompt_tokens'],
                'completion_tokens' => (int)$r['completion_tokens'],
                'total_tokens'      => (int)$r['total_tokens'],
                'cost_usd'          => (float)$r['cost_usd'],
            ], $byOperation),
            'comparison'   => $this->compareStrategies($byStrategy),
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function compareStrategies(array $byStrategy): array
    {
        $base = $byStrategy['single']['avg_cost'] ?? null;
        $out = [];
        foreach (['split', 'split_search'] as $variant) {
            if (!isset($byStrategy[$variant]) || $base === null || $base <= 0) {
                $out[$variant . '_vs_single_pct'] = null;
                continue;
            }
            $delta = ($byStrategy[$variant]['avg_cost'] - $base) / $base * 100;
            $out[$variant . '_vs_single_pct'] = round($delta, 1);
        }
        return $out;
    }
}
