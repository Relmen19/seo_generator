<?php

declare(strict_types=1);

namespace Seo\Service\Editorial\Rule;

use Seo\Service\Editorial\TextExtractor;

class EmptyChartRule implements RuleInterface
{
    private const CHART_TYPES = [
        'chart', 'gauge_chart', 'gauge', 'radar_chart', 'radar',
        'score_rings', 'rings', 'heatmap', 'funnel',
        'stacked_area', 'spark_metrics', 'stats', 'timeline',
    ];

    private const DATA_KEYS = ['items', 'data', 'datasets', 'rings', 'axes', 'rows', 'columns'];

    public function run(array $article, array $blocks): array
    {
        $issues = [];
        foreach ($blocks as $b) {
            if (!in_array($b['type'] ?? '', self::CHART_TYPES, true)) continue;
            $blockId = isset($b['id']) ? (int)$b['id'] : null;
            $content = TextExtractor::blockContent($b);
            if ($this->hasData($content)) continue;
            $issues[] = [
                'severity' => 'error',
                'code'     => 'empty_chart',
                'message'  => "Блок-график без данных: {$b['type']} (блок #{$blockId})",
                'block_id' => $blockId,
            ];
        }
        return $issues;
    }

    private function hasData(array $content): bool
    {
        foreach (self::DATA_KEYS as $k) {
            if (!empty($content[$k]) && is_array($content[$k]) && count($content[$k]) > 0) {
                return true;
            }
        }
        return false;
    }
}
