<?php

declare(strict_types=1);

namespace Seo\Service;

use RuntimeException;
use Seo\Database;
use Seo\Entity\SeoArticle;
use Seo\Entity\SeoArticleBlock;
use Seo\Entity\SeoAuditLog;
use Seo\Entity\SeoBlockType;
use Seo\Entity\SeoIntentType;
use Seo\Entity\SeoKeywordCluster;
use Seo\Entity\SeoRawKeyword;
use Seo\Entity\SeoSiteProfile;
use Seo\Entity\SeoTemplate;
use Seo\Entity\SeoTemplateBlock;
use Throwable;

class ArticleGeneratorService {

    private GptClient $gpt;
    private PromptBuilder $prompts;
    private Database $db;

    public function __construct(?GptClient $gpt = null) {
        $this->gpt = $gpt ?? new GptClient();
        $this->prompts = new PromptBuilder();
        $this->db = Database::getInstance();

        $rows = $this->db->fetchAll(
            "SELECT code, gpt_hint FROM " . SeoBlockType::TABLE . " WHERE gpt_hint IS NOT NULL"
        );
        if ($rows) {
            $this->prompts->setBlockTypeHints(array_column($rows, 'gpt_hint', 'code'));
        }
    }

    private function loadSiteProfile(?int $profileId): ?array {
        if ($profileId === null) return null;
        return $this->db->fetchOne(
            "SELECT * FROM " . SeoSiteProfile::TABLE . " WHERE id = ? AND is_active = 1",
            [$profileId]
        );
    }

    private function applyProfileToPrompts(?int $profileId): void {
        $profile = $this->loadSiteProfile($profileId);
        $this->prompts->setSiteProfile($profile);
    }

    public function getTemplateIdsForIntent(string $intentCode): array {
        $rows = $this->db->fetchAll(
            "SELECT id FROM " . SeoTemplate::TABLE
            . " WHERE intent = ? AND is_active = 1 ORDER BY id",
            [$intentCode]
        );
        return array_map(fn($r) => (int)$r['id'], $rows);
    }

    public function resolveTemplateByIntent(string $intentCode, string $strategy = 'first', ?int $clusterId = null): int {
        $candidates = $this->getTemplateIdsForIntent($intentCode);

        if (empty($candidates)) {
            $candidates = $this->getTemplateIdsForIntent('info');
        }
        if (empty($candidates)) {
            throw new RuntimeException("Нет активных шаблонов для интента '{$intentCode}'");
        }

        switch ($strategy) {
            case 'random':
                return $candidates[array_rand($candidates)];
            case 'rotate':
                $idx = ($clusterId ?? 0) % count($candidates);
                return $candidates[$idx];
            case 'first':
            default:
                return $candidates[0];
        }
    }

    public function getTemplatesForIntent(string $intentCode): array {
        return $this->db->fetchAll(
            "SELECT id, name, slug, description, intent FROM " . SeoTemplate::TABLE
            . " WHERE intent = ? AND is_active = 1 ORDER BY id",
            [$intentCode]
        );
    }

    public function loadIntentType(string $intentCode): ?array {
        return $this->db->fetchOne(
            "SELECT * FROM seo_intent_types WHERE code = ? AND is_active = 1",
            [$intentCode]
        );
    }

    public function enrichArticleWithIntent(array $article): array {
        $intentCode = null;

        if (!empty($article['id'])) {
            $cluster = $this->db->fetchOne(
                "SELECT intent FROM seo_keyword_clusters WHERE article_id = ? AND intent IS NOT NULL LIMIT 1",
                [$article['id']]
            );
            if ($cluster) {
                $intentCode = $cluster['intent'];
            }
        }

        if (!$intentCode && !empty($article['template_id'])) {
            $tpl = $this->db->fetchOne(
                "SELECT intent FROM " . SeoTemplate::TABLE . " WHERE id = ?",
                [$article['template_id']]
            );
            if ($tpl && !empty($tpl['intent'])) {
                $intentCode = $tpl['intent'];
            }
        }

        if (!$intentCode) return $article;

        $intent = $this->loadIntentType($intentCode);
        if (!$intent) return $article;

        $article['_intent_code']  = $intentCode;
        $article['_intent_tone']  = $intent['article_tone'] ?? null;
        $article['_intent_open']  = $intent['article_open'] ?? null;
        $article['_intent_label'] = $intent['label_ru'] ?? '';

        return $article;
    }



    public function generateFromCluster(int $clusterId, array $options = []): array {
        $cluster = $this->db->fetchOne(
            "SELECT c.*, it.article_tone, it.article_open, it.label_ru as intent_label
             FROM " . SeoKeywordCluster::TABLE . " c
             LEFT JOIN " . SeoIntentType::TABLE . " it ON c.intent = it.code
             WHERE c.id = ?",
            [$clusterId]
        );
        if (!$cluster) throw new RuntimeException("Кластер #{$clusterId} не найден");

        $intentCode = $cluster['intent'] ?? 'info';
        $strategy   = $options['strategy'] ?? 'rotate';

        $templateId = $cluster['template_id']
            ?? $this->resolveTemplateByIntent($intentCode, $strategy, $clusterId);

        $keywords = $this->db->fetchAll(
            "SELECT keyword FROM " . SeoRawKeyword::TABLE . " WHERE cluster_id = ? ORDER BY volume DESC LIMIT 20",
            [$clusterId]
        );
        $kwString = implode(', ', array_column($keywords, 'keyword'));

        $title = $cluster['name'] ?? 'Без названия';
        $slug = $this->db->fetchOne("SELECT slug FROM " . SeoKeywordCluster::TABLE . " WHERE id = ?", [$clusterId]);

        $this->db->insert(SeoArticle::SEO_ARTICLE_TABLE, [
            'catalog_id'  => null,
            'template_id' => $templateId,
            'title'       => $title,
            'slug'        => $slug['slug'],
            'keywords'    => $kwString,
            'status'      => 'draft',
            'gpt_model'   => $options['model'] ?? GPT_DEFAULT_MODEL,
            'created_by'  => 'cluster_generator',
        ]);
        $articleId = (int)$this->db->getPdo()->lastInsertId();

        $this->db->update('seo_keyword_clusters',
            'id = :cid',
            ['article_id' => $articleId, 'template_id' => $templateId, 'status' => 'article_created'], [':cid' => $clusterId]
        );

        $options['_intent_code'] = $intentCode;
        $result = $this->generateFullPipeline($articleId, $options);

        return [
            'article_id'  => $articleId,
            'template_id' => $templateId,
            'intent'      => $intentCode,
            'intent_label'=> $cluster['intent_label'] ?? '',
            'cluster_id'  => $clusterId,
            'pipeline'    => $result,
        ];
    }




