<?php

declare(strict_types=1);

namespace Seo\Controller;

use Seo\Service\ArticleGeneratorService;
use Seo\Service\GptClient;
use Seo\Service\KeywordCollectorService;
use Throwable;

/*
 *   GET    keywords/jobs           — список джобов
 *   POST   keywords/jobs           — создать джоб
 *   GET    keywords/jobs/{id}      — детали джоба
 *   DELETE keywords/jobs/{id}      — удалить джоб
 *
 *   POST   keywords/collect/{id}   — запустить сбор (manual/yandex/gpt)
 *   POST   keywords/cluster/{id}       — запустить кластеризацию
 *   POST   keywords/cluster/{id}/sse   — кластеризация с SSE
 *
 *   GET    keywords/intents            — список типов интентов
 *
 *   GET    keywords/raw/{jobId}        — список ключевиков
 *   DELETE keywords/raw/item/{id}      — удалить ключевик
 *   PUT    keywords/raw/move/{id}      — переместить в кластер
 *
 *   GET    keywords/clusters/{jobId}          — список кластеров
 *   GET    keywords/clusters/detail/{id}      — детали кластера
 *   PUT    keywords/clusters/detail/{id}      — обновить кластер
 *   DELETE keywords/clusters/detail/{id}      — удалить кластер
 *   POST   keywords/clusters/approve/{id}     — утвердить
 *   POST   keywords/clusters/reject/{id}      — отклонить
 */
class KeywordController extends AbstractController {

    private KeywordCollectorService $service;
    private ArticleGeneratorService $articleGeneratorService;

    public function __construct() {
        parent::__construct();
        $this->service = new KeywordCollectorService();
        $this->articleGeneratorService = new ArticleGeneratorService(new GptClient());
    }

    public function dispatch(string $method, ?string $action, ?int $id): void {
        $route = trim($_GET['r'] ?? '', '/');
        $segments = explode('/', $route);
        array_shift($segments);

        $seg0 = $segments[0] ?? '';
        $seg1 = $segments[1] ?? '';
        $seg2 = $segments[2] ?? '';

        switch ($seg0) {
            case 'jobs':
                $this->handleJobs($method, $seg1);
                return;

            case 'collect':
                $jobId = $this->toInt($seg1);
                if ($method === 'POST' && $jobId) { $this->collectKeywords($jobId); return; }
                $this->methodNotAllowed();
                return;

            case 'cluster':
                $jobId = $this->toInt($seg1);
                if ($method === 'POST' && $jobId) { $this->handleCluster($jobId, $seg2 === 'sse'); return; }
                $this->methodNotAllowed();
                return;

            case 'intents':
                if ($method === 'GET') {
                    $this->success($this->service->listIntentTypes());
                    return;
                }
                $this->methodNotAllowed();
                return;

            case 'raw':
                $this->handleRaw($method, $seg1, $seg2);
                return;

            case 'clusters':
                $this->handleClusters($method, $seg1, $seg2);
                return;

            default:
                $this->error("Endpoint не найден: keywords/{$seg0}", 404);
        }
    }

    private function toInt(string $seg): ?int {
        return ctype_digit($seg) && $seg !== '' ? (int)$seg : null;
    }


    private function handleJobs(string $method, string $seg1): void {
        $jobId = $this->toInt($seg1);

        if ($method === 'GET' && !$jobId) {
            $page = $this->getPage();
            $perPage = $this->getPerPage();
            $offset = ($page - 1) * $perPage;
            $profileId = $this->getParam('profile_id');
            $pid = ($profileId !== null && $profileId !== '') ? (int)$profileId : null;
            $this->paginated(
                $this->service->listJobs($perPage, $offset, $pid),
                $this->service->countJobs($pid),
                $page, $perPage
            );
            return;
        }
        if ($method === 'POST' && !$jobId) {
            $body = $this->getJsonBody();
            $this->abortIfErrors($this->validateRequired($body, ['seed_keyword']));
            $pid = isset($body['profile_id']) && $body['profile_id'] !== '' ? (int)$body['profile_id'] : null;
            $jid = $this->service->createJob($body['seed_keyword'], $body['source'] ?? 'manual', $body['config'] ?? [], $pid);
            $this->success(['id' => $jid], 201);
            return;
        }
        if ($method === 'GET' && $jobId) {
            $job = $this->service->getJob($jobId);
            if (!$job) $this->notFound('Задача');
            $this->success($job);
            return;
        }
        if ($method === 'DELETE' && $jobId) {
            $this->service->deleteJob($jobId);
            $this->success(['deleted' => true]);
            return;
        }

        $this->methodNotAllowed();
    }


    private function collectKeywords(int $jobId): void {
        $body = $this->getJsonBody();
        $source = $body['source'] ?? 'manual';

        try {
            switch ($source) {
                case 'manual':
                    $keywords = $body['keywords'] ?? [];
                    if (empty($keywords)) { $this->error('Параметр keywords обязателен'); return; }
                    if (is_string($keywords)) {
                        $keywords = array_values(array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $keywords))));
                    }
                    $count = $this->service->collectManual($jobId, $keywords);
                    $this->success(['imported' => $count, 'source' => 'manual']);
                    return;

                case 'gpt':
                    $count = $this->service->collectFromGpt($jobId, $body['config'] ?? []);
                    $this->success(['imported' => $count, 'source' => 'gpt']);
                    return;

