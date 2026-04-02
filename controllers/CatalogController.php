<?php

declare(strict_types=1);

namespace Seo\Controller;

use Seo\Entity\SeoCatalog;
use Seo\Entity\SeoAuditLog;

/*

 GET    /catalogs              — список плоский
 GET    /catalogs/tree         — дерево целиком
 GET    /catalogs/{id}         — один каталог
 GET    /catalogs/{id}/path    — полный путь каталога
 POST   /catalogs              — создать
 PUT    /catalogs/{id}         — обновить
 PUT    /catalogs/{id}/move    — переместить (по parent_id)
 DELETE /catalogs/{id}         — удалить
 */
class CatalogController extends AbstractController {

    public function dispatch(string $method, ?string $action, ?int $id): void {
        switch ($method) {
            case 'GET':
                if ($action === 'tree' && $id === null) $this->tree();
                elseif ($action === 'path' && $id !== null) $this->path($id);
                elseif ($id !== null) $this->show($id);
                else $this->index();
                break;

            case 'POST':
                $this->create();
                break;

            case 'PUT':
                if ($id === null) $this->error('ID обязателен');

                $action === 'move' ? $this->move($id) : $this->update($id);

                break;

            case 'DELETE':
                $id === null ? $this->error('ID обязателен') : $this->delete($id);
                break;

            default: $this->methodNotAllowed();
        }
    }


    private function index(): void {
        $parentId = $this->getParam('parent_id');
        $page     = $this->getPage();
        $perPage  = $this->getPerPage();

        $where = '1=1';
        $params = [];

        $profileId = $this->getParam('profile_id');
        if ($profileId !== null && $profileId !== '') {
            $where .= ' AND profile_id = :profile_id';
            $params[':profile_id'] = (int)$profileId;
        }

        if ($parentId !== null) {
            if ($parentId === 'null' || $parentId === '0') {
                $where .= ' AND parent_id IS NULL';
            } else {
                $where .= ' AND parent_id = :parent_id';
                $params[':parent_id'] = (int)$parentId;
            }
        }

        $total = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM " . SeoCatalog::SEO_CATALOG_TABLE . " WHERE {$where}", $params);

        $rows = $this->db->fetchAll(
            "SELECT * FROM " . SeoCatalog::SEO_CATALOG_TABLE . " WHERE {$where} ORDER BY sort_order, name LIMIT :limit OFFSET :offset",
                 array_merge($params, [':limit' => $perPage, ':offset' => ($page - 1) * $perPage]));

        $items = array_map(fn(array $r) => (new SeoCatalog($r))->toFullArray(), $rows);

        $this->paginated($items, $total, $page, $perPage);
    }

    private function tree(): void {
        $where = 'is_active = 1';
        $treeParams = [];
        $profileId = $this->getParam('profile_id');
        if ($profileId !== null && $profileId !== '') {
            $where .= ' AND profile_id = :profile_id';
            $treeParams[':profile_id'] = (int)$profileId;
        }
        $rows = $this->db->fetchAll("SELECT * FROM " . SeoCatalog::SEO_CATALOG_TABLE . " WHERE {$where} ORDER BY sort_order, name", $treeParams);

        $catalogs = [];
        $byId = [];

        foreach ($rows as $row) {
            $entity = new SeoCatalog($row);
            $byId[$entity->getId()] = $entity;
            $catalogs[] = $entity;
        }

        $tree = [];
        foreach ($catalogs as $catalog) {
            $pid = $catalog->getParentId();
            if ($pid === null || !isset($byId[$pid])) {
                $tree[] = $catalog;
            } else {
                $byId[$pid]->addChild($catalog);
            }
        }

        $this->success(array_map(fn(SeoCatalog $c) => $c->toFullArray(), $tree));
    }

    private function show(int $id): void {
        $row = $this->db->fetchOne("SELECT * FROM " . SeoCatalog::SEO_CATALOG_TABLE . " WHERE id = :id", [':id' => $id]);
        if ($row === null) $this->notFound('Каталог');

        $catalog = new SeoCatalog($row);

        $children = $this->db->fetchAll("SELECT * FROM " . SeoCatalog::SEO_CATALOG_TABLE . " WHERE parent_id = :pid ORDER BY sort_order, name", [':pid' => $id]);
        $catalog->setChildren(array_map(fn(array $r) => new SeoCatalog($r), $children));

        $articlesCount = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM seo_articles WHERE catalog_id = :cid", [':cid' => $id]);

        $data = $catalog->toFullArray();
        $data['articles_count'] = $articlesCount;

        $this->success($data);
    }

    private function path(int $id): void {
        $row = $this->db->fetchOne(
            "WITH RECURSIVE catalog_path AS (
                SELECT id, parent_id, name, slug, CAST(slug AS CHAR(2000)) AS full_path
                FROM " . SeoCatalog::SEO_CATALOG_TABLE . " WHERE id = :id
                UNION ALL
                SELECT c.id, c.parent_id, c.name, c.slug,
                       CONCAT(c.slug, '/', cp.full_path)
                FROM " . SeoCatalog::SEO_CATALOG_TABLE . " c
                JOIN catalog_path cp ON c.id = cp.parent_id
            )
            SELECT full_path FROM catalog_path WHERE parent_id IS NULL",
            [':id' => $id]
        );

