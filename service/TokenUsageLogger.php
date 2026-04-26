<?php

declare(strict_types=1);

namespace Seo\Service;

use Seo\Database;
use Throwable;

class TokenUsageLogger
{
    public const CATEGORY_PROFILE_CREATE     = 'profile_create';
    public const CATEGORY_PROFILE_BRIEF      = 'profile_brief';
    public const CATEGORY_TEMPLATE_CREATE    = 'template_create';
    public const CATEGORY_TEMPLATE_REVIEW    = 'template_review';
    public const CATEGORY_ARTICLE_CREATE     = 'article_create';
    public const CATEGORY_ARTICLE_RESEARCH   = 'article_research';
    public const CATEGORY_ARTICLE_OUTLINE      = 'article_outline';
    public const CATEGORY_ARTICLE_ILLUSTRATION = 'article_illustration';
    public const CATEGORY_ARTICLE_FIXER        = 'article_fixer';
    public const CATEGORY_ARTICLE_AI_REVIEW    = 'article_ai_review';
    public const CATEGORY_TELEGRAM_AGGREGATE   = 'telegram_aggregate';

    public const CATEGORIES = [
        self::CATEGORY_PROFILE_CREATE,
        self::CATEGORY_PROFILE_BRIEF,
        self::CATEGORY_TEMPLATE_CREATE,
        self::CATEGORY_TEMPLATE_REVIEW,
        self::CATEGORY_ARTICLE_CREATE,
        self::CATEGORY_ARTICLE_RESEARCH,
        self::CATEGORY_ARTICLE_OUTLINE,
        self::CATEGORY_ARTICLE_ILLUSTRATION,
        self::CATEGORY_ARTICLE_FIXER,
        self::CATEGORY_ARTICLE_AI_REVIEW,
        self::CATEGORY_TELEGRAM_AGGREGATE,
    ];

    /**
     * USD per 1M tokens — rough public pricing, per (prompt, completion).
     * Unknown models get 0, which keeps logging working without cost.
     */
    private const PRICE_PER_MTOKENS = [
        'gpt-4o'              => [5.00, 15.00],
        'gpt-4o-2024-08-06'   => [2.50, 10.00],
        'gpt-4o-mini'         => [0.15, 0.60],
        'gpt-4-turbo'         => [10.00, 30.00],
        'gpt-4-turbo-preview' => [10.00, 30.00],
        'gpt-4'               => [30.00, 60.00],
        'gpt-4.1'             => [2.00, 8.00],
        'gpt-4.1-mini'        => [0.40, 1.60],
        'gpt-4.1-nano'        => [0.10, 0.40],
        'gpt-3.5-turbo'       => [0.50, 1.50],
        'o1-mini'             => [3.00, 12.00],
        'o1-preview'          => [15.00, 60.00],
    ];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Record a single token-usage row.
     *
     * @param array $context  {profile_id?, category, operation?, entity_type?, entity_id?}
     * @param array $usage    {prompt_tokens?, completion_tokens?, total_tokens?}
     * @param string|null $model
     */
    public function log(array $context, array $usage, ?string $model = null): void
    {
        $category = (string)($context['category'] ?? '');
        if ($category === '') return;

        $prompt     = (int)($usage['prompt_tokens']     ?? 0);
        $completion = (int)($usage['completion_tokens'] ?? 0);
        $total      = (int)($usage['total_tokens']      ?? ($prompt + $completion));
        if ($prompt === 0 && $completion === 0 && $total === 0) return;

        $cost = $this->estimateCost($model, $prompt, $completion);

        try {
            $this->db->insert('seo_token_usage', [
                'profile_id'        => isset($context['profile_id']) && $context['profile_id'] !== null
                    ? (int)$context['profile_id'] : null,
                'category'          => $category,
                'operation'         => $context['operation']   ?? null,
                'entity_type'       => $context['entity_type'] ?? null,
                'entity_id'         => isset($context['entity_id']) && $context['entity_id'] !== null
                    ? (int)$context['entity_id'] : null,
                'model'             => $model,
                'prompt_tokens'     => $prompt,
                'completion_tokens' => $completion,
                'total_tokens'      => $total,
                'cost_usd'          => $cost,
            ]);
        } catch (Throwable $e) {
            // Never let logging break a GPT call.
            error_log('[TokenUsageLogger] insert failed: ' . $e->getMessage());
        }
    }

    public function estimateCost(?string $model, int $promptTokens, int $completionTokens): float
    {
        if ($model === null || $model === '') return 0.0;
        $prices = self::PRICE_PER_MTOKENS[$model] ?? null;
        if ($prices === null) {
            // Try prefix match (e.g. gpt-4o-2024-05-13 → gpt-4o)
            foreach (self::PRICE_PER_MTOKENS as $key => $p) {
                if (strpos($model, $key) === 0) { $prices = $p; break; }
            }
        }
        if ($prices === null) return 0.0;
        [$pIn, $pOut] = $prices;
        return round(($promptTokens * $pIn + $completionTokens * $pOut) / 1000000, 6);
    }

    /**
     * Aggregated usage for a profile: totals + per-category breakdown + recent rows.
     */
    public function summaryForProfile(int $profileId, int $recentLimit = 20): array
    {
        $byCat = $this->db->fetchAll(
            "SELECT category,
                    COUNT(*)                AS calls,
                    SUM(prompt_tokens)      AS prompt_tokens,
                    SUM(completion_tokens)  AS completion_tokens,
                    SUM(total_tokens)       AS total_tokens,
                    SUM(cost_usd)           AS cost_usd,
                    MAX(created_at)         AS last_at
             FROM seo_token_usage
             WHERE profile_id = :pid
             GROUP BY category",
            [':pid' => $profileId]
        );

        $categories = [];
        $totals = ['calls' => 0, 'prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0, 'cost_usd' => 0.0];
        $byCatMap = [];
        foreach ($byCat as $row) {
            $byCatMap[$row['category']] = [
                'calls'             => (int)$row['calls'],
                'prompt_tokens'     => (int)$row['prompt_tokens'],
                'completion_tokens' => (int)$row['completion_tokens'],
                'total_tokens'      => (int)$row['total_tokens'],
                'cost_usd'          => (float)$row['cost_usd'],
                'last_at'           => $row['last_at'],
            ];
            $totals['calls']             += (int)$row['calls'];
            $totals['prompt_tokens']     += (int)$row['prompt_tokens'];
            $totals['completion_tokens'] += (int)$row['completion_tokens'];
            $totals['total_tokens']      += (int)$row['total_tokens'];
            $totals['cost_usd']          += (float)$row['cost_usd'];
        }
        foreach (self::CATEGORIES as $c) {
            $categories[$c] = $byCatMap[$c] ?? [
                'calls' => 0, 'prompt_tokens' => 0, 'completion_tokens' => 0,
                'total_tokens' => 0, 'cost_usd' => 0.0, 'last_at' => null,
            ];
        }

        $recent = $this->db->fetchAll(
            "SELECT id, category, operation, entity_type, entity_id, model,
                    prompt_tokens, completion_tokens, total_tokens, cost_usd, created_at
             FROM seo_token_usage
             WHERE profile_id = :pid
             ORDER BY id DESC
             LIMIT " . (int)$recentLimit,
            [':pid' => $profileId]
        );

        return [
            'profile_id' => $profileId,
            'totals'     => $totals,
            'categories' => $categories,
            'recent'     => $recent,
        ];
    }
}
