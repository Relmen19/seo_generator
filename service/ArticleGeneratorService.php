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
        $metaResult = $this->generateMeta($articleId, $options, $article);
        $article = $this->loadArticle($articleId);
        $blocksResult = $this->generateAllBlocks($articleId, $options);
        $this->writeAudit($articleId, 'generate', [
            'mode' => 'full_pipeline',
            'meta_tokens' => $metaResult['usage'],
            'blocks_tokens' => $blocksResult['usage'],
            'blocks_count' => $blocksResult['blocks_generated'],
        ]);

        return [
            'pipeline' => 'meta → blocks',
            'slug' => $article['slug'],
            'meta' => $metaResult,
            'blocks' => $blocksResult,
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
        $article = $this->loadArticle($articleId);
        $this->applyProfileToPrompts($article['profile_id'] ?? null);
        $article = $this->enrichArticleWithIntent($article);
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
        $article = $this->loadArticle($articleId);
        $this->applyProfileToPrompts($article['profile_id'] ?? null);
        $article = $this->enrichArticleWithIntent($article);
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