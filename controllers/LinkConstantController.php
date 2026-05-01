<?php

declare(strict_types=1);

namespace Seo\Controller;

use Seo\Entity\SeoLinkConstant;

/*
   GET    /links?profile_id={pid}    — список ссылок профиля
   GET    /links/{id}                — одна ссылка
   GET    /links/resolve?article_id  — карта ссылок (по профилю статьи) или profile_id напрямую
   POST   /links                     — создать (profile_id обязателен)
   PUT    /links/{id}                — обновить
   PUT    /links/bulk-replace        — массовая замена URL по ключу
   DELETE /links/{id}                — удалить
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
        $profileId = $this->getParam('profile_id');
        if ($profileId === null || $profileId === '') {
            $this->error('profile_id обязателен');
        }

        $rows = $this->db->fetchAll(
            "SELECT * FROM " . SeoLinkConstant::SEO_LINKS_TABLE . "
             WHERE profile_id = :pid
             ORDER BY `key`",
            [':pid' => (int)$profileId]
        );

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
        $profileId = $this->getParam('profile_id');
        $articleId = $this->getParam('article_id');

        if (($profileId === null || $profileId === '') && $articleId !== null) {
            $row = $this->db->fetchOne("SELECT profile_id FROM seo_articles WHERE id = :id", [':id' => (int)$articleId]);
            $profileId = $row['profile_id'] ?? null;
        }

        if ($profileId === null || $profileId === '') {
            $this->error('profile_id или article_id обязателен');
        }

        $rows = $this->db->fetchAll(
            "SELECT * FROM " . SeoLinkConstant::SEO_LINKS_TABLE . " WHERE profile_id = :pid",
            [':pid' => (int)$profileId]
        );

        $map = [];
        foreach ($rows as $row) {
            $link = new SeoLinkConstant($row);
            $map[$link->getKey()] = [
                'url'      => $link->getUrl(),
                'label'    => $link->getLabel(),
                'target'   => $link->getTarget(),
                'nofollow' => $link->isNofollow(),
            ];
        }

        $this->success($map);
    }

    private function create(): void {
        $data = $this->getJsonBody();
        $this->abortIfErrors($this->validateRequired($data, ['key', 'url', 'profile_id']));

        $profileId = (int)$data['profile_id'];
        $existingKey = $this->db->fetchOne(
            "SELECT id FROM " . SeoLinkConstant::SEO_LINKS_TABLE . " WHERE `key` = :k AND profile_id = :pid",
            [':k' => $data['key'], ':pid' => $profileId]
        );

        if ($existingKey !== null) {
            $this->error("Ключ '{$data['key']}' уже существует в этом профиле", 409);
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
