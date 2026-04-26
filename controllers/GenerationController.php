<?php

declare(strict_types=1);

namespace Seo\Controller;

use Seo\Service\ArticleGeneratorService;
use Seo\Service\ArticleOutlineService;
use Seo\Service\ArticleResearchService;
use Seo\Service\GenerationControlService;
use Seo\Database;
use Seo\Entity\SeoArticle;
use Seo\Service\GptClient;
use Throwable;

/*
   POST   /generate/{articleId}                     — генерация всех блоков (JSON-ответ)
   POST   /generate/{articleId}/sse                 — генерация всех блоков (SSE-поток)
   POST   /generate/{articleId}/block/{blockId}     — (пере)генерация одного блока
   POST   /generate/{articleId}/meta                — генерация мета-тегов
   GET    /generate/models                          — список доступных моделей
   GET    /generate/status                          — проверка API-ключа

 */
class GenerationController extends AbstractController {

    public function dispatch(string $method, ?string $action, ?int $id): void {

        if ($method === 'GET' && $action === 'models' && $id === null) {
            $this->listModels();
            return;
        }

        if ($method === 'GET' && $action === 'status' && $id === null) {
            $this->checkStatus();
            return;
        }

        if ($method === 'GET' && $action === 'research-progress' && $id !== null) {
            $this->researchProgress($id);
            return;
        }

        if ($method !== 'POST') {
            $this->methodNotAllowed();
            return;
        }

        if ($id !== null && $action === null) {
            $this->generateAll($id);
            return;
        }

        if ($id !== null && $action === 'sse') {
            $this->generateAllSSE($id);
            return;
        }

        if ($id !== null && $action === 'meta') {
            $this->generateMeta($id);
            return;
        }

        if ($id !== null && $action === 'research') {
            $this->buildResearch($id);
            return;
        }

        if ($id !== null && $action === 'outline') {
            $this->buildOutline($id);
            return;
        }

        if ($id !== null && $action === 'block') {
            $this->generateBlock($id);
            return;
        }

        if ($id !== null && $action === 'cancel') {
            (new GenerationControlService())->requestCancel($id);
            $this->success(['cancel_requested' => true, 'article_id' => $id]);
            return;
        }

        if ($action !== null && $id !== null) {
            $this->error('Неизвестный endpoint', 404);
            return;
        }

        $this->error('Укажите article_id: POST /generate/{articleId}[/sse|/meta|/research|/outline|/block]');
    }

