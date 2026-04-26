<?php

declare(strict_types=1);

namespace Seo\Service;

use RuntimeException;
use Seo\Database;
use Seo\Entity\SeoArticle;
use Seo\Entity\SeoSiteProfile;
use Seo\Entity\SeoTelegramPost;
use Seo\Entity\SeoTelegramRenderedImage;
use Seo\Service\Editorial\TextExtractor;

class TelegramPostService {

    private const MAX_CAPTION_LENGTH = 1024;
    private const MAX_TEXT_LENGTH = 4096;
    private const MAX_MEDIA_GROUP_SIZE = 10;
    private const MAX_RETRY_ATTEMPTS = 3;

    private Database $db;
    private ?TelegramBlockFormatterService $blockFormatter;
    private ?TelegramCopywriterService $copywriter;

    public function __construct(
        ?TelegramBlockFormatterService $blockFormatter = null,
        ?TelegramCopywriterService $copywriter = null
    ) {
        $this->db             = Database::getInstance();
        $this->blockFormatter = $blockFormatter ?? new TelegramBlockFormatterService();
        $this->copywriter     = $copywriter;
    }

    private function copywriter(): TelegramCopywriterService {
        if ($this->copywriter === null) {
            $this->copywriter = new TelegramCopywriterService();
        }
        return $this->copywriter;
    }

    // ── Post building ────────────────────────────────────────────────────────

    /**
     * Build post draft: render block images, compose text, save to DB.
     * Returns the created telegram post with rendered images.
     */
    public function buildDraft(int $articleId): array {
        $article = $this->loadArticle($articleId);
        $profile = $this->loadProfile((int)$article['profile_id']);
        $profileEntity = new SeoSiteProfile($profile);

        if (!$profileEntity->hasTelegramConfig()) {
            throw new RuntimeException('Telegram не настроен для этого профиля');
        }

        $blocks = $this->db->fetchAll(
            'SELECT * FROM seo_article_blocks WHERE article_id = :aid ORDER BY sort_order',
            [':aid' => $articleId]
        );

        $renderableTypes = $profileEntity->getEffectiveTgRenderBlocks();

        // Separate blocks into renderable (image) and text blocks
        $renderableBlocks  = [];
        $textBlocks        = [];
        $formattableBlocks = [];

        foreach ($blocks as $b) {
            if ((int)$b['is_visible'] !== 1) {
                continue;
            }
            if (in_array($b['type'], $renderableTypes, true)) {
                $renderableBlocks[] = $b;
            } elseif ($b['type'] === 'richtext') {
                $textBlocks[] = $b;
            } else {
                $formattableBlocks[] = $b;
            }
        }

        if (empty($renderableBlocks) && empty($textBlocks) && empty($formattableBlocks)) {
            throw new RuntimeException('Нет блоков для поста. Проверьте типы render-блоков в настройках Telegram профиля.');
        }

        // Determine format
        $format = $profileEntity->getTgPostFormat();
        $renderCount = count($renderableBlocks);

        if ($format === 'auto') {
            $format = ($renderCount > 7) ? 'series' : 'single';
        }

        // Build post_data structure
        $postData = $this->composePostData(
            $format,
            $article,
            $profileEntity,
            $renderableBlocks,
            $textBlocks,
            $formattableBlocks
        );

        // Create telegram post record
        $postId = $this->db->insert(SeoTelegramPost::TABLE, [
            'article_id'  => $articleId,
            'profile_id'  => (int)$article['profile_id'],
            'status'      => SeoTelegramPost::STATUS_DRAFT,
            'post_format'  => $format,
            'post_data'   => json_encode($postData, JSON_UNESCAPED_UNICODE),
        ]);

        // Render block images via Puppeteer
        $renderedImages = $this->renderBlockImages($postId, $renderableBlocks, $profile);

        // Update post_data with rendered image IDs
        $postData = $this->attachImageIds($postData, $renderedImages);
        $this->db->update(
            SeoTelegramPost::TABLE,
            'id = :id',
            ['post_data' => json_encode($postData, JSON_UNESCAPED_UNICODE)],
            [':id' => $postId]
        );

        return $this->getPostWithImages($postId);
    }

    /**
     * Compose the post_data structure based on format.
     * Tries Copywriter (structured GPT) first, falls back to legacy layout on failure.
     */
    private function composePostData(
        string $format,
        array $article,
        SeoSiteProfile $profile,
        array $renderableBlocks,
        array $textBlocks,
        array $formattableBlocks
    ): array {
        $articleLink = $this->buildArticleLink($article);

        try {
            $copy = $this->copywriter()->compose(
                $article,
                $renderableBlocks,
                $textBlocks,
                $formattableBlocks,
                $articleLink,
                $profile
            );
            return $this->buildMessagesFromCopy($format, $copy, $renderableBlocks, $articleLink);
        } catch (\Throwable $e) {
            logMessage('Telegram Copywriter failed, using fallback: ' . $e->getMessage());
        }

        $formattedExtraMessages = $this->formatExtraBlocks($formattableBlocks);
        return $this->composePostDataLegacy(
            $format,
            $article,
            $profile,
            $renderableBlocks,
            $textBlocks,
            $formattedExtraMessages,
            $articleLink
        );
    }

    /**
     * Build post_data messages from Copywriter output.
     *
     * Single format: 1 image message (caption = hook + segments, trimmed to 1024),
     * overflow → follow-up text message with remaining segments.
     *
     * Series format: several image messages with per-chunk captions built from
     * image_captions; segments go into a final text message (or message group).
     *
     * CTA is attached as an URL-button inline_keyboard on the last message.
     */
    private function buildMessagesFromCopy(
        string $format,
        array $copy,
        array $renderableBlocks,
        string $articleLink
    ): array {
        $messages = [];

        $hook           = (string)($copy['hook'] ?? '');
        $segments       = (array)($copy['segments'] ?? []);
        $imageCaptions  = (array)($copy['image_captions'] ?? []);
        $cta            = $copy['cta'] ?? null;

        $hookMd = $hook !== '' ? $this->blockFormatter->boldPlain($hook) : '';

        if ($format === 'single') {
            $imageBlocks = array_slice($renderableBlocks, 0, min(3, self::MAX_MEDIA_GROUP_SIZE));

            $segmentsBlock = $this->blockFormatter->formatSegments($segments);
            $fullText = trim($hookMd
                . ($segmentsBlock->text !== '' ? "\n\n" . $segmentsBlock->text : ''));

            [$captionText, $overflow] = $this->splitForCaption($fullText, self::MAX_CAPTION_LENGTH);

            if (count($imageBlocks) > 1) {
                $messages[] = [
                    'type'        => 'media_group',
                    'block_ids'   => array_map(fn($b) => (int)$b['id'], $imageBlocks),
                    'block_types' => array_map(fn($b) => $b['type'], $imageBlocks),
                    'caption'     => $captionText,
                    'parse_mode'  => 'MarkdownV2',
                ];
            } elseif (count($imageBlocks) === 1) {
                $messages[] = [
                    'type'       => 'photo',
                    'block_id'   => (int)$imageBlocks[0]['id'],
                    'block_type' => $imageBlocks[0]['type'],
                    'caption'    => $captionText,
                    'parse_mode' => 'MarkdownV2',
                ];
            } else {
                // No images — text-only post
                $messages[] = [
                    'type'       => 'text',
                    'text'       => mb_substr($fullText, 0, self::MAX_TEXT_LENGTH),
                    'parse_mode' => 'MarkdownV2',
                ];
                $overflow = '';
            }

            if ($overflow !== '') {
                foreach ($this->splitTextByLimit($overflow, self::MAX_TEXT_LENGTH) as $chunk) {
                    $messages[] = [
                        'type'       => 'text',
                        'text'       => $chunk,
                        'parse_mode' => 'MarkdownV2',
                    ];
                }
            }
        } else {
            // series
            $chunks = array_chunk($renderableBlocks, 3);

            foreach ($chunks as $i => $chunk) {
                $captionLines = [];
                if ($i === 0 && $hookMd !== '') {
                    $captionLines[] = $hookMd;
                }
                foreach ($chunk as $b) {
                    $bid = (string)(int)$b['id'];
                    if (isset($imageCaptions[$bid])) {
                        $captionLines[] = $this->blockFormatter->escapePlain($imageCaptions[$bid]);
                    }
                }
                $caption = mb_substr(implode("\n\n", $captionLines), 0, self::MAX_CAPTION_LENGTH);

                if (count($chunk) > 1) {
                    $messages[] = [
                        'type'        => 'media_group',
                        'block_ids'   => array_map(fn($b) => (int)$b['id'], $chunk),
                        'block_types' => array_map(fn($b) => $b['type'], $chunk),
                        'caption'     => $caption,
                        'parse_mode'  => 'MarkdownV2',
                    ];
                } else {
                    $messages[] = [
                        'type'       => 'photo',
                        'block_id'   => (int)$chunk[0]['id'],
                        'block_type' => $chunk[0]['type'],
                        'caption'    => $caption,
                        'parse_mode' => 'MarkdownV2',
                    ];
                }
            }

            // Segments → trailing text message(s)
            $segmentsBlock = $this->blockFormatter->formatSegments($segments);
            if ($segmentsBlock->text !== '') {
                foreach ($this->splitTextByLimit($segmentsBlock->text, self::MAX_TEXT_LENGTH) as $chunk) {
                    $messages[] = [
                        'type'       => 'text',
                        'text'       => $chunk,
                        'parse_mode' => 'MarkdownV2',
                    ];
                }
            }

            // If no renderable blocks at all, push hook too
            if (empty($chunks) && $hookMd !== '' && empty($messages)) {
                $messages[] = [
                    'type'       => 'text',
                    'text'       => $hookMd,
                    'parse_mode' => 'MarkdownV2',
                ];
            }
        }

        if (!empty($messages)) {
            $this->attachCtaKeyboard($messages, $cta, $articleLink);
        }

        return [
            'messages'     => $messages,
            'article_link' => $articleLink,
            'format'       => $format,
        ];
    }

