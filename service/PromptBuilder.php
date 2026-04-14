<?php

declare(strict_types=1);

namespace Seo\Service;

class PromptBuilder {

    private const DEFAULT_PERSONA = "Ты — профессиональный SEO-копирайтер. JSON-формат. Профессиональный, но доступный стиль. Конкретные факты, данные, примеры.";

    private const RICHTEXT_FORMAT = '{"blocks":[{"type":"heading","text":"...","level":2},{"type":"paragraph","text":"..."},{"type":"list","items":["..."]},{"type":"highlight","text":"..."}]}';
    private const RICHTEXT_RULES  = 'type "list" → "items"(массив строк). type "heading" → "text"+"level"(2/3). Остальные → "text"(строка). Мин. 6 подблоков, чередуй типы.';

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
        $system = "{$persona} Спланируй структуру статьи как единый рассказ.\n\n"
            . "Ответ — строго JSON:\n"
            . "{\"meta_title\":\"60-70 символов\",\"meta_description\":\"150-160 символов\",\"meta_keywords\":\"5-10 слов через запятую\",\"article_plan\":\"до 1000 символов\"}\n\n"
            . "article_plan — пошаговый редакторский план, НЕ список блоков.\n"
            . "Формат: [Тип] Конкретное содержание → [Тип] Содержание → ...\n"
            . "Каждый блок логически вытекает из предыдущего. Конкретика по теме, не переименование блоков.";

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