    public function generateFullPipeline(int $articleId, array $options = []): array {
        $article = $this->loadArticle($articleId);
        $this->applyProfileToPrompts($article['profile_id'] ?? null);
        if (empty($article['slug'])) {
            $slug = $this->generateSlug($article['title']);
            $this->db->update(SeoArticle::SEO_ARTICLE_TABLE,
                'id = :id', ['slug' => $slug], [':id' => $articleId]);
            $article['slug'] = $slug;
        }
        $article = $this->enrichArticleWithIntent($article);

        $researchResult = null;
        if (empty($options['skip_research'])) {
            $researchResult = $this->ensureResearch($articleId, $options);
            $article = $this->loadArticle($articleId);
            $article = $this->enrichArticleWithIntent($article);
        }

        $outlineResult = null;
        if (empty($options['skip_outline'])) {
            try {
                $outlineResult = $this->ensureOutline($articleId, $options);
                $article = $this->loadArticle($articleId);
                $article = $this->enrichArticleWithIntent($article);
            } catch (Throwable $e) {
                $outlineResult = ['status' => 'failed', 'error' => $e->getMessage()];
                logMessage('[ArticleGenerator] outline failed, fallback to template: ' . $e->getMessage(), 'WARN');
            }
        }

        $metaResult = $this->generateMeta($articleId, $options, $article);
        $article = $this->loadArticle($articleId);
        $blocksResult = $this->generateAllBlocks($articleId, $options);

        $heroResult = null;
        $ogResult   = null;
        if (!empty($options['auto_hero'])) {
            try {
                $img = new ImageGeneratorService($this->gpt);
                $heroResult = $img->generateHero($articleId, [
                    'model' => $options['hero_model'] ?? null,
                    'size'  => $options['hero_size']  ?? null,
                ]);
            } catch (Throwable $e) {
                $heroResult = ['status' => 'failed', 'error' => $e->getMessage()];
                logMessage('[ArticleGenerator] auto_hero failed: ' . $e->getMessage(), 'WARN');
            }
        }
        if (!empty($options['auto_og'])) {
            try {
                $img = new ImageGeneratorService($this->gpt);
                $ogResult = $img->generateOg($articleId, []);
            } catch (Throwable $e) {
                $ogResult = ['status' => 'failed', 'error' => $e->getMessage()];
                logMessage('[ArticleGenerator] auto_og failed: ' . $e->getMessage(), 'WARN');
            }
        }

        $this->writeAudit($articleId, 'generate', [
            'mode' => 'full_pipeline',
            'research_tokens' => $researchResult['usage'] ?? null,
            'outline_tokens' => $outlineResult['usage'] ?? null,
            'meta_tokens' => $metaResult['usage'],
            'blocks_tokens' => $blocksResult['usage'],
            'blocks_count' => $blocksResult['blocks_generated'],
            'hero' => $heroResult ? ['image_id' => $heroResult['image_id'] ?? null, 'status' => $heroResult['status'] ?? 'ok'] : null,
            'og'   => $ogResult   ? ['image_id' => $ogResult['image_id']   ?? null, 'status' => $ogResult['status']   ?? 'ok'] : null,
        ]);

        return [
            'pipeline' => 'research → outline → meta → blocks' . ($heroResult ? ' → hero' : '') . ($ogResult ? ' → og' : ''),
            'slug' => $article['slug'],
            'research' => $researchResult,
            'outline' => $outlineResult,
            'meta' => $metaResult,
            'blocks' => $blocksResult,
            'hero' => $heroResult,
            'og'   => $ogResult,
        ];
    }

    private function ensureResearch(int $articleId, array $options): ?array {
        $article = $this->loadArticle($articleId);
        $status = $article['research_status'] ?? 'none';
        $hasBody = !empty($article['research_dossier']);
        $force = !empty($options['force_research']);
        if (!$force && $hasBody && $status === 'ready') {
            return ['status' => 'skipped', 'reason' => 'already_ready'];
        }
        $svc = new ArticleResearchService($this->gpt);
        return $svc->buildDossier($articleId, [
            'model' => $options['research_model'] ?? null,
            'force' => $force,
        ]);
    }

    private function ensureOutline(int $articleId, array $options): ?array {
        $article = $this->loadArticle($articleId);
        $status = $article['outline_status'] ?? 'none';
        $hasBody = !empty($article['article_outline']);
        $force = !empty($options['force_outline']);
        if (!$force && $hasBody && $status === 'ready') {
            return ['status' => 'skipped', 'reason' => 'already_ready'];
        }
        $svc = new ArticleOutlineService($this->gpt);
        return $svc->buildOutline($articleId, [
            'model' => $options['outline_model'] ?? null,
            'force' => $force,
        ]);
    }

