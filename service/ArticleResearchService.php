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

    public const STATUS_NONE    = 'none';
    public const STATUS_OUTLINE = 'outline';
    public const STATUS_FILL    = 'fill';
    public const STATUS_PRUNE   = 'prune';
    public const STATUS_READY   = 'ready';
    public const STATUS_STALE   = 'stale';

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

        $strategy = $opts['strategy'] ?? $this->resolveStrategy($article);

        $title = (string)($article['title'] ?? '');
        $usageAggregate = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
        $modelsSeen = [];

        if ($strategy === 'split' || $strategy === 'split_search') {
            [$normalised, $phaseUsages, $models] = $this->buildSplit($article, $opts, $strategy);
            foreach ($phaseUsages as $u) $this->mergeUsage($usageAggregate, $u);
            $modelsSeen = $models;
        } else {
            [$normalised, $u, $model] = $this->buildSingle($article, $opts);
            $this->mergeUsage($usageAggregate, $u);
            if ($model) $modelsSeen[] = $model;
        }

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

        $this->db->getPdo()->prepare(
            "UPDATE " . SeoArticleIllustration::TABLE
            . " SET status = ? WHERE article_id = ? AND kind = ? AND status = ?"
        )->execute([
            SeoArticleIllustration::STATUS_STALE,
            $articleId,
            SeoArticleIllustration::KIND_HERO,
            SeoArticleIllustration::STATUS_READY,
        ]);

        if (($article['outline_status'] ?? 'none') === 'ready') {
            (new ArticleOutlineService($this->gpt))->markStale($articleId);
        }

        $primaryModel = $modelsSeen[0] ?? null;
        $this->writeAudit($articleId, 'research', [
            'mode'     => 'build_dossier',
            'strategy' => $strategy,
            'model'    => $primaryModel,
            'tokens'   => $usageAggregate,
            'length'   => strlen($dossier),
        ]);

        return [
            'status'   => 'ok',
            'strategy' => $strategy,
            'dossier'  => $dossier,
            'usage'    => $usageAggregate,
            'model'    => $primaryModel,
            'at'       => $now,
        ];
    }

    private function resolveStrategy(array $article): string {
        $profileId = (int)($article['profile_id'] ?? 0);
        if ($profileId <= 0) return 'single';
        $row = $this->db->fetchOne(
            "SELECT research_strategy FROM seo_site_profiles WHERE id = ?", [$profileId]
        );
        $s = (string)($row['research_strategy'] ?? 'single');
        return in_array($s, ['single', 'split', 'split_search'], true) ? $s : 'single';
    }

    private function buildSingle(array $article, array $opts): array {
        $articleId = (int)$article['id'];
        $messages = $this->buildMessages($article);

        $gptOpts = [
            'model'       => $opts['model']       ?? $article['gpt_model'] ?? GPT_DEFAULT_MODEL,
            'temperature' => $opts['temperature'] ?? 0.4,
            'max_tokens'  => $opts['max_tokens']  ?? 3500,
        ];

        $this->gpt->setLogContext([
            'category'    => TokenUsageLogger::CATEGORY_ARTICLE_RESEARCH,
            'operation'   => 'research_single',
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
        return [$normalised, $result['usage'] ?? [], $result['model'] ?? null];
    }

    /**
     * Split pipeline: phase 1 outline questions → phase 2 fill per section.
     * Returns [normalisedDossier, phaseUsages[], modelsSeen[]].
     */
    private function buildSplit(array $article, array $opts, string $strategy): array {
        $articleId = (int)$article['id'];
        $title     = (string)($article['title'] ?? '');
        $profileId = $article['profile_id'] ?? null;
        $usages    = [];
        $models    = [];

        $this->setPhase($articleId, self::STATUS_OUTLINE);

        // ─── Phase 1: outline ───
        $outlineModel = $opts['outline_model'] ?? GPT_DEFAULT_MODEL;
        $this->gpt->setLogContext([
            'category'    => TokenUsageLogger::CATEGORY_ARTICLE_RESEARCH,
            'operation'   => 'research_outline',
            'profile_id'  => $profileId,
            'entity_type' => 'article',
            'entity_id'   => $articleId,
        ]);
        $outlineResult = $this->gpt->chatJson(
            $this->buildOutlineMessages($article),
            ['model' => $outlineModel, 'temperature' => 0.3, 'max_tokens' => 800]
        );
        $outlineData = $outlineResult['data'] ?? null;
        if (!is_array($outlineData) || empty($outlineData['questions']) || !is_array($outlineData['questions'])) {
            throw new RuntimeException("Research: outline не вернул questions");
        }
        $usages[] = $outlineResult['usage'] ?? [];
        if (!empty($outlineResult['model'])) $models[] = $outlineResult['model'];

        $angle    = (string)($outlineData['angle'] ?? '');
        $audience = (string)($outlineData['audience'] ?? '');
        if ($angle === '') {
            throw new RuntimeException("Research: outline без angle");
        }

        $this->setPhase($articleId, self::STATUS_FILL);

        // ─── Phase 2: fill per section ───
        $fillModel = $opts['fill_model'] ?? $article['gpt_model'] ?? GPT_DEFAULT_MODEL;
        $sectionsOrder = ['facts', 'entities', 'benchmarks', 'comparisons', 'counter_theses', 'quotes_cases', 'terms'];
        $idPrefix = [
            'facts' => 'f', 'entities' => 'e', 'benchmarks' => 'b',
            'comparisons' => 'c', 'counter_theses' => 'ct', 'quotes_cases' => 'q',
            'terms' => 't', 'sources' => 'src',
        ];

        $collected = [
            'title'    => $title,
            'angle'    => $angle,
            'audience' => $audience,
        ];
        foreach ($sectionsOrder as $sec) $collected[$sec] = [];
        $collected['sources'] = [];
        $collected['open_questions'] = [];

        $search = new WebSearchClient();

        foreach ($sectionsOrder as $sec) {
            $questions = $outlineData['questions'][$sec] ?? [];
            if (!is_array($questions) || empty($questions)) continue;

            $useSearch = ($strategy === 'split_search')
                && $sec === 'benchmarks'
                && !$search->disabled();

            $this->gpt->setLogContext([
                'category'    => TokenUsageLogger::CATEGORY_ARTICLE_RESEARCH,
                'operation'   => $useSearch ? "research_fill_search_{$sec}" : "research_fill_{$sec}",
                'profile_id'  => $profileId,
                'entity_type' => 'article',
                'entity_id'   => $articleId,
            ]);

            $messages = $this->buildFillMessages($title, $angle, $sec, $questions);

            if ($useSearch) {
                $tools = [[
                    'type' => 'function',
                    'function' => [
                        'name' => 'web_search',
                        'description' => 'Поиск свежих фактов и источников по запросу. Возвращает массив {title,url,description}.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'query' => ['type' => 'string', 'description' => 'Поисковый запрос на русском или английском'],
                                'count' => ['type' => 'integer', 'description' => 'Сколько результатов (1-10)', 'default' => 5],
                            ],
                            'required' => ['query'],
                        ],
                    ],
                ]];
                $handler = function (string $name, array $args) use ($search) {
                    if ($name !== 'web_search') {
                        return json_encode(['error' => "unknown tool: {$name}"], JSON_UNESCAPED_UNICODE);
                    }
                    $q = (string)($args['query'] ?? '');
                    $cnt = (int)($args['count'] ?? 5);
                    if ($q === '') return json_encode(['results' => []], JSON_UNESCAPED_UNICODE);
                    try {
                        $results = $search->search($q, max(1, min($cnt, 10)));
                    } catch (\Throwable $e) {
                        return json_encode(['error' => $e->getMessage(), 'results' => []], JSON_UNESCAPED_UNICODE);
                    }
                    return json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
                };
                $fillResult = $this->gpt->chatJsonWithTools(
                    $messages, $tools, $handler,
                    ['model' => $fillModel, 'temperature' => 0.4, 'max_tokens' => 1500]
                );
            } else {
                $fillResult = $this->gpt->chatJson(
                    $messages,
                    ['model' => $fillModel, 'temperature' => 0.4, 'max_tokens' => 1200]
                );
            }
            $usages[] = $fillResult['usage'] ?? [];
            if (!empty($fillResult['model']) && !in_array($fillResult['model'], $models, true)) {
                $models[] = $fillResult['model'];
            }

            $items = $fillResult['data']['items'] ?? null;
            if (!is_array($items)) continue;

            $i = 0;
            foreach ($items as $raw) {
                if (!is_array($raw)) continue;
                $i++;
                if (empty($raw['id']) || trim((string)$raw['id']) === '') {
                    $raw['id'] = $idPrefix[$sec] . $i;
                }
                $collected[$sec][] = $raw;
            }
        }

        $this->deriveSourcesFromItems($collected);

        if (!empty($opts['prune'])) {
            $this->setPhase($articleId, self::STATUS_PRUNE);
            $pruneModel = $opts['prune_model'] ?? 'gpt-4.1-mini';
            $pruneUsage = $this->runPrune($collected, $articleId, $profileId, $pruneModel);
            if ($pruneUsage !== null) {
                $usages[] = $pruneUsage['usage'] ?? [];
                if (!empty($pruneUsage['model']) && !in_array($pruneUsage['model'], $models, true)) {
                    $models[] = $pruneUsage['model'];
                }
            }
        }

        $normalised = $this->validateDossier($collected, $title);
        return [$normalised, $usages, $models];
    }

    /**
     * Cheap-model pass that asks GPT which collected items duplicate or stray
     * from the angle. Returns {remove_ids:[...]}; we drop matching items.
     * Returns ['usage' => array, 'model' => string] or null when nothing to do.
     */
    private function runPrune(array &$collected, int $articleId, $profileId, string $model): ?array
    {
        $idx = self::indexById($collected);
        if (empty($idx)) return null;

        $lines = [];
        foreach ($idx as $item) {
            $lines[] = self::renderItemLine($item);
        }
        $angle = (string)($collected['angle'] ?? '');
        $title = (string)($collected['title'] ?? '');

        $messages = [
            ['role' => 'system', 'content' =>
                "Ты редактор research-досье. Твоя задача — выкинуть из списка items, "
                . "которые дубликаты, не относятся к angle, или явно слабее остальных. "
                . "Не удаляй больше 25% items за проход. "
                . "Отвечай строго JSON: {\"remove_ids\":[\"id1\",\"id2\",...]}. "
                . "Если ничего удалять не нужно — пустой массив."],
            ['role' => 'user', 'content' =>
                "Тема: {$title}\nAngle: {$angle}\n\nItems:\n" . implode("\n", $lines)],
        ];

        $this->gpt->setLogContext([
            'category'    => TokenUsageLogger::CATEGORY_ARTICLE_RESEARCH,
            'operation'   => 'research_prune',
            'profile_id'  => $profileId,
            'entity_type' => 'article',
            'entity_id'   => $articleId,
        ]);

        try {
            $resp = $this->gpt->chatJson($messages, [
                'model'       => $model,
                'temperature' => 0.1,
                'max_tokens'  => 600,
            ]);
        } catch (\Throwable $e) {
            error_log('[ArticleResearchService] prune failed: ' . $e->getMessage());
            return null;
        }

        $removeIds = $resp['data']['remove_ids'] ?? [];
        if (!is_array($removeIds) || empty($removeIds)) {
            return ['usage' => $resp['usage'] ?? [], 'model' => $resp['model'] ?? null];
        }
        $removeSet = [];
        foreach ($removeIds as $rid) {
            $r = trim((string)$rid);
            if ($r !== '') $removeSet[$r] = true;
        }

        $maxDrop = (int)floor(count($idx) * 0.25);
        if ($maxDrop < 1) $maxDrop = 1;
        $dropped = 0;

        foreach (self::SECTIONS as $key => $_) {
            if (empty($collected[$key]) || !is_array($collected[$key])) continue;
            $kept = [];
            foreach ($collected[$key] as $item) {
                if (!is_array($item)) { $kept[] = $item; continue; }
                $iid = (string)($item['id'] ?? '');
                if ($iid !== '' && isset($removeSet[$iid]) && $dropped < $maxDrop) {
                    $dropped++;
                    continue;
                }
                $kept[] = $item;
            }
            $collected[$key] = $kept;
        }

        return ['usage' => $resp['usage'] ?? [], 'model' => $resp['model'] ?? null];
    }

    private function setPhase(int $articleId, string $phase): void
    {
        try {
            $this->db->update(SeoArticle::SEO_ARTICLE_TABLE, 'id = :aid',
                ['research_status' => $phase], [':aid' => $articleId]);
        } catch (\Throwable $e) {
            error_log('[ArticleResearchService] setPhase failed: ' . $e->getMessage());
        }
    }

    /**
     * Split pipeline does not call a separate "sources" fill — derive sources[]
     * from URL fields collected in facts/benchmarks. Dedupes by url, assigns
     * fresh src{n} ids, preserves any sources that may already be present.
     */
    private function deriveSourcesFromItems(array &$collected): void {
        $existing = is_array($collected['sources'] ?? null) ? $collected['sources'] : [];
        $byUrl = [];
        foreach ($existing as $row) {
            if (!is_array($row)) continue;
            $url = trim((string)($row['url'] ?? ''));
            if ($url === '') continue;
            $byUrl[$url] = $row;
        }

        foreach (['facts', 'benchmarks'] as $sec) {
            if (empty($collected[$sec]) || !is_array($collected[$sec])) continue;
            foreach ($collected[$sec] as $item) {
                if (!is_array($item)) continue;
                $src = $item['source'] ?? null;
                if (!is_string($src)) continue;
                $url = trim($src);
                if ($url === '' || stripos($url, 'http') !== 0) continue;
                if (!isset($byUrl[$url])) {
                    $byUrl[$url] = ['url' => $url, 'title' => ''];
                }
            }
        }

        $out = [];
        $i = 0;
        foreach ($byUrl as $row) {
            $i++;
            $row['id'] = (!empty($row['id']) && trim((string)$row['id']) !== '')
                ? trim((string)$row['id'])
                : ('src' . $i);
            $out[] = $row;
        }
        $collected['sources'] = $out;
    }

    private function mergeUsage(array &$agg, array $u): void {
        foreach (['prompt_tokens', 'completion_tokens', 'total_tokens'] as $k) {
            $agg[$k] = ($agg[$k] ?? 0) + (int)($u[$k] ?? 0);
        }
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

        if (count($out['facts']) < 5) {
            throw new RuntimeException("Research: facts < 5 — досье слишком бедное (промпт требует ≥ 8)");
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

    private function buildOutlineMessages(array $article): array {
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

        $user = sprintf(
            ResearchPrompt::USER_OUTLINE_TEMPLATE,
            $title, $kwLine, $intentLine, ResearchPrompt::OUTLINE_FORMAT
        );

        return [
            ['role' => 'system', 'content' => ResearchPrompt::SYSTEM_OUTLINE],
            ['role' => 'user',   'content' => $user],
        ];
    }

    private function buildFillMessages(string $title, string $angle, string $section, array $questions): array {
        $schema = ResearchPrompt::FILL_SECTION_SCHEMAS[$section] ?? '{"items":[]}';
        $qLines = [];
        foreach ($questions as $i => $q) {
            $qLines[] = ($i + 1) . ". " . trim((string)$q);
        }
        $user = sprintf(
            ResearchPrompt::USER_FILL_TEMPLATE,
            $title, $angle, $section, implode("\n", $qLines), $schema
        );
        return [
            ['role' => 'system', 'content' => ResearchPrompt::SYSTEM_FILL],
            ['role' => 'user',   'content' => $user],
        ];
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
