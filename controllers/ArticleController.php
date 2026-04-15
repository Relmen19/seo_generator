<?php

declare(strict_types=1);

namespace Seo\Controller;

use Seo\Entity\SeoArticle;
use Seo\Entity\SeoArticleBlock;
use Seo\Entity\SeoCatalog;
use Seo\Entity\SeoAuditLog;
use Seo\Service\HtmlRendererService;

/*
     GET             /articles                  список с фильтрами и пагинацией
     GET             /articles/{id}             статья с блоками
     POST            /articles                  создать
     PUT             /articles/{id}             обновить метаданные
     DELETE          /articles/{id}             удалить
     PUT             /articles/{id}/status      сменить статус (publish / unpublish / draft / review)
     PUT             /articles/{id}/toggle      вкл / выкл (is_active)
     PUT             /articles/{id}/move        переместить в другой каталог
     GET             /articles/{id}/blocks      блоки статьи
     POST            /articles/{id}/blocks      добавить блок
     PUT             /articles/{id}/blocks      обновить блок
     DELETE          /articles/{id}/blocks      удалить блок
     PUT             /articles/{id}/reorder     пересортировать блоки
 */
class ArticleController extends AbstractController {

    public function dispatch(string $method, ?string $action, ?int $id): void {
        if ($action === 'render-block' && $method === 'POST') {
            $this->renderBlock();
            return;
        }

        if ($id !== null && $action !== null) {
            switch ($action) {
                case 'blocks':
                    $this->dispatchBlocks($method, $id);
                    return;
                case 'status':
                    $method === 'PUT' ? $this->changeStatus($id) : $this->methodNotAllowed();
                    return;
                case 'toggle':
                    $method === 'PUT' ? $this->toggle($id) : $this->methodNotAllowed();
                    return;
                case 'move':
                    $method === 'PUT' ? $this->move($id) : $this->methodNotAllowed();
                    return;
                case 'reorder':
                    $method === 'PUT' ? $this->reorderBlocks($id) : $this->methodNotAllowed();
                    return;
            }
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

        $page = $this->getPage();
        $perPage = $this->getPerPage();

        $where = '1=1';
        $params = [];

        $profileId = $this->getParam('profile_id');
        if ($profileId !== null && $profileId !== '') {
            $where .= ' AND a.profile_id = :profile_id';
            $params[':profile_id'] = (int)$profileId;
        }

        $status = $this->getParam('status');
        if ($status !== null && SeoArticle::isValidStatus($status)) {
            $where .= ' AND a.status = :status';
            $params[':status'] = $status;
        }

        $catalogId = $this->getParam('catalog_id');
        if ($catalogId !== null) {
            $where .= ' AND a.catalog_id = :catalog_id';
            $params[':catalog_id'] = (int)$catalogId;
        }

        $isActive = $this->getParam('is_active');
        if ($isActive !== null) {
            $where .= ' AND a.is_active = :is_active';
            $params[':is_active'] = (int)$isActive;
        }

        $search = $this->getParam('q');
        if ($search !== null && trim($search) !== '') {
            $where .= ' AND (a.title LIKE :q OR a.keywords LIKE :q2)';
            $params[':q']  = '%' . trim($search) . '%';
            $params[':q2'] = '%' . trim($search) . '%';
        }

        $sortOptions = [
            'created' => 'a.created_at DESC',
            'updated' => 'a.updated_at DESC',
            'title' => 'a.title ASC',
            'published' => 'a.published_at DESC',
        ];
        $sortKey = $this->getParam('sort', 'updated');
        $orderBy = $sortOptions[$sortKey] ?? $sortOptions['updated'];

        $total = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM " . SeoArticle::SEO_ARTICLE_TABLE . " a WHERE {$where}", $params);

        $rows = $this->db->fetchAll(
            "SELECT a.*, c.name AS catalog_name, t.name AS template_name
             FROM " . SeoArticle::SEO_ARTICLE_TABLE . " a
             LEFT JOIN seo_catalogs c ON a.catalog_id = c.id
             LEFT JOIN seo_templates t ON a.template_id = t.id
             WHERE {$where}
             ORDER BY {$orderBy}
             LIMIT :limit OFFSET :offset",
            array_merge($params, [':limit' => $perPage, ':offset' => ($page - 1) * $perPage]));

        $items = array_map(static function (array $row) {
            $article = new SeoArticle($row);
            $data = $article->toFullArray();
            $data['catalog_name']  = $row['catalog_name'];
            $data['template_name'] = $row['template_name'];
            return $data;
        }, $rows);

        $this->paginated($items, $total, $page, $perPage);
    }

    private function show(int $id): void {
        $row = $this->db->fetchOne(
            "SELECT a.*, c.name AS catalog_name, c.slug AS catalog_slug,
                    t.name AS template_name
             FROM " . SeoArticle::SEO_ARTICLE_TABLE . " a
             LEFT JOIN seo_catalogs c ON a.catalog_id = c.id
             LEFT JOIN seo_templates t ON a.template_id = t.id
             WHERE a.id = :aid",
            [':aid' => $id]);

        if ($row === null) $this->notFound('Статья');

        $article = new SeoArticle($row);
        $article->setBlocks($this->loadBlocks($id));

        $data = $article->toFullArray();
        $data['catalog_name'] = $row['catalog_name'];
        $data['catalog_slug'] = $row['catalog_slug'];
        $data['template_name'] = $row['template_name'];

        $data['images_count'] = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM seo_images WHERE article_id = :aid", [':aid' => $id]);

        $stats = $this->db->fetchOne(
            "SELECT COALESCE(SUM(views_total), 0) AS views_total, COALESCE(SUM(views_unique), 0) AS views_unique
                 FROM seo_page_stats_daily WHERE article_id = :aid", [':aid' => $id]);

        $data['stats'] = [
            'views_total'  => (int)($stats['views_total'] ?? 0),
            'views_unique' => (int)($stats['views_unique'] ?? 0),
        ];

        $this->success($data);
    }

    private function create(): void {
        $data = $this->getJsonBody();
        $this->abortIfErrors($this->validateRequired($data, ['title', 'slug', 'template_id']));

        $article = new SeoArticle($data);
        $article->setStatus(SeoArticle::STATUS_DRAFT);
        $article->setVersion(1);

        $newId = $this->db->insert(SeoArticle::SEO_ARTICLE_TABLE, $article->toArray());

        $this->db->insert(SeoAuditLog::SEO_AUDIT_LOG_TABLE, SeoAuditLog::articleAction(
            $newId, SeoAuditLog::ACTION_CREATE, $data['created_by'] ?? 'admin')
        ->toArray());

        $article->setId($newId);
        $this->success($article->toFullArray(), 201);
    }

    private function update(int $id): void {
        $existing = $this->db->fetchOne("SELECT * FROM " . SeoArticle::SEO_ARTICLE_TABLE . " WHERE id = :aid", [':aid' => $id]);
        if ($existing === null) $this->notFound('Статья');

        $data = $this->getJsonBody();

        unset($data['status']);
        $this->chechSlugNotDuplicate($id, $data);

        $article = new SeoArticle($existing);
        $article->fromArray($data);

        $this->db->update(SeoArticle::SEO_ARTICLE_TABLE, $article->toArray(), 'id = :aid', [':aid' => $id]);

        $this->db->insert(SeoAuditLog::SEO_AUDIT_LOG_TABLE, SeoAuditLog::articleAction(
            $id, SeoAuditLog::ACTION_UPDATE, $data['actor'] ?? 'admin',
            ['changed_fields' => array_keys($data)]
        )->toArray());

        $this->success($article->toFullArray());
    }


    private function delete(int $id): void {
        $existing = $this->db->fetchOne("SELECT * FROM " . SeoArticle::SEO_ARTICLE_TABLE . " WHERE id = :aid", [':aid' => $id]);
        if ($existing === null) $this->notFound('Статья');

        $article = new SeoArticle($existing);
        if ($article->isPublished()) {
            $this->error('Нельзя удалить опубликованную статью. Сначала снимите с публикации.', 409);
        }

        $this->db->delete(SeoArticle::SEO_ARTICLE_TABLE, 'id = :aid', [':aid' => $id]);

        $this->db->insert(SeoAuditLog::SEO_AUDIT_LOG_TABLE, SeoAuditLog::articleAction(
            $id, SeoAuditLog::ACTION_DELETE, $_GET['actor'] ?? 'admin'
        )->toArray());

        $this->success(['deleted' => true]);
    }

    private function changeStatus(int $id): void {
        $existing = $this->db->fetchOne("SELECT * FROM " . SeoArticle::SEO_ARTICLE_TABLE . " WHERE id = :aid", [':aid' => $id]);
        if ($existing === null) $this->notFound('Статья');

        $data = $this->getJsonBody();
        $newStatus = $data['status'] ?? '';

        if (!SeoArticle::isValidStatus($newStatus)) {
            $this->error("Невалидный статус: {$newStatus}. Допустимые: " . implode(', ', SeoArticle::STATUSES), 422);
        }

        $updateData = ['status' => $newStatus];

        if ($newStatus === SeoArticle::STATUS_PUBLISHED) {
            $updateData['published_at'] = date('Y-m-d H:i:s');
        }

        $this->db->update(SeoArticle::SEO_ARTICLE_TABLE, $updateData, 'id = :aid', [':aid' => $id]);

        $action = ($newStatus === SeoArticle::STATUS_PUBLISHED)
            ? SeoAuditLog::ACTION_PUBLISH
            : (($newStatus === SeoArticle::STATUS_UNPUBLISHED) ? SeoAuditLog::ACTION_UNPUBLISH : SeoAuditLog::ACTION_UPDATE);

        $this->db->insert(SeoAuditLog::SEO_AUDIT_LOG_TABLE, SeoAuditLog::articleAction(
            $id, $action, $data['actor'] ?? 'admin', ['old_status' => $existing['status'], 'new_status' => $newStatus])
            ->toArray());

        $this->success(['status' => $newStatus]);
    }

    private function toggle(int $id): void {
        $existing = $this->db->fetchOne("SELECT is_active FROM " . SeoArticle::SEO_ARTICLE_TABLE . " WHERE id = :id", [':id' => $id]);
        if ($existing === null) $this->notFound('Статья');

        $newValue = $existing['is_active'] ? 0 : 1;
        $this->db->update(SeoArticle::SEO_ARTICLE_TABLE, ['is_active' => $newValue], 'id = :aid', [':aid' => $id]);

        $this->success(['is_active' => (bool)$newValue]);
    }

    private function move(int $id): void {
        $existing = $this->db->fetchOne("SELECT * FROM " . SeoArticle::SEO_ARTICLE_TABLE . " WHERE id = :id", [':id' => $id]);
        if ($existing === null) {
            $this->notFound('Статья');
        }

        $data = $this->getJsonBody();
        $catalogId = isset($data['catalog_id']) ? (int)$data['catalog_id'] : null;

        if ($catalogId !== null) {
            $cat = $this->db->fetchOne("SELECT id FROM seo_catalogs WHERE id = :cid", [':cid' => $catalogId]);
            if ($cat === null) {
                $this->error('Каталог не найден', 404);
            }
        }

        $this->db->update(SeoArticle::SEO_ARTICLE_TABLE, ['catalog_id' => $catalogId], 'id = :aid', [':aid' => $id]);

        $this->db->insert(SeoAuditLog::SEO_AUDIT_LOG_TABLE, SeoAuditLog::articleAction(
            $id, SeoAuditLog::ACTION_UPDATE, $data['actor'] ?? 'admin',
            ['action' => 'move', 'old_catalog' => $existing['catalog_id'], 'new_catalog' => $catalogId])
            ->toArray());

        $this->success(['catalog_id' => $catalogId]);
    }

    private function dispatchBlocks(string $method, int $articleId): void {
        switch ($method) {
            case 'GET':
                $blocks = $this->loadBlocks($articleId);
                $this->success(array_map(fn(SeoArticleBlock $b) => $b->toFullArray(), $blocks));
                break;
            case 'POST':
                $this->addBlock($articleId);
                break;
            case 'PUT':
            case 'PATCH':
                $this->updateBlock($articleId);
                break;
            case 'DELETE':
                $this->deleteBlock($articleId);
                break;
            default:
                $this->methodNotAllowed();
        }
    }

    private function addBlock(int $articleId): void {
        $data = $this->getJsonBody();
        $this->abortIfErrors($this->validateRequired($data, ['type', 'content']));

        $block = new SeoArticleBlock($data);
        $block->setArticleId($articleId);

        if (!isset($data['sort_order'])) {
            $maxSort = (int)$this->db->fetchColumn(
                "SELECT COALESCE(MAX(sort_order), 0) FROM " . SeoArticleBlock::SEO_ART_BLOCK_TABLE . " WHERE article_id = :aid",
                [':aid' => $articleId]
            );
            $block->setSortOrder($maxSort + 1);
        }

        if (is_array($data['content'])) {
            $block->setContent($data['content']);
        }

        $newId = $this->db->insert(SeoArticleBlock::SEO_ART_BLOCK_TABLE, $block->toArray());
        $block->setId($newId);

        $this->db->update(SeoArticle::SEO_ARTICLE_TABLE, [], 'id = :aid', [':aid' => $articleId], ['version' => 'version + 1']);

        $this->success($block->toFullArray(), 201);
    }

    private function updateBlock(int $articleId): void {
        $data = $this->getJsonBody();
        $blockId = (int)($data['block_id'] ?? $data['id'] ?? 0);
        if ($blockId === 0) $this->error('block_id обязателен');

        $existing = $this->db->fetchOne(
            "SELECT * FROM " . SeoArticleBlock::SEO_ART_BLOCK_TABLE . " WHERE id = :id AND article_id = :aid",
            [':id' => $blockId, ':aid' => $articleId]
        );

        if ($existing === null) $this->notFound('Блок статьи');

        $block = new SeoArticleBlock($existing);
        $block->fromArray($data);

        if (isset($data['content']) && is_array($data['content'])) {
            $block->setContent($data['content']);
        }

        $this->db->update(SeoArticleBlock::SEO_ART_BLOCK_TABLE, $block->toArray(), 'id = :aid', [':aid' => $blockId]);

        $this->db->update(SeoArticle::SEO_ARTICLE_TABLE, [], 'id = :aid', [':aid' => $articleId], ['version' => 'version + 1']);

        $this->success($block->toFullArray());
    }

    private function deleteBlock(int $articleId): void {
        $data = $this->getJsonBody();
        $blockId = (int)($data['block_id'] ?? $data['id'] ?? 0);
        if ($blockId === 0) $this->error('block_id обязателен');

        $deleted = $this->db->delete(
            SeoArticleBlock::SEO_ART_BLOCK_TABLE,
            'id = :id AND article_id = :aid',
            [':id' => $blockId, ':aid' => $articleId]
        );
        if ($deleted === 0) $this->notFound('Блок статьи');

        $this->success(['deleted' => true]);
    }

    private function reorderBlocks(int $articleId): void {
        $data = $this->getJsonBody();
        $order = $data['order'] ?? [];

        if (empty($order)) $this->error('Массив order обязателен');

        $this->db->transaction(function () use ($articleId, $order) {
            // для массива
            if (isset($order[0]) && !is_array($order[0])) {
                foreach ($order as $i => $blockId) {
                    $this->db->execute(
                        "UPDATE " . SeoArticleBlock::SEO_ART_BLOCK_TABLE . " SET sort_order = :sort
                         WHERE id = :id AND article_id = :aid",
                        [':sort' => $i + 1, ':id' => (int)$blockId, ':aid' => $articleId]
                    );
                }
            } else {
                // для мапы
                foreach ($order as $item) {
                    $this->db->execute(
                        "UPDATE " . SeoArticleBlock::SEO_ART_BLOCK_TABLE . " SET sort_order = :sort
                         WHERE id = :id AND article_id = :aid",
                        [':sort' => (int)$item['sort_order'], ':id' => (int)$item['id'], ':aid' => $articleId]
                    );
                }
            }
        });

        $this->success(['reordered' => true]);
    }


    private function loadBlocks(int $articleId): array {
        $rows = $this->db->fetchAll("SELECT * FROM " . SeoArticleBlock::SEO_ART_BLOCK_TABLE . " WHERE article_id = :aid ORDER BY sort_order",
            [':aid' => $articleId]);

        return array_map(fn(array $r) => new SeoArticleBlock($r), $rows);
    }

    private function chechSlugNotDuplicate(int $id, array $data): void {
        if (isset($data['slug'])) {
            $rows = $this->db->fetchOne("SELECT slug FROM " . SeoArticle::SEO_ARTICLE_TABLE . " WHERE slug = :slug AND id != :aid",
                [':slug' => $data['slug'], ':aid' => $id]);

            if (!empty($rows)) $this->error("Slug '{$data['slug']}' уже существует, выберите другой");
        }
    }

    private function renderBlock(): void {
        $body    = $this->getJsonBody();
        $type    = trim($body['type'] ?? '');
        $content = $body['content'] ?? [];

        if ($type === '') {
            $this->error('Параметр type обязателен', 400);
        }
        if (!is_array($content)) {
            $content = [];
        }

        $service = new HtmlRendererService();
        $html    = $service->renderSingleBlock($type, $content);

        $wrapped = '<!DOCTYPE html><html><head><meta charset="utf-8">'
            . '<style>*{box-sizing:border-box}body{font-family:system-ui,-apple-system,sans-serif;'
            . 'padding:16px;background:#fff;color:#111;line-height:1.6;margin:0}'
            . 'img{max-width:100%;height:auto}</style>'
            . '</head><body>' . $html . '</body></html>';

        header('Content-Type: text/html; charset=utf-8');
        echo $wrapped;
        exit;
    }
}
