<?php

declare(strict_types=1);

namespace Seo\Controller;

use Seo\Service\TelegramPostService;
use Throwable;

/*
   POST   /telegram/{articleId}/build-preview   — build draft + render images
   POST   /telegram/{articleId}/send            — send immediately
   POST   /telegram/{articleId}/schedule        — schedule for later
   GET    /telegram/{articleId}/posts           — list posts for article
   GET    /telegram/posts/{postId}              — get single post
   PUT    /telegram/posts/{postId}              — update post data
   DELETE /telegram/posts/{postId}              — delete draft/scheduled post
   POST   /telegram/test-connection             — test bot token + channel
   POST   /telegram/refresh-channel/{profileId} — refresh channel info
   GET    /telegram/rendered-image/{imageId}    — get rendered image data
 */
class TelegramController extends AbstractController {

    public function dispatch(string $method, ?string $action, ?int $id): void {
        $service = new TelegramPostService();

        // POST /telegram/test-connection
        if ($method === 'POST' && $action === 'test-connection') {
            $this->doTestConnection($service);
            return;
        }

        // POST /telegram/refresh-channel/{profileId}
        if ($method === 'POST' && $action === 'refresh-channel' && $id !== null) {
            $this->doRefreshChannel($service, $id);
            return;
        }

        // GET /telegram/rendered-image/{imageId}
        if ($method === 'GET' && $action === 'rendered-image' && $id !== null) {
            $this->doGetRenderedImage($service, $id);
            return;
        }

        // Routes with article ID as first segment
        // POST /telegram/{articleId}/build-preview
        if ($method === 'POST' && $id !== null && $action === 'build-preview') {
            $this->doBuildPreview($service, $id);
            return;
        }

        // POST /telegram/{articleId}/send
        if ($method === 'POST' && $id !== null && $action === 'send') {
            $this->doSend($service, $id);
            return;
        }

        // POST /telegram/{articleId}/schedule
        if ($method === 'POST' && $id !== null && $action === 'schedule') {
            $this->doSchedule($service, $id);
            return;
        }

        // GET /telegram/{articleId}/posts — list posts for article
        if ($method === 'GET' && $id !== null && $action === 'posts') {
            $this->doListPosts($service, $id);
            return;
        }

        // GET /telegram/post/{postId} — get single post
        if ($method === 'GET' && $action === 'post' && $id !== null) {
            $this->doGetPost($service, $id);
            return;
        }

        // PUT /telegram/post/{postId} — update post
        if ($method === 'PUT' && $action === 'post' && $id !== null) {
            $this->doUpdatePost($service, $id);
            return;
        }

        // DELETE /telegram/post/{postId} — delete post
        if ($method === 'DELETE' && $action === 'post' && $id !== null) {
            $this->doDeletePost($service, $id);
            return;
        }

        // DELETE /telegram/{articleId}/posts — delete all posts for article
        if ($method === 'DELETE' && $id !== null && $action === 'posts') {
            $this->doDeleteAllPosts($service, $id);
            return;
        }

        $this->error('Неизвестный маршрут Telegram', 404);
    }

    private function doTestConnection(TelegramPostService $service): void {
        $body = $this->getJsonBody();
        $botToken  = (string)($body['bot_token'] ?? '');
        $channelId = (string)($body['channel_id'] ?? '');

        if ($botToken === '' || $channelId === '') {
            $this->error('bot_token и channel_id обязательны', 422);
        }

        try {
            $result = $service->testConnection($botToken, $channelId);
            $this->success($result);
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 400);
        }
    }

    private function doRefreshChannel(TelegramPostService $service, int $profileId): void {
        try {
            $result = $service->refreshChannelInfo($profileId);
            $this->success($result);
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 400);
        }
    }

    private function doGetRenderedImage(TelegramPostService $service, int $imageId): void {
        $img = $service->getRenderedImage($imageId);
        if ($img === null) {
            $this->notFound('Изображение');
        }

        // Return as PNG binary
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=86400');
        echo base64_decode($img['image_data']);
        exit;
    }

    private function doBuildPreview(TelegramPostService $service, int $articleId): void {
        try {
            $result = $service->buildDraft($articleId);
            $this->success($result);
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    private function doSend(TelegramPostService $service, int $articleId): void {
        $body = $this->getJsonBody();
        $postId = (int)($body['post_id'] ?? 0);

        if ($postId <= 0) {
            $this->error('post_id обязателен', 422);
        }

        try {
            $result = $service->send($postId);
            $this->success($result);
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    private function doSchedule(TelegramPostService $service, int $articleId): void {
        $body = $this->getJsonBody();
        $postId      = (int)($body['post_id'] ?? 0);
        $scheduledAt = (string)($body['scheduled_at'] ?? '');

        if ($postId <= 0 || $scheduledAt === '') {
            $this->error('post_id и scheduled_at обязательны', 422);
        }

        try {
            $result = $service->schedule($postId, $scheduledAt);
            $this->success($result);
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    private function doListPosts(TelegramPostService $service, int $articleId): void {
        $posts = $service->getPostsForArticle($articleId);
        $this->success($posts);
    }

    private function doGetPost(TelegramPostService $service, int $postId): void {
        try {
            $post = $service->getPostWithImages($postId);
            $this->success($post);
        } catch (Throwable $e) {
            $this->notFound('Telegram пост');
        }
    }

    private function doUpdatePost(TelegramPostService $service, int $postId): void {
        $body = $this->getJsonBody();
        try {
            $result = $service->updatePost($postId, $body);
            $this->success($result);
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 400);
        }
    }

    private function doDeletePost(TelegramPostService $service, int $postId): void {
        try {
            $service->deletePost($postId);
            $this->success(['deleted' => true]);
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 400);
        }
    }

    private function doDeleteAllPosts(TelegramPostService $service, int $articleId): void {
        try {
            $count = $service->deleteAllForArticle($articleId);
            $this->success(['deleted' => $count]);
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 400);
        }
    }
}
