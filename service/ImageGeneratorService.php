<?php

declare(strict_types=1);

namespace Seo\Service;

use RuntimeException;
use Seo\Database;
use Seo\Entity\SeoArticle;
use Seo\Entity\SeoArticleBlock;
use Seo\Entity\SeoArticleIllustration;
use Seo\Entity\SeoAuditLog;
use Seo\Entity\SeoImage;
use Seo\Entity\SeoSiteProfile;
use Seo\Entity\SeoTemplateBlock;
use Seo\Enum\ImagePrompt;
use Throwable;

/**
 * Сервис генерации изображений для SEO-статей.
 *
 * Поток:
 *  1. Собирает контекст статьи (title, keywords, block hint)
 *  2. GPT формирует оптимальный DALL-E промпт на английском
 *  3. DALL-E генерирует изображение (b64_json)
 *  4. Сохраняет в seo_images с привязкой к article/block
 *  5. Обновляет content блока (image_id, image_alt)
 *
 * Режимы:
 *  - generateForBlock()      — одно изображение для конкретного блока
 *  - generateForArticle()    — все image_section блоки статьи
 *  - generateCustom()        — произвольный промпт, привязка к статье
 */
class ImageGeneratorService {

    const GPT_IMAGE_GENERATE_URL = 'https://api.openai.com/v1/images/generations';
    const GOOGLE_IMAGEN_URL = 'https://generativelanguage.googleapis.com/v1beta';

    private Database  $db;
    private GptClient $gpt;
    private string $apiKey;
    private string $googleApiKey;
    private string $dalleModel;
    private string $defaultSize;

    private array $lastResult = [];

    public function __construct(?GptClient $gpt = null) {
        $this->db        = Database::getInstance();
        $this->gpt       = $gpt ?? new GptClient();
        $this->apiKey    = GPT_API_KEY;
        $this->googleApiKey = defined('GOOGLE_API_KEY') ? GOOGLE_API_KEY : '';
        $this->dalleModel = defined('DALLE_MODEL') ? DALLE_MODEL : 'dall-e-3';
        $this->defaultSize = defined('DALLE_SIZE') ? DALLE_SIZE : '1024x1024';
    }


    // $options['size'=>string, 'style'=>'vivid'|'natural', 'quality'=>'standard'|'hd', 'custom_prompt'=>string]
    public function generateForBlock(int $articleId, int $blockId, array $options = []): array {
        $article = $this->loadArticle($articleId);
        $block = $this->loadBlock($blockId);

        if ((int)$block['article_id'] !== $articleId) {
            throw new RuntimeException("Блок #{$blockId} не принадлежит статье #{$articleId}");
        }

        $blockContent = is_string($block['content'])
            ? json_decode($block['content'], true) ?? []
            : ($block['content'] ?? []);

        $profile = null;
        if (!empty($article['profile_id'])) {
            $profile = $this->db->fetchOne(
                "SELECT niche, description FROM " . SeoSiteProfile::TABLE . " WHERE id = ?",
                [(int)$article['profile_id']]
            );
        }

        $context = [
            'article_title'       => $article['title'] ?? '',
            'article_keywords'    => $article['keywords'] ?? '',
            'block_type'          => $block['type'],
            'block_name'          => $block['name'] ?? $block['type'],
            'block_title'         => $blockContent['title'] ?? '',
            'block_text'          => $blockContent['text'] ?? '',
            'block_gpt_prompt'    => $block['gpt_prompt'] ?? '',
            'image_alt'           => $blockContent['image_alt'] ?? '',
            'profile_niche'       => $profile['niche'] ?? '',
            'profile_description' => $profile['description'] ?? '',
        ];

        if ($article['template_id']) {
            $tplBlock = $this->db->fetchOne(
                "SELECT config FROM " . SeoTemplateBlock::SEO_TEMPLATE_BLOCK_TABLE . " WHERE template_id = ? AND type = ? LIMIT 1",
                [$article['template_id'], $block['type']]);

            if ($tplBlock) {
                $cfg = json_decode($tplBlock['config'] ?? '{}', true) ?? [];
                $context['template_hint'] = $cfg['hint'] ?? '';
            }
        }

        $dallePrompt = !empty($options['custom_prompt'])
            ? $options['custom_prompt']
            : $this->craftDallePrompt($context, $options);

        $result = $this->callImageApi($dallePrompt, $options);


        $imageId = $this->saveImage([
            'article_id'  => $articleId,
            'block_id'    => $blockId,
            'name'        => mb_substr($context['block_name'] ?: $context['article_title'], 0, 200),
            'alt_text'    => $blockContent['image_alt'] ?? $context['block_title'] ?? $context['article_title'],
            'mime_type'   => 'image/png',
            'data_base64' => $result['b64_json'],
            'source'      => 'generated',
            'gpt_prompt'  => $dallePrompt,
        ]);

        $blockContent['image_id'] = $imageId;
        if (empty($blockContent['image_alt'])) {
            $blockContent['image_alt'] = $context['block_title'] ?: $context['article_title'];
        }

        $this->db->update(SeoArticleBlock::SEO_ART_BLOCK_TABLE,
            'id = :abid', ['content' => json_encode($blockContent, JSON_UNESCAPED_UNICODE)], [':abid' => $blockId]);

        $this->writeAudit($articleId, 'image_generate', [
            'block_id'       => $blockId,
            'image_id'       => $imageId,
            'dalle_model'    => $this->dalleModel,
            'prompt'         => mb_substr($dallePrompt, 0, 500),
            'revised_prompt' => mb_substr($result['revised_prompt'] ?? '', 0, 500),
        ]);

        $this->lastResult = [
            'image_id'       => $imageId,
            'prompt'         => $dallePrompt,
            'revised_prompt' => $result['revised_prompt'] ?? '',
            'size_bytes'     => (int)(strlen($result['b64_json']) * 3 / 4),
        ];

        return $this->lastResult;
    }


