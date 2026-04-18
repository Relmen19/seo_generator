<?php

declare(strict_types=1);

namespace Seo\Service;

/**
 * Formats filled block data into Telegram-ready content.
 *
 * Returns a TelegramBlock DTO:
 *   - text:     MarkdownV2-escaped string for Telegram message
 *   - keyboard: array|null — inline_keyboard rows (for sendMessage reply_markup)
 *
 * Usage:
 *   $formatter = new TelegramBlockFormatterService();
 *   $result = $formatter->format('faq', $blockData);
 *   // $result->text    — send as parse_mode=MarkdownV2
 *   // $result->keyboard — pass to InlineKeyboardMarkup if not null
 */
class TelegramBlockFormatterService
{
    // ── Emoji palette ──────────────────────────────────────────────────────────
    private const BULLET      = '▪️';
    private const CHECK       = '✅';
    private const WARN        = '⚠️';
    private const ALERT       = '🚨';
    private const ARROW_RIGHT = '➡️';
    private const STAR        = '⭐';
    private const CHART_UP    = '📈';
    private const CHART_DOWN  = '📉';
    private const FIRE        = '🔥';
    private const INFO        = 'ℹ️';
    private const QUOTE_OPEN  = '❝';
    private const VERDICT_OK  = '✅';
    private const VERDICT_BAD = '❌';
    private const VERDICT_MID = '🔶';

    // ── Severity → emoji ───────────────────────────────────────────────────────
    private const SEVERITY_EMOJI = [
        'emergency' => self::ALERT,
        'urgent'    => self::WARN,
        'warning'   => self::INFO,
    ];

    // ── Verdict → emoji ────────────────────────────────────────────────────────
    private const VERDICT_EMOJI = [
        'truth'   => self::VERDICT_OK,
        'myth'    => self::VERDICT_BAD,
        'partial' => self::VERDICT_MID,
    ];

    private const VERDICT_LABEL = [
        'truth'   => 'Правда',
        'myth'    => 'Миф',
        'partial' => 'Частично верно',
    ];