        if ($row === null) $this->notFound('Каталог');

        $this->success(['path' => $row['full_path']]);
    }

    private function create(): void {
        $data = $this->getJsonBody();
        $this->abortIfErrors($this->validateRequired($data, ['name', 'slug']));

        $catalog = new SeoCatalog($data);
        $newId = $this->db->insert(SeoCatalog::SEO_CATALOG_TABLE, $catalog->toArray());

        $this->db->insert(SeoAuditLog::SEO_AUDIT_LOG_TABLE, SeoAuditLog::catalogAction(
            $newId, SeoAuditLog::ACTION_CREATE, $data['actor'] ?? 'admin')
            ->toArray());

        $catalog->setId($newId);
        $this->success($catalog->toFullArray(), 201);
    }


    private function update(int $id): void {
        $existing = $this->db->fetchOne("SELECT * FROM " . SeoCatalog::SEO_CATALOG_TABLE . " WHERE id = :id", [':id' => $id]);
        if ($existing === null) $this->notFound('Каталог');


        $data = $this->getJsonBody();
        $catalog = new SeoCatalog($existing);
        $catalog->fromArray($data);

        $this->db->update(SeoCatalog::SEO_CATALOG_TABLE, $catalog->toArray(), 'id = :id', [':id' => $id]);

        $this->db->insert(SeoAuditLog::SEO_AUDIT_LOG_TABLE, SeoAuditLog::catalogAction(
            $id, SeoAuditLog::ACTION_UPDATE, $data['actor'] ?? 'admin',
            ['changed_fields' => array_keys($data)])
            ->toArray());

        $this->success($catalog->toFullArray());
    }


    private function move(int $id): void {
        $existing = $this->db->fetchOne("SELECT * FROM " . SeoCatalog::SEO_CATALOG_TABLE . " WHERE id = :id", [':id' => $id]);
        if ($existing === null) $this->notFound('Каталог');

        $data = $this->getJsonBody();
        $newParentId = isset($data['parent_id']) ? (int)$data['parent_id'] : null;
        $newSortOrder = isset($data['sort_order']) ? (int)$data['sort_order'] : 0;

        if ($newParentId === $id) $this->error('Нельзя переместить каталог в самого себя', 422);

        if ($newParentId !== null) {
            $isDescendant = $this->db->fetchOne(
                "WITH RECURSIVE descendants AS (
                    SELECT id FROM " . SeoCatalog::SEO_CATALOG_TABLE . " WHERE parent_id = :cid
                    UNION ALL
                    SELECT c.id FROM " . SeoCatalog::SEO_CATALOG_TABLE . " c
                    JOIN descendants d ON c.parent_id = d.id
                )
                SELECT 1 FROM descendants WHERE id = :new_parent LIMIT 1",
                [':cid' => $id, ':new_parent' => $newParentId]
            );

            if ($isDescendant !== null) {
                $this->error('Нельзя переместить каталог в его потомка (цикл)', 422);
            }
        }

        $this->db->update(SeoCatalog::SEO_CATALOG_TABLE, [
            'parent_id' => $newParentId,
            'sort_order' => $newSortOrder,
        ], 'id = :cid', [':cid' => $id]);

        $this->db->insert(SeoAuditLog::SEO_AUDIT_LOG_TABLE, SeoAuditLog::catalogAction(
            $id, SeoAuditLog::ACTION_UPDATE, $data['actor'] ?? 'admin',
            ['action' => 'move', 'old_parent' => $existing['parent_id'], 'new_parent' => $newParentId])
            ->toArray());

        $this->success(['moved' => true]);
    }

    private function delete(int $id): void {
        $existing = $this->db->fetchOne("SELECT * FROM " . SeoCatalog::SEO_CATALOG_TABLE . " WHERE id = :id", [':id' => $id]);

        if ($existing === null) $this->notFound('Каталог');

        $childrenCount = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM " . SeoCatalog::SEO_CATALOG_TABLE . " WHERE parent_id = :pid", [':pid' => $id]);
        $articlesCount = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM seo_articles WHERE catalog_id = :cid", [':cid' => $id]);

        $force = ($this->getParam('force') === '1');

        if (($childrenCount > 0 || $articlesCount > 0) && !$force) {
            $this->error("Каталог содержит {$childrenCount} подкаталогов и {$articlesCount} статей. " .
                "Используйте ?force=1 для каскадного удаления, или переместите содержимое.", 409);
        }

        $this->db->delete(SeoCatalog::SEO_CATALOG_TABLE, 'id = :id', [':id' => $id]);

        $this->db->insert(SeoAuditLog::SEO_AUDIT_LOG_TABLE, SeoAuditLog::catalogAction(
            $id, SeoAuditLog::ACTION_DELETE, $_GET['actor'] ?? 'admin')
            ->toArray());

        $this->success(['deleted' => true]);
    }
}