    public function generateForArticle(int $articleId, array $options = []): array {
        $overwrite = $options['overwrite'] ?? false;

        $blocks = $this->db->fetchAll(
            "SELECT * FROM " . SeoArticleBlock::SEO_ART_BLOCK_TABLE . " WHERE article_id = ? ORDER BY sort_order",
            [$articleId]
        );

        $generated = 0;
        $skipped   = 0;
        $results   = [];

        foreach ($blocks as $block) {
            $content = is_string($block['content'])
                ? json_decode($block['content'], true) ?? []
                : ($block['content'] ?? []);

            if (!$overwrite && !empty($content['image_id'])) {
                $skipped++;
                continue;
            }

            try {
                $result = $this->generateForBlock($articleId, (int)$block['id'], $options);
                $results[] = array_merge($result, [
                    'block_id'   => (int)$block['id'],
                    'block_type' => $block['type'],
                    'block_name' => $block['name'],
                ]);
                $generated++;
            } catch (Throwable $e) {
                $results[] = [
                    'block_id' => (int)$block['id'],
                    'error'    => $e->getMessage(),
                ];
            }
        }

        return [
            'generated' => $generated,
            'skipped'   => $skipped,
            'results'   => $results,
        ];
    }

    public function generateCustom(int $articleId, string $prompt, array $options = []): array {
        $result = $this->callImageApi($prompt, $options);

        $imageId = $this->saveImage([
            'article_id'  => $articleId,
            'block_id'    => null,
            'name'        => mb_substr($prompt, 0, 200),
            'alt_text'    => mb_substr($prompt, 0, 300),
            'mime_type'   => 'image/png',
            'data_base64' => $result['b64_json'],
            'source'      => 'generated',
            'gpt_prompt'  => $prompt,
        ]);

        $this->writeAudit($articleId, 'image_generate', [
            'image_id'    => $imageId,
            'dalle_model' => $this->dalleModel,
            'prompt'      => mb_substr($prompt, 0, 500),
        ]);

        return [
            'image_id'       => $imageId,
            'prompt'         => $prompt,
            'revised_prompt' => $result['revised_prompt'] ?? '',
            'size_bytes'     => (int)(strlen($result['b64_json']) * 3 / 4),
        ];
    }

