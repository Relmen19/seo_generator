<?php

declare(strict_types=1);

namespace Seo\Service;

use RuntimeException;
use Seo\Database;
use Seo\Entity\SeoKeywordJob;
use Seo\Entity\SeoRawKeyword;
use Seo\Entity\SeoKeywordCluster;
use Seo\Entity\SeoSiteProfile;
use Seo\Entity\SeoTemplate;
use Seo\Enum\KeywordPrompt;
use Throwable;

class KeywordCollectorService {

    private Database $db;
    private GptClient $gpt;

    private ?array $intentCache = null;

    public function __construct(?GptClient $gpt = null) {
        $this->db  = Database::getInstance();
        $this->gpt = $gpt ?? new GptClient();
    }


    public function getIntentTypes(): array {
        if ($this->intentCache !== null) return $this->intentCache;

        $rows = $this->db->fetchAll(
            "SELECT * FROM seo_intent_types WHERE is_active = 1 ORDER BY sort_order ASC"
        );
        $this->intentCache = [];
        foreach ($rows as $row) {
            $this->intentCache[$row['code']] = $row;
        }
        return $this->intentCache;
    }

    public function listIntentTypes(): array {
        $types = $this->getIntentTypes();
        return array_map(fn($t) => [
            'code'        => $t['code'],
            'label_ru'    => $t['label_ru'],
            'label_en'    => $t['label_en'],
            'color'       => $t['color'],
            'description' => $t['description'],
        ], array_values($types));
    }

    public function getValidIntentCodes(): array {
        return array_keys($this->getIntentTypes());
    }


    public function createJob(string $seed, string $source = 'manual', array $config = [], ?int $profileId = null): int {
        $seed = trim($seed);
        if ($seed === '') throw new RuntimeException('Seed keyword не может быть пустым');

        $row = [
            'seed_keyword' => $seed, 'source' => $source, 'status' => 'pending',
            'config' => !empty($config) ? json_encode($config, JSON_UNESCAPED_UNICODE) : null,
        ];
        if ($profileId !== null) $row['profile_id'] = $profileId;
        $this->db->insert(SeoKeywordJob::TABLE, $row);
        return (int)$this->db->getPdo()->lastInsertId();
    }

    public function getJob(int $id): ?array {
        return $this->db->fetchOne("SELECT * FROM " . SeoKeywordJob::TABLE . " WHERE id = ?", [$id]);
    }

    public function listJobs(int $limit = 50, int $offset = 0, ?int $profileId = null): array {
        $where = '1=1';
        $params = [];
        if ($profileId !== null) {
            $where .= ' AND j.profile_id = ?';
            $params[] = $profileId;
        }
        $params[] = $limit;
        $params[] = $offset;
        return $this->db->fetchAll(
            "SELECT j.*,
                (SELECT COUNT(*) FROM " . SeoRawKeyword::TABLE . " WHERE job_id = j.id) as keyword_count,
                (SELECT COUNT(*) FROM " . SeoKeywordCluster::TABLE . " WHERE job_id = j.id) as cluster_count
             FROM " . SeoKeywordJob::TABLE . " j WHERE {$where} ORDER BY j.created_at DESC LIMIT ? OFFSET ?",
            $params);
    }

    public function countJobs(?int $profileId = null): int {
        if ($profileId !== null) {
            $row = $this->db->fetchOne("SELECT COUNT(*) as cnt FROM " . SeoKeywordJob::TABLE . " WHERE profile_id = ?", [$profileId]);
        } else {
            $row = $this->db->fetchOne("SELECT COUNT(*) as cnt FROM " . SeoKeywordJob::TABLE);
        }
        return (int)($row['cnt'] ?? 0);
    }

    public function deleteJob(int $id): void {
        $pdo = $this->db->getPdo();
        $pdo->exec("DELETE FROM " . SeoRawKeyword::TABLE . " WHERE job_id = " . (int)$id);
        $pdo->exec("DELETE FROM " . SeoKeywordCluster::TABLE . " WHERE job_id = " . (int)$id);
        $pdo->exec("DELETE FROM " . SeoKeywordJob::TABLE . " WHERE id = " . (int)$id);
    }

    private function updateJobStatus(int $jobId, string $status, array $extra = []): void {
        $this->db->update(SeoKeywordJob::TABLE, 'id = :id',
            array_merge(['status' => $status], $extra), [':id' => $jobId]);
    }