        $user .= "\nСоставь план: для каждого блока — конкретное содержание по теме «{$article['title']}».";

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ];
    }


    public function buildFullArticlePrompt(array $article, array $templateBlocks, ?string $systemPrompt = null): array {
        $system = $this->buildSystemMessage($systemPrompt, $article);
        $system .= "\n\nФОРМАТ: JSON {\"block_0\":{...}, \"block_1\":{...}, ...}. Все блоки в одном JSON.\n"
            . "Страница — единый нарратив. Каждый блок логически вытекает из предыдущего.\n"
            . "АНТИДУБЛЯЖ: Блоки НЕ повторяют информацию друг друга. Если данные уже есть в одном блоке — в другом их НЕТ.";

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

        $system .= "\n\nТребования:\n"
            . "1. Контент подробный, с фактами и конкретикой. Никаких общих фраз.\n"
            . "2. Списки — мин. 4 пункта с пояснениями. Параграфы — мин. 3 предложения.\n"
            . "3. Каждый блок содержит ТОЛЬКО свою информацию. Не дублируй данные между блоками.\n"
            . "4. Заголовки визуальных блоков — понятные целевой аудитории.\n";

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

        $user = "Сгенерируй контент для [{$type}] «{$name}».\n";

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
        $user .= "\nОтвет — JSON-объект.";

        return $user;
    }

    private function getRichtextHint(array $config): string {
        $allowed = $config['block_types'] ?? ['paragraph', 'heading', 'list', 'highlight', 'quote'];

        return "\nФормат richtext: " . self::RICHTEXT_FORMAT . "\n"
            . "Допустимые type: " . implode(', ', $allowed) . "\n"
            . self::RICHTEXT_RULES . "\n"
            . "НЕ оборачивай в {\"type\":\"richtext\",\"content\":[...]}!\n";
    }

    private function getTypeWarning(string $type): string {
        if (!empty($this->blockTypeHints[$type])) {
            return "  ⚠ {$this->blockTypeHints[$type]}\n";
        }

        switch ($type) {
            // Плоские объекты — GPT любит вкладывать в подобъекты
            case 'hero':
            case 'cta':
            case 'stats_counter':
                return "  ⚠ ПЛОСКИЙ JSON, без подобъектов!\n";

            // Richtext — самый проблемный формат
            case 'richtext':
                return "  ⚠ Формат: {\"blocks\":[{\"type\":\"...\",\"text\":\"...\"},...]}. НЕ вкладывай в content! list → items, не text!\n";

            // Таблица норм — обязательные states
            case 'range_table':
            case 'norms_table': // legacy alias
                return "  ⚠ rows[].states — ОБЯЗАТЕЛЕН (массив {key,label,range,pct,description})!\n";

            // Диаграммы — descriptions часто забывают
            case 'chart':
                return "  ⚠ doughnut/pie: ОБЯЗАТЕЛЬНО description + datasets[0].descriptions (массив строк)!\n";

            // Gauge — items с min/max/value
            case 'gauge_chart':
                return "  ⚠ items: [{name,value,min,max,unit,color,description}]. value — число, не строка!\n";

            // Timeline — step/title/summary/detail/meta
            case 'timeline':
                return "  ⚠ items: [{step,title,summary,detail,meta}]. detail — 2-3 предложения!\n";

            // Funnel — убывающие значения
            case 'funnel':
                return "  ⚠ items[].value — числа, УБЫВАЮЩИЕ сверху вниз! + description на каждый.\n";

            // Spark metrics — points обязательны
            case 'spark_metrics':
                return "  ⚠ items: [{icon,name,value(строка),unit,trend,trend_up(bool),points(массив 8-12 чисел),color,details}]\n";

            // Score rings — value/max числа
            case 'score_rings':
                return "  ⚠ rings: [{name,subtitle,value(0-100),max(100),color,description}]. total_label обязателен!\n";

            // Heatmap — двумерный массив
            case 'heatmap':
                return "  ⚠ data — двумерный массив чисел [rows×columns]. rows и columns — массивы строк!\n";

            // Radar — axes с value 0-100
            case 'radar_chart':
                return "  ⚠ axes: [{name,value(0-100),description}]. Мин. 5 осей!\n";

            // Before/After — before и after числа
            case 'before_after':
                return "  ⚠ metrics: [{name,before(число),after(число),unit,max}]. max > before и after!\n";

            // Range comparison — ranges массив пар
            case 'range_comparison':
                return "  ⚠ rows[].ranges — массив пар [[min,max],[min,max]], rows[].values — массив чисел. groups обязательны!\n";

            // Stacked area — series с data массивами
            case 'stacked_area':
                return "  ⚠ series: [{name,data(массив чисел = длина labels),color,description}]. labels — периоды!\n";

            // Comparison table — headers и rows
            case 'comparison_table':
                return "  ⚠ headers[0] — название критерия, остальные — варианты. rows — массив массивов. Бинарные: ✓/✗!\n";

            // FAQ — items с question/answer
            case 'faq':
                return "  ⚠ items: [{question,answer}]. Ответы — 2-3 предложения с конкретикой!\n";

            // Accordion — items с title/content
            case 'accordion':
                return "  ⚠ items: [{title,content}]. content — развёрнутый текст, 2-4 предложения!\n";

            // Feature grid — items с icon/title/description
            case 'feature_grid':
                return "  ⚠ items: [{icon(emoji),title,description}]. Мин. 4 карточки!\n";

            // Image section
            case 'image_section':
                return "  ⚠ {image_id,image_alt,title,text,layout}. text — только текст, без HTML!\n";

            // Testimonial
            case 'testimonial':
                return "  ⚠ items: [{name,role,text,rating(1-5)}].\n";

            // ── NEW INTENT BLOCKS ──

            case 'value_checker':
                return "  ⚠ zones: [{key,from(число),to(число),color(hex),label,icon(emoji),text}]. from/to — числовые пороги! disclaimer обязателен!\n";

            case 'criteria_checklist':
            case 'symptom_checklist': // legacy alias
                return "  ⚠ items: [{text,weight(1-3),group}]. thresholds: [{min,max,label,color,text}]. Мин. 6 items, 3 thresholds!\n";

            case 'prep_checklist':
                return "  ⚠ sections: [{name,icon(emoji),items:[{text,important(bool)}]}]. 2-3 секции, 2-4 items в каждой!\n";

            case 'info_cards':
                return "  ⚠ items: [{icon(emoji),title,text,color(hex)}]. layout: \"grid-2\"|\"grid-3\". Мин. 4 карточки!\n";

            case 'story_block':
                return "  ⚠ {variant:\"patient_story\"|\"expert_quote\"|\"key_fact\",icon,accent_color(hex),lead,text,highlight,footnote}. text — 3-5 предложений!\n";

            case 'verdict_card':
                return "  ⚠ items: [{claim,verdict:\"myth\"|\"truth\"|\"partial\",explanation,source}]. Мин. 3 карточки, баланс вердиктов!\n";

            case 'numbered_steps':
                return "  ⚠ steps: [{number(int),title,text,tip,duration}]. 4-5 шагов. tip — совет, duration — время!\n";

            case 'warning_block':
                return "  ⚠ {variant:\"red_flags\"|\"caution\"|\"good_signs\",title,subtitle,items:[{text,severity:\"urgent\"|\"emergency\"|\"warning\"}],footer}!\n";

            case 'mini_calculator':
                return "  ⚠ inputs: [{key,label,type:\"select\"|\"number\",options:[{value,label}],show_if:{key,value}}]. results: [{condition:\"key=val&&key2=val2\",value,text}]!\n";

            case 'comparison_cards':
                return "  ⚠ {title, card_a:{name,badge,color(hex),pros:[],cons:[],price,verdict}, card_b:{...}}. pros/cons — массивы строк!\n";

            case 'progress_tracker':
                return "  ⚠ milestones: [{period,marker(0-100),text,metric}]. marker — позиция на шкале (%). note обязателен!\n";

            case 'key_takeaways':
                return "  ⚠ {title, items:[строки], style:\"numbered\"|\"bullets\"|\"cards\"}. items — простые строки, не объекты!\n";

            case 'expert_panel':
                return "  ⚠ {name,credentials,experience,photo_placeholder(инициалы),text(3-5 предл.),highlight(ключевая фраза)}!\n";

            default:
                return '';
        }
    }

    private function resolvePersona(): string {
        if (!empty($this->siteProfile['gpt_persona'])) {
            return $this->siteProfile['gpt_persona'];
        }
        return self::DEFAULT_PERSONA;
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