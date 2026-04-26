<?php

declare(strict_types=1);

namespace Seo\Service;

use RuntimeException;
use Seo\Database;
use Seo\Entity\SeoArticle;
use Seo\Entity\SeoArticleIllustration;
use Seo\Entity\SeoAuditLog;
use Seo\Entity\SeoSiteProfile;
use Seo\Enum\ResearchPrompt;

/**
 * Builds a structured JSON research dossier for an article.
 * Runs BEFORE meta+blocks generation and feeds them as factual base.
 * Each item has a stable id (f1, b1, c1, ct1, q1, t1, e1, src1) that
 * outline.source_facts must reference.
 * Token usage logged under TokenUsageLogger::CATEGORY_ARTICLE_RESEARCH.
 */
class ArticleResearchService
{
    private GptClient $gpt;
    private Database $db;

    private const SECTIONS = [
        'facts'          => ['claim', 'evidence'],
        'entities'       => ['name', 'definition'],
        'benchmarks'     => ['metric', 'value'],
        'comparisons'    => ['x', 'y'],
        'counter_theses' => ['thesis', 'objection'],
        'quotes_cases'   => ['text'],
        'terms'          => ['term', 'definition'],
        'sources'        => ['url'],
    ];

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
            'max_tokens'  => $opts['max_tokens']  ?? 3500,
        ];

        $this->gpt->setLogContext([
            'category'    => TokenUsageLogger::CATEGORY_ARTICLE_RESEARCH,
            'operation'   => 'build_dossier',
            'profile_id'  => $article['profile_id'] ?? null,
            'entity_type' => 'article',
            'entity_id'   => $articleId,
        ]);

        $result = $this->gpt->chatJson($messages, $gptOpts);
        $data = $result['data'] ?? null;
        if (!is_array($data)) {
            throw new RuntimeException("Research: GPT не вернул JSON-объект");
        }
        $normalised = $this->validateDossier($data, (string)($article['title'] ?? ''));
        $dossier = json_encode($normalised, JSON_UNESCAPED_UNICODE);
        if ($dossier === false || $dossier === '') {
            throw new RuntimeException("Research: не удалось сериализовать dossier в JSON");
        }

        $now = date('Y-m-d H:i:s');
        $this->db->update(SeoArticle::SEO_ARTICLE_TABLE, 'id = :aid', [
            'research_dossier' => $dossier,
            'research_status'  => 'ready',
            'research_at'      => $now,
        ], [':aid' => $articleId]);

        // Hero illustration is built from dossier — invalidate it on dossier change.
        $this->db->getPdo()->prepare(
            "UPDATE " . SeoArticleIllustration::TABLE
            . " SET status = ? WHERE article_id = ? AND kind = ? AND status = ?"
        )->execute([
            SeoArticleIllustration::STATUS_STALE,
            $articleId,
            SeoArticleIllustration::KIND_HERO,
            SeoArticleIllustration::STATUS_READY,
        ]);

        // Outline references dossier item IDs — IDs may have shifted after rebuild,
        // so the outline must be regenerated. Mark stale instead of deleting.
        if (($article['outline_status'] ?? 'none') === 'ready') {
            (new ArticleOutlineService($this->gpt))->markStale($articleId);
        }

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
        $clean = trim($dossier);
        $stored = null;
        if ($clean !== '') {
            $decoded = json_decode($clean, true);
            if (!is_array($decoded)) {
                throw new RuntimeException("Research: невалидный JSON dossier");
            }
            $article = $this->loadArticle($articleId);
            $normalised = $this->validateDossier($decoded, (string)($article['title'] ?? ''));
            $stored = json_encode($normalised, JSON_UNESCAPED_UNICODE);
        }

        $this->db->update(SeoArticle::SEO_ARTICLE_TABLE, 'id = :aid', [
            'research_dossier' => $stored,
            'research_status'  => $stored !== null ? $status : 'none',
            'research_at'      => $stored !== null ? date('Y-m-d H:i:s') : null,
        ], [':aid' => $articleId]);

        $this->writeAudit($articleId, 'research', [
            'mode'   => 'manual_save',
            'length' => $stored !== null ? strlen($stored) : 0,
            'status' => $status,
        ]);
    }

    /**
     * Build a flat map: id => item, so consumers (outline validator, prompt
     * builder) can resolve references like "f3" or "b1" without re-walking.
     */
    public static function indexById(array $dossier): array {
        $idx = [];
        foreach (self::SECTIONS as $key => $_) {
            if (empty($dossier[$key]) || !is_array($dossier[$key])) continue;
            foreach ($dossier[$key] as $item) {
                if (!is_array($item)) continue;
                $id = (string)($item['id'] ?? '');
                if ($id === '') continue;
                $item['_section'] = $key;
                $idx[$id] = $item;
            }
        }
        return $idx;
    }

    /**
     * One-line text representation of a dossier item for prompt injection.
     */
    public static function renderItemLine(array $item): string {
        $section = (string)($item['_section'] ?? '');
        $id      = (string)($item['id'] ?? '');
        switch ($section) {
            case 'facts':
                return "{$id} (факт): " . trim(($item['claim'] ?? '') . ' — ' . ($item['evidence'] ?? ''), ' —');
            case 'benchmarks':
                $cond = !empty($item['conditions']) ? " ({$item['conditions']})" : '';
                return "{$id} (бенчмарк): {$item['metric']} = {$item['value']}{$cond}";
            case 'comparisons':
                $axes = is_array($item['axes'] ?? null) ? implode(', ', $item['axes']) : '';
                $sum  = $item['summary'] ?? '';
                return "{$id} (сравнение): {$item['x']} vs {$item['y']} по [{$axes}] — {$sum}";
            case 'counter_theses':
                return "{$id} (контр-тезис): {$item['thesis']} || возражение: {$item['objection']}";
            case 'quotes_cases':
                $att = !empty($item['attribution']) ? " — {$item['attribution']}" : '';
                return "{$id} ({$item['kind']}): {$item['text']}{$att}";
            case 'terms':
                return "{$id} (термин): {$item['term']} — {$item['definition']}";
            case 'entities':
                return "{$id} (сущность): {$item['name']} — {$item['definition']}";
            case 'sources':
                $title = $item['title'] ?? '';
                return "{$id} (источник): {$item['url']}" . ($title ? " — {$title}" : '');
            default:
                return $id;
        }
    }

    /**
     * Validates dossier shape, drops malformed items, fills missing ids.
     * Throws if structure is irrecoverable (no facts at all).
     */
    private function validateDossier(array $data, string $title): array {
        $out = [
            'title'    => trim((string)($data['title'] ?? $title)),
            'angle'    => trim((string)($data['angle'] ?? '')),
            'audience' => trim((string)($data['audience'] ?? '')),
        ];

        if ($out['angle'] === '') {
            throw new RuntimeException("Research: dossier без angle");
        }

        $idPrefix = [
            'facts' => 'f', 'entities' => 'e', 'benchmarks' => 'b',
            'comparisons' => 'c', 'counter_theses' => 'ct', 'quotes_cases' => 'q',
            'terms' => 't', 'sources' => 'src',
        ];

        foreach (self::SECTIONS as $key => $required) {
            $items = $data[$key] ?? [];
            if (!is_array($items)) $items = [];
            $clean = [];
            $i = 0;
            foreach ($items as $raw) {
                if (!is_array($raw)) continue;
                $i++;
                $missing = false;
                foreach ($required as $field) {
                    if (!isset($raw[$field]) || trim((string)$raw[$field]) === '') {
                        $missing = true; break;
                    }
                }
                if ($missing) continue;
                $id = trim((string)($raw['id'] ?? ''));
                if ($id === '') $id = $idPrefix[$key] . $i;
                $raw['id'] = $id;
                $clean[] = $raw;
            }
            $out[$key] = $clean;
        }

        $out['open_questions'] = [];
        if (!empty($data['open_questions']) && is_array($data['open_questions'])) {
            foreach ($data['open_questions'] as $q) {
                $s = trim((string)$q);
                if ($s !== '') $out['open_questions'][] = $s;
            }
        }

        if (count($out['facts']) < 3) {
            throw new RuntimeException("Research: facts < 3 — досье слишком бедное");
        }

        $seen = [];
        foreach (self::SECTIONS as $key => $_) {
            foreach ($out[$key] as $item) {
                $id = $item['id'];
                if (isset($seen[$id])) {
                    throw new RuntimeException("Research: дубликат id '{$id}'");
                }
                $seen[$id] = true;
            }
        }

        return $out;
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