    private function generateAll(int $articleId): void {
        $body = $this->getJsonBody();

        try {
            $service = $this->createService();
            $result = $service->generateAllBlocks($articleId, [
                'model'       => $body['model']       ?? null,
                'temperature' => $body['temperature'] ?? null,
                'max_tokens'  => $body['max_tokens']  ?? null,
                'overwrite'   => $body['overwrite']   ?? true,
            ]);

            $this->success($result);
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    private function generateAllSSE(int $articleId): void {
        // SSE headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');


        while (ob_get_level()) {
            ob_end_flush();
        }
        ob_implicit_flush();

        $body = [];
        $raw  = file_get_contents('php://input');
        if ($raw) {
            $body = json_decode($raw, true) ?? [];
        }

        try {
            $service = $this->createService();
            $service->generateAllBlocksSSE($articleId, [
                'model'       => $body['model']       ?? null,
                'temperature' => $body['temperature'] ?? null,
                'max_tokens'  => $body['max_tokens']  ?? null,
                'overwrite'   => $body['overwrite']   ?? true,
            ]);
        } catch (Throwable $e) {
            echo "event: error\n";
            echo "data: " . json_encode(['message' => $e->getMessage()], JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
        }

        exit;
    }

    private function generateBlock(int $articleId): void {
        $body = $this->getJsonBody();

        $blockId = (int) ($body['block_id'] ?? 0);
        if ($blockId <= 0) $this->error('block_id обязателен', 422);

        try {
            $service = $this->createService();
            $result = $service->generateSingleBlock($articleId, $blockId, [
                'model'       => $body['model']       ?? null,
                'temperature' => $body['temperature'] ?? null,
                'max_tokens'  => $body['max_tokens']  ?? null,
            ]);

            $this->success($result);
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    private function generateMeta(int $articleId): void {
        $body = $this->getJsonBody();

        try {
            $service = $this->createService();
            $result = $service->generateMeta($articleId, [
                'model'       => $body['model']       ?? null,
                'temperature' => $body['temperature'] ?? null,
            ]);

            $this->success($result);
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    private function buildResearch(int $articleId): void {
        $body = $this->getJsonBody();

        // Manual save: client posts {dossier: "..."} to overwrite without GPT call
        if (array_key_exists('dossier', $body)) {
            try {
                $svc = new ArticleResearchService(new GptClient());
                $dossier = (string)($body['dossier'] ?? '');
                $svc->saveManual($articleId, $dossier, $body['status'] ?? 'ready');
                $row = Database::getInstance()->fetchOne(
                    "SELECT research_dossier, research_status, research_at FROM "
                    . SeoArticle::SEO_ARTICLE_TABLE . " WHERE id = ?", [$articleId]
                );
                $this->success([
                    'mode' => 'manual_save',
                    'dossier' => $row['research_dossier'] ?? null,
                    'status'  => $row['research_status'] ?? 'none',
                    'at'      => $row['research_at'] ?? null,
                ]);
            } catch (Throwable $e) {
                $this->error($e->getMessage(), 500);
            }
            return;
        }

        try {
            $svc = new ArticleResearchService(new GptClient());
            $result = $svc->buildDossier($articleId, [
                'model' => $body['model'] ?? null,
                'force' => !empty($body['force']),
                'prune' => !empty($body['prune']),
                'prune_model' => $body['prune_model'] ?? null,
            ]);
            $this->success($result);
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    private function researchProgress(int $articleId): void {
        try {
            $db = Database::getInstance();
            $row = $db->fetchOne(
                "SELECT research_status, research_at FROM " . SeoArticle::SEO_ARTICLE_TABLE . " WHERE id = ?",
                [$articleId]
            );
            if (!$row) {
                $this->error('Article not found', 404);
                return;
            }
            $rows = $db->fetchAll(
                "SELECT operation,
                        SUM(prompt_tokens) AS prompt_tokens,
                        SUM(completion_tokens) AS completion_tokens,
                        SUM(total_tokens) AS total_tokens,
                        SUM(cost_usd) AS cost_usd
                   FROM seo_token_usage
                  WHERE entity_type = 'article'
                    AND entity_id = ?
                    AND category = 'article_research'
                  GROUP BY operation",
                [$articleId]
            );
            $byOp = [];
            foreach ($rows as $r) {
                $op = (string)($r['operation'] ?? '');
                if ($op === '') $op = 'research';
                $byOp[$op] = [
                    'prompt_tokens'     => (int)$r['prompt_tokens'],
                    'completion_tokens' => (int)$r['completion_tokens'],
                    'total_tokens'      => (int)$r['total_tokens'],
                    'cost_usd'          => (float)$r['cost_usd'],
                ];
            }
            $this->success([
                'status'             => $row['research_status'] ?? 'none',
                'research_at'        => $row['research_at'] ?? null,
                'tokens_by_operation' => $byOp,
            ]);
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    private function buildOutline(int $articleId): void {
        $body = $this->getJsonBody();

        // Manual save: client posts {outline: "<json>"} to overwrite without GPT call
        if (array_key_exists('outline', $body)) {
            try {
                $svc = new ArticleOutlineService(new GptClient());
                $outline = (string)($body['outline'] ?? '');
                $svc->saveManual($articleId, $outline, $body['status'] ?? 'ready');
                $row = Database::getInstance()->fetchOne(
                    "SELECT article_outline, outline_status FROM "
                    . SeoArticle::SEO_ARTICLE_TABLE . " WHERE id = ?", [$articleId]
                );
                $this->success([
                    'mode'    => 'manual_save',
                    'outline' => $row['article_outline'] ?? null,
                    'status'  => $row['outline_status'] ?? 'none',
                ]);
            } catch (Throwable $e) {
                $this->error($e->getMessage(), 500);
            }
            return;
        }

        try {
            $svc = new ArticleOutlineService(new GptClient());
            $result = $svc->buildOutline($articleId, [
                'model' => $body['model'] ?? null,
                'force' => !empty($body['force']),
            ]);
            $this->success($result);
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    private function listModels(): void {
        $this->success([
            'models' => [
                ['id' => 'gpt-4o',       'name' => 'GPT-4o',        'description' => 'Лучшее качество + скорость'],
                ['id' => 'gpt-4o-mini',  'name' => 'GPT-4o Mini',   'description' => 'Быстрый и дешёвый'],
                ['id' => 'gpt-4-turbo',  'name' => 'GPT-4 Turbo',   'description' => 'Самое высокое качество'],
                ['id' => 'gpt-4.1',      'name' => 'GPT-4.1',       'description' => 'Новейшая модель'],
                ['id' => 'gpt-4.1-mini', 'name' => 'GPT-4.1 Mini',  'description' => 'Новейшая компактная модель'],
                ['id' => 'o3-mini',      'name' => 'o3-mini',       'description' => 'Reasoning модель'],
            ],
            'default' => GPT_DEFAULT_MODEL,
        ]);
    }

    private function checkStatus(): void {
        $hasKey = !empty(GPT_API_KEY);

        $this->success([
            'api_key_configured' => $hasKey,
            'default_model'      => GPT_DEFAULT_MODEL,
            'timeout'            => GPT_TIMEOUT,
        ]);
    }

    private function createService(): ArticleGeneratorService {
        return new ArticleGeneratorService(new GptClient());
    }
}