    private function appendJobError(int $jobId, string $message): void {
        $job = $this->getJob($jobId);
        $log = ($job['error_log'] ?? '') . "\n[" . date('H:i:s') . "] " . $message;
        $this->db->update(SeoKeywordJob::TABLE, 'id = :id', ['error_log' => trim($log)], [':id' => $jobId]);
    }


    public function collectManual(int $jobId, array $keywords): int {
        $job = $this->getJob($jobId);
        if (!$job) throw new RuntimeException("Job #{$jobId} не найден");

        $this->updateJobStatus($jobId, 'collecting');
        $count = 0;

        foreach ($keywords as $kw) {
            $row = is_array($kw) ? $kw : ['keyword' => $kw];
            $keyword = trim($row['keyword'] ?? '');
            if ($keyword === '') continue;

            $exists = $this->db->fetchOne(
                "SELECT id FROM " . SeoRawKeyword::TABLE . " WHERE job_id = ? AND keyword = ?",
                [$jobId, $keyword]);
            if ($exists) continue;

            $this->db->insert(SeoRawKeyword::TABLE, [
                'job_id' => $jobId, 'keyword' => $keyword, 'source' => 'manual',
                'volume' => isset($row['volume']) ? (int)$row['volume'] : null,
                'competition' => isset($row['competition']) ? (float)$row['competition'] : null,
                'cpc' => isset($row['cpc']) ? (float)$row['cpc'] : null,
            ]);
            $count++;
        }

        $total = $this->countKeywords($jobId);
        $this->updateJobStatus($jobId, 'pending', ['total_found' => $total]);
        return $count;
    }

    public function collectFromYandex(int $jobId, array $config = []): int {
        $job = $this->getJob($jobId);
        if (!$job) throw new RuntimeException("Job #{$jobId} не найден");

        $this->updateJobStatus($jobId, 'collecting');

        $seed    = $job['seed_keyword'];
        $region  = $config['region'] ?? '225';
        $apiUser = $config['yandex_user'] ?? (defined('YANDEX_XML_USER') ? YANDEX_XML_USER : '');
        $apiKey  = $config['yandex_key'] ?? (defined('YANDEX_XML_KEY') ? YANDEX_XML_KEY : '');

        if (!$apiUser || !$apiKey) {
            $this->appendJobError($jobId, 'Yandex XML: не заданы credentials');
            $this->updateJobStatus($jobId, 'error');
            throw new RuntimeException('Yandex XML API credentials not configured');
        }

        $count = 0;
        $maxKeywords = (int)($config['max_keywords'] ?? 500);
        $minVolume = (int)($config['min_volume'] ?? 0);
        $queue = [$seed];
        $processed = [];
        $depth = (int)($config['depth'] ?? 1);

        try {
            while (!empty($queue) && $count < $maxKeywords) {
                $query = array_shift($queue);
                if (in_array($query, $processed, true)) continue;
                $processed[] = $query;

                $suggestions = $this->yandexWordstatRequest($query, $region, $apiUser, $apiKey);

                foreach ($suggestions as $item) {
                    if ($count >= $maxKeywords) break;
                    $kw = trim($item['keyword'] ?? '');
                    $vol = (int)($item['volume'] ?? 0);
                    if ($kw === '' || ($minVolume > 0 && $vol < $minVolume)) continue;

                    $exists = $this->db->fetchOne(
                        "SELECT id FROM " . SeoRawKeyword::TABLE . " WHERE job_id = ? AND keyword = ?",
                        [$jobId, $kw]);
                    if ($exists) continue;

                    $this->db->insert(SeoRawKeyword::TABLE, [
                        'job_id' => $jobId, 'keyword' => $kw, 'source' => 'yandex', 'volume' => $vol,
                    ]);
                    $count++;

                    if ($depth > 1 && count($processed) < $depth * 5) $queue[] = $kw;
                }
                usleep(500000);
            }
        } catch (Throwable $e) {
            $this->appendJobError($jobId, 'Yandex: ' . $e->getMessage());
        }

        $total = $this->countKeywords($jobId);
        $this->updateJobStatus($jobId, 'pending', ['total_found' => $total]);
        return $count;
    }

