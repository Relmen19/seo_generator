<?php

declare(strict_types=1);

namespace Seo\Service;

use RuntimeException;
use Seo\Entity\SeoSiteProfile;
use Seo\Service\Editorial\TextExtractor;

/**
 * TelegramCopywriterService
 *
 * Один GPT-вызов на пост → возвращает семантическую структуру:
 *   {
 *     "hook":     "первое предложение-крючок",
 *     "segments": [ {type, ...fields}, ... ],
 *     "image_captions": { "<block_id>": "короткая подпись" },
 *     "cta":      { "text": "Читать полностью", "kind": "article_link" }
 *   }
 *
 * Формат сегментов согласуется с TelegramBlockFormatterService::formatSegments().
 * Сырые строки без Markdown — PHP-сторона сама эскейпит в MarkdownV2.
 */
class TelegramCopywriterService
{
    private const ALLOWED_SEGMENT_TYPES = [
        'paragraph', 'heading', 'list', 'highlight',
        'quote', 'callout', 'stat', 'faq',
    ];

    private const ALLOWED_CALLOUT_SEVERITY = ['info', 'warning', 'alert', 'success'];

    private const MAX_IMAGE_CAPTION = 150;

    private GptClient $gpt;

    public function __construct(?GptClient $gpt = null)
    {
        $this->gpt = $gpt ?? new GptClient();
    }

