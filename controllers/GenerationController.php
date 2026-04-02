<?php

declare(strict_types=1);

namespace Seo\Controller;

use Seo\Service\ArticleGeneratorService;
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

        if ($id !== null && $action === 'block') {
            $this->generateBlock($id);
            return;
        }

        if ($action !== null && $id !== null) {
            $this->error('Неизвестный endpoint', 404);
            return;
        }

        $this->error('Укажите article_id: POST /generate/{articleId}[/sse|/meta|/block]');
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
        ob_implicit_flush(1);

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