    private function yandexWordstatRequest(string $query, string $region, string $user, string $key): array {
        $url = 'https://xmlriver.com/search_yandex/xml?' . http_build_query([
                'user' => $user, 'key' => $key, 'query' => $query,
                'lr' => $region, 'type' => 'wordstat', 'device' => 'all',
            ]);
        $ctx = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true]]);
        $response = @file_get_contents($url, false, $ctx);
        if ($response === false) throw new RuntimeException("Yandex XML: сетевая ошибка для '{$query}'");

        $xml = @simplexml_load_string($response);
        if (!$xml) throw new RuntimeException("Yandex XML: невалидный ответ для '{$query}'");

        $results = [];
        if (isset($xml->wordstat->item)) {
            foreach ($xml->wordstat->item as $item) {
                $results[] = ['keyword' => (string)$item->keyword, 'volume' => (int)$item->number];
            }
        }
        return $results;
    }

    public function collectFromGpt(int $jobId, array $config = []): int {
        $job = $this->getJob($jobId);
        if (!$job) throw new RuntimeException("Job #{$jobId} не найден");

        $this->updateJobStatus($jobId, 'collecting');
        $seed = $job['seed_keyword'];
        $maxKeywords = (int)($config['max_keywords'] ?? 200);

        $profile = null;
        if (!empty($job['profile_id'])) {
            $profile = $this->db->fetchOne(
                "SELECT niche FROM " . SeoSiteProfile::TABLE . " WHERE id = ?",
                [(int)$job['profile_id']]
            );
        }
        $niche = !empty($profile['niche']) ? $profile['niche'] : 'тематического сайта';

        $intentCodes = implode('|', $this->getValidIntentCodes());

        $messages = [
            ['role' => 'system', 'content' => sprintf(KeywordPrompt::COLLECT_SYSTEM, $niche, $intentCodes)],
            ['role' => 'user', 'content' => sprintf(KeywordPrompt::COLLECT_USER, $maxKeywords, $seed)],
        ];

        try {
            $result = $this->gpt->chatJson($messages, [
                'model' => $config['model'] ?? GPT_DEFAULT_MODEL, 'temperature' => SEO_TEMPERATURE_CREATIVE, 'max_tokens' => SEO_MAX_TOKENS_LARGE,
            ]);
            $data = $result['data'];
            $items = $data['keywords'] ?? (isset($data[0]) ? $data : []);

            $count = 0;
            foreach ($items as $item) {
                if (!is_array($item) || empty($item['keyword'])) continue;
                if ($count >= $maxKeywords) break;
                $kw = trim($item['keyword']);

                $exists = $this->db->fetchOne(
                    "SELECT id FROM " . SeoRawKeyword::TABLE . " WHERE job_id = ? AND keyword = ?",
                    [$jobId, $kw]);
                if ($exists) continue;

                $this->db->insert(SeoRawKeyword::TABLE, [
                    'job_id' => $jobId, 'keyword' => $kw, 'source' => 'gpt',
                    'volume' => (int)($item['volume_estimate'] ?? 0),
                ]);
                $count++;
            }

            $total = $this->countKeywords($jobId);
            $this->updateJobStatus($jobId, 'pending', ['total_found' => $total]);
            return $count;
        } catch (Throwable $e) {
            $this->appendJobError($jobId, 'GPT collect: ' . $e->getMessage());
            $this->updateJobStatus($jobId, 'error');
            throw $e;
        }
    }


    public function clusterJob(int $jobId, array $options = []): array {
        $job = $this->getJob($jobId);
        if (!$job) throw new RuntimeException("Job #{$jobId} не найден");

        $this->updateJobStatus($jobId, 'clustering');
        $this->db->getPdo()->exec("UPDATE " . SeoRawKeyword::TABLE . " SET cluster_id = NULL, is_processed = 0 WHERE job_id = " . (int)$jobId);
        $this->db->getPdo()->exec("DELETE FROM " . SeoKeywordCluster::TABLE . " WHERE job_id = " . (int)$jobId);

        $keywords = $this->db->fetchAll(
            "SELECT id, keyword, volume, competition FROM " . SeoRawKeyword::TABLE . " WHERE job_id = ? ORDER BY volume DESC", [$jobId]);
        if (empty($keywords)) { $this->updateJobStatus($jobId, 'error'); throw new RuntimeException('Нет данных для кластеризации'); }

        $batchSize = (int)($options['batch_size'] ?? 80);
        $model = $options['model'] ?? GPT_DEFAULT_MODEL;
        $batches = array_chunk($keywords, $batchSize);
        $allClusters = [];

        $lastBatchIdx = count($batches) - 1;
        foreach ($batches as $batchIdx => $batch) {
            try { $allClusters[] = $this->clusterBatch($batch, $job['seed_keyword'], $model); }
            catch (Throwable $e) { $this->appendJobError($jobId, "Batch {$batchIdx}: " . $e->getMessage()); }
            // Throttle between GPT calls to avoid hitting OpenAI per-minute rate limits.
            if ($batchIdx < $lastBatchIdx) usleep(100000);
        }

        if (empty($allClusters)) { $this->updateJobStatus($jobId, 'error'); throw new RuntimeException('Все батчи с ошибками'); }

        $merged = count($allClusters) === 1 ? $allClusters[0] : $this->mergeBatches($allClusters, $job['seed_keyword'], $model);

        $clusterCount = 0;
        foreach ($merged as $cl) { $clusterCount += $this->saveCluster($jobId, $cl, $keywords); }

        $this->updateJobStatus($jobId, 'done', ['total_clusters' => $clusterCount]);
        return ['job_id' => $jobId, 'total_keywords' => count($keywords), 'total_clusters' => $clusterCount];
    }

    public function clusterJobSSE(int $jobId, array $options = []): void {
        $job = $this->getJob($jobId);
        if (!$job) { $this->sendSSE('error', ['message' => "Job #{$jobId} не найден"]); return; }

        $this->updateJobStatus($jobId, 'clustering');
        $this->sendSSE('start', ['job_id' => $jobId, 'seed' => $job['seed_keyword']]);

        $this->db->getPdo()->exec("UPDATE " . SeoRawKeyword::TABLE . " SET cluster_id = NULL, is_processed = 0 WHERE job_id = " . (int)$jobId);
        $this->db->getPdo()->exec("DELETE FROM " . SeoKeywordCluster::TABLE . " WHERE job_id = " . (int)$jobId);

        $keywords = $this->db->fetchAll(
            "SELECT id, keyword, volume, competition FROM " . SeoRawKeyword::TABLE . " WHERE job_id = ? ORDER BY volume DESC", [$jobId]);
        if (empty($keywords)) { $this->sendSSE('error', ['message' => 'Нет данных']); $this->updateJobStatus($jobId, 'error'); return; }

        $batchSize = (int)($options['batch_size'] ?? 80);
        $model = $options['model'] ?? GPT_DEFAULT_MODEL;
        $batches = array_chunk($keywords, $batchSize);
        $totalBatches = count($batches);
        $allClusters = [];

        $this->sendSSE('progress', ['phase' => 'clustering', 'total_keywords' => count($keywords), 'total_batches' => $totalBatches]);

        $lastBatchIdx = $totalBatches - 1;
        foreach ($batches as $batchIdx => $batch) {
            $this->sendSSE('batch_start', ['batch' => $batchIdx + 1, 'total' => $totalBatches, 'keywords_in_batch' => count($batch)]);
            try {
                $batchClusters = $this->clusterBatch($batch, $job['seed_keyword'], $model);
                $allClusters[] = $batchClusters;
                $this->sendSSE('batch_done', ['batch' => $batchIdx + 1, 'clusters_found' => count($batchClusters)]);
            } catch (Throwable $e) {
                $this->appendJobError($jobId, "Batch {$batchIdx}: " . $e->getMessage());
                $this->sendSSE('batch_error', ['batch' => $batchIdx + 1, 'error' => $e->getMessage()]);
            }
            // Throttle between GPT calls to stay under OpenAI rate limits.
            if ($batchIdx < $lastBatchIdx) usleep(100000);
        }

        if (empty($allClusters)) { $this->sendSSE('error', ['message' => 'Все батчи с ошибками']); $this->updateJobStatus($jobId, 'error'); return; }

        if (count($allClusters) > 1) {
            $this->sendSSE('progress', ['phase' => 'merging', 'batches' => count($allClusters)]);
            $merged = $this->mergeBatches($allClusters, $job['seed_keyword'], $model);
        } else { $merged = $allClusters[0]; }

        $this->sendSSE('progress', ['phase' => 'saving', 'clusters' => count($merged)]);
        $clusterCount = 0;
        foreach ($merged as $cl) { $clusterCount += $this->saveCluster($jobId, $cl, $keywords); }

        $this->updateJobStatus($jobId, 'done', ['total_clusters' => $clusterCount]);
        $this->sendSSE('done', ['total_clusters' => $clusterCount, 'total_keywords' => count($keywords)]);
    }

    private function clusterBatch(array $keywords, string $seedTopic, string $model): array {
        $kwList = array_map(fn($kw) => "- {$kw['keyword']}" . ($kw['volume'] ? " ({$kw['volume']})" : ''), $keywords);
        $kwText = implode("\n", $kwList);

        $messages = [
            ['role' => 'system', 'content' => $this->getClusteringSystemPrompt()],
            ['role' => 'user',   'content' => "Тема: \"{$seedTopic}\"\n\nЗапросы:\n{$kwText}\n\nСгруппируй. Ответ -- JSON."],
        ];

        $result = $this->gpt->chatJson($messages, ['model' => $model, 'temperature' => AI_TEMPERATURE, 'max_tokens' => MAX_TOKENS]);
        $data = $result['data'];
        return $data['clusters'] ?? (isset($data[0]) ? $data : []);
    }

    private function mergeBatches(array $batchResults, string $seedTopic, string $model): array {
        $intentCodes = implode('|', $this->getValidIntentCodes());

        $allNames = [];
        foreach ($batchResults as $batch) {
            foreach ($batch as $cl) {
                $name = $cl['name'] ?? 'N/A';
                $allNames[] = "- \"{$name}\" (" . count($cl['keywords'] ?? []) . " запросов, intent: " . ($cl['intent'] ?? '?') . ')';
            }
        }

        $messages = [
            ['role' => 'system', 'content' => sprintf(KeywordPrompt::MERGE_SYSTEM, $intentCodes)],
            ['role' => 'user', 'content' => "Тема: \"{$seedTopic}\"\n\nКластеры:\n" . implode("\n", $allNames)],
        ];

        $result = $this->gpt->chatJson($messages, ['model' => $model, 'temperature' => SEO_TEMPERATURE_PRECISE, 'max_tokens' => SEO_MAX_TOKENS_SMALL]);
        $mergeMap = $result['data']['merged'] ?? [];

        $finalClusters = [];
        $usedNames = [];

        foreach ($mergeMap as $merge) {
            $keepNames = $merge['keep_names'] ?? [];
            $mergedKeywords = [];
            foreach ($batchResults as $batch) {
                foreach ($batch as $cl) {
                    if (in_array($cl['name'] ?? '', $keepNames, true)) {
                        $mergedKeywords = array_merge($mergedKeywords, $cl['keywords'] ?? []);
                        $usedNames[] = $cl['name'] ?? '';
                    }
                }
            }

            $finalClusters[] = [
                'name' => $merge['final_name'] ?? ($keepNames[0] ?? 'Кластер'),
                'intent' => $merge['intent'] ?? 'info',
                'article_angle' => $merge['article_angle'] ?? '',
                'template_id' => $this->resolveTemplateId($merge),
                'keywords' => array_values(array_unique($mergedKeywords)),
            ];
        }

        foreach ($batchResults as $batch) {
            foreach ($batch as $cl) {
                if (!in_array($cl['name'] ?? '', $usedNames, true)) $finalClusters[] = $cl;
            }
        }
        return $finalClusters;
    }

    public function resolveTemplateId(array $cluster): int {
        $templates = $this->db->fetchAll(
            "SELECT id FROM " . SeoTemplate::TABLE . " WHERE intent = ? AND is_active = 1 ORDER BY id ASC", [$cluster['intent']]);

        if (empty($templates)) throw new RuntimeException("No active template for intent: {$cluster['intent']}");

        $index = $cluster['keyword_count'] % count($templates);
        return (int) $templates[$index]['id'];
    }

    private function saveCluster(int $jobId, array $cluster, array $allKeywords): int {
        $name = $cluster['name'] ?? 'Без названия';
        $kwList = $cluster['keywords'] ?? [];
        $totalVolume = 0; $matchedCount = 0;
        foreach ($allKeywords as $kw) {
            if (in_array($kw['keyword'], $kwList, true)) { $totalVolume += (int)($kw['volume'] ?? 0); $matchedCount++; }
        }

        $intent = $cluster['intent'] ?? 'info';
        $validCodes = $this->getValidIntentCodes();
        if (!in_array($intent, $validCodes, true)) {
            $intent = 'info';
        }

        $this->db->insert(SeoKeywordCluster::TABLE, [
            'job_id' => $jobId, 'name' => $name, 'slug' => $this->generateSlug($name),
            'intent' => $intent, 'summary' => $cluster['summary'] ?? null,
            'article_angle' => $cluster['article_angle'] ?? null,
            'template_id' => $this->resolveTemplateId($cluster),
            'total_volume' => $totalVolume, 'keyword_count' => $matchedCount,
            'priority' => $this->calculatePriority($totalVolume, $matchedCount), 'status' => 'new',
        ]);
        $clusterId = (int)$this->db->getPdo()->lastInsertId();

        foreach ($allKeywords as $kw) {
            if (in_array($kw['keyword'], $kwList, true)) {
                $this->db->update(SeoRawKeyword::TABLE, 'id = :id', ['cluster_id' => $clusterId, 'is_processed' => 1], [':id' => $kw['id']]);
            }
        }
        return 1;
    }

    private function calculatePriority(int $totalVolume, int $count): int {
        if ($totalVolume <= 0) return 0;
        return min(10, max(0, (int)round(log($totalVolume, 2) / 2)));
    }

    private function getClusteringSystemPrompt(): string {
        $intents = $this->getIntentTypes();

        $intentBlock = "";
        foreach ($intents as $code => $t) {
            $intentBlock .= "\n- intent=\"{$code}\" ({$t['label_ru']}): {$t['gpt_hint']}";
        }

        $intentCodes = implode('|', array_keys($intents));

        return KeywordPrompt::CLUSTER_SYSTEM_INTRO
            . KeywordPrompt::CLUSTER_SYSTEM_RULES
            . KeywordPrompt::CLUSTER_SYSTEM_INTENT_HEADER
            . $intentBlock
            . KeywordPrompt::CLUSTER_SYSTEM_IMPORTANT
            . sprintf(KeywordPrompt::CLUSTER_RESPONSE_FORMAT, $intentCodes);
    }



    public function listKeywords(int $jobId, int $limit = 100, int $offset = 0,
                                 ?int $clusterId = null, ?string $sort = null, ?string $search = null): array {
        $where = "WHERE job_id = ?";
        $params = [$jobId];

        if ($clusterId !== null) {
            if ($clusterId === 0) { $where .= " AND cluster_id IS NULL"; }
            else { $where .= " AND cluster_id = ?"; $params[] = $clusterId; }
        }
        if ($search !== null && $search !== '') {
            $where .= " AND keyword LIKE ?";
            $params[] = '%' . $search . '%';
        }

        switch ($sort) {
            case 'volume_desc': $orderBy = 'volume DESC'; break;
            case 'volume_asc': $orderBy = 'volume ASC'; break;
            case 'alpha': $orderBy = 'keyword ASC'; break;
            default: $orderBy = 'volume DESC, keyword ASC';
        };

        return $this->db->fetchAll(
            "SELECT * FROM " . SeoRawKeyword::TABLE . " {$where} ORDER BY {$orderBy} LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset]));
    }

    public function countKeywords(int $jobId, ?int $clusterId = null, ?string $search = null): int {
        $where = "WHERE job_id = ?";
        $params = [$jobId];

        if ($clusterId !== null) {
            if ($clusterId === 0) { $where .= " AND cluster_id IS NULL"; }
            else { $where .= " AND cluster_id = ?"; $params[] = $clusterId; }
        }
        if ($search !== null && $search !== '') {
            $where .= " AND keyword LIKE ?";
            $params[] = '%' . $search . '%';
        }

        $row = $this->db->fetchOne("SELECT COUNT(*) as cnt FROM " . SeoRawKeyword::TABLE . " {$where}", $params);
        return (int)($row['cnt'] ?? 0);
    }

    public function deleteKeyword(int $id): void {
        $this->db->getPdo()->exec("DELETE FROM " . SeoRawKeyword::TABLE . " WHERE id = " . (int)$id);
    }

    public function updateKeyword(int $id, array $fields): void {
        $allowed = ['volume', 'competition', 'cpc', 'keyword'];
        $update = [];
        foreach ($fields as $k => $v) {
            if (in_array($k, $allowed, true)) $update[$k] = $v;
        }
        if (empty($update)) return;
        $this->db->update(SeoRawKeyword::TABLE, 'id = :id', $update, [':id' => $id]);
    }

    public function moveKeywordToCluster(int $keywordId, ?int $clusterId): void {
        $kw = $this->db->fetchOne("SELECT cluster_id FROM " . SeoRawKeyword::TABLE . " WHERE id = ?", [$keywordId]);
        $oldClusterId = $kw ? (int)($kw['cluster_id'] ?? 0) : 0;

        $this->db->update(SeoRawKeyword::TABLE, 'id = :id', ['cluster_id' => $clusterId], [':id' => $keywordId]);

        if ($clusterId) $this->recalcClusterStats($clusterId);
        if ($oldClusterId && $oldClusterId !== $clusterId) $this->recalcClusterStats($oldClusterId);
    }


    public function listClusters(int $jobId, ?string $status = null): array {
        $where = "WHERE c.job_id = ?";
        $params = [$jobId];
        if ($status) { $where .= " AND c.status = ?"; $params[] = $status; }

        return $this->db->fetchAll(
            "SELECT c.*, t.name as template_name,
                    it.label_ru as intent_label, it.color as intent_color
             FROM " . SeoKeywordCluster::TABLE . " c
             LEFT JOIN seo_templates t ON t.id = c.template_id
             LEFT JOIN seo_intent_types it ON it.code = c.intent
             {$where} ORDER BY c.priority DESC, c.total_volume DESC", $params);
    }

    public function getCluster(int $id): ?array {
        return $this->db->fetchOne(
            "SELECT c.*, t.name as template_name,
                    it.label_ru as intent_label, it.color as intent_color,
                    it.description as intent_description, it.article_tone, it.article_open
             FROM " . SeoKeywordCluster::TABLE . " c
             LEFT JOIN seo_templates t ON t.id = c.template_id
             LEFT JOIN seo_intent_types it ON it.code = c.intent
             WHERE c.id = ?", [$id]);
    }

    public function updateCluster(int $id, array $fields): void {
        $allowed = ['name', 'slug', 'intent', 'summary', 'article_angle', 'template_id', 'status', 'parent_id'];
        $update = [];
        foreach ($fields as $k => $v) { if (in_array($k, $allowed, true)) $update[$k] = $v; }

        if (isset($update['intent']) && $update['intent'] !== null) {
            $validCodes = $this->getValidIntentCodes();
            if (!in_array($update['intent'], $validCodes, true)) {
                throw new RuntimeException("Невалидный intent: {$update['intent']}");
            }
        }

        if (!empty($update)) $this->db->update(SeoKeywordCluster::TABLE, 'id = :id', $update, [':id' => $id]);
    }

    public function approveCluster(int $id): void { $this->updateCluster($id, ['status' => 'approved']); }
    public function rejectCluster(int $id): void  { $this->updateCluster($id, ['status' => 'rejected']); }

    public function deleteCluster(int $id): void {
        $this->db->update(SeoRawKeyword::TABLE, 'cluster_id = :cid', ['cluster_id' => null, 'is_processed' => 0], [':cid' => $id]);
        $this->db->getPdo()->exec("DELETE FROM " . SeoKeywordCluster::TABLE . " WHERE id = " . (int)$id);
    }

    private function recalcClusterStats(int $clusterId): void {
        $stats = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt, COALESCE(SUM(volume),0) as vol FROM " . SeoRawKeyword::TABLE . " WHERE cluster_id = ?", [$clusterId]);
        $this->db->update(SeoKeywordCluster::TABLE, 'id = :id', [
            'keyword_count' => (int)$stats['cnt'], 'total_volume' => (int)$stats['vol'],
            'priority' => $this->calculatePriority((int)$stats['vol'], (int)$stats['cnt']),
        ], [':id' => $clusterId]);
    }


    private function generateSlug(string $text): string {
        $map = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z','и'=>'i','й'=>'j','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
        $slug = strtr(mb_strtolower($text), $map);
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug);
        $slug = trim(preg_replace('/-{2,}/', '-', $slug), '-');
        return mb_strlen($slug) > 120 ? preg_replace('/-[^-]*$/', '', mb_substr($slug, 0, 120)) : $slug;
    }

    private function sendSSE(string $event, array $data): void {
        echo "event: {$event}\ndata: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        if (ob_get_level()) ob_flush();
        flush();
    }
}