<?php

declare(strict_types=1);

namespace Seo\Service\Editorial\Rule;

class UnknownInDossierRule implements RuleInterface
{
    private float $threshold;

    public function __construct(float $threshold = 0.30)
    {
        $this->threshold = $threshold;
    }

    public function run(array $article, array $blocks): array
    {
        $raw = $article['research_dossier'] ?? '';
        if (!is_string($raw) || $raw === '') return [];
        $data = json_decode($raw, true);
        if (!is_array($data)) return [];

        $sections = ['facts', 'entities', 'benchmarks', 'comparisons',
            'counter_theses', 'quotes_cases', 'terms', 'sources'];

        $total = 0;
        $unknown = 0;
        $unknownPatterns = ['unknown', 'неизвестно', 'не известно', 'n/a', 'tbd', '???'];

        foreach ($sections as $sec) {
            if (empty($data[$sec]) || !is_array($data[$sec])) continue;
            foreach ($data[$sec] as $item) {
                if (!is_array($item)) continue;
                foreach ($item as $k => $v) {
                    if ($k === 'id' || $k === '_section') continue;
                    if (!is_string($v)) continue;
                    $s = trim(mb_strtolower($v));
                    if ($s === '') continue;
                    $total++;
                    foreach ($unknownPatterns as $p) {
                        if (mb_strpos($s, $p) !== false) {
                            $unknown++;
                            break;
                        }
                    }
                }
            }
        }

        if ($total === 0) return [];
        $ratio = $unknown / $total;
        if ($ratio < $this->threshold) return [];

        $pct = (int)round($ratio * 100);
        return [[
            'severity' => 'warn',
            'code'     => 'unknown_in_dossier',
            'message'  => "В досье много неизвестных значений: {$pct}% ({$unknown} из {$total})",
            'block_id' => null,
        ]];
    }
}
