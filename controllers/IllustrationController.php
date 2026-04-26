<?php

declare(strict_types=1);

namespace Seo\Controller;

use Seo\Database;
use Seo\Entity\SeoArticleIllustration;
use Seo\Entity\SeoImage;
use Seo\Service\ImageGeneratorService;
use Throwable;

/*
   GET    /illustrations/{articleId}                — list of hero/og rows
   POST   /illustrations/{articleId}/hero           — generate hero (Imagen/DALL-E)
   POST   /illustrations/{articleId}/og             — render OG via Puppeteer
   POST   /illustrations/{articleId}/upload-hero    — manual upload (body: data_base64, mime_type?)
   POST   /illustrations/{articleId}/upload-og      — manual upload OG
   DELETE /illustrations/{articleId}/{kind}         — drop illustration row + image
 */
class IllustrationController extends AbstractController {

    public function dispatch(string $method, ?string $action, ?int $id): void {
        if ($id === null) {
            $this->error('Укажите article_id: /illustrations/{articleId}[/hero|/og|...]');
            return;
        }

        if ($method === 'GET' && $action === null) {
            $this->index($id);
            return;
        }

        if ($method === 'POST' && $action === 'hero') {
            $this->generateHero($id);
            return;
        }

        if ($method === 'POST' && $action === 'og') {
            $this->generateOg($id);
            return;
        }

        if ($method === 'POST' && $action === 'upload-hero') {
            $this->uploadManual($id, SeoArticleIllustration::KIND_HERO);
            return;
        }

        if ($method === 'POST' && $action === 'upload-og') {
            $this->uploadManual($id, SeoArticleIllustration::KIND_OG);
            return;
        }

        if ($method === 'DELETE' && in_array($action, SeoArticleIllustration::KINDS, true)) {
            $this->remove($id, $action);
            return;
        }

        $this->methodNotAllowed();
    }

    private function index(int $articleId): void {
        $rows = Database::getInstance()->fetchAll(
            "SELECT id, article_id, kind, prompt, model, image_id, status, error, cost_cents, created_at, updated_at
             FROM " . SeoArticleIllustration::TABLE . "
             WHERE article_id = ?
             ORDER BY FIELD(kind, 'hero','og','inline')",
            [$articleId]
        );
        $this->success(['items' => $rows]);
    }

    private function generateHero(int $articleId): void {
        $body = $this->getJsonBody();
        $opts = [];
        if (!empty($body['model']))         $opts['model']         = (string)$body['model'];
        if (!empty($body['size']))          $opts['size']          = (string)$body['size'];
        if (!empty($body['custom_prompt'])) $opts['custom_prompt'] = (string)$body['custom_prompt'];
        if (!empty($body['prompt_model']))  $opts['prompt_model']  = (string)$body['prompt_model'];

        try {
            $svc = new ImageGeneratorService();
            $res = $svc->generateHero($articleId, $opts);
            $this->success($res);
        } catch (Throwable $e) {
            $this->error('Hero generation failed: ' . $e->getMessage(), 500);
        }
    }

    private function generateOg(int $articleId): void {
        try {
            $svc = new ImageGeneratorService();
            $res = $svc->generateOg($articleId, []);
            $this->success($res);
        } catch (Throwable $e) {
            $this->error('OG generation failed: ' . $e->getMessage(), 500);
        }
    }

    private function uploadManual(int $articleId, string $kind): void {
        $body = $this->getJsonBody();
        $b64  = (string)($body['data_base64'] ?? '');
        if ($b64 === '') {
            $this->error('data_base64 обязателен');
            return;
        }
        // Strip data URI prefix if present.
        if (preg_match('#^data:([^;]+);base64,(.*)$#s', $b64, $m)) {
            $mime = $m[1];
            $b64  = $m[2];
        } else {
            $mime = (string)($body['mime_type'] ?? 'image/png');
        }

        $binary = base64_decode($b64, true);
        if ($binary === false || $binary === '') {
            $this->error('Некорректный base64');
            return;
        }

        $db = Database::getInstance();
        $existing = $db->fetchOne(
            "SELECT id, image_id FROM " . SeoArticleIllustration::TABLE . " WHERE article_id = ? AND kind = ?",
            [$articleId, $kind]
        );

        $width = $height = null;
        $info  = @getimagesizefromstring($binary);
        if ($info !== false) {
            $width  = $info[0];
            $height = $info[1];
            if (!empty($info['mime'])) $mime = $info['mime'];
        }

        $stmt = $db->getPdo()->prepare(
            "INSERT INTO seo_images (article_id, block_id, name, alt_text, mime_type, width, height, data_base64, source, gpt_prompt)
             VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, NULL)"
        );
        $stmt->execute([
            $articleId,
            mb_substr($kind . ' (manual upload)', 0, 200),
            null,
            $mime,
            $width,
            $height,
            $b64,
            SeoImage::SOURCE_UPLOADED,
        ]);
        $newImageId = (int)$db->getPdo()->lastInsertId();

        $row = [
            'article_id' => $articleId,
            'kind'       => $kind,
            'prompt'     => null,
            'model'      => 'manual',
            'status'     => SeoArticleIllustration::STATUS_READY,
            'error'      => null,
            'image_id'   => $newImageId,
            'cost_cents' => 0,
        ];
        if ($existing) {
            $db->update(SeoArticleIllustration::TABLE, 'id = :id', $row, [':id' => (int)$existing['id']]);
            if (!empty($existing['image_id']) && (int)$existing['image_id'] !== $newImageId) {
                try {
                    $db->getPdo()->prepare("DELETE FROM seo_images WHERE id = ?")
                        ->execute([(int)$existing['image_id']]);
                } catch (Throwable $e) {
                    error_log('[IllustrationController] failed to drop old image: ' . $e->getMessage());
                }
            }
        } else {
            $db->insert(SeoArticleIllustration::TABLE, $row);
        }

        $this->success([
            'kind'     => $kind,
            'image_id' => $newImageId,
            'model'    => 'manual',
        ]);
    }

    private function remove(int $articleId, string $kind): void {
        $db = Database::getInstance();
        $row = $db->fetchOne(
            "SELECT id, image_id FROM " . SeoArticleIllustration::TABLE . " WHERE article_id = ? AND kind = ?",
            [$articleId, $kind]
        );
        if (!$row) { $this->notFound('Иллюстрация'); return; }

        $db->getPdo()->prepare("DELETE FROM " . SeoArticleIllustration::TABLE . " WHERE id = ?")
            ->execute([(int)$row['id']]);
        if (!empty($row['image_id'])) {
            try {
                $db->getPdo()->prepare("DELETE FROM seo_images WHERE id = ?")
                    ->execute([(int)$row['image_id']]);
            } catch (Throwable $e) {
                error_log('[IllustrationController] failed to drop image on remove: ' . $e->getMessage());
            }
        }
        $this->success(['removed' => true, 'kind' => $kind]);
    }
}
