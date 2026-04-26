<?php

declare(strict_types=1);

namespace Seo\Service;

use Seo\Enum\ArticlePrompt;
use Seo\Service\ArticleResearchService;

class PromptBuilder {

    private ?array $siteProfile = null;
    private array $blockTypeHints = [];

    public function setSiteProfile(?array $profile): self {
        $this->siteProfile = $profile;
        return $this;
    }

    public function setBlockTypeHints(array $hints): self {
        $this->blockTypeHints = $hints;
        return $this;
    }

    public function buildBlockPrompt(array $article, array $templateBlock, array $articleBlock = [],
                                     ?string $systemPrompt = null, array $allBlockTypes = [],
                                     ?array $outlineSection = null, array $previousSummaries = []): array {
        $system = $this->buildSystemMessage($systemPrompt, $article);
        $dossierIdx = $this->dossierIndex($article);
        $user   = $this->buildBlockUserMessage($templateBlock, $articleBlock, $allBlockTypes, $outlineSection, $previousSummaries, $dossierIdx);

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ];
    }

    public function buildMetaPrompt(array $article, array $templateBlocks): array {
        $persona = $this->resolvePersona();
        $system = $persona . ArticlePrompt::META_PLAN_INSTRUCTION;

        // ── Intent context for meta ──
        if (!empty($article['_intent_tone'])) {
            $system .= "\n\nТОН СТАТЬИ (учти при написании meta и plan): " . $article['_intent_tone'];
        }

        $user = "Тема: {$article['title']}\n";
        if (!empty($article['slug']))     $user .= "Slug: {$article['slug']}\n";
        if (!empty($article['keywords'])) $user .= "Ключевые слова: {$article['keywords']}\n";

        $user .= $this->buildResearchSection($article, "Опирайся на досье ниже — план должен покрывать его факты, не выдумывай вне досье.");

        if (!empty($templateBlocks)) {
            $user .= "\nБлоки шаблона:\n";
            foreach ($templateBlocks as $b) {
                $user .= "{$b['sort_order']}. [{$b['type']}] " . ($b['name'] ?? $b['type']) . "\n";
            }
        }

        $user .= "\n" . sprintf(ArticlePrompt::META_USER_PLAN_SUFFIX, $article['title']);

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ];
    }


    public function buildFullArticlePrompt(array $article, array $templateBlocks, ?string $systemPrompt = null): array {
        $system = $this->buildSystemMessage($systemPrompt, $article);
        $system .= ArticlePrompt::FULL_ARTICLE_FORMAT;

        $user = "Статья: «{$article['title']}»\n";
        if (!empty($article['keywords']))     $user .= "Ключевые слова: {$article['keywords']}\n";
        if (!empty($article['article_plan'])) $user .= "План: {$article['article_plan']}\n";
        $user .= "ВСЕ блоки — строго по теме «{$article['title']}»!\n\n";

        $total = count($templateBlocks);
        foreach ($templateBlocks as $i => $tb) {
            $config = $this->decodeConfig($tb['config'] ?? null);
            $type   = $tb['type'] ?? 'unknown';
            $name   = $tb['name'] ?? $type;
            $hint   = $config['hint'] ?? '';

            $user .= "block_{$i}: [{$type}] {$name}\n";

            // Позиционный контекст (компактно)
            $nav = [];
            if ($i > 0) $nav[] = "после [{$templateBlocks[$i-1]['type']}]";
            if ($i < $total - 1) $nav[] = "перед [{$templateBlocks[$i+1]['type']}]";
            if ($nav) $user .= "  Контекст: " . implode(', ', $nav) . "\n";

            if ($hint) $user .= "  Hint: {$hint}\n";

            // Поля — компактная строка
            $fieldParts = [];
            if (!empty($config['fields']))       $fieldParts[] = "fields: " . implode(', ', $config['fields']);
            if (!empty($config['items_fields']))  $fieldParts[] = "items: " . implode(', ', $config['items_fields']);
            if (!empty($config['chart_types']))   $fieldParts[] = "chart: " . implode('|', $config['chart_types']);
            if ($fieldParts) $user .= "  " . implode(' | ', $fieldParts) . "\n";

            // Критические предупреждения (компактно)
            $user .= $this->getTypeWarning($type);
            $user .= "\n";
        }

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ];
    }

    private function buildSystemMessage(?string $templateSystemPrompt, array $article): string {
        $system = !empty($templateSystemPrompt)
            ? $templateSystemPrompt
            : $this->resolvePersona();

        $gptRules = $this->siteProfile['gpt_rules'] ?? null;

        $system .= "\n\n" . ArticlePrompt::SYSTEM_REQUIREMENTS;

        if (!empty($gptRules)) {
            $system .= "5. " . $gptRules . "\n";
        }

        $system .= "\nСтатья: «{$article['title']}»";

        if (!empty($article['keywords']))        $system .= "\nКлючи: {$article['keywords']}";
        if (!empty($article['article_plan']))    $system .= "\nПлан: {$article['article_plan']}";
        if (!empty($article['meta_description'])) $system .= "\nОписание: {$article['meta_description']}";

        $system .= $this->buildResearchSection($article, "Используй ТОЛЬКО факты, цифры и сравнения из досье. Если факта в досье нет — не выдумывай.");

        // ── Intent context injection ──
        // Если статья привязана к кластеру с интентом, добавляем тон и стиль
        if (!empty($article['_intent_tone'])) {
            $system .= "\n\nТОН И СТРУКТУРА (по интенту):\n" . $article['_intent_tone'];
        }
        if (!empty($article['_intent_open'])) {
            $system .= "\nПРИМЕР ОТКРЫТИЯ СТАТЬИ: " . $article['_intent_open'];
        }

        return $system;
    }

    private function dossierIndex(array $article): array {
        $raw = $article['research_dossier'] ?? null;
        if ($raw === null || trim((string)$raw) === '') return [];
        $decoded = is_array($raw) ? $raw : json_decode((string)$raw, true);
        if (!is_array($decoded)) return [];
        return ArticleResearchService::indexById($decoded);
    }

    private function buildBlockUserMessage(array $templateBlock, array $articleBlock, array $allBlockTypes, ?array $outlineSection = null, array $previousSummaries = [], array $dossierIdx = []): string {
        $config = $this->decodeConfig($templateBlock['config'] ?? null);
        $type   = $templateBlock['type'] ?? 'unknown';
        $name   = $templateBlock['name'] ?? $type;
        $hint   = $config['hint'] ?? '';
        $fields = $config['fields'] ?? [];

        $hasExistingContent = false;
        $current = $articleBlock['content'] ?? null;
        if ($current !== null && $current !== '[]' && $current !== '{}') {
            $decoded = is_string($current) ? json_decode($current, true) : $current;
            $hasExistingContent = !empty($decoded);
        }

        $hasGptPrompt = !empty($articleBlock['gpt_prompt']);

        // Если есть текущий контент и доп. инструкции — режим модификации
        if ($hasExistingContent && $hasGptPrompt) {
            $user = "Измени контент блока [{$type}] «{$name}» согласно инструкциям ниже.\n";
            $user .= "ВАЖНО: Верни ВЕСЬ блок целиком (не только изменённую часть). "
                    . "Сохрани существующую структуру и данные, изменяя ТОЛЬКО то, что указано в инструкциях. "
                    . "НЕ добавляй новые элементы верхнего уровня, если это явно не запрошено.\n\n";
        } else {
            $user = sprintf(ArticlePrompt::BLOCK_USER_GENERATE, $type, $name) . "\n";
        }

        if ($hint)            $user .= "Описание: {$hint}\n";
        if (!empty($fields))  $user .= "Поля JSON: " . implode(', ', $fields) . "\n";

        // Outline section context — где блок в нарративе и что должен сказать
        if ($outlineSection !== null) {
            $h2    = (string)($outlineSection['h2_title'] ?? '');
            $role  = (string)($outlineSection['narrative_role'] ?? '');
            $brief = (string)($outlineSection['content_brief'] ?? '');
            $facts = $outlineSection['source_facts'] ?? [];
            $user .= "\n── Секция статьи (контекст из outline) ──\n";
            if ($h2 !== '')    $user .= "H2: {$h2}\n";
            if ($role !== '')  $user .= "Роль в нарративе: {$role}\n";
            if ($brief !== '') $user .= "Что должна сказать секция: {$brief}\n";
            if (is_array($facts) && !empty($facts)) {
                $user .= "Опорные факты из досье (ОБЯЗАТЕЛЬНО используй каждый):\n";
                foreach ($facts as $f) {
                    $id = trim((string)$f);
                    if ($id === '') continue;
                    if (isset($dossierIdx[$id])) {
                        $user .= "  - " . ArticleResearchService::renderItemLine($dossierIdx[$id]) . "\n";
                    } else {
                        $user .= "  - {$id}\n";
                    }
                }
            }
            $user .= "Блок не существует сам по себе — он часть сквозного рассказа. "
                  . "Не повторяй то, что должно быть в соседних секциях.\n";
        }

        if (!empty($previousSummaries)) {
            $user .= "\n── Что уже сказано в предыдущих секциях (НЕ повторяй; можешь ссылаться: «как мы видели выше…») ──\n";
            foreach ($previousSummaries as $ps) {
                $h2  = trim((string)($ps['h2'] ?? ''));
                $sum = trim((string)($ps['summary'] ?? ''));
                if ($h2 === '' && $sum === '') continue;
                $user .= "• " . ($h2 !== '' ? "[{$h2}] " : '') . $sum . "\n";
            }
        }

        if ($type === 'richtext') $user .= $this->getRichtextHint($config);

        // Доп. поля (компактно)
        $extra = [];
        if (!empty($config['items_fields']))  $extra[] = "items: " . implode(', ', $config['items_fields']);
        if (!empty($config['block_types']))   $extra[] = "подблоки: " . implode(', ', $config['block_types']);
        if (!empty($config['chart_types']))   $extra[] = "chart: " . implode('|', $config['chart_types']);
        if ($extra) $user .= implode(' | ', $extra) . "\n";

        if ($hasGptPrompt) $user .= "Инструкции по изменению: {$articleBlock['gpt_prompt']}\n";
        if (!empty($allBlockTypes)) $user .= "Структура: " . implode(' → ', $allBlockTypes) . "\n";

        // Текущий контент
        if ($hasExistingContent) {
            $user .= "\nТекущий контент (модифицируй его согласно инструкциям):\n"
                    . json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        }

        $user .= $this->getTypeWarning($type);
        $user .= "\n" . ArticlePrompt::BLOCK_USER_RESPONSE;

        return $user;
    }

    private function getRichtextHint(array $config): string {
        // Default = full universal set: works across any niche (tech/cooking/finance/health/etc).
        // Templates can narrow via config.block_types if needed.
        $defaultAllowed = [
            'paragraph', 'heading', 'list', 'highlight', 'quote',
            'callout', 'figure', 'table', 'footnote',
            'steps', 'stat', 'pros_cons', 'definition',
            'code',
        ];
        $allowed = $config['block_types'] ?? $defaultAllowed;
        $hasLongform = (bool)array_intersect($allowed, [
            'callout', 'code', 'figure', 'table', 'footnote',
            'steps', 'stat', 'pros_cons', 'definition',
        ]);

        $format = $hasLongform ? ArticlePrompt::RICHTEXT_FORMAT : ArticlePrompt::RICHTEXT_FORMAT_BASIC;
        $rules  = $hasLongform ? ArticlePrompt::RICHTEXT_RULES  : ArticlePrompt::RICHTEXT_RULES_BASIC;

        return "\nФормат richtext: " . $format . "\n"
            . "Допустимые type: " . implode(', ', $allowed) . "\n"
            . $rules . "\n"
            . ArticlePrompt::RICHTEXT_NO_WRAP;
    }

    private function getTypeWarning(string $type): string {
        if (!empty($this->blockTypeHints[$type])) {
            return "  ⚠ {$this->blockTypeHints[$type]}\n";
        }
        return '';
    }

    private function resolvePersona(): string {
        if (!empty($this->siteProfile['gpt_persona'])) {
            return $this->siteProfile['gpt_persona'];
        }
        return ArticlePrompt::DEFAULT_PERSONA;
    }

    /**
     * Returns compact text rendition of the JSON dossier with id-prefixed
     * lines, or "" if the dossier is missing/invalid.
     */
    private function buildResearchSection(array $article, string $usageRule): string {
        $raw = $article['research_dossier'] ?? null;
        if ($raw === null || trim((string)$raw) === '') return '';

        $decoded = is_array($raw) ? $raw : json_decode((string)$raw, true);
        if (!is_array($decoded)) return '';

        $angle = trim((string)($decoded['angle'] ?? ''));
        $idx = ArticleResearchService::indexById($decoded);
        if (empty($idx)) return '';

        $lines = [];
        foreach ($idx as $item) {
            $lines[] = ArticleResearchService::renderItemLine($item);
        }
        $body = ($angle !== '' ? "Якорь: {$angle}\n\n" : '') . implode("\n", $lines);

        $maxLen = 8000;
        if (strlen($body) > $maxLen) {
            $body = substr($body, 0, $maxLen) . "\n…[усечено]";
        }
        return "\n\nRESEARCH DOSSIER (фактическая база, ссылайся по ID). " . $usageRule . "\n\n" . $body;
    }

    private function decodeConfig($config): array {
        if (is_array($config))  return $config;
        if (is_string($config)) {
            $decoded = json_decode($config, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}