                case 'yandex':
                    $count = $this->service->collectFromYandex($jobId, $body['config'] ?? []);
                    $this->success(['imported' => $count, 'source' => 'yandex']);
                    return;

                default:
                    $this->error("Неизвестный источник: {$source}");
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }


    private function handleCluster(int $jobId, bool $isSSE): void {
        $body = $this->getJsonBody();
        $options = ['model' => $body['model'] ?? GPT_DEFAULT_MODEL, 'batch_size' => $body['batch_size'] ?? 80];

        if ($isSSE) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no');
            if (ob_get_level()) ob_end_clean();
            $this->service->clusterJobSSE($jobId, $options);
            exit;
        }

        try {
            $this->success($this->service->clusterJob($jobId, $options));
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }


    private function handleRaw(string $method, string $seg1, string $seg2): void {
        // DELETE keywords/raw/item/{id}
        if ($seg1 === 'item' && $method === 'DELETE') {
            $kwId = $this->toInt($seg2);
            if (!$kwId) { $this->error('ID обязателен'); return; }
            $this->service->deleteKeyword($kwId);
            $this->success(['deleted' => true]);
            return;
        }

        // PUT keywords/raw/move/{id}
        if ($seg1 === 'move' && $method === 'PUT') {
            $kwId = $this->toInt($seg2);
            if (!$kwId) { $this->error('ID обязателен'); return; }
            $body = $this->getJsonBody();
            $this->service->moveKeywordToCluster($kwId, isset($body['cluster_id']) ? (int)$body['cluster_id'] : null);
            $this->success(['moved' => true]);
            return;
        }

        // PUT keywords/raw/update/{id}  — изменить volume/competition/cpc
        if ($seg1 === 'update' && $method === 'PUT') {
            $kwId = $this->toInt($seg2);
            if (!$kwId) { $this->error('ID обязателен'); return; }
            $body = $this->getJsonBody();
            $this->service->updateKeyword($kwId, $body);
            $this->success(['updated' => true]);
            return;
        }

        // GET keywords/raw/{jobId}
        $jobId = $this->toInt($seg1);
        if ($method === 'GET' && $jobId) {
            $page = $this->getPage();
            $perPage = $this->getPerPage(100);
            $offset = ($page - 1) * $perPage;
            $clusterId = $this->getParam('cluster_id');
            $sort = $this->getParam('sort');
            $search = $this->getParam('search');

            $this->paginated(
                $this->service->listKeywords($jobId, $perPage, $offset,
                    $clusterId !== null ? (int)$clusterId : null, $sort, $search),
                $this->service->countKeywords($jobId,
                    $clusterId !== null ? (int)$clusterId : null, $search),
                $page, $perPage
            );
            return;
        }

        $this->methodNotAllowed();
    }


    private function handleClusters(string $method, string $seg1, string $seg2): void {
        // GET keywords/clusters/{jobId}
        $jobId = $this->toInt($seg1);
        if ($method === 'GET' && $jobId && $seg2 === '') {
            $this->success($this->service->listClusters($jobId, $this->getParam('status')));
            return;
        }

        $detailId = $this->toInt($seg2);

        // keywords/clusters/detail/{id}
        if ($seg1 === 'detail' && $detailId) {
            if ($method === 'GET') {
                $cl = $this->service->getCluster($detailId);
                if (!$cl) $this->notFound('Кластер');
                $this->success($cl);
                return;
            }
            if ($method === 'PUT') {
                $this->service->updateCluster($detailId, $this->getJsonBody());
                $this->success(['updated' => true]);
                return;
            }
            if ($method === 'DELETE') {
                $this->service->deleteCluster($detailId);
                $this->success(['deleted' => true]);
                return;
            }
        }

        // POST keywords/clusters/approve/{id}
        if ($seg1 === 'approve' && $method === 'POST' && $detailId) {
            $this->service->approveCluster($detailId);
            $this->success(['status' => 'approved']);
            return;
        }

        // POST keywords/clusters/reject/{id}
        if ($seg1 === 'reject' && $method === 'POST' && $detailId) {
            $this->service->rejectCluster($detailId);
            $this->success(['status' => 'rejected']);
            return;
        }

        // DELETE keywords/clusters/all/{jobId}  — удалить все кластеры задачи
        $allJobId = $this->toInt($seg2);
        if ($seg1 === 'all' && $method === 'DELETE' && $allJobId) {
            $deleted = $this->service->deleteAllClusters($allJobId);
            $this->success(['deleted' => $deleted]);
            return;
        }

        // DELETE keywords/clusters/article/{clusterId}  — открепить/удалить статью кластера
        if ($seg1 === 'article' && $method === 'DELETE' && $detailId) {
            $this->service->deleteClusterArticle($detailId);
            $this->success(['deleted' => true]);
            return;
        }

        $clusterId = $this->toInt($seg2);

        // POST keywords/clusters/generate-article/{id}
        if ($seg1 === 'generate-article' && $method === 'POST' && $clusterId) {
            $body = $this->getJsonBody();

            $this->articleGeneratorService->generateFromCluster($clusterId, $body);
            $this->success(['status' => 'done']);
            return;
        }

        $this->methodNotAllowed();
    }
}