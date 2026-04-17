<?php

declare(strict_types=1);

namespace Seo\Service;

use RuntimeException;
use Seo\Database;
use Seo\Entity\SeoArticle;
use Seo\Entity\SeoSiteProfile;
use Seo\Entity\SeoTelegramPost;
use Seo\Entity\SeoTelegramRenderedImage;

class TelegramPostService {

    private const MAX_CAPTION_LENGTH = 1024;
    private const MAX_TEXT_LENGTH = 4096;
    private const MAX_MEDIA_GROUP_SIZE = 10;
    private const MAX_RETRY_ATTEMPTS = 3;

    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
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
        $renderableBlocks = [];
        $textBlocks = [];
        foreach ($blocks as $b) {
            if (in_array($b['type'], $renderableTypes, true) && (int)$b['is_visible'] === 1) {
                $renderableBlocks[] = $b;
            } elseif ($b['type'] === 'richtext' && (int)$b['is_visible'] === 1) {
                $textBlocks[] = $b;
            }
        }

        if (empty($renderableBlocks) && empty($textBlocks)) {
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
            $textBlocks
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
            ['post_data' => json_encode($postData, JSON_UNESCAPED_UNICODE)],
            'id = :id',
            [':id' => $postId]
        );

        return $this->getPostWithImages($postId);
    }

    /**
     * Compose the post_data structure based on format.
     */
    private function composePostData(
        string $format,
        array $article,
        SeoSiteProfile $profile,
        array $renderableBlocks,
        array $textBlocks
    ): array {
        $articleLink = $this->buildArticleLink($article);
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

        return [
            'messages'     => $messages,
            'article_link' => $articleLink,
            'format'       => 'series',
        ];
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
            $content = json_decode($block['content'] ?? '{}', true);
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
                    . "Сделай краткое описание статьи для поста в Telegram-канале.\n"
                    . "Требования:\n"
                    . "- Максимум 500 символов\n"
                    . "- Используй HTML-теги Telegram: <b>, <i>, <a href=\"\">\n"
                    . "- НЕ используй эмодзи и разделители\n"
                    . "- Текст должен быть информативным и побуждать прочитать статью\n"
                    . "- Выдели ключевые факты жирным\n"
                    . "- Отвечай ТОЛЬКО текстом поста, без пояснений",
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
            $content = json_decode($block['content'] ?? '{}', true);
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
            $content = json_decode($block['content'] ?? '{}', true);
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
            $content = json_decode($block['content'] ?? '{}', true);
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
        // Strip all tags except Telegram-supported ones
        $text = strip_tags($html, '<b><i><u><s><a><code><pre>');
        // Remove attributes from non-link tags (keep href on <a>)
        $text = preg_replace('/<(b|i|u|s|code|pre)\s[^>]*>/ui', '<$1>', $text);
        // Clean <a> tags: keep only href attribute
        $text = preg_replace_callback('/<a\s[^>]*href=["\']([^"\']*)["\'][^>]*>/ui', function ($m) {
            return '<a href="' . htmlspecialchars($m[1]) . '">';
        }, $text);
        // Collapse whitespace
        $text = preg_replace('/\s+/u', ' ', $text);
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
            $content = json_decode($block['content'] ?? '{}', true);
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
            ['status' => SeoTelegramPost::STATUS_SENDING],
            'id = :id',
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
                ['status' => SeoTelegramPost::STATUS_FAILED, 'error_message' => 'Пост не содержит сообщений'],
                'id = :id',
                [':id' => $postId]
            );
            throw new RuntimeException('Пост не содержит сообщений. Проверьте настройки render-блоков в профиле.');
        }

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
                                $item['caption'] = $msg['caption'];
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
                        $result = $client->sendMessage($channelId, $msg['text'], [
                            'parse_mode'             => $msg['parse_mode'] ?? 'HTML',
                            'disable_web_page_preview' => true,
                        ]);
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
                [
                    'status'         => SeoTelegramPost::STATUS_SENT,
                    'sent_at'        => date('Y-m-d H:i:s'),
                    'tg_message_ids' => json_encode($messageIds),
                    'tg_post_url'    => $postUrl,
                    'error_message'  => null,
                ],
                'id = :id',
                [':id' => $postId]
            );

            return $this->getPostWithImages($postId);

        } catch (\Throwable $e) {
            $this->db->update(
                SeoTelegramPost::TABLE,
                [
                    'status'        => SeoTelegramPost::STATUS_FAILED,
                    'error_message' => mb_substr($e->getMessage(), 0, 1000),
                ],
                'id = :id',
                [':id' => $postId]
            );
            throw $e;
        }
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
            [
                'status'       => SeoTelegramPost::STATUS_SCHEDULED,
                'scheduled_at' => $scheduledAt,
            ],
            'id = :id',
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
            . ' ORDER BY scheduled_at ASC LIMIT 10',
            [':st' => SeoTelegramPost::STATUS_SCHEDULED]
        );

        // Failed posts eligible for retry (exponential backoff via updated_at)
        $failedRows = $this->db->fetchAll(
            'SELECT id, attempts FROM ' . SeoTelegramPost::TABLE
            . ' WHERE status = :st AND attempts < :max'
            . ' AND updated_at <= DATE_SUB(NOW(), INTERVAL POW(2, attempts) MINUTE)'
            . ' ORDER BY updated_at ASC LIMIT 5',
            [':st' => SeoTelegramPost::STATUS_FAILED, ':max' => self::MAX_RETRY_ATTEMPTS]
        );

        $allRows = array_merge($rows, $failedRows);
        $processed = 0;

        foreach ($allRows as $row) {
            try {
                $this->send((int)$row['id']);
                $processed++;
            } catch (\Throwable $e) {
                logMessage("Telegram cron: ошибка отправки поста {$row['id']} (attempt {$row['attempts']}): " . $e->getMessage());

                // If max retries exceeded, mark as permanently failed
                if ((int)$row['attempts'] >= self::MAX_RETRY_ATTEMPTS) {
                    $this->db->update(
                        SeoTelegramPost::TABLE,
                        ['error_message' => 'Превышено макс. кол-во попыток (' . self::MAX_RETRY_ATTEMPTS . '). ' . mb_substr($e->getMessage(), 0, 500)],
                        'id = :id',
                        [':id' => (int)$row['id']]
                    );
                }
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
            'SELECT id, block_id, block_type, width, height, sort_order FROM '
            . SeoTelegramRenderedImage::TABLE . ' WHERE tg_post_id = :pid ORDER BY sort_order',
            [':pid' => $postId]
        );
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
     * Update post data (edit caption/text).
     */
    public function updatePost(int $postId, array $data): array {
        $post = $this->loadPost($postId);

        if ($post['status'] === SeoTelegramPost::STATUS_SENT) {
            throw new RuntimeException('Нельзя редактировать отправленный пост');
        }

        $updateFields = [];
        if (isset($data['post_data'])) {
            $updateFields['post_data'] = json_encode($data['post_data'], JSON_UNESCAPED_UNICODE);
        }
        if (isset($data['scheduled_at'])) {
            $updateFields['scheduled_at'] = $data['scheduled_at'];
        }

        if (!empty($updateFields)) {
            $this->db->update(
                SeoTelegramPost::TABLE,
                $updateFields,
                'id = :id',
                [':id' => $postId]
            );
        }

        return $this->getPostWithImages($postId);
    }

    /**
     * Delete a draft or scheduled post.
     */
    public function deletePost(int $postId): void {
        $post = $this->loadPost($postId);

        if ($post['status'] === SeoTelegramPost::STATUS_SENDING) {
            throw new RuntimeException('Нельзя удалить пост во время отправки');
        }

        $this->db->delete(SeoTelegramPost::TABLE, 'id = :id', [':id' => $postId]);
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
            [
                'tg_channel_name'   => $info['channel_name'],
                'tg_channel_avatar' => $info['channel_avatar'],
            ],
            'id = :id',
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