    // ──────────────────────────────────────────────────────────────────────────
    //  Public API
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Format any block by its type code.
     *
     * @param  string $type  Block type code (e.g. 'faq', 'cta', 'richtext')
     * @param  array  $data  Filled block data (the content fields)
     * @return TelegramBlock
     */
    public function format(string $type, array $data): TelegramBlock
    {
        $method = 'format' . str_replace('_', '', ucwords($type, '_'));

        if (method_exists($this, $method)) {
            return $this->$method($data);
        }

        // Graceful fallback — just dump whatever text fields are present
        return $this->formatFallback($type, $data);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Block formatters
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * hero — крупный заголовок + подзаголовок + опциональная CTA-кнопка
     */
    private function formatHero(array $d): TelegramBlock
    {
        $lines   = [];
        $keyboard = null;

        if (!empty($d['title'])) {
            $lines[] = $this->bold($d['title']);
        }
        if (!empty($d['subtitle'])) {
            $lines[] = '';
            $lines[] = $this->escape($d['subtitle']);
        }

        if (!empty($d['cta_text']) && !empty($d['cta_link_key'])) {
            $keyboard = $this->singleButton($d['cta_text'], $d['cta_link_key']);
        }

        return new TelegramBlock(implode("\n", $lines), $keyboard);
    }

    /**
     * stats_counter — компактная строка ключевых цифр
     */
    private function formatStatsCounter(array $d): TelegramBlock
    {
        $items = $d['items'] ?? [];
        if (empty($items)) {
            return TelegramBlock::empty();
        }

        $lines = [];
        foreach ($items as $item) {
            $value  = $this->escape((string)($item['value'] ?? ''));
            $suffix = $this->escape((string)($item['suffix'] ?? ''));
            $label  = $this->escape($item['label'] ?? '');
            $lines[] = self::BULLET . ' ' . $this->bold("{$value}{$suffix}") . " — {$label}";
        }

        return new TelegramBlock(implode("\n", $lines));
    }

    /**
     * richtext — смешанные блоки: paragraph / heading / list / highlight / quote
     */
    private function formatRichtext(array $d): TelegramBlock
    {
        $blocks = $d['blocks'] ?? [];
        $parts  = [];

        foreach ($blocks as $block) {
            $type = $block['type'] ?? 'paragraph';

            switch ($type) {
                case 'heading':
                    $parts[] = $this->bold($block['text'] ?? '');
                    break;

                case 'paragraph':
                    $parts[] = $this->escape($block['text'] ?? '');
                    break;

                case 'list':
                    $listLines = [];
                    foreach ($block['items'] ?? [] as $item) {
                        $listLines[] = self::BULLET . ' ' . $this->escape($item);
                    }
                    $parts[] = implode("\n", $listLines);
                    break;

                case 'highlight':
                    // Telegram block-quote (MarkdownV2 syntax: >text)
                    $parts[] = $this->blockQuote($block['text'] ?? '');
                    break;

                case 'quote':
                    $parts[] = self::QUOTE_OPEN . ' ' . $this->italic($block['text'] ?? '');
                    break;
            }
        }

        return new TelegramBlock(implode("\n\n", array_filter($parts, fn($p) => $p !== '')));
    }

    /**
     * faq — вопрос жирным, ответ — цитата
     */
    private function formatFaq(array $d): TelegramBlock
    {
        $items = $d['items'] ?? [];
        if (empty($items)) {
            return TelegramBlock::empty();
        }

        $parts = [];
        foreach ($items as $item) {
            $q = $this->bold($item['question'] ?? '');
            $a = $this->blockQuote($item['answer'] ?? '');
            $parts[] = $q . "\n" . $a;
        }

        return new TelegramBlock(implode("\n\n", $parts));
    }

    /**
     * cta — текст + кнопки Telegram inline keyboard
     */
    private function formatCta(array $d): TelegramBlock
    {
        $lines    = [];
        $keyboard = null;

        if (!empty($d['title'])) {
            $lines[] = $this->bold($d['title']);
        }
        if (!empty($d['text'])) {
            $lines[] = $this->escape($d['text']);
        }

        $buttons = [];
        if (!empty($d['primary_btn_text']) && !empty($d['primary_btn_link_key'])) {
            $buttons[] = [
                ['text' => $d['primary_btn_text'], 'callback_data' => $d['primary_btn_link_key']],
            ];
        }
        if (!empty($d['secondary_btn_text']) && !empty($d['secondary_btn_link_key'])) {
            $buttons[] = [
                ['text' => $d['secondary_btn_text'], 'callback_data' => $d['secondary_btn_link_key']],
            ];
        }

        if (!empty($buttons)) {
            $keyboard = ['inline_keyboard' => $buttons];
        }

        return new TelegramBlock(implode("\n\n", array_filter($lines)), $keyboard);
    }

    /**
     * accordion — разделы с жирными заголовками и текстом
     */
    private function formatAccordion(array $d): TelegramBlock
    {
        $items = $d['items'] ?? [];
        if (empty($items)) {
            return TelegramBlock::empty();
        }

        $parts = [];
        foreach ($items as $item) {
            $title   = $this->bold($item['title'] ?? '');
            $content = $this->escape($item['content'] ?? '');
            $parts[] = "{$title}\n{$content}";
        }

        return new TelegramBlock(implode("\n\n", $parts));
    }

    /**
     * feature_grid — иконка + жирный заголовок + описание
     */
    private function formatFeatureGrid(array $d): TelegramBlock
    {
        $items = $d['items'] ?? [];
        if (empty($items)) {
            return TelegramBlock::empty();
        }

        $lines = [];
        foreach ($items as $item) {
            $icon  = $item['icon'] ?? self::BULLET;
            $title = $this->bold($item['title'] ?? '');
            $desc  = $this->escape($item['description'] ?? '');
            $lines[] = "{$icon} {$title} — {$desc}";
        }

        return new TelegramBlock(implode("\n", $lines));
    }

    /**
     * info_cards — список карточек
     */
    private function formatInfoCards(array $d): TelegramBlock
    {
        $items = $d['items'] ?? [];
        if (empty($items)) {
            return TelegramBlock::empty();
        }

        $parts = [];
        foreach ($items as $item) {
            $icon  = $item['icon'] ?? self::INFO;
            $title = $this->bold($item['title'] ?? '');
            $text  = $this->escape($item['text'] ?? '');
            $parts[] = "{$icon} {$title}\n{$text}";
        }

        return new TelegramBlock(implode("\n\n", $parts));
    }

    /**
     * key_takeaways — ключевые выводы (numbered / bullets / cards)
     */
    private function formatKeyTakeaways(array $d): TelegramBlock
    {
        $items = $d['items'] ?? [];
        $style = $d['style'] ?? 'bullets';
        $title = $d['title'] ?? '';

        $lines = [];
        if ($title !== '') {
            $lines[] = $this->bold($title);
            $lines[] = '';
        }

        foreach ($items as $i => $item) {
            $text = $this->escape($item);
            $lines[] = match ($style) {
                'numbered' => $this->bold((string)($i + 1) . '.') . ' ' . $text,
                'cards'    => self::FIRE . ' ' . $text,
                default    => self::BULLET . ' ' . $text,
            };
        }

        return new TelegramBlock(implode("\n", $lines));
    }

    /**
     * numbered_steps — пошаговые инструкции
     */
    private function formatNumberedSteps(array $d): TelegramBlock
    {
        $steps = $d['steps'] ?? [];
        if (empty($steps)) {
            return TelegramBlock::empty();
        }

        $parts = [];
        foreach ($steps as $step) {
            $num      = $step['number'] ?? '?';
            $title    = $this->bold("{$num}. " . ($step['title'] ?? ''));
            $text     = $this->escape($step['text'] ?? '');
            $duration = !empty($step['duration']) ? $this->italic("⏱ {$step['duration']}") : '';
            $tip      = !empty($step['tip'])      ? self::INFO . ' ' . $this->italic($step['tip']) : '';

            $block = $title;
            if ($duration) {
                $block .= "  {$duration}";
            }
            $block .= "\n{$text}";
            if ($tip) {
                $block .= "\n{$tip}";
            }
            $parts[] = $block;
        }

        return new TelegramBlock(implode("\n\n", $parts));
    }

    /**
     * timeline — этапы с мета-информацией
     */
    private function formatTimeline(array $d): TelegramBlock
    {
        $items = $d['items'] ?? [];
        $title = $d['title'] ?? '';

        $lines = [];
        if ($title !== '') {
            $lines[] = $this->bold($title);
            $lines[] = '';
        }

        foreach ($items as $item) {
            $step    = $item['step'] ?? '';
            $name    = $item['title'] ?? '';
            $summary = $item['summary'] ?? '';
            $meta    = !empty($item['meta']) ? $this->italic($item['meta']) : '';

            $header = $this->bold("{$step}. {$name}");
            if ($meta) {
                $header .= " — {$meta}";
            }
            $lines[] = $header;

            if ($summary !== '') {
                $lines[] = $this->escape($summary);
            }
            $lines[] = '';
        }

        return new TelegramBlock(rtrim(implode("\n", $lines)));
    }

    /**
     * warning_block — тревожные симптомы / предупреждения
     */
    private function formatWarningBlock(array $d): TelegramBlock
    {
        $title    = $d['title'] ?? '';
        $subtitle = $d['subtitle'] ?? '';
        $items    = $d['items'] ?? [];
        $footer   = $d['footer'] ?? '';
        $variant  = $d['variant'] ?? 'caution';

        $headerEmoji = match ($variant) {
            'red_flags'  => self::ALERT,
            'good_signs' => self::CHECK,
            default      => self::WARN,
        };

        $lines = [];
        if ($title !== '') {
            $lines[] = "{$headerEmoji} " . $this->bold($title);
        }
        if ($subtitle !== '') {
            $lines[] = $this->italic($subtitle);
        }
        if (!empty($lines)) {
            $lines[] = '';
        }

        foreach ($items as $item) {
            $emoji = self::SEVERITY_EMOJI[$item['severity'] ?? 'warning'] ?? self::INFO;
            $lines[] = "{$emoji} " . $this->escape($item['text'] ?? '');
        }

        if ($footer !== '') {
            $lines[] = '';
            $lines[] = $this->italic($this->escape($footer));
        }

        return new TelegramBlock(implode("\n", $lines));
    }

    /**
     * expert_panel — экспертное мнение как цитата
     */
    private function formatExpertPanel(array $d): TelegramBlock
    {
        $name        = $d['name'] ?? '';
        $credentials = $d['credentials'] ?? '';
        $text        = $d['text'] ?? '';
        $highlight   = $d['highlight'] ?? '';

        $lines = [];

        if ($highlight !== '') {
            $lines[] = $this->blockQuote($highlight);
            $lines[] = '';
        }

        if ($text !== '') {
            $lines[] = $this->escape($text);
            $lines[] = '';
        }

        if ($name !== '') {
            $attribution = $this->bold($name);
            if ($credentials !== '') {
                $attribution .= ', ' . $this->italic($this->escape($credentials));
            }
            $lines[] = self::QUOTE_OPEN . ' ' . $attribution;
        }

        return new TelegramBlock(rtrim(implode("\n", $lines)));
    }

    /**
     * story_block — история пациента / ключевой факт / цитата эксперта
     */
    private function formatStoryBlock(array $d): TelegramBlock
    {
        $variant   = $d['variant'] ?? 'key_fact';
        $icon      = $d['icon'] ?? match ($variant) {
            'patient_story' => '📖',
            'expert_quote'  => '🗣',
            default         => self::FIRE,
        };
        $lead      = $d['lead'] ?? '';
        $text      = $d['text'] ?? '';
        $highlight = $d['highlight'] ?? '';
        $footnote  = $d['footnote'] ?? '';

        $lines = [];
        if ($lead !== '') {
            $lines[] = "{$icon} " . $this->bold($lead);
        }
        if ($text !== '') {
            $lines[] = $this->escape($text);
        }
        if ($highlight !== '') {
            $lines[] = '';
            $lines[] = $this->blockQuote($highlight);
        }
        if ($footnote !== '') {
            $lines[] = $this->italic($this->escape($footnote));
        }

        return new TelegramBlock(implode("\n", $lines));
    }

    /**
     * verdict_card — карточки мифов и правды
     */
    private function formatVerdictCard(array $d): TelegramBlock
    {
        $items = $d['items'] ?? [];
        if (empty($items)) {
            return TelegramBlock::empty();
        }

        $parts = [];
        foreach ($items as $item) {
            $verdict = $item['verdict'] ?? 'partial';
            $emoji   = self::VERDICT_EMOJI[$verdict]  ?? self::VERDICT_MID;
            $label   = self::VERDICT_LABEL[$verdict]  ?? 'Частично верно';
            $claim   = $this->bold($item['claim'] ?? '');
            $explain = $this->escape($item['explanation'] ?? '');
            $src     = !empty($item['source'])
                ? $this->italic($this->escape($item['source']))
                : '';

            $block = "{$emoji} {$claim}\n{$this->italic($label)}\n{$explain}";
            if ($src !== '') {
                $block .= "\n{$src}";
            }
            $parts[] = $block;
        }

        return new TelegramBlock(implode("\n\n", $parts));
    }

    /**
     * testimonial — отзывы со звёздами
     */
    private function formatTestimonial(array $d): TelegramBlock
    {
        $items = $d['items'] ?? [];
        if (empty($items)) {
            return TelegramBlock::empty();
        }

        $parts = [];
        foreach ($items as $item) {
            $stars  = str_repeat(self::STAR, min((int)($item['rating'] ?? 5), 5));
            $name   = $this->bold($item['name'] ?? '');
            $role   = !empty($item['role']) ? ', ' . $this->italic($this->escape($item['role'])) : '';
            $text   = $this->blockQuote($item['text'] ?? '');
            $parts[] = "{$stars}\n{$text}\n{$name}{$role}";
        }

        return new TelegramBlock(implode("\n\n", $parts));
    }

    /**
     * before_after — метрики до/после
     */
    private function formatBeforeAfter(array $d): TelegramBlock
    {
        $title   = $d['title'] ?? '';
        $metrics = $d['metrics'] ?? [];

        $lines = [];
        if ($title !== '') {
            $lines[] = $this->bold($title);
            $lines[] = '';
        }

        foreach ($metrics as $m) {
            $name   = $this->escape($m['name'] ?? '');
            $unit   = $this->escape($m['unit'] ?? '');
            $before = $m['before'] ?? 0;
            $after  = $m['after'] ?? 0;
            $arrow  = $after >= $before ? self::CHART_UP : self::CHART_DOWN;
            $lines[] = "{$arrow} {$name}: "
                . $this->bold("{$before}{$unit}")
                . " → "
                . $this->bold("{$after}{$unit}");
        }

        return new TelegramBlock(implode("\n", $lines));
    }

    /**
     * comparison_cards — сравнение двух вариантов
     */
    private function formatComparisonCards(array $d): TelegramBlock
    {
        $title  = $d['title'] ?? '';
        $cardA  = $d['card_a'] ?? [];
        $cardB  = $d['card_b'] ?? [];

        $lines = [];
        if ($title !== '') {
            $lines[] = $this->bold($title);
            $lines[] = '';
        }

        foreach ([$cardA, $cardB] as $card) {
            $name    = $card['name'] ?? '';
            $badge   = !empty($card['badge']) ? " [{$card['badge']}]" : '';
            $price   = !empty($card['price']) ? "\n💰 " . $this->escape($card['price']) : '';
            $verdict = !empty($card['verdict']) ? "\n" . $this->italic($this->escape($card['verdict'])) : '';

            $lines[] = $this->bold($name) . $this->italic($this->escape($badge));

            foreach ($card['pros'] ?? [] as $pro) {
                $lines[] = self::CHECK . ' ' . $this->escape($pro);
            }
            foreach ($card['cons'] ?? [] as $con) {
                $lines[] = '❌ ' . $this->escape($con);
            }

            $lines[] = $price . $verdict;
            $lines[] = '';
        }

        return new TelegramBlock(rtrim(implode("\n", $lines)));
    }

    /**
     * comparison_table — таблица сравнения (текстовая версия)
     */
    private function formatComparisonTable(array $d): TelegramBlock
    {
        $title   = $d['title'] ?? '';
        $headers = $d['headers'] ?? [];
        $rows    = $d['rows'] ?? [];
        $desc    = $d['description'] ?? '';

        $lines = [];
        if ($title !== '') {
            $lines[] = $this->bold($title);
        }
        if ($desc !== '') {
            $lines[] = $this->italic($this->escape($desc));
        }
        if (!empty($lines)) {
            $lines[] = '';
        }

        // Header row
        if (!empty($headers)) {
            $headerLine = implode(' · ', array_map(fn($h) => $this->bold($h), $headers));
            $lines[] = $headerLine;
            $lines[] = '─────────────────';
        }

        foreach ($rows as $row) {
            $cells   = (array) $row;
            $criterion = $this->bold(array_shift($cells));
            $values    = array_map(fn($c) => $this->escape((string)$c), $cells);
            $lines[] = "{$criterion}: " . implode(' · ', $values);
        }

        return new TelegramBlock(implode("\n", $lines));
    }

    /**
     * gauge_chart — показатели с процентными значениями
     */
    private function formatGaugeChart(array $d): TelegramBlock
    {
        $title = $d['title'] ?? '';
        $items = $d['items'] ?? [];

        $lines = [];
        if ($title !== '') {
            $lines[] = $this->bold($title);
            $lines[] = '';
        }

        foreach ($items as $item) {
            $name  = $this->escape($item['name'] ?? '');
            $value = $item['value'] ?? 0;
            $max   = $item['max'] ?? 100;
            $unit  = $this->escape($item['unit'] ?? '');
            $desc  = !empty($item['description']) ? ' — ' . $this->italic($this->escape($item['description'])) : '';
            $pct   = $max > 0 ? (int)(($value / $max) * 100) : 0;
            $bar   = $this->progressBar($pct);

            $lines[] = self::BULLET . ' ' . $this->bold($name) . ": {$value}{$unit}{$desc}";
            $lines[] = $bar;
        }

        return new TelegramBlock(implode("\n", $lines));
    }

    /**
     * spark_metrics — ключевые метрики с трендами
     */
    private function formatSparkMetrics(array $d): TelegramBlock
    {
        $title = $d['title'] ?? '';
        $items = $d['items'] ?? [];

        $lines = [];
        if ($title !== '') {
            $lines[] = $this->bold($title);
            $lines[] = '';
        }

        foreach ($items as $item) {
            $icon    = $item['icon'] ?? self::CHART_UP;
            $name    = $this->bold($item['name'] ?? '');
            $value   = $this->escape((string)($item['value'] ?? ''));
            $unit    = $this->escape($item['unit'] ?? '');
            $trend   = $this->escape($item['trend'] ?? '');
            $trendUp = (bool)($item['trend_up'] ?? true);
            $arrow   = $trendUp ? '↑' : '↓';
            $details = !empty($item['details']) ? "\n   " . $this->italic($this->escape($item['details'])) : '';

            $lines[] = "{$icon} {$name}: {$value}{$unit} {$arrow} {$trend}{$details}";
        }

        return new TelegramBlock(implode("\n", $lines));
    }

    /**
     * score_rings — кольца оценок
     */
    private function formatScoreRings(array $d): TelegramBlock
    {
        $title      = $d['title'] ?? '';
        $totalLabel = $d['total_label'] ?? '';
        $rings      = $d['rings'] ?? [];

        $lines = [];
        if ($title !== '') {
            $lines[] = $this->bold($title);
        }
        if (!empty($rings)) {
            $totalValue = (int)(array_sum(array_column($rings, 'value')) / count($rings));
            $lines[] = $this->bold("{$totalValue}") . ' — ' . $this->italic($this->escape($totalLabel));
            $lines[] = '';
        }

        foreach ($rings as $ring) {
            $name    = $this->bold($ring['name'] ?? '');
            $value   = $ring['value'] ?? 0;
            $sub     = !empty($ring['subtitle'])    ? ' (' . $this->escape($ring['subtitle']) . ')' : '';
            $desc    = !empty($ring['description']) ? ' — ' . $this->italic($this->escape($ring['description'])) : '';
            $bar     = $this->progressBar($value);
            $lines[] = self::BULLET . ' ' . $name . $sub . ': ' . $this->bold((string)$value) . $desc;
            $lines[] = $bar;
        }

        return new TelegramBlock(implode("\n", $lines));
    }

    /**
     * radar_chart — паукообразная диаграмма как список осей
     */
    private function formatRadarChart(array $d): TelegramBlock
    {
        $title = $d['title'] ?? '';
        $axes  = $d['axes'] ?? [];

        $lines = [];
        if ($title !== '') {
            $lines[] = $this->bold($title);
            $lines[] = '';
        }

        foreach ($axes as $axis) {
            $name  = $this->bold($axis['name'] ?? '');
            $value = (int)($axis['value'] ?? 0);
            $desc  = !empty($axis['description']) ? ' — ' . $this->italic($this->escape($axis['description'])) : '';
            $bar   = $this->progressBar($value);
            $lines[] = "{$name}: {$value}%{$desc}";
            $lines[] = $bar;
        }

        return new TelegramBlock(implode("\n", $lines));
    }

    /**
     * progress_tracker — прогресс по этапам
     */
    private function formatProgressTracker(array $d): TelegramBlock
    {
        $title      = $d['title'] ?? '';
        $milestones = $d['milestones'] ?? [];
        $note       = $d['note'] ?? '';

        $lines = [];
        if ($title !== '') {
            $lines[] = $this->bold($title);
            $lines[] = '';
        }

        foreach ($milestones as $m) {
            $period = $this->bold($m['period'] ?? '');
            $metric = $this->escape($m['metric'] ?? '');
            $text   = $this->escape($m['text'] ?? '');
            $marker = $m['marker'] ?? 0;

            $lines[] = "{$period} — {$metric}";
            if ($text !== '') {
                $lines[] = $this->italic($text);
            }
            $lines[] = $this->progressBar($marker);
            $lines[] = '';
        }

        if ($note !== '') {
            $lines[] = $this->italic($this->escape($note));
        }

        return new TelegramBlock(rtrim(implode("\n", $lines)));
    }

    /**
     * prep_checklist — чек-лист подготовки
     */
    private function formatPrepChecklist(array $d): TelegramBlock
    {
        $title    = $d['title'] ?? '';
        $subtitle = $d['subtitle'] ?? '';
        $sections = $d['sections'] ?? [];

        $lines = [];
        if ($title !== '') {
            $lines[] = $this->bold($title);
        }
        if ($subtitle !== '') {
            $lines[] = $this->italic($this->escape($subtitle));
        }
        if (!empty($lines)) {
            $lines[] = '';
        }

        foreach ($sections as $section) {
            $icon = $section['icon'] ?? self::BULLET;
            $name = $this->bold("{$icon} " . ($section['name'] ?? ''));
            $lines[] = $name;

            foreach ($section['items'] ?? [] as $item) {
                $check = $item['important'] ? self::ALERT : self::CHECK;
                $text  = $this->escape($item['text'] ?? '');
                $lines[] = "  {$check} {$text}";
            }
            $lines[] = '';
        }

        return new TelegramBlock(rtrim(implode("\n", $lines)));
    }

    /**
     * criteria_checklist / symptom_checklist — интерактивный чек-лист с порогами
     * (В Telegram — статичный список с весами)
     */
    private function formatCriteriaChecklist(array $d): TelegramBlock
    {
        return $this->renderChecklist($d);
    }

    private function formatSymptomChecklist(array $d): TelegramBlock
    {
        return $this->renderChecklist($d);
    }

    private function renderChecklist(array $d): TelegramBlock
    {
        $title    = $d['title'] ?? '';
        $subtitle = $d['subtitle'] ?? '';
        $items    = $d['items'] ?? [];
        $ctaText  = $d['cta_text'] ?? '';
        $ctaKey   = $d['cta_link_key'] ?? '';

        $lines = [];
        if ($title !== '') {
            $lines[] = $this->bold($title);
        }
        if ($subtitle !== '') {
            $lines[] = $this->italic($this->escape($subtitle));
        }
        if (!empty($lines)) {
            $lines[] = '';
        }

        $byGroup = [];
        foreach ($items as $item) {
            $byGroup[$item['group'] ?? ''][] = $item;
        }

        foreach ($byGroup as $group => $groupItems) {
            if ($group !== '') {
                $lines[] = $this->bold($group);
            }
            foreach ($groupItems as $item) {
                $weight  = (int)($item['weight'] ?? 1);
                $dot     = $weight >= 3 ? self::ALERT : ($weight >= 2 ? self::WARN : self::BULLET);
                $lines[] = "{$dot} " . $this->escape($item['text'] ?? '');
            }
            $lines[] = '';
        }

        $keyboard = null;
        if ($ctaText !== '' && $ctaKey !== '') {
            $keyboard = $this->singleButton($ctaText, $ctaKey);
        }

        return new TelegramBlock(rtrim(implode("\n", $lines)), $keyboard);
    }

    /**
     * mini_calculator — описание калькулятора с формулой
     */
    private function formatMiniCalculator(array $d): TelegramBlock
    {
        $title   = $d['title'] ?? '';
        $desc    = $d['description'] ?? '';
        $formula = $d['formula_description'] ?? '';
        $disc    = $d['disclaimer'] ?? '';

        $lines = [];
        if ($title !== '') {
            $lines[] = '🧮 ' . $this->bold($title);
        }
        if ($desc !== '') {
            $lines[] = $this->escape($desc);
        }
        if ($formula !== '') {
            $lines[] = '';
            $lines[] = $this->blockQuote($formula);
        }
        if ($disc !== '') {
            $lines[] = '';
            $lines[] = $this->italic($this->escape($disc));
        }

        return new TelegramBlock(implode("\n", $lines));
    }

    /**
     * value_checker — зоны значений
     */
    private function formatValueChecker(array $d): TelegramBlock
    {
        $title = $d['title'] ?? '';
        $desc  = $d['description'] ?? '';
        $zones = $d['zones'] ?? [];
        $disc  = $d['disclaimer'] ?? '';

        $lines = [];
        if ($title !== '') {
            $lines[] = $this->bold($title);
        }
        if ($desc !== '') {
            $lines[] = $this->italic($this->escape($desc));
        }
        if (!empty($lines) && !empty($zones)) {
            $lines[] = '';
        }

        foreach ($zones as $zone) {
            $icon  = $zone['icon'] ?? self::INFO;
            $label = $this->bold($zone['label'] ?? '');
            $from  = $zone['from'] ?? 0;
            $to    = $zone['to'] ?? 0;
            $text  = $this->escape($zone['text'] ?? '');
            $lines[] = "{$icon} {$label} ({$from}–{$to}): {$text}";
        }

        if ($disc !== '') {
            $lines[] = '';
            $lines[] = $this->italic($this->escape($disc));
        }

        return new TelegramBlock(implode("\n", $lines));
    }

    /**
     * funnel — воронка этапов
     */
    private function formatFunnel(array $d): TelegramBlock
    {
        $title = $d['title'] ?? '';
        $desc  = $d['description'] ?? '';
        $items = $d['items'] ?? [];

        $lines = [];
        if ($title !== '') {
            $lines[] = $this->bold($title);
        }
        if ($desc !== '') {
            $lines[] = $this->italic($this->escape($desc));
        }
        if (!empty($lines)) {
            $lines[] = '';
        }

        $max = max(array_column($items, 'value') ?: [1]);
        foreach ($items as $item) {
            $name  = $this->bold($item['name'] ?? '');
            $value = $item['value'] ?? 0;
            $desc2 = !empty($item['description']) ? ' — ' . $this->italic($this->escape($item['description'])) : '';
            $pct   = $max > 0 ? (int)(($value / $max) * 100) : 0;
            $bar   = $this->progressBar($pct, 8);
            $lines[] = "{$name}: {$value}{$desc2}";
            $lines[] = $bar;
        }

        return new TelegramBlock(implode("\n", $lines));
    }

    /**
     * chart — текстовое представление графика
     */
    private function formatChart(array $d): TelegramBlock
    {
        $title    = $d['title'] ?? '';
        $desc     = $d['description'] ?? '';
        $labels   = $d['labels'] ?? [];
        $datasets = $d['datasets'] ?? [];

        $lines = [];
        if ($title !== '') {
            $lines[] = '📊 ' . $this->bold($title);
        }
        if ($desc !== '') {
            $lines[] = $this->italic($this->escape($desc));
        }
        if (!empty($lines)) {
            $lines[] = '';
        }

        $data  = $datasets[0]['data'] ?? [];
        $descs = $datasets[0]['descriptions'] ?? [];
        $total = array_sum($data) ?: 1;

        foreach ($labels as $i => $label) {
            $value    = $data[$i] ?? 0;
            $itemDesc = !empty($descs[$i]) ? ' — ' . $this->italic($this->escape($descs[$i])) : '';
            $pct      = (int)(($value / $total) * 100);
            $bar      = $this->progressBar($pct, 8);
            $lines[] = $this->bold($this->escape($label)) . ": {$value} ({$pct}%){$itemDesc}";
            $lines[] = $bar;
        }

        return new TelegramBlock(implode("\n", $lines));
    }

    /**
     * heatmap — текстовая версия карты активности
     */
    private function formatHeatmap(array $d): TelegramBlock
    {
        $title   = $d['title'] ?? '';
        $desc    = $d['description'] ?? '';
        $rows    = $d['rows'] ?? [];
        $columns = $d['columns'] ?? [];
        $data    = $d['data'] ?? [];

        $lines = [];
        if ($title !== '') {
            $lines[] = $this->bold($title);
        }
        if ($desc !== '') {
            $lines[] = $this->italic($this->escape($desc));
        }
        if (!empty($lines)) {
            $lines[] = '';
        }

        // Find global max for relative bars
        $maxVal = 1;
        foreach ($data as $dataRow) {
            foreach ($dataRow as $cell) {
                $maxVal = max($maxVal, (int)$cell);
            }
        }

        foreach ($rows as $rIdx => $rowLabel) {
            $cells = $data[$rIdx] ?? [];
            $parts = [];
            foreach ($columns as $cIdx => $col) {
                $val   = $cells[$cIdx] ?? 0;
                $pct   = (int)(($val / $maxVal) * 100);
                $block = $pct >= 75 ? '█' : ($pct >= 50 ? '▓' : ($pct >= 25 ? '▒' : '░'));
                $parts[] = $block;
            }
            $lines[] = $this->bold($rowLabel) . ' ' . implode('', $parts);
        }

        // Column labels legend
        $lines[] = '';
        $lines[] = $this->italic(implode(' · ', $columns));

        return new TelegramBlock(implode("\n", $lines));
    }

    /**
     * stacked_area — серии данных
     */
    private function formatStackedArea(array $d): TelegramBlock
    {
        $title  = $d['title'] ?? '';
        $labels = $d['labels'] ?? [];
        $series = $d['series'] ?? [];

        $lines = [];
        if ($title !== '') {
            $lines[] = '📈 ' . $this->bold($title);
            $lines[] = '';
        }

        foreach ($series as $s) {
            $name = $this->bold($s['name'] ?? '');
            $data = $s['data'] ?? [];
            $desc = !empty($s['description']) ? ' — ' . $this->italic($s['description']) : '';
            $max  = max($data ?: [1]);

            $lines[] = "{$name}{$desc}";
            $cells   = [];
            foreach ($data as $val) {
                $pct    = $max > 0 ? (int)(($val / $max) * 100) : 0;
                $cells[] = "{$val}";
            }
            $lines[] = $this->italic(implode(' → ', $cells));
        }

        return new TelegramBlock(implode("\n", $lines));
    }

    /**
     * range_table — таблица норм с состояниями
     */
    private function formatRangeTable(array $d): TelegramBlock
    {
        $rows    = $d['rows'] ?? [];
        $caption = $d['caption'] ?? '';

        $lines = [];
        if ($caption !== '') {
            $lines[] = $this->bold($caption);
            $lines[] = '';
        }

        foreach ($rows as $row) {
            $name   = $this->bold($row['name'] ?? '');
            $unit   = $this->escape($row['unit'] ?? '');
            $active = (int)($row['active'] ?? 0);
            $states = $row['states'] ?? [];

            $lines[] = "{$name}" . ($unit ? " ({$unit})" : '');

            foreach ($states as $i => $state) {
                $marker = $i === $active ? self::ARROW_RIGHT : '  ';
                $label  = $this->escape($state['label'] ?? '');
                $range  = $this->escape($state['range'] ?? '');
                $stateDesc = !empty($state['description'])
                    ? ' — ' . $this->italic($state['description'])
                    : '';
                $lines[] = "  {$marker} {$label}: {$range}{$stateDesc}";
            }
            $lines[] = '';
        }

        return new TelegramBlock(rtrim(implode("\n", $lines)));
    }

    /**
     * range_comparison — сравнение диапазонов по группам
     */
    private function formatRangeComparison(array $d): TelegramBlock
    {
        $title  = $d['title'] ?? '';
        $groups = $d['groups'] ?? [];
        $rows   = $d['rows'] ?? [];

        $lines = [];
        if ($title !== '') {
            $lines[] = $this->bold($title);
            $lines[] = '';
        }

        foreach ($rows as $row) {
            $name   = $this->bold($row['name'] ?? '');
            $values = $row['values'] ?? [];
            $ranges = $row['ranges'] ?? [];

            $lines[] = $name;
            foreach ($groups as $gi => $group) {
                $val   = $values[$gi] ?? '?';
                $range = $ranges[$gi] ?? [0, 0];
                $from  = $range[0] ?? 0;
                $to    = $range[1] ?? 0;
                $lines[] = "  " . $this->escape($group) . ": {$val} (норма: {$from}–{$to})";
            }
            $lines[] = '';
        }

        return new TelegramBlock(rtrim(implode("\n", $lines)));
    }

    /**
     * image_section — текстовый блок (изображение отправляется отдельно)
     */
    private function formatImageSection(array $d): TelegramBlock
    {
        $lines = [];
        if (!empty($d['title'])) {
            $lines[] = $this->bold($d['title']);
        }
        if (!empty($d['text'])) {
            $lines[] = $this->escape($d['text']);
        }
        if (!empty($d['image_caption'])) {
            $lines[] = $this->italic($this->escape($d['image_caption']));
        }

        return new TelegramBlock(implode("\n\n", array_filter($lines)));
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Copywriter segments
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Отформатировать структуру, пришедшую от TelegramCopywriterService.
     *
     * @param array $segments массив элементов с ключом `type`:
     *   paragraph / heading / list(+intro) / highlight / quote(+author) /
     *   callout(severity) / stat(value,label) / faq(items[{question,answer}])
     * @return TelegramBlock  текст в MarkdownV2, keyboard = null
     */
    public function formatSegments(array $segments): TelegramBlock
    {
        $parts = [];

        foreach ($segments as $seg) {
            $type = $seg['type'] ?? '';
            $chunk = $this->renderSegment($type, $seg);
            if ($chunk !== '') {
                $parts[] = $chunk;
            }
        }

        return new TelegramBlock(implode("\n\n", $parts));
    }

    private function renderSegment(string $type, array $seg): string
    {
        switch ($type) {
            case 'heading':
                return $this->bold((string)($seg['text'] ?? ''));

            case 'paragraph':
                return $this->escape((string)($seg['text'] ?? ''));

            case 'list':
                $lines = [];
                $intro = trim((string)($seg['intro'] ?? ''));
                if ($intro !== '') {
                    $lines[] = $this->escape($intro);
                }
                foreach ((array)($seg['items'] ?? []) as $item) {
                    $lines[] = self::BULLET . ' ' . $this->escape((string)$item);
                }
                return implode("\n", $lines);

            case 'highlight':
                return $this->blockQuote((string)($seg['text'] ?? ''));

            case 'quote':
                $text   = (string)($seg['text'] ?? '');
                $author = trim((string)($seg['author'] ?? ''));
                $line   = self::QUOTE_OPEN . ' ' . $this->italic($text);
                if ($author !== '') {
                    $line .= "\n— " . $this->bold($author);
                }
                return $line;

            case 'callout':
                $severity = (string)($seg['severity'] ?? 'info');
                $text     = (string)($seg['text'] ?? '');
                $emoji    = $this->calloutEmoji($severity);
                return $emoji . ' ' . $this->escape($text);

            case 'stat':
                $value = (string)($seg['value'] ?? '');
                $label = (string)($seg['label'] ?? '');
                return $this->bold($value) . ' — ' . $this->escape($label);

            case 'faq':
                $items = [];
                foreach ((array)($seg['items'] ?? []) as $it) {
                    if (!is_array($it)) continue;
                    $q = $this->bold((string)($it['question'] ?? ''));
                    $a = $this->blockQuote((string)($it['answer'] ?? ''));
                    $items[] = $q . "\n" . $a;
                }
                return implode("\n\n", $items);
        }

        return '';
    }

    private function calloutEmoji(string $severity): string
    {
        switch ($severity) {
            case 'alert':   return self::ALERT;
            case 'warning': return self::WARN;
            case 'success': return self::CHECK;
            case 'info':
            default:        return self::INFO;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /** Fallback for unknown block types */
    private function formatFallback(string $type, array $data): TelegramBlock
    {
        $texts = [];
        array_walk_recursive($data, static function ($val) use (&$texts) {
            if (is_string($val) && trim($val) !== '') {
                $texts[] = $val;
            }
        });

        return new TelegramBlock($this->escape(implode("\n", $texts)));
    }

    /** Public: MarkdownV2-safe escape of a raw string. */
    public function escapePlain(string $text): string
    {
        return $this->escape($text);
    }

    /** Public: bold-wrap (with internal escape) a raw string. */
    public function boldPlain(string $text): string
    {
        return $this->bold($text);
    }

    /** Escape special chars for MarkdownV2 */
    private function escape(string $text): string
    {
        // Characters that must be escaped in MarkdownV2 outside entities
        return preg_replace_callback(
            '/[_*\[\]()~`>#+\-=|{}.!\\\\]/',
            static fn($m) => '\\' . $m[0],
            $text
        );
    }

    private function bold(string $text): string
    {
        return '*' . $this->escape($text) . '*';
    }

    private function italic(string $text): string
    {
        // text passed here is already escaped or raw — we escape just in case
        return '_' . $this->escape($text) . '_';
    }

    /**
     * Telegram MarkdownV2 block-quote: every line prefixed with ">"
     */
    private function blockQuote(string $text): string
    {
        $escaped = $this->escape($text);
        $lines   = explode("\n", $escaped);
        return implode("\n", array_map(static fn($l) => '>' . $l, $lines));
    }

    /** ASCII progress bar 0–100 */
    private function progressBar(int $pct, int $width = 10): string
    {
        $pct    = max(0, min(100, $pct));
        $filled = (int)round(($pct / 100) * $width);
        $empty  = $width - $filled;
        return '`' . str_repeat('█', $filled) . str_repeat('░', $empty) . "` {$pct}%";
    }

    /** Single inline button row */
    private function singleButton(string $text, string $callbackData): array
    {
        return ['inline_keyboard' => [[['text' => $text, 'callback_data' => $callbackData]]]];
    }
}