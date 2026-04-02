<?php

declare(strict_types=1);

namespace Seo\Controller;

use Seo\Entity\SeoImage;
use Seo\Service\ImageGeneratorService;
use Throwable;

/*
    GET    /images            — список (без base64)
    GET    /images/{id}       — одно изображение с base64
    GET    /images/{id}/raw   — отдача бинарника (для <img src="">)
    POST   /images            — загрузить / создать
    PUT    /images/{id}       — обновить метаданные или данные
    DELETE /images/{id}       — удалить
 */
class ImageController extends AbstractController {


    public function dispatch(string $method, ?string $action, ?int $id): void {
        if ($id !== null && $action === 'raw' && $method === 'GET') {
            $this->raw($id);
            return;
        }

        if ($method === 'POST' && $action === 'upload' && $id === null) {
            $this->upload();
            return;
        }

        if ($method === 'POST' && $action === 'generate' && $id === null) {
            $this->generate();
            return;
        }

        if ($method === 'POST' && $action === 'generate-all' && $id === null) {
            $this->generateAll();
            return;
        }

        if ($method === 'POST' && $id !== null && $action === 'regenerate') {
            $this->regenerate($id);
            return;
        }

        switch ($method) {
            case 'GET':
                $id !== null ? $this->show($id) : $this->index();
                break;
            case 'POST':
                $this->create();
                break;
            case 'PUT':
            case 'PATCH':
                $id !== null ? $this->update($id) : $this->error('ID обязателен');
                break;
            case 'DELETE':
                $id !== null ? $this->delete($id) : $this->error('ID обязателен');
                break;
            default:
                $this->methodNotAllowed();
        }
    }

    private function index(): void {
        $page    = $this->getPage();
        $perPage = $this->getPerPage();

        $where  = '1=1';
        $params = [];

        $articleId = $this->getParam('article_id');
        if ($articleId !== null) {
            $where .= ' AND article_id = :article_id';
            $params[':article_id'] = (int)$articleId;
        }

        $blockId = $this->getParam('block_id');
        if ($blockId !== null) {
            $where .= ' AND block_id = :block_id';
            $params[':block_id'] = (int)$blockId;
        }

        $source = $this->getParam('source');
        if ($source !== null) {
            $where .= ' AND source = :source';
            $params[':source'] = $source;
        }

        $total = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM " . SeoImage::SEO_IMAGE_TABLE . " WHERE {$where}",
            $params
        );

        $rows = $this->db->fetchAll(
            "SELECT id, article_id, block_id, name, alt_text, mime_type,
                    width, height, source, gpt_prompt,
                    LENGTH(data_base64) AS data_length,
                    created_at, updated_at
             FROM " . SeoImage::SEO_IMAGE_TABLE . "
             WHERE {$where}
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset",
            array_merge($params, [':limit' => $perPage, ':offset' => ($page - 1) * $perPage])
        );

        $items = array_map(static function (array $row) {
            $row['has_data'] = ((int)($row['data_length'] ?? 0)) > 0;
            $row['size_bytes'] = (int)(((int)($row['data_length'] ?? 0)) * 3 / 4);
            unset($row['data_length']);
            return $row;
        }, $rows);

