<?php

declare(strict_types=1);

namespace Seo\Service;

use RuntimeException;
use Seo\Database;
use Seo\Entity\SeoArticle;
use Seo\Entity\SeoAuditLog;
use Seo\Entity\SeoSiteProfile;
use Seo\Enum\ResearchPrompt;

/**
 * Builds a markdown research dossier for an article.
 * Runs BEFORE meta+blocks generation and feeds them as factual base.
 * Token usage logged under TokenUsageLogger::CATEGORY_ARTICLE_RESEARCH.
 */
class ArticleResearchService
{
    private GptClient $gpt;
    private Database $db;

    public function __construct(?GptClient $gpt = null) {
        $this->gpt = $gpt ?? new GptClient();
        $this->db = Database::getInstance();
    }

    /**
     * Build dossier and persist on the article.
     * $opts: model?, temperature?, max_tokens?, force? (regenerate even if ready)
     */
    public function buildDossier(int $articleId, array $opts = []): array {
        $article = $this->loadArticle($articleId);

        if (empty($opts['force']) && ($article['research_status'] ?? 'none') === 'ready'
            && !empty($article['research_dossier'])) {
            return [
                'status' => 'skipped',
                'reason' => 'already_ready',
                'dossier' => $article['research_dossier'],
            ];
        }

        $messages = $this->buildMessages($article);

        $gptOpts = [
            'model'       => $opts['model']       ?? $article['gpt_model'] ?? GPT_DEFAULT_MODEL,
            'temperature' => $opts['temperature'] ?? 0.4,
            'max_tokens'  => $opts['max_tokens']  ?? 2500,
        ];

        $this->gpt->setLogContext([
            'category'    => TokenUsageLogger::CATEGORY_ARTICLE_RESEARCH,
            'operation'   => 'build_dossier',
            'profile_id'  => $article['profile_id'] ?? null,
            'entity_type' => 'article',
            'entity_id'   => $articleId,
        ]);

        $result = $this->gpt->chat($messages, $gptOpts);
        $dossier = trim((string)($result['content'] ?? ''));

        if ($dossier === '') {
            throw new RuntimeException("Research: пустой ответ от GPT");
        }

        $now = date('Y-m-d H:i:s');
        $this->db->update(SeoArticle::SEO_ARTICLE_TABLE, 'id = :aid', [
            'research_dossier' => $dossier,
            'research_status'  => 'ready',
            'research_at'      => $now,
        ], [':aid' => $articleId]);

        $this->writeAudit($articleId, 'research', [
            'mode'   => 'build_dossier',
            'model'  => $result['model'] ?? null,
            'tokens' => $result['usage'] ?? [],
            'length' => strlen($dossier),
        ]);

        return [
            'status'  => 'ok',
            'dossier' => $dossier,
            'usage'   => $result['usage'] ?? [],
            'model'   => $result['model'] ?? null,
            'at'      => $now,
        ];
    }

    public function saveManual(int $articleId, string $dossier, string $status = 'ready'): void {
        $this->db->update(SeoArticle::SEO_ARTICLE_TABLE, 'id = :aid', [
            'research_dossier' => $dossier !== '' ? $dossier : null,
            'research_status'  => $dossier !== '' ? $status : 'none',
            'research_at'      => $dossier !== '' ? date('Y-m-d H:i:s') : null,
        ], [':aid' => $articleId]);

        $this->writeAudit($articleId, 'research', [
            'mode'   => 'manual_save',
            'length' => strlen($dossier),
            'status' => $status,
        ]);
    }

    public function markStale(int $articleId): void {
        $this->db->update(SeoArticle::SEO_ARTICLE_TABLE, 'id = :aid', [
            'research_status' => 'stale',
        ], [':aid' => $articleId]);
    }

    private function buildMessages(array $article): array {
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

        $skeleton = str_replace('{TITLE}', $title, ResearchPrompt::SKELETON);
        $user = sprintf(ResearchPrompt::USER_TEMPLATE, $title, $kwLine, $intentLine, $skeleton);

        return [
            ['role' => 'system', 'content' => ResearchPrompt::SYSTEM],
            ['role' => 'user',   'content' => $user],
        ];
    }

    private function loadIntentForArticle(array $article): ?array {
        if (!empty($article['id'])) {
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
            if ($intentCode) {
                $row = $this->db->fetchOne(
                    "SELECT code, label_ru FROM seo_intent_types WHERE code = ? AND is_active = 1",
                    [$intentCode]
                );
                if ($row) return $row;
            }
        }
        return null;
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
                SeoAuditLog::articleAction($articleId, $action, 'system/research', ['details' => $json])->toArray());
        } catch (\Throwable $e) {
            error_log('[ArticleResearchService] audit failed: ' . $e->getMessage());
        }
    }
}