    /**
     * Decode persisted outline JSON. Returns array of section dicts or [] if empty/invalid.
     */
    private function loadOutlineSections(array $article): array {
        if (($article['outline_status'] ?? 'none') !== 'ready') return [];
        $raw = (string)($article['article_outline'] ?? '');
        if ($raw === '') return [];
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) return [];
        $sections = $decoded['sections'] ?? [];
        return is_array($sections) ? $sections : [];
    }

    /**
     * Convert outline section to a synthetic template-block dict
     * compatible with PromptBuilder/findExistingBlock/insert flow.
     */
    private function sectionToTemplateBlock(array $section, int $index): array {
        $type = (string)($section['block_type'] ?? 'richtext');
        $name = (string)($section['h2_title'] ?? ($section['id'] ?? ('section_' . $index)));
        $config = [
            'hint'   => (string)($section['content_brief'] ?? ''),
            'fields' => [],
        ];
        // Outline sections target long-form articles. Enable extended subblock palette
        // (callout/code/figure/table/footnote) so RichtextBlockRenderer renders lf-* layout.
        if ($type === 'richtext') {
            $config['block_types'] = [
                'paragraph', 'heading', 'list', 'highlight', 'quote',
                'callout', 'code', 'figure', 'table', 'footnote',
            ];
        }
        return [
            'type'        => $type,
            'name'        => $name,
            'sort_order'  => $index,
            'config'      => json_encode($config, JSON_UNESCAPED_UNICODE),
        ];
    }

    public function generateFullPipelineSSE(int $articleId, array $options = []): void {
        $article = $this->loadArticle($articleId);

        if (empty($article['slug'])) {
            $slug = $this->generateSlug($article['title']);
            $this->db->update(SeoArticle::SEO_ARTICLE_TABLE,
                'id = :id', ['slug' => $slug], [':id' => $articleId]);
            $this->sendSSE('slug_generated', ['slug' => $slug]);
        }

        if (empty($options['skip_research'])) {
            $this->sendSSE('research_start', ['article_id' => $articleId]);
            try {
                $researchResult = $this->ensureResearch($articleId, $options);
                $this->sendSSE('research_done', [
                    'status' => $researchResult['status'] ?? 'ok',
                    'usage'  => $researchResult['usage'] ?? null,
                    'length' => isset($researchResult['dossier']) ? strlen($researchResult['dossier']) : null,
                ]);
            } catch (Throwable $e) {
                $this->sendSSE('research_error', ['error' => $e->getMessage()]);
            }
        }

        if (empty($options['skip_outline'])) {
            $this->sendSSE('outline_start', ['article_id' => $articleId]);
            try {
                $outlineResult = $this->ensureOutline($articleId, $options);
                $this->sendSSE('outline_done', [
                    'status'   => $outlineResult['status'] ?? 'ok',
                    'usage'    => $outlineResult['usage'] ?? null,
                    'sections' => $outlineResult['sections'] ?? null,
                ]);
            } catch (Throwable $e) {
                $this->sendSSE('outline_error', ['error' => $e->getMessage()]);
            }
        }

        $this->sendSSE('meta_start', ['article_id' => $articleId]);
        try {
            $metaResult = $this->generateMeta($articleId, $options);
            $this->sendSSE('meta_done', [
                'meta_title' => $metaResult['meta_title'],
                'meta_description' => $metaResult['meta_description'],
                'article_plan' => $metaResult['article_plan'],
                'usage' => $metaResult['usage'],
            ]);
        } catch (Throwable $e) {
            $this->sendSSE('meta_error', ['error' => $e->getMessage()]);
        }
        $this->generateAllBlocksSSE($articleId, $options);

        if (!empty($options['auto_hero'])) {
            $this->sendSSE('hero_start', ['article_id' => $articleId]);
            try {
                $img = new ImageGeneratorService($this->gpt);
                $heroResult = $img->generateHero($articleId, [
                    'model' => $options['hero_model'] ?? null,
                    'size'  => $options['hero_size']  ?? null,
                ]);
                $this->sendSSE('hero_done', [
                    'image_id' => $heroResult['image_id'] ?? null,
                    'model'    => $heroResult['model']    ?? null,
                ]);
            } catch (Throwable $e) {
                $this->sendSSE('hero_error', ['error' => $e->getMessage()]);
            }
        }

        if (!empty($options['auto_og'])) {
            $this->sendSSE('og_start', ['article_id' => $articleId]);
            try {
                $img = new ImageGeneratorService($this->gpt);
                $ogResult = $img->generateOg($articleId, []);
                $this->sendSSE('og_done', [
                    'image_id' => $ogResult['image_id'] ?? null,
                ]);
            } catch (Throwable $e) {
                $this->sendSSE('og_error', ['error' => $e->getMessage()]);
            }
        }
    }

    public function generateMeta(int $articleId, array $options = [], ?array $enrichedArticle = null): array {
        if ($enrichedArticle === null) {
            $article = $this->loadArticle($articleId);
            $this->applyProfileToPrompts($article['profile_id'] ?? null);
            $article = $this->enrichArticleWithIntent($article);
        } else {
            $article = $enrichedArticle;
        }
        $templateBlocks = !empty($article['template_id'])
            ? $this->loadTemplateBlocks((int)$article['template_id'])
            : [];

        $messages = $this->prompts->buildMetaPrompt($article, $templateBlocks);

        $gptOptions = [
            'model' => $options['model'] ?? $article['gpt_model'] ?? GPT_DEFAULT_MODEL,
            'temperature' => $options['temperature'] ?? 0.5,
            'max_tokens' => $options['max_tokens'] ?? 500,
        ];

        $this->gpt->setLogContext([
            'category'    => TokenUsageLogger::CATEGORY_ARTICLE_CREATE,
            'operation'   => 'generate_meta',
            'profile_id'  => $article['profile_id'] ?? null,
            'entity_type' => 'article',
            'entity_id'   => $articleId,
        ]);
        $result = $this->gpt->chatJson($messages, $gptOptions);
        $meta = $result['data'];


        $article = $this->loadArticle($articleId);
        $updateFields = [
            'meta_title'       => $meta['meta_title'] ?? null,
            'meta_description' => $meta['meta_description'] ?? null,
            'article_plan'     => $meta['article_plan'] ?? null,
            'meta_keywords'    => $meta['meta_keywords'] ?? null,
        ];

        if (empty($article['slug']) && !empty($meta['meta_title'])) {
            $updateFields['slug'] = $this->generateSlug($meta['meta_title']);
        }

        $this->db->update(SeoArticle::SEO_ARTICLE_TABLE, 'id = :aid',
            $updateFields, [':aid' => $articleId]);

        $this->writeAudit($articleId, 'generate', [
            'mode' => 'meta',
            'model' => $result['model'],
            'tokens' => $result['usage'],
        ]);

        return [
            'meta_title'       => $meta['meta_title'] ?? '',
            'meta_description' => $meta['meta_description'] ?? '',
            'article_plan'     => $meta['article_plan'] ?? '',
            'meta_keywords'    => $meta['meta_keywords'] ?? '',
            'usage'  => $result['usage'],
            'model'  => $result['model'],
        ];
    }

    public function generateAllBlocks(int $articleId, array $options = []): array {
        if (empty($options['skip_research'])) {
            $this->ensureResearch($articleId, $options);
        }
        if (empty($options['skip_outline'])) {
            try { $this->ensureOutline($articleId, $options); }
            catch (Throwable $e) { /* fallback to template */ }
        }
        $article = $this->loadArticle($articleId);
        $this->applyProfileToPrompts($article['profile_id'] ?? null);
        $article = $this->enrichArticleWithIntent($article);

        $outlineSections = $this->loadOutlineSections($article);
        if (!empty($outlineSections)) {
            return $this->generateAllBlocksFromOutline($articleId, $article, $outlineSections, $options);
        }

        $template = $this->loadTemplate((int)$article['template_id']);
        $templateBlocks = $this->loadTemplateBlocks((int)$article['template_id']);

        if (empty($templateBlocks)) throw new RuntimeException('У шаблона нет блоков');

        $overwrite = $options['overwrite'] ?? true;

        $messages = $this->prompts->buildFullArticlePrompt(
            $article, $templateBlocks,
            ($template['gpt_system_prompt'] ?? '')
        );

        $gptOptions = [
            'model' => $options['model'] ?? $article['gpt_model'] ?? GPT_DEFAULT_MODEL,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 8000,
        ];

        $this->gpt->setLogContext([
            'category'    => TokenUsageLogger::CATEGORY_ARTICLE_CREATE,
            'operation'   => 'generate_all_blocks',
            'profile_id'  => $article['profile_id'] ?? null,
            'entity_type' => 'article',
            'entity_id'   => $articleId,
        ]);
        $result = $this->gpt->chatJson($messages, $gptOptions);
        $data   = $result['data'];

        $existingBlocks = $this->loadArticleBlocks($articleId);
        $blocksGenerated = 0;

        foreach ($templateBlocks as $i => $tb) {
            $key = "block_{$i}";
            $blockContent = $data[$key] ?? null;
            if ($blockContent === null) continue;

            $blockContent = $this->unwrapBlockContent($blockContent, $tb['type'] ?? '');

            $existing = $this->findExistingBlock($existingBlocks, $tb);
            $contentJson = json_encode($blockContent, JSON_UNESCAPED_UNICODE);

            if ($existing && $overwrite) {
                $this->db->update(SeoArticleBlock::SEO_ART_BLOCK_TABLE,
                    'id = :id',
                    ['content' => $contentJson, 'name' => $tb['name']], [':id' => $existing['id']]);
            } elseif (!$existing) {
                try {
                    $this->db->insert(SeoArticleBlock::SEO_ART_BLOCK_TABLE, [
                        'article_id' => $articleId,
                        'type' => $tb['type'],
                        'name' => $tb['name'],
                        'content' => $contentJson,
                        'sort_order' => $tb['sort_order'],
                        'is_visible' => 1,
                    ]);
                } catch (Throwable $e) {
                    logMessage(print_r($e, true), 'ERROR');
                    throw $e;
                }
            }
            $blocksGenerated++;
        }

        $this->updateGenerationLog($articleId, [
            'mode' => 'all_blocks', 'model' => $result['model'],
            'usage' => $result['usage'], 'blocks' => $blocksGenerated,
            'timestamp' => date('c'),
        ]);
        $this->writeAudit($articleId, 'generate', [
            'mode' => 'all_blocks', 'blocks' => $blocksGenerated,
            'model' => $result['model'], 'tokens' => $result['usage'],
        ]);

        return [
            'blocks_generated' => $blocksGenerated,
            'usage' => $result['usage'],
            'model' => $result['model'],
        ];
    }

    /**
     * Outline-driven block generation. One GPT call per section,
     * persisted as article blocks with sort_order = section index.
     * Section's content_brief lands in gpt_prompt of the persisted block.
     */
    private function generateAllBlocksFromOutline(int $articleId, array $article, array $sections, array $options): array {
        $template = !empty($article['template_id']) ? $this->loadTemplate((int)$article['template_id']) : null;
        $systemPrompt = $template['gpt_system_prompt'] ?? null;

        $allTypes = array_map(fn($s) => (string)($s['block_type'] ?? ''), $sections);
        $dossierIndex = $this->loadDossierIndex($article);

        // Wipe stale template-driven blocks so outline becomes the single source of truth
        $overwrite = $options['overwrite'] ?? true;
        if ($overwrite) {
            $this->db->delete(SeoArticleBlock::SEO_ART_BLOCK_TABLE, 'article_id = :aid', [':aid' => $articleId]);
        }
        $existingBlocks = $this->loadArticleBlocks($articleId);

        $totalUsage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
        $blocksGenerated = 0;
        $modelUsed = null;
        $coverageReport = ['ok' => 0, 'partial' => 0, 'missing' => 0, 'sections' => []];

        $previousSummaries = [];
        foreach ($sections as $i => $section) {
            $tb = $this->sectionToTemplateBlock($section, $i);
            $messages = $this->prompts->buildBlockPrompt(
                $article, $tb, [], $systemPrompt, $allTypes, $section, $previousSummaries
            );

            $gptOptions = [
                'model'       => $options['model']       ?? $article['gpt_model'] ?? GPT_DEFAULT_MODEL,
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens'  => $options['max_tokens']  ?? 4000,
            ];

            $this->gpt->setLogContext([
                'category'    => TokenUsageLogger::CATEGORY_ARTICLE_CREATE,
                'operation'   => 'generate_block_outline',
                'profile_id'  => $article['profile_id'] ?? null,
                'entity_type' => 'article',
                'entity_id'   => $articleId,
            ]);
            $result = $this->gpt->chatJson($messages, $gptOptions);
            $modelUsed = $result['model'] ?? $modelUsed;

            foreach (['prompt_tokens', 'completion_tokens', 'total_tokens'] as $k) {
                $totalUsage[$k] += ($result['usage'][$k] ?? 0);
            }

            $unwrapped = $this->unwrapBlockContent($result['data'], $tb['type']);
            $contentJson = json_encode($unwrapped, JSON_UNESCAPED_UNICODE);

            $existing = $this->findExistingBlock($existingBlocks, $tb);
            if ($existing && $overwrite) {
                $this->db->update(SeoArticleBlock::SEO_ART_BLOCK_TABLE, 'id = :id', [
                    'content'    => $contentJson,
                    'name'       => $tb['name'],
                    'gpt_prompt' => (string)($section['content_brief'] ?? ''),
                ], [':id' => $existing['id']]);
            } elseif (!$existing) {
                $this->db->insert(SeoArticleBlock::SEO_ART_BLOCK_TABLE, [
                    'article_id' => $articleId,
                    'type'       => $tb['type'],
                    'name'       => $tb['name'],
                    'content'    => $contentJson,
                    'sort_order' => $tb['sort_order'],
                    'is_visible' => 1,
                    'gpt_prompt' => (string)($section['content_brief'] ?? ''),
                ]);
            }
            $blocksGenerated++;

            $cov = $this->factsCoverage($section, $dossierIndex, $unwrapped);
            $coverageReport['sections'][] = [
                'section_id' => $section['id'] ?? null,
                'h2'         => $section['h2_title'] ?? null,
            ] + $cov;
            $coverageReport[$cov['verdict']]++;

            $previousSummaries[] = [
                'h2'      => (string)($section['h2_title'] ?? $tb['name'] ?? ''),
                'summary' => $this->buildSectionSummary($unwrapped),
            ];
            if (count($previousSummaries) > 2) {
                $previousSummaries = array_slice($previousSummaries, -2);
            }
        }

        $this->updateGenerationLog($articleId, [
            'mode' => 'outline_blocks', 'model' => $modelUsed,
            'usage' => $totalUsage, 'blocks' => $blocksGenerated,
            'coverage' => ['ok' => $coverageReport['ok'], 'partial' => $coverageReport['partial'], 'missing' => $coverageReport['missing']],
            'timestamp' => date('c'),
        ]);
        $this->writeAudit($articleId, 'generate', [
            'mode' => 'outline_blocks', 'blocks' => $blocksGenerated,
            'model' => $modelUsed, 'tokens' => $totalUsage,
            'coverage' => $coverageReport,
        ]);

        return [
            'blocks_generated' => $blocksGenerated,
            'usage' => $totalUsage,
            'model' => $modelUsed,
            'source' => 'outline',
        ];
    }

    public function generateSingleBlock(int $articleId, int $blockId, array $options = []): array {
        $article = $this->loadArticle($articleId);
        $this->applyProfileToPrompts($article['profile_id'] ?? null);
        $block = $this->loadArticleBlock($blockId, $articleId);

        $templateBlock = $this->resolveTemplateBlock($article, $block);

        $allBlocks = $this->loadArticleBlocks($articleId);
        $allTypes = array_map(fn($b) => $b['type'], $allBlocks);

        $template = $article['template_id']
            ? $this->loadTemplate((int)$article['template_id'])
            : null;

        $messages = $this->prompts->buildBlockPrompt(
            $article, $templateBlock, $block,
            ($template['gpt_system_prompt'] ?? ''), $allTypes
        );

        $gptOptions = [
            'model' => $options['model'] ?? $article['gpt_model'] ?? GPT_DEFAULT_MODEL,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 4000,
        ];

        $this->gpt->setLogContext([
            'category'    => TokenUsageLogger::CATEGORY_ARTICLE_CREATE,
            'operation'   => 'generate_single_block',
            'profile_id'  => $article['profile_id'] ?? null,
            'entity_type' => 'article',
            'entity_id'   => $articleId,
        ]);
        $result = $this->gpt->chatJson($messages, $gptOptions);
        $unwrapped = $this->unwrapBlockContent($result['data'], $block['type'] ?? '');
        $contentJson = json_encode($unwrapped, JSON_UNESCAPED_UNICODE);

        $this->db->update(SeoArticleBlock::SEO_ART_BLOCK_TABLE,
            'id = :bid AND article_id = :aid',
            ['content' => $contentJson],
            [':bid' => $blockId, ':aid' => $articleId]);

        $this->updateGenerationLog($articleId, [
            'mode' => 'single_block', 'block_id' => $blockId,
            'block_type' => $block['type'], 'model' => $result['model'],
            'usage' => $result['usage'], 'timestamp' => date('c'),
        ]);
        $this->writeAudit($articleId, 'regenerate', [
            'block_id' => $blockId, 'block_type' => $block['type'],
            'model' => $result['model'], 'tokens' => $result['usage'],
        ]);

        return [
            'content' => $unwrapped,
            'usage' => $result['usage'],
            'model' => $result['model'],
        ];
    }

    private function loadDossierIndex(array $article): array {
        $raw = trim((string)($article['research_dossier'] ?? ''));
        if ($raw === '') return [];
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) return [];
        return ArticleResearchService::indexById($decoded);
    }

    /**
     * Score whether the generated block actually mentions the facts it was
     * supposed to use. For each source_fact ID, we extract a few content
     * tokens (≥4-letter words) from the dossier item and check at least one
     * appears in the block content (case-insensitive).
     *
     * Verdicts:
     *   ok      — every referenced fact has at least one token hit
     *   partial — at least one fact covered, at least one missing
     *   missing — zero facts covered (or no source_facts at all)
     */
    private function factsCoverage(array $section, array $dossierIndex, $content): array {
        $ids = $section['source_facts'] ?? [];
        if (!is_array($ids) || empty($ids)) {
            return ['verdict' => 'missing', 'covered' => [], 'uncovered' => [], 'total' => 0];
        }
        $haystack = mb_strtolower($this->flattenContentText($content), 'UTF-8');
        if ($haystack === '') {
            return [
                'verdict'   => 'missing',
                'covered'   => [],
                'uncovered' => array_values(array_map('strval', $ids)),
                'total'     => count($ids),
            ];
        }
        $covered = [];
        $uncovered = [];
        foreach ($ids as $rawId) {
            $id = (string)$rawId;
            if (!isset($dossierIndex[$id])) {
                $uncovered[] = $id;
                continue;
            }
            $tokens = $this->factTokens($dossierIndex[$id]);
            $hit = false;
            foreach ($tokens as $tok) {
                if ($tok !== '' && mb_strpos($haystack, $tok, 0, 'UTF-8') !== false) {
                    $hit = true;
                    break;
                }
            }
            if ($hit) $covered[] = $id;
            else      $uncovered[] = $id;
        }
        $verdict = empty($covered) ? 'missing' : (empty($uncovered) ? 'ok' : 'partial');
        return [
            'verdict'   => $verdict,
            'covered'   => $covered,
            'uncovered' => $uncovered,
            'total'     => count($ids),
        ];
    }

    private function flattenContentText($node): string {
        if (is_string($node)) return $node;
        if (!is_array($node)) return '';
        $out = '';
        foreach ($node as $v) {
            $piece = $this->flattenContentText($v);
            if ($piece !== '') $out .= ' ' . $piece;
        }
        return $out;
    }

    /**
     * Pull keyword tokens from a dossier item: words ≥4 chars, lowered, deduped.
     * Limits to 8 tokens to keep the substring scan cheap.
     */
    private function factTokens(array $item): array {
        $section = (string)($item['_section'] ?? '');
        $sources = [];
        switch ($section) {
            case 'facts':         $sources = [$item['claim'] ?? '', $item['evidence'] ?? '']; break;
            case 'benchmarks':    $sources = [$item['metric'] ?? '', $item['value'] ?? '']; break;
            case 'comparisons':   $sources = [$item['x'] ?? '', $item['y'] ?? '', $item['summary'] ?? '']; break;
            case 'counter_theses':$sources = [$item['thesis'] ?? '']; break;
            case 'quotes_cases':  $sources = [$item['text'] ?? '', $item['attribution'] ?? '']; break;
            case 'terms':         $sources = [$item['term'] ?? '']; break;
            case 'entities':      $sources = [$item['name'] ?? '']; break;
            case 'sources':       $sources = [$item['title'] ?? '']; break;
        }
        $text = mb_strtolower(implode(' ', array_filter($sources, 'is_string')), 'UTF-8');
        if ($text === '') return [];
        $words = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = [];
        foreach ($words as $w) {
            if (mb_strlen($w, 'UTF-8') < 4) continue;
            $tokens[$w] = true;
            if (count($tokens) >= 8) break;
        }
        return array_keys($tokens);
    }

    /**
     * Naive summary: walks the block content array and grabs the first
     * non-empty paragraph/text/heading, trimmed to ~220 chars. No GPT call.
     */
    private function buildSectionSummary($content): string {
        $text = $this->extractFirstText($content);
        $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
        if ($text === '') return '';
        if (mb_strlen($text) > 220) {
            $text = mb_substr($text, 0, 220);
            $text = preg_replace('/\s+\S*$/u', '', $text) . '…';
        }
        return $text;
    }

    private function extractFirstText($node): string {
        if (is_string($node)) return $node;
        if (!is_array($node)) return '';
        foreach (['text', 'content', 'paragraph', 'body', 'description', 'lead', 'subtitle', 'title'] as $k) {
            if (isset($node[$k]) && is_string($node[$k]) && trim($node[$k]) !== '') {
                return $node[$k];
            }
        }
        foreach ($node as $v) {
            $found = $this->extractFirstText($v);
            if ($found !== '') return $found;
        }
        return '';
    }

    /**
     * Разворачивает контент, обёрнутый GPT во внешний объект с именем типа
     * или в общие обёртки: {comparison_table: {...}}, {content: {...}}, {data: {...}}, {fields: {...}}.
     */
    private function unwrapBlockContent($data, string $type)
    {
        if (!is_array($data)) return $data;
        $wrappers = array_unique(array_filter([$type, 'content', 'data', 'fields', 'block', 'result']));
        $guard = 0;
        while ($guard++ < 3 && is_array($data) && count($data) === 1) {
            $key = array_key_first($data);
            if (!is_string($key) || !in_array($key, $wrappers, true)) break;
            $inner = $data[$key];
            if (!is_array($inner)) break;
            $data = $inner;
        }
        return $data;
    }

    public function generateAllBlocksSSE(int $articleId, array $options = []): void {
        if (empty($options['skip_research'])) {
            $this->sendSSE('research_start', ['article_id' => $articleId]);
            try {
                $r = $this->ensureResearch($articleId, $options);
                $this->sendSSE('research_done', [
                    'status' => $r['status'] ?? 'ok',
                    'usage'  => $r['usage'] ?? null,
                    'length' => isset($r['dossier']) ? strlen($r['dossier']) : null,
                ]);
            } catch (Throwable $e) {
                $this->sendSSE('research_error', ['error' => $e->getMessage()]);
            }
        }
        if (empty($options['skip_outline'])) {
            $this->sendSSE('outline_start', ['article_id' => $articleId]);
            try {
                $o = $this->ensureOutline($articleId, $options);
                $this->sendSSE('outline_done', [
                    'status'   => $o['status'] ?? 'ok',
                    'usage'    => $o['usage'] ?? null,
                    'sections' => $o['sections'] ?? null,
                ]);
            } catch (Throwable $e) {
                $this->sendSSE('outline_error', ['error' => $e->getMessage()]);
            }
        }
        $article = $this->loadArticle($articleId);
        $this->applyProfileToPrompts($article['profile_id'] ?? null);
        $article = $this->enrichArticleWithIntent($article);

        $outlineSections = $this->loadOutlineSections($article);
        if (!empty($outlineSections)) {
            $this->generateAllBlocksSSEFromOutline($articleId, $article, $outlineSections, $options);
            return;
        }

        $template = $this->loadTemplate((int)$article['template_id']);
        $templateBlocks = $this->loadTemplateBlocks((int)$article['template_id']);

        if (empty($templateBlocks)) {
            $this->sendSSE('error', ['message' => 'У шаблона нет блоков']);
            return;
        }

        $overwrite = $options['overwrite'] ?? true;
        $existingBlocks = $this->loadArticleBlocks($articleId);
        $totalBlocks = count($templateBlocks);
        $totalUsage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];

        $this->sendSSE('start', [
            'total_blocks' => $totalBlocks,
            'article_id' => $articleId,
            'model' => $options['model'] ?? $article['gpt_model'] ?? GPT_DEFAULT_MODEL,
        ]);

        foreach ($templateBlocks as $i => $tb) {
            $this->sendSSE('block_start', [
                'index' => $i, 'type' => $tb['type'],
                'name' => $tb['name'], 'total' => $totalBlocks,
            ]);

            try {
                $allTypes = array_map(fn($t) => $t['type'], $templateBlocks);
                $messages = $this->prompts->buildBlockPrompt(
                    $article, $tb, [], ($template['gpt_system_prompt'] ?? ''), $allTypes);

                $gptOptions = [
                    'model' => $options['model'] ?? $article['gpt_model'] ?? GPT_DEFAULT_MODEL,
                    'temperature' => $options['temperature'] ?? 0.7,
                    'max_tokens' => $options['max_tokens'] ?? 4000,
                ];

                $this->gpt->setLogContext([
                    'category'    => TokenUsageLogger::CATEGORY_ARTICLE_CREATE,
                    'operation'   => 'generate_block_sse',
                    'profile_id'  => $article['profile_id'] ?? null,
                    'entity_type' => 'article',
                    'entity_id'   => $articleId,
                ]);
                $result = $this->gpt->chatJson($messages, $gptOptions);

                foreach (['prompt_tokens', 'completion_tokens', 'total_tokens'] as $k) {
                    $totalUsage[$k] += ($result['usage'][$k] ?? 0);
                }

                $unwrapped = $this->unwrapBlockContent($result['data'], $tb['type'] ?? '');
                $contentJson = json_encode($unwrapped, JSON_UNESCAPED_UNICODE);
                $existing = $this->findExistingBlock($existingBlocks, $tb);

                if ($existing && $overwrite) {
                    $this->db->update(SeoArticleBlock::SEO_ART_BLOCK_TABLE,
                        'id = :abid',
                        ['content' => $contentJson, 'name' => $tb['name']], [':abid' => $existing['id']]);
                    $savedBlockId = (int)$existing['id'];
                } elseif (!$existing) {
                    $this->db->insert(SeoArticleBlock::SEO_ART_BLOCK_TABLE, [
                        'article_id' => $articleId, 'type' => $tb['type'],
                        'name' => $tb['name'], 'content' => $contentJson,
                        'sort_order' => $tb['sort_order'], 'is_visible' => 1,
                    ]);
                    $savedBlockId = (int)$this->db->getPdo()->lastInsertId();
                } else {
                    $savedBlockId = (int)$existing['id'];
                }

                $this->sendSSE('block_done', [
                    'index' => $i, 'block_id' => $savedBlockId,
                    'type' => $tb['type'], 'name' => $tb['name'],
                    'content' => $unwrapped, 'usage' => $result['usage'],
                ]);

            } catch (Throwable $e) {
                $this->sendSSE('block_error', [
                    'index' => $i, 'type' => $tb['type'],
                    'name' => $tb['name'], 'error' => $e->getMessage(),
                ]);
            }
        }

        $this->updateGenerationLog($articleId, [
            'mode' => 'sse_sequential',
            'model' => $options['model'] ?? $article['gpt_model'] ?? GPT_DEFAULT_MODEL,
            'usage' => $totalUsage, 'blocks' => $totalBlocks,
            'timestamp' => date('c'),
        ]);
        $this->writeAudit($articleId, 'generate', [
            'mode' => 'all_blocks_sse', 'blocks' => $totalBlocks,
            'model' => $options['model'] ?? $article['gpt_model'] ?? GPT_DEFAULT_MODEL,
            'tokens' => $totalUsage,
        ]);
        $this->sendSSE('done', [
            'total_blocks' => $totalBlocks, 'total_usage' => $totalUsage,
        ]);
    }

    private function generateAllBlocksSSEFromOutline(int $articleId, array $article, array $sections, array $options): void {
        $template = !empty($article['template_id']) ? $this->loadTemplate((int)$article['template_id']) : null;
        $systemPrompt = $template['gpt_system_prompt'] ?? null;
        $allTypes = array_map(fn($s) => (string)($s['block_type'] ?? ''), $sections);
        $dossierIndex = $this->loadDossierIndex($article);
        $coverageReport = ['ok' => 0, 'partial' => 0, 'missing' => 0, 'sections' => []];

        $overwrite = $options['overwrite'] ?? true;
        if ($overwrite) {
            $this->db->delete(SeoArticleBlock::SEO_ART_BLOCK_TABLE, 'article_id = :aid', [':aid' => $articleId]);
        }
        $existingBlocks = $this->loadArticleBlocks($articleId);
        $totalSections = count($sections);
        $totalUsage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];

        $this->sendSSE('start', [
            'total_blocks' => $totalSections,
            'article_id'   => $articleId,
            'source'       => 'outline',
            'model'        => $options['model'] ?? $article['gpt_model'] ?? GPT_DEFAULT_MODEL,
        ]);

        $previousSummaries = [];
        foreach ($sections as $i => $section) {
            $tb = $this->sectionToTemplateBlock($section, $i);
            $this->sendSSE('block_start', [
                'index' => $i, 'type' => $tb['type'], 'name' => $tb['name'],
                'narrative_role' => $section['narrative_role'] ?? null,
                'total' => $totalSections,
            ]);

            try {
                $messages = $this->prompts->buildBlockPrompt(
                    $article, $tb, [], $systemPrompt, $allTypes, $section, $previousSummaries
                );
                $gptOptions = [
                    'model'       => $options['model']       ?? $article['gpt_model'] ?? GPT_DEFAULT_MODEL,
                    'temperature' => $options['temperature'] ?? 0.7,
                    'max_tokens'  => $options['max_tokens']  ?? 4000,
                ];
                $this->gpt->setLogContext([
                    'category'    => TokenUsageLogger::CATEGORY_ARTICLE_CREATE,
                    'operation'   => 'generate_block_outline_sse',
                    'profile_id'  => $article['profile_id'] ?? null,
                    'entity_type' => 'article',
                    'entity_id'   => $articleId,
                ]);
                $result = $this->gpt->chatJson($messages, $gptOptions);

                foreach (['prompt_tokens', 'completion_tokens', 'total_tokens'] as $k) {
                    $totalUsage[$k] += ($result['usage'][$k] ?? 0);
                }

                $unwrapped = $this->unwrapBlockContent($result['data'], $tb['type']);
                $contentJson = json_encode($unwrapped, JSON_UNESCAPED_UNICODE);
                $existing = $this->findExistingBlock($existingBlocks, $tb);

                if ($existing && $overwrite) {
                    $this->db->update(SeoArticleBlock::SEO_ART_BLOCK_TABLE, 'id = :abid', [
                        'content'    => $contentJson,
                        'name'       => $tb['name'],
                        'gpt_prompt' => (string)($section['content_brief'] ?? ''),
                    ], [':abid' => $existing['id']]);
                    $savedId = (int)$existing['id'];
                } elseif (!$existing) {
                    $this->db->insert(SeoArticleBlock::SEO_ART_BLOCK_TABLE, [
                        'article_id' => $articleId,
                        'type'       => $tb['type'],
                        'name'       => $tb['name'],
                        'content'    => $contentJson,
                        'sort_order' => $tb['sort_order'],
                        'is_visible' => 1,
                        'gpt_prompt' => (string)($section['content_brief'] ?? ''),
                    ]);
                    $savedId = (int)$this->db->getPdo()->lastInsertId();
                } else {
                    $savedId = (int)$existing['id'];
                }

                $cov = $this->factsCoverage($section, $dossierIndex, $unwrapped);
                $coverageReport['sections'][] = [
                    'section_id' => $section['id'] ?? null,
                    'h2'         => $section['h2_title'] ?? null,
                ] + $cov;
                $coverageReport[$cov['verdict']]++;

                $this->sendSSE('block_done', [
                    'index' => $i, 'block_id' => $savedId,
                    'type' => $tb['type'], 'name' => $tb['name'],
                    'content' => $unwrapped, 'usage' => $result['usage'],
                    'coverage' => $cov,
                ]);

                $previousSummaries[] = [
                    'h2'      => (string)($section['h2_title'] ?? $tb['name'] ?? ''),
                    'summary' => $this->buildSectionSummary($unwrapped),
                ];
                if (count($previousSummaries) > 2) {
                    $previousSummaries = array_slice($previousSummaries, -2);
                }
            } catch (Throwable $e) {
                $this->sendSSE('block_error', [
                    'index' => $i, 'type' => $tb['type'],
                    'name' => $tb['name'], 'error' => $e->getMessage(),
                ]);
            }
        }

        $this->updateGenerationLog($articleId, [
            'mode' => 'outline_sse',
            'model' => $options['model'] ?? $article['gpt_model'] ?? GPT_DEFAULT_MODEL,
            'usage' => $totalUsage, 'blocks' => $totalSections,
            'coverage' => ['ok' => $coverageReport['ok'], 'partial' => $coverageReport['partial'], 'missing' => $coverageReport['missing']],
            'timestamp' => date('c'),
        ]);
        $this->writeAudit($articleId, 'generate', [
            'mode' => 'outline_sse', 'blocks' => $totalSections,
            'model' => $options['model'] ?? $article['gpt_model'] ?? GPT_DEFAULT_MODEL,
            'tokens' => $totalUsage,
            'coverage' => $coverageReport,
        ]);
        $this->sendSSE('done', [
            'total_blocks' => $totalSections, 'total_usage' => $totalUsage, 'source' => 'outline',
            'coverage' => ['ok' => $coverageReport['ok'], 'partial' => $coverageReport['partial'], 'missing' => $coverageReport['missing']],
        ]);
    }

    public function generateSlug(string $title): string {
        $translitMap = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh',
            'з'=>'z','и'=>'i','й'=>'j','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o',
            'п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'kh','ц'=>'ts',
            'ч'=>'ch','ш'=>'sh','щ'=>'shch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
            'А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Е'=>'E','Ё'=>'Yo','Ж'=>'Zh',
            'З'=>'Z','И'=>'I','Й'=>'J','К'=>'K','Л'=>'L','М'=>'M','Н'=>'N','О'=>'O',
            'П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F','Х'=>'Kh','Ц'=>'Ts',
            'Ч'=>'Ch','Ш'=>'Sh','Щ'=>'Shch','Ъ'=>'','Ы'=>'Y','Ь'=>'','Э'=>'E','Ю'=>'Yu','Я'=>'Ya',
        ];

        $slug = strtr($title, $translitMap);
        $slug = mb_strtolower($slug);
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug);
        $slug = preg_replace('/-{2,}/', '-', $slug);
        $slug = trim($slug, '-');

        if (mb_strlen($slug) > 120) {
            $slug = mb_substr($slug, 0, 120);
            $slug = preg_replace('/-[^-]*$/', '', $slug);
        }

        return $slug;
    }

    private function findExistingBlock(array $existingBlocks, array $templateBlock): ?array {
        foreach ($existingBlocks as $eb) {
            if ((int)$eb['sort_order'] === (int)$templateBlock['sort_order']
                && $eb['type'] === $templateBlock['type']) {
                return $eb;
            }
        }
        return null;
    }

    private function resolveTemplateBlock(array $article, array $block): array {
        if ($article['template_id']) {
            $templateBlocks = $this->loadTemplateBlocks((int)$article['template_id']);

            foreach ($templateBlocks as $tb) {
                if ($tb['type'] === $block['type'] && (int)$tb['sort_order'] === (int)$block['sort_order']) {
                    return $tb;
                }
            }

            foreach ($templateBlocks as $tb) {
                if ($tb['type'] === $block['type']) return $tb;
            }
        }

        return [
            'type' => $block['type'],
            'name' => $block['name'] ?? $block['type'],
            'config' => json_encode(['hint' => '', 'fields' => []]),
        ];
    }


    private function sendSSE(string $event, array $data): void {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        if (ob_get_level()) ob_flush();
        flush();
    }

    private function loadArticle(int $id): array {
        $row = $this->db->fetchOne("SELECT * FROM " . SeoArticle::SEO_ARTICLE_TABLE . " WHERE id = ?", [$id]);
        if (!$row) throw new RuntimeException("Статья #{$id} не найдена");
        return $row;
    }

    private function loadTemplate(int $id): array {
        $row = $this->db->fetchOne("SELECT * FROM " . SeoTemplate::TABLE . " WHERE id = ?", [$id]);
        if (!$row) throw new RuntimeException("Шаблон #{$id} не найден");
        return $row;
    }

    private function loadTemplateBlocks(int $templateId): array {
        return $this->db->fetchAll(
            "SELECT * FROM " . SeoTemplateBlock::SEO_TEMPLATE_BLOCK_TABLE
            . " WHERE template_id = ? ORDER BY sort_order", [$templateId]);
    }

    private function loadArticleBlocks(int $articleId): array {
        return $this->db->fetchAll(
            "SELECT * FROM " . SeoArticleBlock::SEO_ART_BLOCK_TABLE
            . " WHERE article_id = ? ORDER BY sort_order", [$articleId]);
    }

    private function loadArticleBlock(int $blockId, int $articleId): array {
        $row = $this->db->fetchOne(
            "SELECT * FROM " . SeoArticleBlock::SEO_ART_BLOCK_TABLE
            . " WHERE id = ? AND article_id = ?", [$blockId, $articleId]);
        if (!$row) throw new RuntimeException("Блок #{$blockId} не найден в статье #{$articleId}");
        return $row;
    }

    private function updateGenerationLog(int $articleId, array $logEntry): void {
        $json = json_encode($logEntry, JSON_UNESCAPED_UNICODE);
        $this->db->update(SeoArticle::SEO_ARTICLE_TABLE,
            'id = :id', ['generation_log' => $json], [':id' => $articleId],
            ['version' => 'version + 1']);
    }

    private function writeAudit(int $articleId, string $action, array $details): void {
        $json = json_encode($details, JSON_UNESCAPED_UNICODE);
        $this->db->insert(SeoAuditLog::SEO_AUDIT_LOG_TABLE,
            SeoAuditLog::articleAction($articleId, $action,
                'system/gpt', ['details' => $json])->toArray());
    }
}