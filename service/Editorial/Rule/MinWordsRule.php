<?php

declare(strict_types=1);

namespace Seo\Service\Editorial\Rule;

use Seo\Service\Editorial\TextExtractor;

class MinWordsRule implements RuleInterface
{
    private int $minWords;

    public function __construct(int $minWords = 800)
    {
        $this->minWords = $minWords;
    }

    public function run(array $article, array $blocks): array
    {
        $total = 0;
        foreach ($blocks as $b) {
            if (!(int)($b['is_visible'] ?? 1)) continue;
            $content = TextExtractor::blockContent($b);
            $total += TextExtractor::wordCount(TextExtractor::collectText($content));
        }
        if ($total >= $this->minWords) return [];
        return [[
            'severity' => 'warn',
            'code'     => 'min_words',
            'message'  => "Статья короче минимума: {$total} слов (требуется {$this->minWords})",
            'block_id' => null,
        ]];
    }
}
