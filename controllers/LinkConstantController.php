<?php

declare(strict_types=1);

namespace Seo\Controller;

use Seo\Entity\SeoLinkConstant;

/*
   GET    /links                  — список (глобальные + по статье)
   GET    /links/{id}             — одна ссылка
   GET    /links/resolve          — разрешить все ссылки для статьи (article_id)
   POST   /links                  — создать
   PUT    /links/{id}             — обновить
   PUT    /links/bulk-replace     — массовая замена URL по ключу
   DELETE /links/{id}             — удалить
 */
class LinkConstantController extends AbstractController {

    public function dispatch(string $method, ?string $action, ?int $id): void {
        if ($method === 'GET' && $action === 'resolve') {
            $this->resolve();
            return;
        }
        if ($method === 'PUT' && $action === 'bulk-replace') {
            $this->bulkReplace();
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
        $articleId = $this->getParam('article_id');
        $globalOnly = $this->getParam('global');

        $where  = '1=1';
        $params = [];

        if ($globalOnly === '1') {
            $where .= ' AND article_id IS NULL';
        } elseif ($articleId !== null) {
            $where .= ' AND (article_id IS NULL OR article_id = :article_id)';
            $params[':article_id'] = (int)$articleId;
        }

        $rows = $this->db->fetchAll("SELECT * FROM " . SeoLinkConstant::SEO_LINKS_TABLE . " WHERE {$where} ORDER BY `key`", $params);

        $items = array_map(fn(array $r) => (new SeoLinkConstant($r))->toFullArray(), $rows);

        $this->success($items);
    }

    private function show(int $id): void {
        $row = $this->db->fetchOne("SELECT * FROM " . SeoLinkConstant::SEO_LINKS_TABLE . " WHERE id = :id", [':id' => $id]);
        if ($row === null) {
            $this->notFound('Ссылка');
        }
        $this->success((new SeoLinkConstant($row))->toFullArray());
    }

    private function resolve(): void {
        $articleId = $this->getParam('article_id');
        if ($articleId === null) $this->error('article_id обязателен');

        $rows = $this->db->fetchAll(
            "SELECT * FROM " . SeoLinkConstant::SEO_LINKS_TABLE . "
             WHERE article_id IS NULL OR article_id = :aid
             ORDER BY article_id ASC",
            [':aid' => (int)$articleId]
        );

        $map = [];
        foreach ($rows as $row) {
            $link = new SeoLinkConstant($row);
            $map[$link->getKey()] = [
                'url'       => $link->getUrl(),
                'label'     => $link->getLabel(),
                'target'    => $link->getTarget(),
                'nofollow'  => $link->isNofollow(),
                'is_global' => $link->isGlobal(),
            ];
        }

        $this->success($map);
    }

    private function create(): void {
        $data = $this->getJsonBody();
        $this->abortIfErrors($this->validateRequired($data, ['key', 'url']));


        $articleId = isset($data['article_id']) ? (int)$data['article_id'] : null;
        $existingKey = $this->db->fetchOne(
            "SELECT id FROM " . SeoLinkConstant::SEO_LINKS_TABLE . " WHERE `key` = :k AND article_id <=> :aid",
            [':k' => $data['key'], ':aid' => $articleId]);

        if ($existingKey !== null) {
            $scope = $articleId ? "для статьи #{$articleId}" : 'глобально';
            $this->error("Ключ '{$data['key']}' уже существует {$scope}", 409);
        }

        $link = new SeoLinkConstant($data);
        $newId = $this->db->insert(SeoLinkConstant::SEO_LINKS_TABLE, $link->toArray());
        $link->setId($newId);

        $this->success($link->toFullArray(), 201);
    }

    private function update(int $id): void {
        $existing = $this->db->fetchOne("SELECT * FROM " . SeoLinkConstant::SEO_LINKS_TABLE . " WHERE id = :id", [':id' => $id]);
        if ($existing === null) $this->notFound('Ссылка');

        $data = $this->getJsonBody();
        $link = new SeoLinkConstant($existing);
        $link->fromArray($data);

        $this->db->update(SeoLinkConstant::SEO_LINKS_TABLE, 'id = :id', $link->toArray(), [':id' => $id]);
        $this->success($link->toFullArray());
    }

    private function bulkReplace(): void {
        $data = $this->getJsonBody();
        $this->abortIfErrors($this->validateRequired($data, ['key', 'new_url']));

        $affected = $this->db->execute(
            "UPDATE " . SeoLinkConstant::SEO_LINKS_TABLE . " SET url = :url WHERE `key` = :k",
            [':url' => $data['new_url'], ':k' => $data['key']]
        );

        $this->success([
            'key'      => $data['key'],
            'new_url'  => $data['new_url'],
            'affected' => $affected,
        ]);
    }

    private function delete(int $id): void {
        $deleted = $this->db->delete(SeoLinkConstant::SEO_LINKS_TABLE, 'id = :id', [':id' => $id]);
        if ($deleted === 0) $this->notFound('Ссылка');
        $this->success(['deleted' => true]);
    }
}