    private function craftDallePrompt(array $context, array $options = []): string {
        $nicheCtx = !empty($context['profile_niche'])
            ? "в нише «{$context['profile_niche']}»"
            : "на профессиональную тему";
        $system = sprintf(ImagePrompt::CRAFT_SYSTEM, $nicheCtx);

        $userMsg = "Контекст статьи:\n";
        $userMsg .= "Заголовок: {$context['article_title']}\n";
        $userMsg .= "Ключевые слова: {$context['article_keywords']}\n";

        if (!empty($context['block_title'])) {
            $userMsg .= "Название секции: {$context['block_title']}\n";
        }
        if (!empty($context['block_text'])) {
            $userMsg .= "Текст секции: " . mb_substr($context['block_text'], 0, 300) . "\n";
        }
        if (!empty($context['template_hint'])) {
            $userMsg .= "Подсказка из шаблона: {$context['template_hint']}\n";
        }
        if (!empty($context['block_gpt_prompt'])) {
            $userMsg .= "Дополнительные инструкции: {$context['block_gpt_prompt']}\n";
        }
        if (!empty($context['image_alt'])) {
            $userMsg .= "Alt-текст (желаемое описание): {$context['image_alt']}\n";
        }

        $userMsg .= "\n" . ImagePrompt::CRAFT_USER_FOOTER;

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $userMsg],
        ];

        $result = $this->gpt->chat($messages, [
            'model'       => $options['prompt_model'] ?? SEO_IMAGE_PROMPT_MODEL,
            'temperature' => SEO_TEMPERATURE_IMAGE,
            'max_tokens'  => SEO_MAX_TOKENS_IMG_PROMPT,
        ]);

        $prompt = trim($result['content']);

        $prompt = trim($prompt, "\"'");

        if (empty($prompt)) {
            throw new RuntimeException('GPT вернул пустой промпт для DALL-E');
        }

        return $prompt;
    }

    private function isGoogleModel(array $options): bool {
        $model = $options['model'] ?? '';
        return $model === 'gemini-2.5-flash-image'
            || strpos($model, 'imagen') === 0;
    }

    private function callImageApi(string $prompt, array $options = []): array {
        $isGoogleModel = $this->isGoogleModel($options);

        if ($isGoogleModel) {
            return $this->callGoogleImagen($prompt, $options);
        }
        return $this->callDalle($prompt, $options);
    }

    private function callDalle(string $prompt, array $options = []): array {
        $size    = $options['size']    ?? $this->defaultSize;
        $quality = $options['quality'] ?? 'standard';
        $style   = $options['style']   ?? 'vivid';

        $payload = [
            'model'           => $this->dalleModel,
            'prompt'          => $prompt,
            'n'               => 1,
            'size'            => $size,
            'response_format' => 'b64_json',
        ];

        if (strpos($this->dalleModel, 'dall-e-3') !== false) {
            $payload['quality'] = $quality;
            $payload['style']   = $style;
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $ch = curl_init(self::GPT_IMAGE_GENERATE_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => AI_REQUEST_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("DALL-E curl error: {$err}");
        }
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            $errMsg = $data['error']['message'] ?? "HTTP {$httpCode}";
            throw new RuntimeException("DALL-E API error: {$errMsg}");
        }

        $imageData = $data['data'][0] ?? null;
        if (!$imageData || empty($imageData['b64_json'])) {
            throw new RuntimeException('DALL-E вернул пустой ответ');
        }

        return [
            'b64_json'       => $imageData['b64_json'],
            'revised_prompt' => $imageData['revised_prompt'] ?? null,
        ];
    }

    private function callGoogleImagen(string $prompt, array $options = []): array {
        if (empty($this->googleApiKey)) {
            throw new RuntimeException('GOOGLE_API_KEY не задан. Задайте его в .env для использования Imagen.');
        }

        $size = $options['size'] ?? $this->defaultSize;
        $aspectMap = [
            '1024x1024' => '1:1',
            '1792x1024' => '16:9',
            '1024x1792' => '9:16',
        ];
        $aspectRatio = $aspectMap[$size] ?? '1:1';

        $url = self::GOOGLE_IMAGEN_URL . "/models/" . $options['model'] . ":generateContent?key=" . $this->googleApiKey;

        $payload = [
            'contents' => [
                ['parts' => [ ['text' => $prompt] ] ],
            ],
            'generationConfig' => [
                'responseModalities' => ["TEXT", "IMAGE"],
                'imageConfig' => ['aspectRatio' => $aspectRatio],
            ],
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => AI_REQUEST_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("Google Imagen curl error: {$err}");
        }
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            $errMsg = $data['error']['message'] ?? "HTTP {$httpCode}";
            throw new RuntimeException("Google Imagen API error: {$errMsg}");
        }

        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        $imageData = null;
        foreach ($parts as $part) {
            if (!empty($part['inlineData']['data'])) {
                $imageData = $part['inlineData']['data'];
                break;
            }
        }

        if (!$imageData) {
            throw new RuntimeException('Google Imagen вернул пустой ответ');
        }

        return [
            'b64_json'       => $imageData,
            'revised_prompt' => null,
        ];
    }


    private function saveImage(array $data): int {
        $binary = base64_decode((string)($data['data_base64'] ?? ''), true);
        $width = null;
        $height = null;
        if ($binary !== false && $binary !== '') {
            $info = @getimagesizefromstring($binary);
            if ($info !== false) {
                $width = $info[0];
                $height = $info[1];
                if (!empty($info['mime'])) {
                    $data['mime_type'] = $info['mime'];
                }
            }
        }

        $stmt = $this->db->getPdo()->prepare(
            "INSERT INTO seo_images (article_id, block_id, name, alt_text, mime_type, width, height, data_base64, source, gpt_prompt)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['article_id'],
            $data['block_id'],
            $data['name'],
            $data['alt_text'],
            $data['mime_type'] ?? 'image/png',
            $width,
            $height,
            $data['data_base64'],
            $data['source'] ?? 'generated',
            $data['gpt_prompt'],
        ]);

        return (int)$this->db->getPdo()->lastInsertId();
    }

    private function loadArticle(int $id): array {
        $r = $this->db->fetchOne("SELECT * FROM " . SeoArticle::SEO_ARTICLE_TABLE . " WHERE id = ?", [$id]);
        if (!$r) throw new RuntimeException("Статья #{$id} не найдена");
        return $r;
    }

    private function loadBlock(int $id): array {
        $r = $this->db->fetchOne("SELECT * FROM " . SeoArticleBlock::SEO_ART_BLOCK_TABLE . " WHERE id = ?", [$id]);
        if (!$r) throw new RuntimeException("Блок #{$id} не найден");
        return $r;
    }

    private function writeAudit(int $articleId, string $action, array $details): void {
        $json = json_encode($details, JSON_UNESCAPED_UNICODE);

        $this->db->insert(SeoAuditLog::SEO_AUDIT_LOG_TABLE,
            SeoAuditLog::articleAction($articleId, $action, 'system/imagegen', ['details' => $json])
            ->toArray());
    }

    public function setDalleModel(string $model): void {
        $this->dalleModel = $model;
    }

    public function getLastResult(): array {
        return $this->lastResult;
    }

    // ─────────────────────────────────────────────────────────────
    // Hero / OG illustration pipeline (article-level).
    // ─────────────────────────────────────────────────────────────

    /**
     * Generate hero banner: GPT crafts prompt from research_dossier + brand kit,
     * Imagen-3 / DALL-E renders 16:9, saved into seo_images and linked via
     * seo_article_illustrations(kind=hero). Replaces previous hero atomically.
     *
     * $options: model (gemini-2.5-flash-image|dall-e-3), size (default 1792x1024),
     *           custom_prompt (skip GPT crafting), prompt_model (GPT model for crafter).
     */
    public function generateHero(int $articleId, array $options = []): array {
        $article = $this->loadArticle($articleId);
        $profile = $this->loadProfileForArticle($article);
        $brandKit = $this->extractBrandKit($profile);

        $size = $options['size'] ?? '1792x1024';
        $options['size'] = $size;
        $options['model'] = $options['model'] ?? 'gemini-2.5-flash-image';

        $prompt = !empty($options['custom_prompt'])
            ? $options['custom_prompt']
            : $this->buildHeroPrompt($articleId, $article, $profile, $brandKit, $options);

        $this->upsertIllustration($articleId, SeoArticleIllustration::KIND_HERO, [
            'status' => SeoArticleIllustration::STATUS_PENDING,
            'prompt' => $prompt,
            'model'  => $options['model'],
        ]);

        try {
            $result = $this->callImageApi($prompt, $options);
        } catch (Throwable $e) {
            $this->upsertIllustration($articleId, SeoArticleIllustration::KIND_HERO, [
                'status' => SeoArticleIllustration::STATUS_FAILED,
                'error'  => $e->getMessage(),
            ]);
            throw $e;
        }

        $imageId = $this->saveImage([
            'article_id'  => $articleId,
            'block_id'    => null,
            'name'        => mb_substr('hero: ' . ($article['title'] ?? ''), 0, 200),
            'alt_text'    => mb_substr($article['title'] ?? '', 0, 300),
            'mime_type'   => 'image/png',
            'data_base64' => $result['b64_json'],
            'source'      => SeoImage::SOURCE_GENERATED,
            'gpt_prompt'  => $prompt,
        ]);

        $this->upsertIllustration($articleId, SeoArticleIllustration::KIND_HERO, [
            'status'     => SeoArticleIllustration::STATUS_READY,
            'prompt'     => $prompt,
            'model'      => $options['model'],
            'image_id'   => $imageId,
            'cost_cents' => $this->estimateImageCostCents($options['model'], $size),
            'error'      => null,
        ]);

        $this->writeAudit($articleId, 'illustration_hero_generate', [
            'image_id' => $imageId,
            'model'    => $options['model'],
            'size'     => $size,
            'prompt'   => mb_substr($prompt, 0, 500),
        ]);

        return [
            'kind'     => SeoArticleIllustration::KIND_HERO,
            'image_id' => $imageId,
            'prompt'   => $prompt,
            'model'    => $options['model'],
        ];
    }

    /**
     * Generate OG image (1200x630) via puppeteer: HTML template + brand kit + title.
     * No image-API call by default — pure HTML→PNG. Cheap & deterministic.
     */
    public function generateOg(int $articleId, array $options = []): array {
        $article  = $this->loadArticle($articleId);
        $profile  = $this->loadProfileForArticle($article);
        $brandKit = $this->extractBrandKit($profile);

        $heroIllust = $this->loadIllustration($articleId, SeoArticleIllustration::KIND_HERO);
        $bgDataUri  = null;
        $heroIsUsable = $heroIllust
            && !empty($heroIllust['image_id'])
            && ($heroIllust['status'] ?? '') === SeoArticleIllustration::STATUS_READY;
        if ($heroIsUsable) {
            $img = $this->db->fetchOne(
                "SELECT mime_type, data_base64 FROM seo_images WHERE id = ?",
                [(int)$heroIllust['image_id']]
            );
            if ($img) {
                $bgDataUri = 'data:' . $img['mime_type'] . ';base64,' . $img['data_base64'];
            }
        }

        $html = $this->renderOgHtml($article, $profile, $brandKit, $bgDataUri);

        $this->upsertIllustration($articleId, SeoArticleIllustration::KIND_OG, [
            'status' => SeoArticleIllustration::STATUS_PENDING,
            'prompt' => null,
            'model'  => 'puppeteer',
        ]);

        try {
            $puppeteer = new PuppeteerClient();
            $shot = $puppeteer->screenshot($html, 1200, 1);
        } catch (Throwable $e) {
            $this->upsertIllustration($articleId, SeoArticleIllustration::KIND_OG, [
                'status' => SeoArticleIllustration::STATUS_FAILED,
                'error'  => $e->getMessage(),
            ]);
            throw $e;
        }

        $imageId = $this->saveImage([
            'article_id'  => $articleId,
            'block_id'    => null,
            'name'        => mb_substr('og: ' . ($article['title'] ?? ''), 0, 200),
            'alt_text'    => mb_substr($article['title'] ?? '', 0, 300),
            'mime_type'   => 'image/png',
            'data_base64' => $shot['image'],
            'source'      => SeoImage::SOURCE_RENDERED,
            'gpt_prompt'  => null,
        ]);

        $this->upsertIllustration($articleId, SeoArticleIllustration::KIND_OG, [
            'status'   => SeoArticleIllustration::STATUS_READY,
            'model'    => 'puppeteer',
            'image_id' => $imageId,
            'error'    => null,
        ]);

        $this->writeAudit($articleId, 'illustration_og_generate', [
            'image_id' => $imageId,
            'width'    => $shot['width']  ?? null,
            'height'   => $shot['height'] ?? null,
        ]);

        return [
            'kind'     => SeoArticleIllustration::KIND_OG,
            'image_id' => $imageId,
            'model'    => 'puppeteer',
        ];
    }

    /**
     * Build hero prompt via GPT: title + keywords + research_dossier (truncated)
     * + brand kit fed into HERO_CRAFT_SYSTEM. Token usage logged under
     * CATEGORY_ARTICLE_ILLUSTRATION.
     */
    public function buildHeroPrompt(
        int $articleId,
        array $article,
        ?array $profile,
        array $brandKit,
        array $options = []
    ): string {
        $niche = $profile['niche'] ?? '';
        $nicheCtx = $niche !== '' ? "в нише «{$niche}»" : "на профессиональную тему";
        $styleHint = $brandKit['illustration_style']
            ?: 'flat vector isometric, ytsaurus.tech style, soft gradients, clean shapes';
        $palette = !empty($brandKit['palette'])
            ? json_encode($brandKit['palette'], JSON_UNESCAPED_UNICODE)
            : '{"primary":"#2563eb","accent":"#22c55e","ink":"#0f172a","bg":"#f8fafc"}';

        $system = sprintf(ImagePrompt::HERO_CRAFT_SYSTEM, $nicheCtx, $styleHint, $palette);

        $userMsg  = "Контекст статьи:\n";
        $userMsg .= "Заголовок: " . ($article['title']    ?? '') . "\n";
        $userMsg .= "Ключевые слова: " . ($article['keywords'] ?? '') . "\n";
        $dossier = (string)($article['research_dossier'] ?? '');
        if ($dossier !== '') {
            $userMsg .= "\nResearch dossier:\n" . mb_substr($dossier, 0, 4000) . "\n";
        }
        $userMsg .= "\n" . ImagePrompt::HERO_CRAFT_USER_FOOTER;

        $this->gpt->setLogContext([
            'category'    => TokenUsageLogger::CATEGORY_ARTICLE_ILLUSTRATION,
            'profile_id'  => $article['profile_id'] ?? null,
            'operation'   => 'build_hero_prompt',
            'entity_type' => 'article',
            'entity_id'   => $articleId,
        ]);

        try {
            $result = $this->gpt->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $userMsg],
            ], [
                'model'       => $options['prompt_model'] ?? SEO_IMAGE_PROMPT_MODEL,
                'temperature' => SEO_TEMPERATURE_IMAGE,
                'max_tokens'  => SEO_MAX_TOKENS_IMG_PROMPT,
            ]);
        } finally {
            $this->gpt->clearLogContext();
        }

        $prompt = trim((string)($result['content'] ?? ''), " \t\n\r\0\x0B\"'");
        if ($prompt === '') {
            throw new RuntimeException('GPT вернул пустой hero prompt');
        }
        return $prompt;
    }

    /**
     * Render OG HTML from public/og-template.html with placeholders substituted.
     * Placeholders: {{title}} {{accent_color}} {{primary_color}} {{ink_color}}
     *               {{bg_color}} {{logo_data_uri}} {{bg_image_data_uri}} {{niche}}
     */
    public function renderOgHtml(
        array $article,
        ?array $profile,
        array $brandKit,
        ?string $bgImageDataUri = null
    ): string {
        $tplPath = dirname(__DIR__) . '/public/og-template.html';
        if (!is_file($tplPath)) {
            throw new RuntimeException("OG template not found: {$tplPath}");
        }
        $tpl = (string)file_get_contents($tplPath);

        $palette = $brandKit['palette'] ?? [];
        $logoDataUri = '';
        if (!empty($brandKit['logo_image_id'])) {
            $img = $this->db->fetchOne(
                "SELECT mime_type, data_base64 FROM seo_images WHERE id = ?",
                [(int)$brandKit['logo_image_id']]
            );
            if ($img) {
                $logoDataUri = 'data:' . $img['mime_type'] . ';base64,' . $img['data_base64'];
            }
        }

        $vars = [
            '{{title}}'             => htmlspecialchars((string)($article['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{{niche}}'             => htmlspecialchars((string)($profile['niche'] ?? ''),  ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{{primary_color}}'     => $this->safeColor($palette['primary'] ?? '#2563eb'),
            '{{accent_color}}'      => $this->safeColor($palette['accent']  ?? '#22c55e'),
            '{{ink_color}}'         => $this->safeColor($palette['ink']     ?? '#0f172a'),
            '{{bg_color}}'          => $this->safeColor($palette['bg']      ?? '#f8fafc'),
            '{{logo_data_uri}}'     => $logoDataUri,
            '{{bg_image_data_uri}}' => $bgImageDataUri ?? '',
        ];
        return strtr($tpl, $vars);
    }

    /** UPSERT a row in seo_article_illustrations. Old image_id is detached and deleted. */
    private function upsertIllustration(int $articleId, string $kind, array $data): int {
        $existing = $this->loadIllustration($articleId, $kind);

        $row = [
            'article_id' => $articleId,
            'kind'       => $kind,
            'prompt'     => array_key_exists('prompt',     $data) ? $data['prompt']     : ($existing['prompt']     ?? null),
            'model'      => array_key_exists('model',      $data) ? $data['model']      : ($existing['model']      ?? null),
            'status'     => array_key_exists('status',     $data) ? $data['status']     : ($existing['status']     ?? SeoArticleIllustration::STATUS_PENDING),
            'error'      => array_key_exists('error',      $data) ? $data['error']      : ($existing['error']      ?? null),
            'cost_cents' => array_key_exists('cost_cents', $data) ? (int)$data['cost_cents'] : (int)($existing['cost_cents'] ?? 0),
            'image_id'   => array_key_exists('image_id',   $data) ? $data['image_id']   : ($existing['image_id']   ?? null),
        ];

        if ($existing) {
            $oldImageId = $existing['image_id'] ?? null;
            $this->db->update(
                SeoArticleIllustration::TABLE,
                'id = :id',
                $row,
                [':id' => (int)$existing['id']]
            );
            $id = (int)$existing['id'];
            if ($oldImageId && !empty($row['image_id']) && (int)$oldImageId !== (int)$row['image_id']) {
                try {
                    $this->db->getPdo()->prepare("DELETE FROM seo_images WHERE id = ?")
                        ->execute([(int)$oldImageId]);
                } catch (Throwable $e) {
                    error_log('[ImageGeneratorService] failed to delete stale image #' . $oldImageId . ': ' . $e->getMessage());
                }
            }
            return $id;
        }

        $this->db->insert(SeoArticleIllustration::TABLE, $row);
        return (int)$this->db->getPdo()->lastInsertId();
    }

    public function loadIllustration(int $articleId, string $kind): ?array {
        $r = $this->db->fetchOne(
            "SELECT * FROM " . SeoArticleIllustration::TABLE . " WHERE article_id = ? AND kind = ?",
            [$articleId, $kind]
        );
        return $r ?: null;
    }

    private function loadProfileForArticle(array $article): ?array {
        if (empty($article['profile_id'])) return null;
        return $this->db->fetchOne(
            "SELECT * FROM " . SeoSiteProfile::TABLE . " WHERE id = ?",
            [(int)$article['profile_id']]
        );
    }

    private function extractBrandKit(?array $profile): array {
        if (!$profile) return ['palette' => [], 'illustration_style' => '', 'logo_image_id' => null];
        $palette = [];
        if (!empty($profile['brand_palette'])) {
            $decoded = is_array($profile['brand_palette'])
                ? $profile['brand_palette']
                : json_decode((string)$profile['brand_palette'], true);
            if (is_array($decoded)) $palette = $decoded;
        }
        return [
            'palette'            => $palette,
            'illustration_style' => (string)($profile['brand_illustration_style'] ?? ''),
            'logo_image_id'      => $profile['brand_logo_image_id'] ?? null,
        ];
    }

    private function safeColor(string $c): string {
        return preg_match('/^#[0-9a-fA-F]{3,8}$|^rgb\([^)]+\)$|^rgba\([^)]+\)$/', $c) ? $c : '#2563eb';
    }

    /** Rough cost estimate in cents per image generation by model+size. */
    private function estimateImageCostCents(string $model, string $size): int {
        if (strpos($model, 'dall-e-3') !== false) {
            return $size === '1792x1024' || $size === '1024x1792' ? 8 : 4; // standard quality
        }
        if (strpos($model, 'gemini') !== false || strpos($model, 'imagen') !== false) {
            return 4;
        }
        return 0;
    }
}