<?php

declare(strict_types=1);

namespace Seo\Service\Editorial\Rule;

use Seo\Service\Editorial\TextExtractor;

class BannedPhrasesRule implements RuleInterface
{
    /** @var string[] */
    private array $phrases;

    public function __construct(array $phrases = null)
    {
        $this->phrases = $phrases ?? [
            'в современном мире',
            'на сегодняшний день',
            'не секрет',
            'играет важную роль',
            'играет ключевую роль',
            'трудно переоценить',
            'уникальный',
            'революционный',
            'инновационный',
            'не за горами',
            'в наше время',
            'in today\'s world',
            'cutting-edge',
            'game-changing',
        ];
    }

    public function run(array $article, array $blocks): array
    {
        // phrase => [block_id, ...]
        $hits = [];
        foreach ($blocks as $b) {
            $blockId = isset($b['id']) ? (int)$b['id'] : null;
            $content = TextExtractor::blockContent($b);
            $text = mb_strtolower(TextExtractor::collectText($content));
            if ($text === '') continue;
            foreach ($this->phrases as $p) {
                $needle = mb_strtolower($p);
                if (mb_strpos($text, $needle) !== false) {
                    if (!isset($hits[$p])) $hits[$p] = [];
                    if ($blockId !== null) $hits[$p][] = $blockId;
                }
            }
        }

        $issues = [];
        foreach ($hits as $phrase => $blockIds) {
            $blockIds = array_values(array_unique($blockIds));
            $count = count($blockIds);
            $first = $blockIds[0] ?? null;
            $list  = $count > 1 ? " (блоки: " . implode(', ', $blockIds) . ")" : " (блок #{$first})";
            $issues[] = [
                'severity' => 'info',
                'code'     => 'banned_phrase',
                'message'  => "Штамп «{$phrase}» в {$count} " . ($count === 1 ? 'блоке' : 'блоках') . $list,
                'block_id' => $count === 1 ? $first : null,
            ];
        }
        return $issues;
    }
}