    /**
     * Сгенерировать пост на основе статьи + выбранных рендер-блоков.
     *
     * @param  array          $article          строка из seo_articles
     * @param  array          $renderableBlocks блоки, которые пойдут картинками
     * @param  array          $textBlocks       richtext-блоки статьи (для контекста)
     * @param  array          $extraBlocks      прочие не-картиночные блоки (context)
     * @param  string         $articleLink      финальная ссылка на статью
     * @param  SeoSiteProfile $profile
     * @return array                            нормализованная структура
     * @throws RuntimeException                 если GPT недоступен или вернул мусор
     */
    public function compose(
        array $article,
        array $renderableBlocks,
        array $textBlocks,
        array $extraBlocks,
        string $articleLink,
        SeoSiteProfile $profile
    ): array {
        if (empty(GPT_API_KEY)) {
            throw new RuntimeException('GPT_API_KEY не задан — copywriter невозможен');
        }

        $articleText    = $this->collectArticleText($textBlocks, $extraBlocks);
        $imageBlocksCtx = $this->describeImageBlocks($renderableBlocks);

        $system = $this->buildSystemPrompt($profile);
        $user   = $this->buildUserPrompt($article, $articleText, $imageBlocksCtx, $articleLink);

        $this->gpt->setLogContext([
            'category'    => TokenUsageLogger::CATEGORY_TELEGRAM_AGGREGATE,
            'operation'   => 'compose_post',
            'profile_id'  => $profile->getId(),
            'entity_type' => 'article',
            'entity_id'   => isset($article['id']) ? (int)$article['id'] : null,
        ]);
        $result = $this->gpt->chatJson(
            [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
            [
                'temperature' => SEO_TEMPERATURE_CREATIVE,
                'max_tokens'  => 1500,
            ]
        );

        $raw = $result['data'] ?? [];
        return $this->normalize($raw, $renderableBlocks);
    }

    // ── Prompt ────────────────────────────────────────────────────────────────

    private function buildSystemPrompt(SeoSiteProfile $profile): string
    {
        $persona = $profile->getGptPersona() ?: 'Ты — контент-редактор Telegram-канала';
        $tone    = $profile->getTone() ?: 'дружелюбный, экспертный';

        return $persona . "\n"
            . "Тон: {$tone}.\n\n"
            . "Твоя задача — написать ЖИВОЙ пост для Telegram-канала по статье. "
            . "Пост НЕ должен быть копией статьи. Это тизер: заинтересовать и подвести "
            . "к переходу на полный материал.\n\n"
            . "Принципы:\n"
            . "- Пиши коротко, по делу, без воды и клише.\n"
            . "- НЕ начинай со слов \"В этой статье...\", \"Сегодня расскажем...\", приветствий.\n"
            . "- Суммаризируй, не пересказывай дословно.\n"
            . "- Если в статье есть перечисления (типы, варианты, инструменты) — \n"
            . "  оформляй их как segment типа \"list\" с короткими пунктами, а не расписывай каждый.\n"
            . "- НЕ используй Markdown, HTML, эмодзи (эмодзи добавит PHP-сторона).\n"
            . "- НЕ экранируй спецсимволы вручную. Давай чистый текст.\n\n"
            . "Отвечай строго в формате JSON со следующей структурой:\n"
            . $this->jsonSchemaSpec();
    }

    private function jsonSchemaSpec(): string
    {
        return <<<'JSON'
{
  "hook": "string — первое предложение-крючок, до 200 символов",
  "segments": [
    { "type": "paragraph", "text": "обычный абзац" },
    { "type": "heading",   "text": "короткий заголовок подраздела" },
    {
      "type": "list",
      "intro": "опциональный ввод перед списком",
      "items": ["пункт 1", "пункт 2", "пункт 3"]
    },
    { "type": "highlight", "text": "ключевая мысль — будет оформлена как цитата-выделение" },
    {
      "type": "quote",
      "text":   "цитата",
      "author": "опционально: автор/источник"
    },
    {
      "type": "callout",
      "severity": "info|warning|alert|success",
      "text": "предупреждение, совет или важный факт одной строкой"
    },
    {
      "type": "stat",
      "value": "число или процент (строкой)",
      "label": "что означает эта цифра"
    },
    {
      "type": "faq",
      "items": [
        { "question": "вопрос", "answer": "ответ" }
      ]
    }
  ],
  "image_captions": {
    "<block_id>": "короткая подпись"
  },
  "cta": {
    "text": "текст кнопки (например: Читать полностью)",
    "kind": "article_link"
  }
}
JSON;
    }

    private function buildUserPrompt(
        array $article,
        string $articleText,
        array $imageBlocks,
        string $articleLink
    ): string {
        $title = (string)($article['title'] ?? '');
        $lead  = (string)($article['lead'] ?? '');

        $parts = [];
        $parts[] = "Заголовок статьи: {$title}";
        if ($lead !== '') {
            $parts[] = "Лид: {$lead}";
        }
        $parts[] = "Ссылка: {$articleLink}";

        if (!empty($imageBlocks)) {
            $parts[] = "\nБлоки-картинки (пойдут в пост как изображения — для них нужны подписи в image_captions):";
            foreach ($imageBlocks as $b) {
                $parts[] = "- block_id={$b['id']}, type={$b['type']}, суть: {$b['summary']}";
            }
        }

        $parts[] = "\nТекст статьи (используй как источник, но НЕ копируй):";
        $parts[] = mb_substr($articleText, 0, 6000);

        return implode("\n", $parts);
    }

    // ── Контекст статьи ───────────────────────────────────────────────────────

    private function collectArticleText(array $textBlocks, array $extraBlocks): string
    {
        $chunks = [];

        foreach ($textBlocks as $block) {
            $content = TextExtractor::blockContent($block);
            if (!is_array($content)) continue;
            $text = $this->textFromRichtext($content);
            if ($text !== '') {
                $chunks[] = $text;
            }
        }

        foreach ($extraBlocks as $block) {
            $content = TextExtractor::blockContent($block);
            if (!is_array($content)) continue;
            $text = $this->textFromArbitraryBlock($block['type'] ?? '', $content);
            if ($text !== '') {
                $chunks[] = "[{$block['type']}]\n" . $text;
            }
        }

        return implode("\n\n", $chunks);
    }

    private function textFromRichtext(array $content): string
    {
        if (isset($content['html'])) {
            $text = strip_tags(str_replace(['</p>', '<br>', '<br/>'], "\n", (string)$content['html']));
            return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        }

        if (isset($content['blocks']) && is_array($content['blocks'])) {
            $parts = [];
            foreach ($content['blocks'] as $b) {
                if (isset($b['text'])) $parts[] = (string)$b['text'];
                if (isset($b['items']) && is_array($b['items'])) {
                    foreach ($b['items'] as $it) {
                        $parts[] = is_string($it) ? $it : json_encode($it, JSON_UNESCAPED_UNICODE);
                    }
                }
            }
            return implode("\n", $parts);
        }

        return '';
    }

    private function textFromArbitraryBlock(string $type, array $content): string
    {
        // Простая эвристика: собираем все текстовые поля блока
        $fields = [];
        array_walk_recursive($content, static function ($val) use (&$fields) {
            if (is_string($val) && trim($val) !== '' && mb_strlen($val) < 500) {
                $fields[] = $val;
            }
        });
        return implode("\n", $fields);
    }

    private function describeImageBlocks(array $renderableBlocks): array
    {
        $out = [];
        foreach ($renderableBlocks as $b) {
            $content = TextExtractor::blockContent($b);
            if (!is_array($content)) $content = [];
            $summary = $content['title'] ?? $content['subtitle'] ?? $content['caption'] ?? $b['name'] ?? '';

            $out[] = [
                'id'      => (int)$b['id'],
                'type'    => (string)$b['type'],
                'summary' => mb_substr((string)$summary, 0, 200),
            ];
        }
        return $out;
    }

    // ── Нормализация GPT-ответа ───────────────────────────────────────────────

    /**
     * Приводит сырой JSON от GPT к строгой, предсказуемой структуре.
     * Отбрасывает невалидные сегменты, обрезает длинные подписи.
     */
    private function normalize(array $raw, array $renderableBlocks): array
    {
        $hook = isset($raw['hook']) ? trim((string)$raw['hook']) : '';

        $segments = [];
        foreach ((array)($raw['segments'] ?? []) as $seg) {
            $norm = $this->normalizeSegment($seg);
            if ($norm !== null) {
                $segments[] = $norm;
            }
        }

        // image_captions: оставляем только ключи, которые есть в renderableBlocks
        $validIds = [];
        foreach ($renderableBlocks as $b) {
            $validIds[(string)(int)$b['id']] = true;
        }
        $captions = [];
        foreach ((array)($raw['image_captions'] ?? []) as $bid => $text) {
            $bid = (string)$bid;
            if (!isset($validIds[$bid])) continue;
            $caption = trim((string)$text);
            if ($caption === '') continue;
            $captions[$bid] = mb_substr($caption, 0, self::MAX_IMAGE_CAPTION);
        }

        $cta = null;
        if (isset($raw['cta']) && is_array($raw['cta'])) {
            $ctaText = trim((string)($raw['cta']['text'] ?? ''));
            $ctaKind = (string)($raw['cta']['kind'] ?? 'article_link');
            if ($ctaText !== '') {
                $cta = ['text' => mb_substr($ctaText, 0, 64), 'kind' => $ctaKind];
            }
        }

        if ($hook === '' && empty($segments)) {
            throw new RuntimeException('GPT не вернул ни hook, ни segments');
        }

        return [
            'hook'           => $hook,
            'segments'       => $segments,
            'image_captions' => $captions,
            'cta'            => $cta,
        ];
    }

    private function normalizeSegment($seg): ?array
    {
        if (!is_array($seg)) return null;
        $type = (string)($seg['type'] ?? '');
        if (!in_array($type, self::ALLOWED_SEGMENT_TYPES, true)) return null;

        switch ($type) {
            case 'paragraph':
            case 'heading':
            case 'highlight':
                $text = trim((string)($seg['text'] ?? ''));
                return $text === '' ? null : ['type' => $type, 'text' => $text];

            case 'list':
                $items = [];
                foreach ((array)($seg['items'] ?? []) as $it) {
                    $line = trim((string)$it);
                    if ($line !== '') $items[] = $line;
                }
                if (empty($items)) return null;
                $out = ['type' => 'list', 'items' => $items];
                $intro = trim((string)($seg['intro'] ?? ''));
                if ($intro !== '') $out['intro'] = $intro;
                return $out;

            case 'quote':
                $text = trim((string)($seg['text'] ?? ''));
                if ($text === '') return null;
                $out = ['type' => 'quote', 'text' => $text];
                $author = trim((string)($seg['author'] ?? ''));
                if ($author !== '') $out['author'] = $author;
                return $out;

            case 'callout':
                $text = trim((string)($seg['text'] ?? ''));
                if ($text === '') return null;
                $sev = (string)($seg['severity'] ?? 'info');
                if (!in_array($sev, self::ALLOWED_CALLOUT_SEVERITY, true)) $sev = 'info';
                return ['type' => 'callout', 'severity' => $sev, 'text' => $text];

            case 'stat':
                $value = trim((string)($seg['value'] ?? ''));
                $label = trim((string)($seg['label'] ?? ''));
                if ($value === '' || $label === '') return null;
                return ['type' => 'stat', 'value' => $value, 'label' => $label];

            case 'faq':
                $items = [];
                foreach ((array)($seg['items'] ?? []) as $it) {
                    if (!is_array($it)) continue;
                    $q = trim((string)($it['question'] ?? ''));
                    $a = trim((string)($it['answer'] ?? ''));
                    if ($q === '' || $a === '') continue;
                    $items[] = ['question' => $q, 'answer' => $a];
                }
                return empty($items) ? null : ['type' => 'faq', 'items' => $items];
        }

        return null;
    }
}