    /**
     * Attach URL button to the last message for the article link / custom CTA.
     * If the last message is a media_group/photo (caption-only), create a
     * trailing text message with the keyboard, since media_group doesn't
     * support inline_keyboard.
     */
    private function attachCtaKeyboard(array &$messages, ?array $cta, string $articleLink): void
    {
        $ctaText = 'Читать полностью';
        if (is_array($cta) && !empty($cta['text'])) {
            $ctaText = (string)$cta['text'];
        }

        $keyboard = [
            'inline_keyboard' => [[
                ['text' => $ctaText, 'url' => $articleLink],
            ]],
        ];

        $lastIdx = count($messages) - 1;
        $last = &$messages[$lastIdx];
        if ($last['type'] === 'text') {
            $last['keyboard'] = $keyboard;
            return;
        }
        unset($last);

        // Trailing text-only hint to carry the keyboard
        $messages[] = [
            'type'       => 'text',
            'text'       => $this->blockFormatter->escapePlain('Подробнее в статье'),
            'parse_mode' => 'MarkdownV2',
            'keyboard'   => $keyboard,
            'cta_hint'   => true,
        ];
    }

    /**
     * Validate that URL is acceptable to Telegram (public HTTP/HTTPS host).
     * Rejects localhost, private IPs, bare hostnames without a dot.
     */
    private function isPublicTelegramUrl(string $url): bool {
        if ($url === '') {
            return false;
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }
        $host = strtolower($host);
        if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $isPublic = filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
            if ($isPublic === false) {
                return false;
            }
        } elseif (strpos($host, '.') === false) {
            return false;
        }
        return true;
    }

    /**
     * Sanitize inline keyboards before sending: strip buttons whose URL is
     * not acceptable to Telegram (localhost etc.). Drop keyboards / hint-only
     * messages that become empty as a result. Returns cleaned messages.
     */
    private function sanitizeKeyboards(array $messages): array {
        $cleaned = [];
        foreach ($messages as $msg) {
            if (empty($msg['keyboard']) || !is_array($msg['keyboard'])) {
                $cleaned[] = $msg;
                continue;
            }
            $rows = $msg['keyboard']['inline_keyboard'] ?? [];
            $newRows = [];
            foreach ($rows as $row) {
                $newRow = [];
                foreach ($row as $btn) {
                    if (isset($btn['url']) && !$this->isPublicTelegramUrl((string)$btn['url'])) {
                        logMessage(
                            'Telegram: inline button URL rejected (not public): ' . $btn['url'],
                            'WARN'
                        );
                        continue;
                    }
                    $newRow[] = $btn;
                }
                if (!empty($newRow)) {
                    $newRows[] = $newRow;
                }
            }
            if (empty($newRows)) {
                unset($msg['keyboard']);
                if (!empty($msg['cta_hint'])) {
                    continue;
                }
            } else {
                $msg['keyboard'] = ['inline_keyboard' => $newRows];
            }
            $cleaned[] = $msg;
        }
        return $cleaned;
    }

    /**
     * Cut a MarkdownV2 text at the caption limit, returning [caption, overflow].
     * Tries to split on paragraph boundaries; avoids breaking MarkdownV2 entities
     * mid-line only as best effort (GPT output is plain text escaped by formatter).
     */
    private function splitForCaption(string $text, int $limit): array
    {
        if (mb_strlen($text) <= $limit) {
            return [$text, ''];
        }

        $paragraphs = explode("\n\n", $text);
        $caption    = '';
        $overflow   = [];

        foreach ($paragraphs as $para) {
            $candidate = $caption === '' ? $para : $caption . "\n\n" . $para;
            if ($overflow === [] && mb_strlen($candidate) <= $limit) {
                $caption = $candidate;
            } else {
                $overflow[] = $para;
            }
        }

        return [$caption, implode("\n\n", $overflow)];
    }

    // ── Legacy layout (fallback) ─────────────────────────────────────────────

    private function composePostDataLegacy(
        string $format,
        array $article,
        SeoSiteProfile $profile,
        array $renderableBlocks,
        array $textBlocks,
        array $formattedExtraMessages,
        string $articleLink
    ): array {
        $articleTitle = $article['title'] ?? '';

        if ($format === 'single') {
            // Single post: pick top images (max 10) + summarized caption
            $imageBlocks = array_slice($renderableBlocks, 0, min(3, self::MAX_MEDIA_GROUP_SIZE));

            // Try GPT summarization, fall back to block text extraction
            $captionText = $this->generateCaption($article, $textBlocks, $articleLink, $profile);

            $messages = [];
            if (count($imageBlocks) > 1) {
                $messages[] = [
                    'type'        => 'media_group',
                    'block_ids'   => array_map(fn($b) => (int)$b['id'], $imageBlocks),
                    'block_types' => array_map(fn($b) => $b['type'], $imageBlocks),
                    'caption'     => mb_substr($captionText, 0, self::MAX_CAPTION_LENGTH),
                    'parse_mode'  => 'HTML',
                ];
            } elseif (count($imageBlocks) === 1) {
                $messages[] = [
                    'type'       => 'photo',
                    'block_id'   => (int)$imageBlocks[0]['id'],
                    'block_type' => $imageBlocks[0]['type'],
                    'caption'    => mb_substr($captionText, 0, self::MAX_CAPTION_LENGTH),
                    'parse_mode' => 'HTML',
                ];
            }

            // If caption was truncated, add a follow-up text message
            if (mb_strlen($captionText) > self::MAX_CAPTION_LENGTH) {
                $fullText = mb_substr($captionText, 0, self::MAX_TEXT_LENGTH);
                $messages[] = [
                    'type'       => 'text',
                    'text'       => $fullText,
                    'parse_mode' => 'HTML',
                ];
            }

            return [
                'messages'     => $messages,
                'article_link' => $articleLink,
                'format'       => 'single',
            ];
        }

        // Series mode: split renderable blocks into chunks of 2-3
        $chunks = array_chunk($renderableBlocks, 3);
        $messages = [];

        foreach ($chunks as $i => $chunk) {
            $chunkText = $this->buildChunkText($chunk, $textBlocks, $i, count($chunks), $articleTitle);

            if ($i === count($chunks) - 1) {
                $chunkText .= "\n\n" . '<a href="' . htmlspecialchars($articleLink) . '">Читать полностью</a>';
            }

            if (count($chunk) > 1) {
                $messages[] = [
                    'type'        => 'media_group',
                    'block_ids'   => array_map(fn($b) => (int)$b['id'], $chunk),
                    'block_types' => array_map(fn($b) => $b['type'], $chunk),
                    'caption'     => mb_substr($chunkText, 0, self::MAX_CAPTION_LENGTH),
                    'parse_mode'  => 'HTML',
                ];
            } else {
                $messages[] = [
                    'type'       => 'photo',
                    'block_id'   => (int)$chunk[0]['id'],
                    'block_type' => $chunk[0]['type'],
                    'caption'    => mb_substr($chunkText, 0, self::MAX_CAPTION_LENGTH),
                    'parse_mode' => 'HTML',
                ];
            }
        }

        $ctaMessages    = [];
        $regularMessages = [];

        foreach ($formattedExtraMessages as $msg) {
            if ($msg['keyboard'] !== null) {
                $ctaMessages[] = $msg;
            } else {
                $regularMessages[] = $msg;
            }
        }
        foreach (array_merge($regularMessages, $ctaMessages) as $extraMsg) {
            $messages[] = [
                'type'       => 'text',
                'text'       => $extraMsg['text'],
                'parse_mode' => 'MarkdownV2',
                'keyboard'   => $extraMsg['keyboard'],   // null или inline_keyboard
            ];
        }

        return [
            'messages'     => $messages,
            'article_link' => $articleLink,
            'format'       => $format,
        ];
    }

    private function formatExtraBlocks(array $blocks): array
    {
        $messages = [];

        foreach ($blocks as $block) {
            $type    = $block['type'] ?? '';
            $content = TextExtractor::blockContent($block);

            if ($type === '' || !is_array($content)) {
                continue;
            }

            try {
                $formatted = $this->blockFormatter->format($type, $content);
            } catch (\Throwable $e) {
                logMessage("Telegram formatter: ошибка блока {$block['id']} ({$type}): " . $e->getMessage());
                continue;
            }

            if ($formatted->isEmpty()) {
                continue;
            }

            // Разбиваем длинный текст на части, чтобы уложиться в лимит Telegram
            $chunks = $this->splitTextByLimit($formatted->text, self::MAX_TEXT_LENGTH);

            foreach ($chunks as $i => $chunk) {
                $messages[] = [
                    'type'     => $type,
                    'text'     => $chunk,
                    // keyboard только на последний чанк, чтобы кнопка была после текста
                    'keyboard' => ($i === count($chunks) - 1) ? $formatted->keyboard : null,
                ];
            }
        }

        return $messages;
    }

    /**
     * Split MarkdownV2 text into chunks of max $limit characters.
     * Splits on double-newline boundaries to not break Telegram entities.
     */
    private function splitTextByLimit(string $text, int $limit): array
    {
        if (mb_strlen($text) <= $limit) {
            return [$text];
        }

        $chunks     = [];
        $paragraphs = explode("\n\n", $text);
        $current    = '';

        foreach ($paragraphs as $para) {
            $candidate = $current === '' ? $para : $current . "\n\n" . $para;

            if (mb_strlen($candidate) > $limit) {
                if ($current !== '') {
                    $chunks[]  = $current;
                }
                // If single paragraph itself exceeds limit — hard split
                $current = mb_strlen($para) > $limit
                    ? mb_substr($para, 0, $limit - 3) . '\\.\\.\\.'
                    : $para;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks ?: [$text];
    }

    /**
     * Generate caption: try GPT summarization, fall back to block text.
     */
    private function generateCaption(array $article, array $textBlocks, string $articleLink, SeoSiteProfile $profile): string {
        $title = $article['title'] ?? '';

        // Try GPT summarization
        try {
            $summary = $this->gptSummarize($article, $textBlocks, $profile);
            if ($summary !== '') {
                $parts = ['<b>' . htmlspecialchars($title) . '</b>', $summary];
                $parts[] = '<a href="' . htmlspecialchars($articleLink) . '">Читать полностью</a>';
                return implode("\n\n", $parts);
            }
        } catch (\Throwable $e) {
            logMessage('Telegram GPT summarization failed: ' . $e->getMessage());
        }

        // Fallback to block text extraction
        return $this->buildCaptionFromBlocks($textBlocks, $title, $articleLink, self::MAX_CAPTION_LENGTH);
    }

    /**
     * Summarize article text via GPT for Telegram post caption.
     */
    private function gptSummarize(array $article, array $textBlocks, SeoSiteProfile $profile): string {
        if (empty(GPT_API_KEY)) {
            return '';
        }

        // Collect article text
        $textParts = [];
        foreach ($textBlocks as $block) {
            $content = TextExtractor::blockContent($block);
            $text = $this->extractTextFromContent($content);
            if ($text !== '') {
                $textParts[] = $text;
            }
        }

        $fullText = implode("\n\n", $textParts);
        if (mb_strlen($fullText) < 100) {
            return ''; // Too short to summarize
        }

        $gpt = new GptClient();
        $persona = $profile->getGptPersona() ?: 'Ты — контент-менеджер';
        $tone = $profile->getTone() ?: 'professional';

        $result = $gpt->chat([
            [
                'role' => 'system',
                'content' => $persona . "\n\nТон: {$tone}.\n"
                    . "Напиши пост для Telegram-канала по материалам статьи.\n"
                    . "Требования:\n"
                    . "- Максимум 500 символов\n"
                    . "- Используй HTML-теги Telegram: <b>, <i>, <a href=\"url\">\n"
                    . "- Разделяй абзацы двойным переводом строки\n"
                    . "- НЕ копируй текст из статьи дословно — суммаризируй и перефразируй\n"
                    . "- Выдели 2-3 ключевых факта или вывода жирным\n"
                    . "- Текст должен быть самостоятельным и информативным\n"
                    . "- НЕ используй эмодзи, хештеги, разделители типа ——\n"
                    . "- НЕ начинай с приветствий или вступлений\n"
                    . "- Отвечай ТОЛЬКО текстом поста, без пояснений и кавычек",
            ],
            [
                'role' => 'user',
                'content' => "Заголовок: {$article['title']}\n\nТекст статьи:\n" . mb_substr($fullText, 0, 3000),
            ],
        ], [
            'temperature' => SEO_TEMPERATURE_CREATIVE,
            'max_tokens' => 400,
        ]);

        $summary = trim($result['content'] ?? '');

        // Strip any unsupported tags that GPT might add
        $summary = $this->htmlToTelegramText($summary);

        return $summary;
    }

    /**
     * Build caption text from text blocks, with truncation.
     */
    private function buildCaptionFromBlocks(array $textBlocks, string $title, string $link, int $maxLen): string {
        $parts = [];
        $parts[] = '<b>' . htmlspecialchars($title) . '</b>';

        foreach ($textBlocks as $block) {
            $content = TextExtractor::blockContent($block);
            $text = $this->extractTextFromContent($content);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        $parts[] = '<a href="' . htmlspecialchars($link) . '">Читать полностью</a>';

        $full = implode("\n\n", $parts);

        if (mb_strlen($full) > $maxLen) {
            $full = mb_substr($full, 0, $maxLen - 30) . "...\n\n" . '<a href="' . htmlspecialchars($link) . '">Читать полностью</a>';
        }

        return $full;
    }

    /**
     * Build full text for follow-up message.
     */
    private function buildFullText(array $textBlocks, string $title, string $link): string {
        $parts = ['<b>' . htmlspecialchars($title) . '</b>'];

        foreach ($textBlocks as $block) {
            $content = TextExtractor::blockContent($block);
            $text = $this->extractTextFromContent($content);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        $parts[] = '<a href="' . htmlspecialchars($link) . '">Читать полностью</a>';

        return implode("\n\n", $parts);
    }

    /**
     * Build text for a chunk of blocks in series mode.
     */
    private function buildChunkText(array $chunk, array $textBlocks, int $chunkIndex, int $totalChunks, string $title): string {
        $parts = [];

        if ($chunkIndex === 0) {
            $parts[] = '<b>' . htmlspecialchars($title) . '</b>';
        }

        foreach ($chunk as $block) {
            $content = TextExtractor::blockContent($block);
            $blockTitle = $content['title'] ?? $block['name'] ?? '';
            if ($blockTitle !== '') {
                $parts[] = '<b>' . htmlspecialchars($blockTitle) . '</b>';
            }
            $desc = $this->extractTextFromContent($content);
            if ($desc !== '') {
                $parts[] = $desc;
            }
        }

        $text = implode("\n\n", $parts);
        return mb_substr($text, 0, self::MAX_CAPTION_LENGTH);
    }

    /**
     * Extract readable text from block content JSON.
     */
    private function extractTextFromContent(array $content): string {
        // richtext blocks
        if (isset($content['html'])) {
            return $this->htmlToTelegramText($content['html']);
        }

        // blocks with description
        if (isset($content['description'])) {
            return $this->cleanText((string)$content['description']);
        }

        // blocks with subtitle
        if (isset($content['subtitle'])) {
            return $this->cleanText((string)$content['subtitle']);
        }

        // blocks with items list
        if (isset($content['items']) && is_array($content['items'])) {
            $lines = [];
            foreach (array_slice($content['items'], 0, 5) as $item) {
                if (is_string($item)) {
                    $lines[] = '• ' . $this->cleanText($item);
                } elseif (is_array($item)) {
                    $label = $item['label'] ?? $item['title'] ?? $item['name'] ?? '';
                    $value = $item['value'] ?? $item['text'] ?? '';
                    if ($label !== '') {
                        $lines[] = '<b>' . htmlspecialchars($label) . '</b>: ' . htmlspecialchars((string)$value);
                    }
                }
            }
            return implode("\n", $lines);
        }

        return '';
    }

    /**
     * Convert HTML to Telegram-safe HTML subset.
     */
    private function htmlToTelegramText(string $html): string {
        // Convert block-level elements to newlines before stripping
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $text = preg_replace('/<\/p>\s*<p[^>]*>/i', "\n\n", $text);
        $text = preg_replace('/<\/?(?:p|div|h[1-6]|li|tr)[^>]*>/i', "\n", $text);

        // Strip all tags except Telegram-supported ones
        $text = strip_tags($text, '<b><i><u><s><a><code><pre>');
        // Remove attributes from non-link tags (keep href on <a>)
        $text = preg_replace('/<(b|i|u|s|code|pre)\s[^>]*>/ui', '<$1>', $text);
        // Clean <a> tags: keep only href attribute
        $text = preg_replace_callback('/<a\s[^>]*href=["\']([^"\']*)["\'][^>]*>/ui', function ($m) {
            return '<a href="' . htmlspecialchars($m[1]) . '">';
        }, $text);
        // Collapse horizontal whitespace, preserve newlines
        $text = preg_replace('/[^\S\n]+/u', ' ', $text);
        // Collapse excessive newlines
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }

    /**
     * Clean text for Telegram (strip HTML, trim).
     */
    private function cleanText(string $text): string {
        return trim(strip_tags($text));
    }

    // ── Image rendering ──────────────────────────────────────────────────────

    /**
     * Render blocks to PNG via Puppeteer and save to DB.
     */
    private function renderBlockImages(int $postId, array $blocks, array $profile): array {
        $renderer = new HtmlRendererService();
        $renderer->setSiteProfile($profile);
        $puppeteer = new PuppeteerClient();

        $images = [];

        foreach ($blocks as $i => $block) {
            $content = TextExtractor::blockContent($block);
            if (!is_array($content)) {
                $content = [];
            }

            try {
                $html = $renderer->renderSingleBlockPreview($block['type'], $content);
                $result = $puppeteer->screenshot($html, 800, 2);

                $imageId = $this->db->insert(SeoTelegramRenderedImage::TABLE, [
                    'tg_post_id' => $postId,
                    'block_id'   => (int)$block['id'],
                    'block_type' => $block['type'],
                    'source'     => SeoTelegramRenderedImage::SOURCE_BLOCK_RENDER,
                    'image_data' => $result['image'],
                    'width'      => $result['width'] ?? null,
                    'height'     => $result['height'] ?? null,
                    'sort_order' => $i,
                ]);

                $images[(int)$block['id']] = $imageId;
            } catch (\Throwable $e) {
                logMessage("Telegram: ошибка рендера блока {$block['id']} ({$block['type']}): " . $e->getMessage());
            }
        }

        return $images;
    }

    /**
     * Attach rendered image IDs to post_data messages.
     */
    private function attachImageIds(array $postData, array $blockToImageMap): array {
        foreach ($postData['messages'] as &$msg) {
            if ($msg['type'] === 'media_group' && isset($msg['block_ids'])) {
                $msg['rendered_image_ids'] = [];
                foreach ($msg['block_ids'] as $blockId) {
                    if (isset($blockToImageMap[$blockId])) {
                        $msg['rendered_image_ids'][] = $blockToImageMap[$blockId];
                    }
                }
            } elseif ($msg['type'] === 'photo' && isset($msg['block_id'])) {
                $msg['rendered_image_id'] = $blockToImageMap[$msg['block_id']] ?? null;
            }
        }
        unset($msg);
        return $postData;
    }

    // ── Post-level image CRUD (Phase 2) ──────────────────────────────────────

    /**
     * Render a specific article block and attach its PNG to the post.
     * The block must belong to the post's article.
     */
    public function addBlockImage(int $postId, int $blockId): array {
        $post = $this->loadPost($postId);
        $this->assertEditable($post);

        $block = $this->db->fetchOne(
            'SELECT * FROM seo_article_blocks WHERE id = :id',
            [':id' => $blockId]
        );
        if ($block === null) {
            throw new RuntimeException("Блок #{$blockId} не найден");
        }
        if ((int)$block['article_id'] !== (int)$post['article_id']) {
            throw new RuntimeException("Блок #{$blockId} не принадлежит статье этого поста");
        }

        $profile = $this->loadProfile((int)$post['profile_id']);

        $renderer = new HtmlRendererService();
        $renderer->setSiteProfile($profile);
        $puppeteer = new PuppeteerClient();

        $content = TextExtractor::blockContent($block);
        if (!is_array($content)) {
            $content = [];
        }

        $html = $renderer->renderSingleBlockPreview($block['type'], $content);
        $result = $puppeteer->screenshot($html, 800, 2);

        $this->db->insert(SeoTelegramRenderedImage::TABLE, [
            'tg_post_id' => $postId,
            'block_id'   => (int)$block['id'],
            'block_type' => (string)$block['type'],
            'source'     => SeoTelegramRenderedImage::SOURCE_BLOCK_RENDER,
            'image_data' => $result['image'],
            'width'      => $result['width'] ?? null,
            'height'     => $result['height'] ?? null,
            'sort_order' => $this->nextImageSortOrder($postId),
        ]);

        return $this->getPostWithImages($postId);
    }

    /**
     * Copy an article image (from seo_images) into the post's rendered images pool.
     */
    public function addArticleImage(int $postId, int $articleImageId): array {
        $post = $this->loadPost($postId);
        $this->assertEditable($post);

        $img = $this->db->fetchOne(
            'SELECT id, article_id, name, mime_type, width, height, data_base64 '
            . 'FROM seo_images WHERE id = :id',
            [':id' => $articleImageId]
        );
        if ($img === null) {
            throw new RuntimeException("Изображение #{$articleImageId} не найдено");
        }
        if ((int)$img['article_id'] !== (int)$post['article_id']) {
            throw new RuntimeException('Изображение не принадлежит статье этого поста');
        }

        $mime = (string)($img['mime_type'] ?? 'image/png');
        $data = (string)($img['data_base64'] ?? '');
        if ($data === '') {
            throw new RuntimeException('У исходного изображения пустые данные');
        }

        $this->db->insert(SeoTelegramRenderedImage::TABLE, [
            'tg_post_id' => $postId,
            'block_id'   => null,
            'block_type' => '',
            'source'     => SeoTelegramRenderedImage::SOURCE_ARTICLE_IMAGE,
            'image_data' => $data,
            'custom_meta' => json_encode([
                'article_image_id' => (int)$img['id'],
                'name'             => $img['name'] ?? null,
                'mime'             => $mime,
            ], JSON_UNESCAPED_UNICODE),
            'width'      => isset($img['width']) ? (int)$img['width'] : null,
            'height'     => isset($img['height']) ? (int)$img['height'] : null,
            'sort_order' => $this->nextImageSortOrder($postId),
        ]);

        return $this->getPostWithImages($postId);
    }

    /**
     * Upload a user-provided image file to the post's pool.
     *
     * @param int    $postId
     * @param string $tmpPath  Path to the uploaded tmp file (from $_FILES)
     * @param string $mime     Detected MIME type
     * @param int    $size     File size in bytes
     * @param string $name     Original filename (for metadata)
     */
    public function uploadImage(int $postId, string $tmpPath, string $mime, int $size, string $name): array {
        $post = $this->loadPost($postId);
        $this->assertEditable($post);

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowed, true)) {
            throw new RuntimeException('Недопустимый тип файла: ' . $mime
                . '. Разрешены: ' . implode(', ', $allowed));
        }
        // Telegram photo limit: 10 MB
        if ($size > 10 * 1024 * 1024) {
            throw new RuntimeException('Файл больше 10 MB');
        }

        $binary = @file_get_contents($tmpPath);
        if ($binary === false || $binary === '') {
            throw new RuntimeException('Не удалось прочитать загруженный файл');
        }

        $width = null;
        $height = null;
        $info = @getimagesizefromstring($binary);
        if ($info !== false) {
            $width  = (int)$info[0];
            $height = (int)$info[1];
        }

        $this->db->insert(SeoTelegramRenderedImage::TABLE, [
            'tg_post_id' => $postId,
            'block_id'   => null,
            'block_type' => '',
            'source'     => SeoTelegramRenderedImage::SOURCE_UPLOAD,
            'image_data' => base64_encode($binary),
            'custom_meta' => json_encode([
                'name' => $name,
                'mime' => $mime,
                'size' => $size,
            ], JSON_UNESCAPED_UNICODE),
            'width'      => $width,
            'height'     => $height,
            'sort_order' => $this->nextImageSortOrder($postId),
        ]);

        return $this->getPostWithImages($postId);
    }

    /**
     * Delete a rendered image and strip its references from all messages of
     * the owning post.
     */
    public function deleteImage(int $imageId): array {
        $img = $this->db->fetchOne(
            'SELECT id, tg_post_id FROM ' . SeoTelegramRenderedImage::TABLE
            . ' WHERE id = :id',
            [':id' => $imageId]
        );
        if ($img === null) {
            throw new RuntimeException("Изображение #{$imageId} не найдено");
        }

        $postId = (int)$img['tg_post_id'];
        $post = $this->loadPost($postId);
        $this->assertEditable($post);

        $this->db->delete(
            SeoTelegramRenderedImage::TABLE,
            'id = :id',
            [':id' => $imageId]
        );

        // Strip from post_data.messages
        $postData = json_decode($post['post_data'] ?? '{}', true);
        if (is_array($postData) && isset($postData['messages']) && is_array($postData['messages'])) {
            $changed = false;
            foreach ($postData['messages'] as &$msg) {
                if (isset($msg['rendered_image_id']) && (int)$msg['rendered_image_id'] === $imageId) {
                    unset($msg['rendered_image_id']);
                    $changed = true;
                }
                if (isset($msg['rendered_image_ids']) && is_array($msg['rendered_image_ids'])) {
                    $before = $msg['rendered_image_ids'];
                    $msg['rendered_image_ids'] = array_values(array_filter(
                        $msg['rendered_image_ids'],
                        function ($id) use ($imageId) { return (int)$id !== $imageId; }
                    ));
                    if ($msg['rendered_image_ids'] !== $before) {
                        $changed = true;
                    }
                    if (empty($msg['rendered_image_ids'])) {
                        unset($msg['rendered_image_ids']);
                    }
                }
            }
            unset($msg);

            if ($changed) {
                $this->db->update(
                    SeoTelegramPost::TABLE,
                    'id = :id',
                    ['post_data' => json_encode($postData, JSON_UNESCAPED_UNICODE)],
                    [':id' => $postId]
                );
            }
        }

        return $this->getPostWithImages($postId);
    }

    private function assertEditable(array $post): void {
        if ($post['status'] === SeoTelegramPost::STATUS_SENDING) {
            throw new RuntimeException('Нельзя менять пост во время отправки');
        }
        if ($post['status'] === SeoTelegramPost::STATUS_SENT) {
            throw new RuntimeException('Нельзя менять уже отправленный пост');
        }
    }

    private function nextImageSortOrder(int $postId): int {
        $row = $this->db->fetchOne(
            'SELECT COALESCE(MAX(sort_order), -1) AS m FROM '
            . SeoTelegramRenderedImage::TABLE . ' WHERE tg_post_id = :pid',
            [':pid' => $postId]
        );
        return ((int)($row['m'] ?? -1)) + 1;
    }

    // ── Sending ──────────────────────────────────────────────────────────────

    /**
     * Send a telegram post immediately.
     */
    public function send(int $postId): array {
        $post = $this->loadPost($postId);
        $profile = $this->loadProfile((int)$post['profile_id']);
        $profileEntity = new SeoSiteProfile($profile);

        if (!$profileEntity->hasTelegramConfig()) {
            throw new RuntimeException('Telegram не настроен для профиля');
        }

        $this->db->update(
            SeoTelegramPost::TABLE,
            'id = :id',
            ['status' => SeoTelegramPost::STATUS_SENDING],
            [':id' => $postId],
            ['attempts' => 'attempts + 1']
        );

        $client = new TelegramApiClient($profileEntity->getTgBotToken());
        $channelId = $profileEntity->getTgChannelId();
        $postData = json_decode($post['post_data'], true);
        $messages = $postData['messages'] ?? [];

        if (empty($messages)) {
            $this->db->update(
                SeoTelegramPost::TABLE,
                'id = :id',
                ['status' => SeoTelegramPost::STATUS_FAILED, 'error_message' => 'Пост не содержит сообщений'],
                [':id' => $postId]
            );
            throw new RuntimeException('Пост не содержит сообщений. Проверьте настройки render-блоков в профиле.');
        }

        $messages = $this->sanitizeKeyboards($messages);

        $messageIds = [];
        $postUrl = null;

        try {
            foreach ($messages as $msg) {
                switch ($msg['type']) {
                    case 'media_group':
                        $images = $this->loadRenderedImagesForMessage($msg);
                        $mediaItems = [];
                        foreach ($images as $idx => $img) {
                            $item = ['data' => $img['image_data']];
                            if ($idx === 0 && isset($msg['caption'])) {
                                $item['caption']    = $msg['caption'];
                                $item['parse_mode'] = $msg['parse_mode'] ?? 'HTML';
                            }
                            $mediaItems[] = $item;
                        }
                        $result = $client->sendMediaGroup($channelId, $mediaItems);
                        if (isset($result['result']) && is_array($result['result'])) {
                            foreach ($result['result'] as $r) {
                                $messageIds[] = $r['message_id'];
                            }
                            $firstMsgId = $result['result'][0]['message_id'] ?? null;
                            if ($firstMsgId !== null && $postUrl === null) {
                                $postUrl = $this->buildPostUrl($channelId, $firstMsgId);
                            }
                        }
                        break;

                    case 'photo':
                        $imgData = $this->loadRenderedImageData($msg['rendered_image_id'] ?? 0);
                        if ($imgData !== null) {
                            $opts = [];
                            if (isset($msg['caption'])) {
                                $opts['caption'] = $msg['caption'];
                                $opts['parse_mode'] = $msg['parse_mode'] ?? 'HTML';
                            }
                            if (!empty($msg['keyboard'])) {
                                $opts['reply_markup'] = json_encode(
                                    $msg['keyboard'],
                                    JSON_UNESCAPED_UNICODE
                                );
                            }
                            $result = $client->sendPhoto($channelId, $imgData, $opts);
                            $msgId = $result['result']['message_id'] ?? null;
                            if ($msgId !== null) {
                                $messageIds[] = $msgId;
                                if ($postUrl === null) {
                                    $postUrl = $this->buildPostUrl($channelId, $msgId);
                                }
                            }
                        }
                        break;

                    case 'text':
                        $textOpts = [
                            'parse_mode'               => $msg['parse_mode'] ?? 'HTML',
                            'disable_web_page_preview' => true,
                        ];

                        if (!empty($msg['keyboard'])) {
                            $textOpts['reply_markup'] = json_encode(
                                $msg['keyboard'],
                                JSON_UNESCAPED_UNICODE
                            );
                        }
                        $result = $client->sendMessage($channelId, $msg['text'], $textOpts);
                        $msgId = $result['result']['message_id'] ?? null;
                        if ($msgId !== null) {
                            $messageIds[] = $msgId;
                            if ($postUrl === null) {
                                $postUrl = $this->buildPostUrl($channelId, $msgId);
                            }
                        }
                        break;
                }

                // Small delay between messages to avoid rate limits
                if (count($postData['messages']) > 1) {
                    usleep(500000); // 0.5s
                }
            }

            $this->db->update(
                SeoTelegramPost::TABLE,
                'id = :id',
                [
                    'status'         => SeoTelegramPost::STATUS_SENT,
                    'sent_at'        => date('Y-m-d H:i:s'),
                    'tg_message_ids' => json_encode($messageIds),
                    'tg_post_url'    => $postUrl,
                    'error_message'  => null,
                ],
                [':id' => $postId]
            );

            return $this->getPostWithImages($postId);

        } catch (\Throwable $e) {
            $this->db->update(
                SeoTelegramPost::TABLE,
                'id = :id',
                [
                    'status'        => SeoTelegramPost::STATUS_FAILED,
                    'error_message' => mb_substr($e->getMessage(), 0, 1000),
                ],
                [':id' => $postId]
            );
            throw $e;
        }
    }

    /**
     * Regenerate post text via Copywriter without re-rendering block images.
     * Keeps existing rendered images attached (same block_id → image_id map).
     *
     * @throws RuntimeException when post is not editable (sending/sent) or
     *                          Copywriter unavailable.
     */
    public function recompose(int $postId): array {
        $post = $this->loadPost($postId);

        if (in_array($post['status'], [SeoTelegramPost::STATUS_SENDING, SeoTelegramPost::STATUS_SENT], true)) {
            throw new RuntimeException('Нельзя перегенерировать отправленный пост');
        }

        $article = $this->loadArticle((int)$post['article_id']);
        $profile = $this->loadProfile((int)$post['profile_id']);
        $profileEntity = new SeoSiteProfile($profile);

        if (!$profileEntity->hasTelegramConfig()) {
            throw new RuntimeException('Telegram не настроен для этого профиля');
        }

        $blocks = $this->db->fetchAll(
            'SELECT * FROM seo_article_blocks WHERE article_id = :aid ORDER BY sort_order',
            [':aid' => (int)$post['article_id']]
        );

        $renderableTypes = $profileEntity->getEffectiveTgRenderBlocks();
        $renderableBlocks  = [];
        $textBlocks        = [];
        $formattableBlocks = [];

        foreach ($blocks as $b) {
            if ((int)$b['is_visible'] !== 1) continue;
            if (in_array($b['type'], $renderableTypes, true)) {
                $renderableBlocks[] = $b;
            } elseif ($b['type'] === 'richtext') {
                $textBlocks[] = $b;
            } else {
                $formattableBlocks[] = $b;
            }
        }

        $format = $post['post_format'] ?? 'single';
        if ($format === 'auto') {
            $format = (count($renderableBlocks) > 7) ? 'series' : 'single';
        }

        $postData = $this->composePostData(
            $format,
            $article,
            $profileEntity,
            $renderableBlocks,
            $textBlocks,
            $formattableBlocks
        );

        // Re-attach existing rendered image IDs (block_id → image_id)
        $existing = $this->db->fetchAll(
            'SELECT id, block_id FROM ' . SeoTelegramRenderedImage::TABLE
            . ' WHERE tg_post_id = :pid',
            [':pid' => $postId]
        );
        $blockToImage = [];
        foreach ($existing as $row) {
            $blockToImage[(int)$row['block_id']] = (int)$row['id'];
        }
        $postData = $this->attachImageIds($postData, $blockToImage);

        $this->db->update(
            SeoTelegramPost::TABLE,
            'id = :id',
            [
                'post_data'     => json_encode($postData, JSON_UNESCAPED_UNICODE),
                'status'        => SeoTelegramPost::STATUS_DRAFT,
                'error_message' => null,
            ],
            [':id' => $postId]
        );

        return $this->getPostWithImages($postId);
    }

    /**
     * Schedule a post for later sending.
     */
    public function schedule(int $postId, string $scheduledAt): array {
        $post = $this->loadPost($postId);

        if (!in_array($post['status'], [SeoTelegramPost::STATUS_DRAFT, SeoTelegramPost::STATUS_FAILED], true)) {
            throw new RuntimeException('Можно планировать только черновики или неудачные посты');
        }

        $this->db->update(
            SeoTelegramPost::TABLE,
            'id = :id',
            [
                'status'       => SeoTelegramPost::STATUS_SCHEDULED,
                'scheduled_at' => $scheduledAt,
            ],
            [':id' => $postId]
        );

        return $this->getPostWithImages($postId);
    }

    /**
     * Process all scheduled posts that are due.
     * Called by cron. Also retries failed posts (up to MAX_RETRY_ATTEMPTS).
     */
    public function processScheduledPosts(): int {
        // Scheduled posts that are due
        $rows = $this->db->fetchAll(
            'SELECT id, attempts FROM ' . SeoTelegramPost::TABLE
            . ' WHERE status = :st AND scheduled_at <= NOW()'
            . ' ORDER BY scheduled_at ASC LIMIT 50',
            [':st' => SeoTelegramPost::STATUS_SCHEDULED]
        );

        // Failed posts eligible for retry (exponential backoff via updated_at)
        $failedRows = $this->db->fetchAll(
            'SELECT id, attempts FROM ' . SeoTelegramPost::TABLE
            . ' WHERE status = :st AND attempts < :max'
            . ' AND updated_at <= DATE_SUB(NOW(), INTERVAL POW(2, attempts) MINUTE)'
            . ' ORDER BY updated_at ASC LIMIT 10',
            [':st' => SeoTelegramPost::STATUS_FAILED, ':max' => self::MAX_RETRY_ATTEMPTS]
        );

        $allRows = array_merge($rows, $failedRows);
        $processed = 0;
        $total = count($allRows);

        foreach ($allRows as $idx => $row) {
            try {
                $this->send((int)$row['id']);
                $processed++;
            } catch (\Throwable $e) {
                logMessage("Telegram cron: ошибка отправки поста {$row['id']} (attempt {$row['attempts']}): " . $e->getMessage());

                // If max retries exceeded, mark as permanently failed
                if ((int)$row['attempts'] >= self::MAX_RETRY_ATTEMPTS) {
                    $this->db->update(
                        SeoTelegramPost::TABLE,
                        'id = :id',
                        ['error_message' => 'Превышено макс. кол-во попыток (' . self::MAX_RETRY_ATTEMPTS . '). ' . mb_substr($e->getMessage(), 0, 500)],
                        [':id' => (int)$row['id']]
                    );
                }
            }

            // Throttle to stay under Telegram global rate limit (~30 msg/sec).
            // 200ms gap → 5 posts/sec, well below the cap, leaves headroom for media uploads.
            if ($idx < $total - 1) {
                usleep(200000);
            }
        }

        return $processed;
    }

    // ── CRUD helpers ─────────────────────────────────────────────────────────

    /**
     * Get posts for an article.
     */
    public function getPostsForArticle(int $articleId): array {
        $rows = $this->db->fetchAll(
            'SELECT * FROM ' . SeoTelegramPost::TABLE . ' WHERE article_id = :aid ORDER BY created_at DESC',
            [':aid' => $articleId]
        );

        return array_map(function (array $row) {
            $row['post_data'] = json_decode($row['post_data'] ?? '{}', true);
            $row['tg_message_ids'] = json_decode($row['tg_message_ids'] ?? 'null', true);
            return $row;
        }, $rows);
    }

    /**
     * Get a single post with its rendered images.
     */
    public function getPostWithImages(int $postId): array {
        $post = $this->loadPost($postId);
        $post['post_data'] = json_decode($post['post_data'] ?? '{}', true);
        $post['tg_message_ids'] = json_decode($post['tg_message_ids'] ?? 'null', true);

        $images = $this->db->fetchAll(
            'SELECT id, block_id, block_type, source, custom_meta, width, height, sort_order FROM '
            . SeoTelegramRenderedImage::TABLE . ' WHERE tg_post_id = :pid ORDER BY sort_order',
            [':pid' => $postId]
        );
        foreach ($images as &$row) {
            if (isset($row['custom_meta']) && is_string($row['custom_meta'])) {
                $decoded = json_decode($row['custom_meta'], true);
                $row['custom_meta'] = is_array($decoded) ? $decoded : null;
            }
        }
        unset($row);
        $post['rendered_images'] = $images;

        return $post;
    }

    /**
     * Get rendered image data by ID (base64).
     */
    public function getRenderedImage(int $imageId): ?array {
        return $this->db->fetchOne(
            'SELECT * FROM ' . SeoTelegramRenderedImage::TABLE . ' WHERE id = :id',
            [':id' => $imageId]
        );
    }

    /**
     * Update post data (edit caption/text/keyboard/ordering/count).
     */
    public function updatePost(int $postId, array $data): array {
        $post = $this->loadPost($postId);

        if ($post['status'] === SeoTelegramPost::STATUS_SENDING) {
            throw new RuntimeException('Нельзя редактировать пост во время отправки');
        }

        $updateFields = [];
        if (isset($data['post_data'])) {
            $normalized = $this->validateAndNormalizePostData(
                $data['post_data'],
                $postId,
                $post
            );
            $updateFields['post_data'] = json_encode($normalized, JSON_UNESCAPED_UNICODE);
        }
        if (isset($data['scheduled_at'])) {
            $updateFields['scheduled_at'] = $data['scheduled_at'];
        }

        if (!empty($updateFields)) {
            $this->db->update(
                SeoTelegramPost::TABLE,
                'id = :id',
                $updateFields,
                [':id' => $postId]
            );
        }

        return $this->getPostWithImages($postId);
    }

    /**
     * Normalize incoming post_data: infer message type from image count,
     * enforce Telegram limits, validate keyboard structure + URLs.
     * Throws RuntimeException on violations.
     */
    private function validateAndNormalizePostData(array $incoming, int $postId, array $post): array {
        if (!isset($incoming['messages']) || !is_array($incoming['messages']) || empty($incoming['messages'])) {
            throw new RuntimeException('Пост должен содержать хотя бы одно сообщение');
        }

        $validImageIds = $this->loadPostImageIds($postId);

        $messages = [];
        foreach ($incoming['messages'] as $i => $raw) {
            if (!is_array($raw)) {
                throw new RuntimeException('Сообщение #' . ($i + 1) . ': некорректная структура');
            }
            $messages[] = $this->normalizeMessage($raw, $i + 1, $validImageIds);
        }

        // Preserve article_link / format from previous post_data when possible
        $prev = json_decode($post['post_data'] ?? '{}', true);
        if (!is_array($prev)) {
            $prev = [];
        }

        return [
            'messages'     => $messages,
            'article_link' => (string)($incoming['article_link'] ?? $prev['article_link'] ?? ''),
            'format'       => (string)($incoming['format'] ?? $prev['format'] ?? 'single'),
        ];
    }

    private function normalizeMessage(array $raw, int $num, array $validImageIds): array {
        // Collect image IDs (support both media_group list and single photo refs)
        $imgIds = [];
        if (isset($raw['rendered_image_ids']) && is_array($raw['rendered_image_ids'])) {
            foreach ($raw['rendered_image_ids'] as $id) {
                $id = (int)$id;
                if ($id > 0) {
                    $imgIds[] = $id;
                }
            }
        }
        if (empty($imgIds) && isset($raw['rendered_image_id']) && (int)$raw['rendered_image_id'] > 0) {
            $imgIds[] = (int)$raw['rendered_image_id'];
        }

        // Dedup while preserving order
        $imgIds = array_values(array_unique($imgIds));

        if (count($imgIds) > self::MAX_MEDIA_GROUP_SIZE) {
            throw new RuntimeException(
                'Сообщение #' . $num . ': превышен лимит изображений ('
                . self::MAX_MEDIA_GROUP_SIZE . ')'
            );
        }
        foreach ($imgIds as $id) {
            if (!in_array($id, $validImageIds, true)) {
                throw new RuntimeException(
                    'Сообщение #' . $num . ': изображение #' . $id
                    . ' не принадлежит этому посту'
                );
            }
        }

        // Infer type from image count
        if (count($imgIds) === 0) {
            $type = 'text';
        } elseif (count($imgIds) === 1) {
            $type = 'photo';
        } else {
            $type = 'media_group';
        }

        $parseMode = (string)($raw['parse_mode'] ?? 'MarkdownV2');
        if (!in_array($parseMode, ['MarkdownV2', 'HTML'], true)) {
            $parseMode = 'MarkdownV2';
        }

        $out = [
            'type'       => $type,
            'parse_mode' => $parseMode,
        ];

        if ($type === 'text') {
            $text = (string)($raw['text'] ?? $raw['caption'] ?? '');
            if (trim($text) === '') {
                throw new RuntimeException('Сообщение #' . $num . ': пустой текст');
            }
            if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
                throw new RuntimeException(
                    'Сообщение #' . $num . ': текст превышает лимит '
                    . self::MAX_TEXT_LENGTH . ' символов (' . mb_strlen($text) . ')'
                );
            }
            $out['text'] = $text;
        } else {
            $caption = (string)($raw['caption'] ?? $raw['text'] ?? '');
            if (mb_strlen($caption) > self::MAX_CAPTION_LENGTH) {
                throw new RuntimeException(
                    'Сообщение #' . $num . ': подпись превышает лимит '
                    . self::MAX_CAPTION_LENGTH . ' символов (' . mb_strlen($caption) . ')'
                );
            }
            if ($type === 'photo') {
                $out['rendered_image_id'] = $imgIds[0];
            } else {
                $out['rendered_image_ids'] = $imgIds;
            }
            if ($caption !== '') {
                $out['caption'] = $caption;
            }
        }

        // Preserve source-block refs if the client sent them (useful for re-render in Phase 2)
        if (isset($raw['block_id']) && (int)$raw['block_id'] > 0) {
            $out['block_id'] = (int)$raw['block_id'];
        }
        if (isset($raw['block_type']) && is_string($raw['block_type']) && $raw['block_type'] !== '') {
            $out['block_type'] = $raw['block_type'];
        }
        if (isset($raw['block_ids']) && is_array($raw['block_ids'])) {
            $out['block_ids'] = array_values(array_map('intval', $raw['block_ids']));
        }
        if (isset($raw['block_types']) && is_array($raw['block_types'])) {
            $out['block_types'] = array_values(array_map('strval', $raw['block_types']));
        }

        // Keyboard
        if (!empty($raw['keyboard'])) {
            if ($type === 'media_group') {
                throw new RuntimeException(
                    'Сообщение #' . $num . ': кнопки нельзя прикрепить к группе изображений. '
                    . 'Перенесите кнопки в текстовое сообщение.'
                );
            }
            $kb = $this->normalizeKeyboard($raw['keyboard'], $num);
            if (!empty($kb)) {
                $out['keyboard'] = $kb;
            }
        }

        return $out;
    }

    private function normalizeKeyboard($keyboard, int $msgNum): array {
        if (!is_array($keyboard) || !isset($keyboard['inline_keyboard']) || !is_array($keyboard['inline_keyboard'])) {
            throw new RuntimeException('Сообщение #' . $msgNum . ': некорректная структура клавиатуры');
        }

        $rows = [];
        foreach ($keyboard['inline_keyboard'] as $r => $row) {
            if (!is_array($row)) {
                throw new RuntimeException(
                    'Сообщение #' . $msgNum . ': строка кнопок ' . ($r + 1) . ' некорректна'
                );
            }
            $outRow = [];
            foreach ($row as $btn) {
                if (!is_array($btn)) {
                    continue;
                }
                $text = trim((string)($btn['text'] ?? ''));
                $url  = trim((string)($btn['url'] ?? ''));

                // Silently drop fully empty button slots (user hasn't filled them yet)
                if ($text === '' && $url === '') {
                    continue;
                }
                if ($text === '') {
                    throw new RuntimeException(
                        'Сообщение #' . $msgNum . ': кнопка в строке ' . ($r + 1) . ' без текста'
                    );
                }
                if (mb_strlen($text) > 64) {
                    throw new RuntimeException(
                        'Сообщение #' . $msgNum . ': текст кнопки «'
                        . mb_substr($text, 0, 30) . '…» длиннее 64 символов'
                    );
                }
                if ($url === '') {
                    throw new RuntimeException(
                        'Сообщение #' . $msgNum . ': кнопка «' . $text . '» без ссылки'
                    );
                }
                if (!preg_match('#^https?://#i', $url)) {
                    throw new RuntimeException(
                        'Сообщение #' . $msgNum . ': кнопка «' . $text
                        . '» — недопустимая ссылка (нужна http:// или https://)'
                    );
                }
                $outRow[] = ['text' => $text, 'url' => $url];
            }
            if (!empty($outRow)) {
                $rows[] = $outRow;
            }
        }

        if (empty($rows)) {
            return [];
        }
        return ['inline_keyboard' => $rows];
    }

    private function loadPostImageIds(int $postId): array {
        $rows = $this->db->fetchAll(
            'SELECT id FROM ' . SeoTelegramRenderedImage::TABLE . ' WHERE tg_post_id = :pid',
            [':pid' => $postId]
        );
        $ids = [];
        foreach ($rows as $r) {
            $ids[] = (int)$r['id'];
        }
        return $ids;
    }

    /**
     * Delete a draft/scheduled/failed/sent post. For sent posts, also deletes
     * the messages from the Telegram channel. Message-deletion failures are
     * logged but do not block DB removal — Telegram's 48h channel limit or
     * lack of admin rights must not leave orphan DB rows.
     */
    public function deletePost(int $postId): void {
        $post = $this->loadPost($postId);

        if ($post['status'] === SeoTelegramPost::STATUS_SENDING) {
            throw new RuntimeException('Нельзя удалить пост во время отправки');
        }

        $this->deleteTelegramMessagesFor($post);
        $this->db->delete(SeoTelegramPost::TABLE, 'id = :id', [':id' => $postId]);
    }

    /**
     * Delete all posts for an article (except sending).
     */
    public function deleteAllForArticle(int $articleId): int
    {
        $posts = $this->db->fetchAll(
            'SELECT * FROM ' . SeoTelegramPost::TABLE . ' WHERE article_id = :aid',
            [':aid' => $articleId]
        );

        $deleted = 0;
        foreach ($posts as $p) {
            if ($p['status'] === SeoTelegramPost::STATUS_SENDING) {
                continue;
            }
            $this->deleteTelegramMessagesFor($p);
            $this->db->delete(SeoTelegramPost::TABLE, 'id = :id', [':id' => (int)$p['id']]);
            $deleted++;
        }

        return $deleted;
    }

    /**
     * Best-effort deletion of Telegram messages associated with a post.
     * Failures per-message are logged and swallowed.
     */
    private function deleteTelegramMessagesFor(array $post): void {
        if ($post['status'] !== SeoTelegramPost::STATUS_SENT) {
            return;
        }
        $raw = $post['tg_message_ids'] ?? null;
        $ids = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : null);
        if (!is_array($ids) || empty($ids)) {
            return;
        }

        $profile = $this->db->fetchOne(
            'SELECT * FROM ' . SeoSiteProfile::TABLE . ' WHERE id = :id',
            [':id' => (int)$post['profile_id']]
        );
        if ($profile === null) {
            return;
        }
        $profileEntity = new SeoSiteProfile($profile);
        if (!$profileEntity->hasTelegramConfig()) {
            return;
        }

        $client = new TelegramApiClient($profileEntity->getTgBotToken());
        $channelId = $profileEntity->getTgChannelId();

        foreach ($ids as $mid) {
            $mid = (int)$mid;
            if ($mid <= 0) {
                continue;
            }
            try {
                $client->deleteMessage($channelId, $mid);
            } catch (\Throwable $e) {
                logMessage(
                    "Telegram deleteMessage failed (post #{$post['id']}, msg #{$mid}): " . $e->getMessage(),
                    'WARN'
                );
            }
        }
    }

    /**
     * Test Telegram connection for a profile.
     */
    public function testConnection(string $botToken, string $channelId): array {
        $client = new TelegramApiClient($botToken);
        $chatResult = $client->getChat($channelId);
        $chat = $chatResult['result'] ?? [];

        $memberCount = 0;
        try {
            $memberCount = $client->getChatMemberCount($channelId);
        } catch (\Throwable $e) {
            // Some channels don't allow member count
        }

        $channelName = $chat['title'] ?? $chat['username'] ?? '';
        $channelAvatar = null;

        // Try to download avatar
        $photoId = $chat['photo']['big_file_id'] ?? $chat['photo']['small_file_id'] ?? null;
        if ($photoId !== null) {
            try {
                $avatarData = $client->downloadFile($photoId);
                $channelAvatar = base64_encode($avatarData);
            } catch (\Throwable $e) {
                // Non-critical
            }
        }

        return [
            'channel_name'   => $channelName,
            'channel_type'   => $chat['type'] ?? 'unknown',
            'member_count'   => $memberCount,
            'channel_avatar' => $channelAvatar,
            'username'       => $chat['username'] ?? null,
        ];
    }

    /**
     * Refresh cached channel info on profile.
     */
    public function refreshChannelInfo(int $profileId): array {
        $profile = $this->loadProfile($profileId);
        $profileEntity = new SeoSiteProfile($profile);

        if (!$profileEntity->hasTelegramConfig()) {
            throw new RuntimeException('Telegram не настроен');
        }

        $info = $this->testConnection(
            $profileEntity->getTgBotToken(),
            $profileEntity->getTgChannelId()
        );

        $this->db->update(
            SeoSiteProfile::TABLE,
            'id = :id',
            [
                'tg_channel_name'   => $info['channel_name'],
                'tg_channel_avatar' => $info['channel_avatar'],
            ],
            [':id' => $profileId]
        );

        return $info;
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function loadArticle(int $id): array {
        $row = $this->db->fetchOne('SELECT * FROM seo_articles WHERE id = :id', [':id' => $id]);
        if ($row === null) {
            throw new RuntimeException("Статья #{$id} не найдена");
        }
        return $row;
    }

    private function loadProfile(int $id): array {
        $row = $this->db->fetchOne('SELECT * FROM ' . SeoSiteProfile::TABLE . ' WHERE id = :id', [':id' => $id]);
        if ($row === null) {
            throw new RuntimeException("Профиль #{$id} не найден");
        }
        return $row;
    }

    private function loadPost(int $id): array {
        $row = $this->db->fetchOne('SELECT * FROM ' . SeoTelegramPost::TABLE . ' WHERE id = :id', [':id' => $id]);
        if ($row === null) {
            throw new RuntimeException("Telegram пост #{$id} не найден");
        }
        return $row;
    }

    private function loadRenderedImagesForMessage(array $msg): array {
        $ids = $msg['rendered_image_ids'] ?? [];
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return $this->db->fetchAll(
            'SELECT * FROM ' . SeoTelegramRenderedImage::TABLE
            . " WHERE id IN ({$placeholders}) ORDER BY sort_order",
            array_values($ids)
        );
    }

    private function loadRenderedImageData(int $imageId): ?string {
        if ($imageId <= 0) {
            return null;
        }
        $row = $this->db->fetchOne(
            'SELECT image_data FROM ' . SeoTelegramRenderedImage::TABLE . ' WHERE id = :id',
            [':id' => $imageId]
        );
        return $row !== null ? $row['image_data'] : null;
    }

    private function buildArticleLink(array $article): string {
        $publishedUrl = $article['published_url'] ?? '';
        if ($publishedUrl !== '') {
            return $publishedUrl . '?utm_source=telegram&utm_medium=channel';
        }

        // Fallback to search.php redirect
        return SEO_SEARCH_SCRIPT . '?link=tg_article_' . $article['id'] . '&aid=' . $article['id'];
    }

    private function buildPostUrl(string $channelId, int $messageId): ?string {
        // @username -> t.me/username/messageId
        if (strpos($channelId, '@') === 0) {
            $username = substr($channelId, 1);
            return 'https://t.me/' . $username . '/' . $messageId;
        }

        // Numeric channel IDs: strip -100 prefix for public URL
        $numericId = ltrim($channelId, '-');
        if (strpos($numericId, '100') === 0) {
            $numericId = substr($numericId, 3);
        }
        return 'https://t.me/c/' . $numericId . '/' . $messageId;
    }
}
