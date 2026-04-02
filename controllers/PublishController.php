<?php

declare(strict_types=1);

namespace Seo\Controller;

use Seo\Service\PublishService;
use Throwable;

/*
   POST   /publish/{articleId}           — опубликовать на хост
   POST   /publish/{articleId}/preview   — предпросмотр (HTML)
   POST   /publish/{articleId}/unpublish — снять с публикации
 */
class PublishController extends AbstractController {

    public function dispatch(string $method, ?string $action, ?int $id): void {
        if ($method !== 'POST') {
            $this->methodNotAllowed();
            return;
        }

        if ($id !== null && $action === null) {
            $this->doPublish($id);
            return;
        }

        if ($id !== null && $action === 'preview') {
            $this->doPreview($id);
            return;
        }

        if ($id !== null && $action === 'unpublish') {
            $this->doUnpublish($id);
            return;
        }

        $this->error('Укажите article_id: POST /publish/{articleId}[/preview|/unpublish]');
    }


    private function doPublish(int $articleId): void {
        $body = $this->getJsonBody();
        $targetId = (int)($body['target_id'] ?? 0);

        if ($targetId <= 0) $this->error('target_id обязателен', 422);

        try {
            $service = new PublishService();
            $result = $service->publish($articleId, $targetId);
            $this->success($result);
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    private function doPreview(int $articleId): void {
        try {
            $service = new PublishService();
            $html = $service->preview($articleId);
            header('Content-Type: text/html; charset=utf-8');
            echo $html;
            exit;
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    private function doUnpublish(int $articleId): void {
        try {
            $service = new PublishService();
            $result = $service->unpublish($articleId);
            $this->success($result);
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }
}
