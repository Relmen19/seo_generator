<?php

declare(strict_types=1);

namespace Seo\Service;

use Seo\Enum\ArticlePrompt;

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
                                     ?string $systemPrompt = null, array $allBlockTypes = []): array {
        $system = $this->buildSystemMessage($systemPrompt, $article);
        $user   = $this->buildBlockUserMessage($templateBlock, $articleBlock, $allBlockTypes);

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

    private function buildBlockUserMessage(array $templateBlock, array $articleBlock, array $allBlockTypes): string {
        $config = $this->decodeConfig($templateBlock['config'] ?? null);
        $type   = $templateBlock['type'] ?? 'unknown';
        $name   = $templateBlock['name'] ?? $type;
        $hint   = $config['hint'] ?? '';
        $fields = $config['fields'] ?? [];

        $user = sprintf(ArticlePrompt::BLOCK_USER_GENERATE, $type, $name) . "\n";

        if ($hint)            $user .= "Описание: {$hint}\n";
        if (!empty($fields))  $user .= "Поля JSON: " . implode(', ', $fields) . "\n";

        if ($type === 'richtext') $user .= $this->getRichtextHint($config);

        // Доп. поля (компактно)
        $extra = [];
        if (!empty($config['items_fields']))  $extra[] = "items: " . implode(', ', $config['items_fields']);
        if (!empty($config['block_types']))   $extra[] = "подблоки: " . implode(', ', $config['block_types']);
        if (!empty($config['chart_types']))   $extra[] = "chart: " . implode('|', $config['chart_types']);
        if ($extra) $user .= implode(' | ', $extra) . "\n";

        if (!empty($articleBlock['gpt_prompt'])) $user .= "Доп. инструкции: {$articleBlock['gpt_prompt']}\n";
        if (!empty($allBlockTypes))              $user .= "Структура: " . implode(' → ', $allBlockTypes) . "\n";

        // Текущий контент для улучшения
        $current = $articleBlock['content'] ?? null;
        if ($current !== null && $current !== '[]' && $current !== '{}') {
            $decoded = is_string($current) ? json_decode($current, true) : $current;
            if (!empty($decoded)) {
                $user .= "\nТекущий контент:\n" . json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
            }
        }

        $user .= $this->getTypeWarning($type);
        $user .= "\n" . ArticlePrompt::BLOCK_USER_RESPONSE;

        return $user;
    }

    private function getRichtextHint(array $config): string {
        $allowed = $config['block_types'] ?? ['paragraph', 'heading', 'list', 'highlight', 'quote'];

        return "\nФормат richtext: " . ArticlePrompt::RICHTEXT_FORMAT . "\n"
            . "Допустимые type: " . implode(', ', $allowed) . "\n"
            . ArticlePrompt::RICHTEXT_RULES . "\n"
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

    private function decodeConfig($config): array {
        if (is_array($config))  return $config;
        if (is_string($config)) {
            $decoded = json_decode($config, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}