        $this->paginated($items, $total, $page, $perPage);
    }


    private function show(int $id): void {
        $row = $this->db->fetchOne("SELECT * FROM " . SeoImage::SEO_IMAGE_TABLE . " WHERE id = :id", [':id' => $id]);
        if ($row === null) $this->notFound('Изображение');

        $image = new SeoImage($row);
        $this->success($image->toFullArray());
    }


    private function raw(int $id): void {
        $row = $this->db->fetchOne(
            "SELECT mime_type, data_base64 FROM " . SeoImage::SEO_IMAGE_TABLE . " WHERE id = :id", [':id' => $id]);

        if ($row === null) {
            http_response_code(404);
            exit;
        }

        $binary = base64_decode($row['data_base64']);
        header('Content-Type: ' . $row['mime_type']);
        header('Content-Length: ' . strlen($binary));
        header('Cache-Control: public, max-age=31536000');
        echo $binary;
        exit;
    }


    private function create(): void {
        $data = $this->getJsonBody();
        $this->abortIfErrors($this->validateRequired($data, ['data_base64']));

        $image = new SeoImage($data);

        if ($image->getWidth() === null || $image->getHeight() === null) {
            $this->detectDimensions($image);
        }

        $newId = $this->db->insert(SeoImage::SEO_IMAGE_TABLE, $image->toArray());
        $image->setId($newId);

        $this->success($image->toArrayLight(), 201);
    }

    private function update(int $id): void {
        $existing = $this->db->fetchOne("SELECT * FROM " . SeoImage::SEO_IMAGE_TABLE . " WHERE id = :id", [':id' => $id]);
        if ($existing === null) $this->notFound('Изображение');

        $data = $this->getJsonBody();
        $image = new SeoImage($existing);
        $image->fromArray($data);

        if (isset($data['data_base64'])) $this->detectDimensions($image);

        $this->db->update(SeoImage::SEO_IMAGE_TABLE, $image->toArray(), 'id = :id', [':id' => $id]);
        $this->success($image->toArrayLight());
    }

    private function delete(int $id): void {
        $deleted = $this->db->delete(SeoImage::SEO_IMAGE_TABLE, 'id = :id', [':id' => $id]);
        if ($deleted === 0) $this->notFound('Изображение');
        $this->success(['deleted' => true]);
    }

    private function upload(): void {
        if (empty($_FILES['file'])) $this->error('Файл не передан (поле "file")', 422);

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errMap = [
                UPLOAD_ERR_INI_SIZE   => 'Файл превышает upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE  => 'Файл превышает MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL    => 'Файл загружен частично',
                UPLOAD_ERR_NO_FILE    => 'Файл не был загружен',
                UPLOAD_ERR_NO_TMP_DIR => 'Нет временной директории',
                UPLOAD_ERR_CANT_WRITE => 'Ошибка записи',
            ];
            $this->error($errMap[$file['error']] ?? 'Ошибка загрузки #'.$file['error'], 422);
        }

        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        $mime = $file['type'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
        if (!in_array($mime, $allowed, true)) $this->error('Недопустимый тип файла: ' . $mime . '. Разрешены: ' . implode(', ', $allowed), 422);


        // Size limit: 10MB
        if ($file['size'] > 10 * 1024 * 1024) $this->error('Файл слишком большой (макс. 10MB)', 422);

        $binary  = file_get_contents($file['tmp_name']);
        $base64  = base64_encode($binary);

        $width = null; $height = null;
        $info = @getimagesizefromstring($binary);
        if ($info !== false) {
            $width = $info[0];
            $height = $info[1];
            $mime = $info['mime'] ?: $mime;
        }

        $articleId = !empty($_POST['article_id']) ? (int)$_POST['article_id'] : null;
        $blockId = !empty($_POST['block_id']) ? (int)$_POST['block_id'] : null;
        $name = $_POST['name'] ?? pathinfo($file['name'], PATHINFO_FILENAME);
        $altText = $_POST['alt_text'] ?? $name;
        $source = $_POST['source'] ?? 'uploaded';
        $layout = $_POST['layout'] ?? 'center';

        $image = new SeoImage([
            'article_id'  => $articleId,
            'block_id'    => $blockId,
            'name'        => $name,
            'alt_text'    => $altText,
            'mime_type'   => $mime,
            'width'       => $width,
            'height'      => $height,
            'data_base64' => $base64,
            'source'      => $source,
            'layout'      => $layout,
        ]);

        $newId = $this->db->insert(SeoImage::SEO_IMAGE_TABLE, $image->toArray());
        $image->setId($newId);

        $this->success($image->toArrayLight(), 201);
    }

    //  article_id, block_id, size, quality, style, custom_prompt
    private function generate(): void {
        $data = $this->getJsonBody();
        $articleId = (int)($data['article_id'] ?? 0);
        $blockId   = (int)($data['block_id'] ?? 0);

        if ($articleId <= 0 || $blockId <= 0) $this->error('article_id и block_id обязательны', 422);


        $options = array_intersect_key($data, array_flip(['size', 'quality', 'style', 'custom_prompt', 'prompt_model']));

        try {
            $service = new ImageGeneratorService();
            $result = $service->generateForBlock($articleId, $blockId, $options);
            $this->success($result, 201);
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    // article_id, overwrite, size, quality, style
    private function generateAll(): void {
        $data = $this->getJsonBody();
        $articleId = (int)($data['article_id'] ?? 0);

        if ($articleId <= 0) $this->error('article_id обязателен', 422);


        $options = array_intersect_key($data, array_flip(['overwrite', 'size', 'quality', 'style', 'prompt_model']));

        try {
            $service = new ImageGeneratorService();
            $result = $service->generateForArticle($articleId, $options);
            $this->success($result);
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }


    //   custom_prompt, size, quality, style
    private function regenerate(int $imageId): void {
        $existing = $this->db->fetchOne(
            "SELECT * FROM " . SeoImage::SEO_IMAGE_TABLE . " WHERE id = :id", [':id' => $imageId]);

        if ($existing === null) $this->notFound('Изображение');

        $data = $this->getJsonBody();
        $articleId = (int)($existing['article_id'] ?? 0);
        $blockId   = (int)($existing['block_id'] ?? 0);

        if ($articleId <= 0) $this->error('Изображение не привязано к статье', 422);

        $options = array_intersect_key($data, array_flip(['size', 'quality', 'style', 'prompt_model']));

        if (!empty($data['custom_prompt'])) {
            $options['custom_prompt'] = $data['custom_prompt'];
        } elseif (!empty($existing['gpt_prompt'])) {
            $options['custom_prompt'] = $existing['gpt_prompt'];
        }

        try {
            $service = new ImageGeneratorService();

            if ($blockId > 0) {
                $result = $service->generateForBlock($articleId, $blockId, $options);
            } else {
                $prompt = $options['custom_prompt'] ?? $existing['gpt_prompt'] ?? 'Medical illustration';
                $result = $service->generateCustom($articleId, $prompt, $options);
            }

            $this->db->delete(SeoImage::SEO_IMAGE_TABLE, 'id = :id', [':id' => $imageId]);

            $this->success($result, 201);
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    private function detectDimensions(SeoImage $image): void {
        $binary = base64_decode($image->getDataBase64());
        if ($binary === false) {
            return;
        }

        $info = @getimagesizefromstring($binary);
        if ($info !== false) {
            $image->setWidth($info[0]);
            $image->setHeight($info[1]);
            if (!empty($info['mime'])) {
                $image->setMimeType($info['mime']);
            }
        }
    }
}
