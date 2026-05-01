<?php

declare(strict_types=1);

namespace Seo\Controller;

use Seo\Entity\SeoPublishTarget;

/*
   GET    /publish-targets            — список
   GET    /publish-targets/{id}       — один таргет
   POST   /publish-targets            — создать
   PUT    /publish-targets/{id}       — обновить
   DELETE /publish-targets/{id}       — удалить
 */
class PublishTargetController extends AbstractController{


    public function dispatch(string $method, ?string $action, ?int $id): void{
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
        $where = '1=1';
        $params = [];

        $profileId = $this->getParam('profile_id');
        if ($profileId === null || $profileId === '') {
            $this->error('profile_id обязателен');
        }
        $where .= ' AND profile_id = :profile_id';
        $params[':profile_id'] = (int)$profileId;

        $rows = $this->db->fetchAll("SELECT * FROM " . SeoPublishTarget::SEO_PUBLISH_TARGET_TABLE . " WHERE {$where} ORDER BY name", $params);
        $items = array_map(fn(array $r) => (new SeoPublishTarget($r))->toFullArray(), $rows);
        $this->success($items);
    }

    private function show(int $id): void {
        $row = $this->db->fetchOne("SELECT * FROM " . SeoPublishTarget::SEO_PUBLISH_TARGET_TABLE . " WHERE id = :id", [':id' => $id]);
        if ($row === null) $this->notFound('Таргет публикации');
        $this->success((new SeoPublishTarget($row))->toFullArray());
    }

    private function create(): void {
        $data = $this->getJsonBody();
        $this->abortIfErrors($this->validateRequired($data, ['name', 'base_url', 'profile_id', 'type']));

        if (!in_array($data['type'], SeoPublishTarget::TYPES, true)) {
            $this->error('Недопустимый тип площадки');
        }

        $target = new SeoPublishTarget($data);
        $newId = $this->db->insert(SeoPublishTarget::SEO_PUBLISH_TARGET_TABLE, $target->toArray());
        $target->setId($newId);

        $this->success($target->toFullArray(), 201);
    }

    private function update(int $id): void {
        $existing = $this->db->fetchOne("SELECT * FROM " . SeoPublishTarget::SEO_PUBLISH_TARGET_TABLE . " WHERE id = :id", [':id' => $id]);
        if ($existing === null) $this->notFound('Таргет публикации');

        $data = $this->getJsonBody();
        $target = new SeoPublishTarget($existing);
        $target->fromArray($data);

        $this->db->update(SeoPublishTarget::SEO_PUBLISH_TARGET_TABLE, 'id = :id', $target->toArray(), [':id' => $id]);
        $this->success($target->toFullArray());
    }

    private function delete(int $id): void {
        $deleted = $this->db->delete(SeoPublishTarget::SEO_PUBLISH_TARGET_TABLE, 'id = :id', [':id' => $id]);
        if ($deleted === 0) $this->notFound('Таргет публикации');
        $this->success(['deleted' => true]);
    }
}
