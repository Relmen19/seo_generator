<?php

declare(strict_types=1);

namespace Seo\Service;

use RuntimeException;
use Seo\Database;
use Seo\Entity\SeoArticle;
use Seo\Entity\SeoAuditLog;
use Seo\Entity\SeoBlockType;
use Seo\Enum\OutlinePrompt;

/**
 * Builds a structured JSON outline for an article.
 * Runs AFTER research dossier and BEFORE meta + blocks.
 * Outline is the source of truth for block iteration if outline_status='ready'.
 * Token usage logged under TokenUsageLogger::CATEGORY_ARTICLE_OUTLINE.
 */
class ArticleOutlineService
{
    private GptClient $gpt;
    private Database $db;

    private const ALLOWED_ROLES = ['hook', 'problem', 'deep_dive', 'tradeoff', 'benchmark', 'faq', 'cta'];

    public function __construct(?GptClient $gpt = null) {
        $this->gpt = $gpt ?? new GptClient();
        $this->db = Database::getInstance();
    }

    /**
     * Build outline and persist on the article.
     * $opts: model?, temperature?, max_tokens?, force?
     */
    public function buildOutline(int $articleId, array $opts = []): array {
        $article = $this->loadArticle($articleId);

        if (empty($opts['force'])
            && ($article['outline_status'] ?? 'none') === 'ready'
            && !empty($article['article_outline'])) {
            return [
                'status'  => 'skipped',
                'reason'  => 'already_ready',
                'outline' => $article['article_outline'],
            ];
        }

        $dossier = trim((string)($article['research_dossier'] ?? ''));
        if ($dossier === '' || ($article['research_status'] ?? 'none') !== 'ready') {
            throw new RuntimeException(
                "Outline: нельзя строить — research dossier обязателен (research_status=ready)."
            );
        }

        $blockTypes = $this->loadAllowedBlockTypes();
        if (empty($blockTypes)) {
            throw new RuntimeException("Outline: нет активных типов блоков в seo_block_types");
        }

        $messages = $this->buildMessages($article, $dossier, $blockTypes);

        $gptOpts = [
            'model'       => $opts['model']       ?? $article['gpt_model'] ?? GPT_DEFAULT_MODEL,
            'temperature' => $opts['temperature'] ?? 0.4,
            'max_tokens'  => $opts['max_tokens']  ?? 3000,
        ];

        $this->gpt->setLogContext([
            'category'    => TokenUsageLogger::CATEGORY_ARTICLE_OUTLINE,
            'operation'   => 'build_outline',
            'profile_id'  => $article['profile_id'] ?? null,
            'entity_type' => 'article',
            'entity_id'   => $articleId,
        ]);

        $result = $this->gpt->chatJson($messages, $gptOpts);
        $data = $result['data'] ?? [];

        $allowedTypes = array_keys($blockTypes);
        $sections = $this->validateOutline($data, $allowedTypes, $dossier);

        $outline = ['sections' => $sections];
        $outlineJson = json_encode($outline, JSON_UNESCAPED_UNICODE);

        $this->db->update(SeoArticle::SEO_ARTICLE_TABLE, 'id = :aid', [
            'article_outline' => $outlineJson,
            'outline_status'  => 'ready',
        ], [':aid' => $articleId]);

        $this->writeAudit($articleId, 'outline', [
            'mode'     => 'build_outline',
            'model'    => $result['model'] ?? null,
            'tokens'   => $result['usage'] ?? [],
            'sections' => count($sections),
        ]);

        return [
            'status'   => 'ok',
            'outline'  => $outlineJson,
            'sections' => $sections,
            'usage'    => $result['usage'] ?? [],
            'model'    => $result['model'] ?? null,
        ];
    }

    public function saveManual(int $articleId, string $outlineJson, string $status = 'ready'): void {
        $clean = trim($outlineJson);
        if ($clean !== '') {
            $decoded = json_decode($clean, true);
            if (!is_array($decoded) || !isset($decoded['sections']) || !is_array($decoded['sections'])) {
                throw new RuntimeException("Outline: невалидный JSON (ожидается {sections:[...]})");
            }
        }

        $this->db->update(SeoArticle::SEO_ARTICLE_TABLE, 'id = :aid', [
            'article_outline' => $clean !== '' ? $clean : null,
            'outline_status'  => $clean !== '' ? $status : 'none',
        ], [':aid' => $articleId]);

        $this->writeAudit($articleId, 'outline', [
            'mode'   => 'manual_save',
            'length' => strlen($clean),
            'status' => $status,
        ]);
    }

    public function markStale(int $articleId): void {
        $this->db->update(SeoArticle::SEO_ARTICLE_TABLE, 'id = :aid', [
            'outline_status' => 'stale',
        ], [':aid' => $articleId]);
    }

