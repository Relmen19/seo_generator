<?php

declare(strict_types=1);

namespace Seo\Service\Editorial\Rule;

use Seo\Service\Editorial\TextExtractor;

class RepetitionRule implements RuleInterface
{
    private int $n;
    private int $minRepeats;

    public function __construct(int $n = 4, int $minRepeats = 3)
    {
        $this->n = $n;
        $this->minRepeats = $minRepeats;
    }

    public function run(array $article, array $blocks): array
    {
        $perBlockGrams = [];
        foreach ($blocks as $b) {
            $blockId = isset($b['id']) ? (int)$b['id'] : null;
            $text = TextExtractor::collectText(TextExtractor::blockContent($b));
            $tokens = $this->tokenize($text);
            if (count($tokens) < $this->n) continue;
            $perBlockGrams[$blockId] = $this->ngrams($tokens, $this->n);
        }

        $globalCounts = [];
        foreach ($perBlockGrams as $blockId => $grams) {
            foreach (array_unique($grams) as $g) {
                $globalCounts[$g] = ($globalCounts[$g] ?? 0) + 1;
            }
        }

        $issues = [];
        $reported = [];
        foreach ($globalCounts as $gram => $cnt) {
            if ($cnt < $this->minRepeats) continue;
            if (isset($reported[$gram])) continue;
            $reported[$gram] = true;
            $issues[] = [
                'severity' => 'warn',
                'code'     => 'repetition',
                'message'  => "Повторяющаяся фраза в {$cnt} блоках: «{$gram}»",
                'block_id' => null,
            ];
            if (count($issues) >= 20) break;
        }
        return $issues;
    }

    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '';
        $tokens = preg_split('/\s+/u', trim($text)) ?: [];
        $tokens = array_values(array_filter($tokens, fn($t) => mb_strlen($t) >= 3));
        return $tokens;
    }

    private function ngrams(array $tokens, int $n): array
    {
        $out = [];
        $count = count($tokens) - $n + 1;
        for ($i = 0; $i < $count; $i++) {
            $out[] = implode(' ', array_slice($tokens, $i, $n));
        }
        return $out;
    }
}
