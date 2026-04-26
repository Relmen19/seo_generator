<?php

declare(strict_types=1);

namespace Seo\Service\Editorial\Rule;

use Seo\Service\Editorial\TextExtractor;

class EmptyChartRule implements RuleInterface
{
    /**
     * Map block_type → data keys, по которым проверяем наличие данных.
     * Источник правды — seo_block_types.json_schema (см. 020_block_type_schemas.sql).
     * Для каждого типа достаточно одного непустого ключа из списка.
     */
    private const TYPE_DATA_KEYS = [
        'chart'         => ['items', 'data'],
        'gauge_chart'   => ['items'],
        'gauge'         => ['items'],
        'radar_chart'   => ['metrics', 'items'],
        'radar'         => ['metrics', 'items'],
        'score_rings'   => ['rings', 'items'],
        'rings'         => ['rings', 'items'],
        'heatmap'       => ['rows', 'columns', 'data'],
        'funnel'        => ['items', 'stages'],
        'stacked_area'  => ['series', 'labels'],
        'spark_metrics' => ['items', 'metrics'],
        'stats'         => ['items', 'stats', 'metrics'],
        'timeline'      => ['items', 'events'],
    ];

    public function run(array $article, array $blocks): array
    {
        $issues = [];
        foreach ($blocks as $b) {
            $type = (string)($b['type'] ?? '');
            if (!isset(self::TYPE_DATA_KEYS[$type])) continue;
            $blockId = isset($b['id']) ? (int)$b['id'] : null;
            $content = TextExtractor::blockContent($b);
            if ($this->hasData($content, self::TYPE_DATA_KEYS[$type])) continue;
            $issues[] = [
                'severity' => 'error',
                'code'     => 'empty_chart',
                'message'  => "Блок-график без данных: {$type} (блок #{$blockId})",
                'block_id' => $blockId,
            ];
        }
        return $issues;
    }

    private function hasData(array $content, array $keys): bool
    {
        foreach ($keys as $k) {
            if (!empty($content[$k]) && is_array($content[$k]) && count($content[$k]) > 0) {
                return true;
            }
        }
        return false;
    }
}