    /**
     * Returns map: code => display_name.
     */
    private function loadAllowedBlockTypes(): array {
        $rows = $this->db->fetchAll(
            "SELECT code, display_name, description FROM " . SeoBlockType::TABLE
            . " WHERE is_active = 1 ORDER BY sort_order, code"
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(string)$r['code']] = (string)($r['display_name'] ?: $r['code']);
        }
        return $out;
    }

    private function buildMessages(array $article, string $dossier, array $blockTypes): array {
        $title    = (string)($article['title'] ?? '');
        $keywords = (string)($article['keywords'] ?? '');
        $kwLine   = $keywords !== '' ? "Ключевые слова: {$keywords}\n" : '';

        $intentLine = '';
        $intent = $this->loadIntentForArticle($article);
        if ($intent !== null) {
            $intentLine = "Интент: {$intent['code']}";
            if (!empty($intent['label_ru'])) $intentLine .= " ({$intent['label_ru']})";
            $intentLine .= "\n";
        }

        $typesList = '';
        foreach ($blockTypes as $code => $name) {
            $typesList .= "- {$code} — {$name}\n";
        }

        $maxDossier = 8000;
        if (strlen($dossier) > $maxDossier) {
            $dossier = substr($dossier, 0, $maxDossier) . "\n…[усечено]";
        }

        $user = sprintf(
            OutlinePrompt::USER_TEMPLATE,
            $title, $kwLine, $intentLine, rtrim($typesList), $dossier, OutlinePrompt::FORMAT
        );

        return [
            ['role' => 'system', 'content' => OutlinePrompt::SYSTEM],
            ['role' => 'user',   'content' => $user],
        ];
    }

    /**
     * Validate parsed JSON, normalise sections.
     * Throws on hard violations (no sections, empty source_facts, invalid block_type).
     */
    private function validateOutline($data, array $allowedTypes, string $dossier): array {
        if (!is_array($data) || !isset($data['sections']) || !is_array($data['sections'])) {
            throw new RuntimeException("Outline: ответ не содержит массив sections");
        }
        $sections = [];
        $i = 0;
        foreach ($data['sections'] as $raw) {
            $i++;
            if (!is_array($raw)) {
                throw new RuntimeException("Outline: секция #{$i} не объект");
            }
            $id            = trim((string)($raw['id'] ?? ('s' . $i)));
            $h2            = trim((string)($raw['h2_title'] ?? ''));
            $role          = trim((string)($raw['narrative_role'] ?? ''));
            $blockType     = trim((string)($raw['block_type'] ?? ''));
            $contentBrief  = trim((string)($raw['content_brief'] ?? ''));
            $sourceFacts   = $raw['source_facts'] ?? [];

            if ($h2 === '')           throw new RuntimeException("Outline: секция #{$i} без h2_title");
            if ($contentBrief === '') throw new RuntimeException("Outline: секция #{$i} без content_brief");
            if (!in_array($role, self::ALLOWED_ROLES, true)) {
                throw new RuntimeException("Outline: секция #{$i} имеет неизвестный narrative_role '{$role}'");
            }
            if (!in_array($blockType, $allowedTypes, true)) {
                throw new RuntimeException("Outline: секция #{$i} имеет неизвестный block_type '{$blockType}'");
            }
            if (!is_array($sourceFacts) || empty($sourceFacts)) {
                throw new RuntimeException("Outline: секция #{$i} имеет пустой source_facts");
            }

            $cleanFacts = [];
            foreach ($sourceFacts as $f) {
                $s = trim((string)$f);
                if ($s === '') continue;
                $cleanFacts[] = $s;
            }
            if (empty($cleanFacts)) {
                throw new RuntimeException("Outline: секция #{$i} — все source_facts пустые");
            }

            $sections[] = [
                'id'             => $id !== '' ? $id : ('s' . $i),
                'h2_title'       => $h2,
                'narrative_role' => $role,
                'block_type'     => $blockType,
                'content_brief'  => $contentBrief,
                'source_facts'   => $cleanFacts,
            ];
        }

        if (empty($sections)) {
            throw new RuntimeException("Outline: нет секций");
        }
        return $sections;
    }

    private function loadIntentForArticle(array $article): ?array {
        if (empty($article['id'])) return null;
        $cluster = $this->db->fetchOne(
            "SELECT intent FROM seo_keyword_clusters WHERE article_id = ? AND intent IS NOT NULL LIMIT 1",
            [$article['id']]
        );
        $intentCode = $cluster['intent'] ?? null;
        if (!$intentCode && !empty($article['template_id'])) {
            $tpl = $this->db->fetchOne(
                "SELECT intent FROM seo_templates WHERE id = ?",
                [$article['template_id']]
            );
            $intentCode = $tpl['intent'] ?? null;
        }
        if (!$intentCode) return null;
        $row = $this->db->fetchOne(
            "SELECT code, label_ru FROM seo_intent_types WHERE code = ? AND is_active = 1",
            [$intentCode]
        );
        return $row ?: null;
    }

    private function loadArticle(int $id): array {
        $row = $this->db->fetchOne(
            "SELECT * FROM " . SeoArticle::SEO_ARTICLE_TABLE . " WHERE id = ?", [$id]
        );
        if (!$row) throw new RuntimeException("Статья #{$id} не найдена");
        return $row;
    }

    private function writeAudit(int $articleId, string $action, array $details): void {
        try {
            $json = json_encode($details, JSON_UNESCAPED_UNICODE);
            $this->db->insert(SeoAuditLog::SEO_AUDIT_LOG_TABLE,
                SeoAuditLog::articleAction($articleId, $action, 'system/outline', ['details' => $json])->toArray());
        } catch (\Throwable $e) {
            error_log('[ArticleOutlineService] audit failed: ' . $e->getMessage());
        }
    }
}
