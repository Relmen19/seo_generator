<?php

declare(strict_types=1);

namespace Seo\Controller;

use Seo\Entity\SeoBlockType;

/*
   GET    /block-types              — list all block types
   GET    /block-types/{code}       — single block type by code
   POST   /block-types              — create new block type
   PUT    /block-types/{code}       — update block type
   DELETE /block-types/{code}       — delete block type
 */
class BlockTypeController extends AbstractController {

    public function dispatch(string $method, ?string $action, ?int $id): void {
        switch ($method) {
            case 'GET':
                $action !== null ? $this->show($action) : $this->index();
                break;
            case 'POST':
                $this->create();
                break;
            case 'PUT':
            case 'PATCH':
                $action !== null ? $this->update($action) : $this->error('Код блока обязателен');
                break;
            case 'DELETE':
                $action !== null ? $this->delete($action) : $this->error('Код блока обязателен');
                break;
            default:
                $this->methodNotAllowed();
        }
    }

    private function index(): void {
        $category = $this->getParam('category');
        $activeOnly = (bool)(int)$this->getParam('active_only', 0);

        $where = '1=1';
        $params = [];

        if ($activeOnly) {
            $where .= ' AND is_active = 1';
        }
        if ($category !== null) {
            $where .= ' AND category = :category';
            $params[':category'] = $category;
        }

        $rows = $this->db->fetchAll(
            "SELECT * FROM " . SeoBlockType::TABLE . " WHERE {$where} ORDER BY sort_order, code",
            $params
        );

        $items = array_map(
            static fn(array $row) => (new SeoBlockType($row))->toFullArray(),
            $rows
        );

        $this->success($items);
    }

    private function show(string $code): void {
        $row = $this->db->fetchOne(
            "SELECT * FROM " . SeoBlockType::TABLE . " WHERE code = :code",
            [':code' => $code]
        );
        if ($row === null) $this->notFound('Тип блока');

        $this->success((new SeoBlockType($row))->toFullArray());
    }

    private function create(): void {
        $data = $this->getJsonBody();
        $this->abortIfErrors($this->validateRequired($data, ['code', 'display_name']));

        $code = trim((string)($data['code'] ?? ''));
        if (!preg_match('/^[a-z0-9_]{1,50}$/', $code)) {
            $this->error("Код должен содержать только строчные буквы, цифры и '_', длина 1-50", 422);
        }

        if ($this->codeExists($code)) {
            $this->error("Тип блока '{$code}' уже существует", 409);
        }

        if (isset($data['category']) && !in_array($data['category'], SeoBlockType::validCategories(), true)) {
            $this->error("Категория должна быть одной из: " . implode(', ', SeoBlockType::validCategories()), 422);
        }

        $bt = new SeoBlockType($data);
        $bt->setCode($code);

        $insertData = array_merge(['code' => $code], $bt->toArray());
        $this->db->insert(SeoBlockType::TABLE, $insertData);

        $this->success($bt->toFullArray(), 201);
    }

    private function update(string $code): void {
        $existing = $this->db->fetchOne(
            "SELECT * FROM " . SeoBlockType::TABLE . " WHERE code = :code",
            [':code' => $code]
        );
        if ($existing === null) $this->notFound('Тип блока');

        $data = $this->getJsonBody();

        if (isset($data['category']) && !in_array($data['category'], SeoBlockType::validCategories(), true)) {
            $this->error("Категория должна быть одной из: " . implode(', ', SeoBlockType::validCategories()), 422);
        }

        $allowed = ['display_name', 'description', 'category', 'icon', 'json_schema', 'gpt_hint', 'is_active', 'sort_order'];
        $fields = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            $this->error('Нет допустимых полей для обновления', 422);
        }

        if (isset($fields['is_active'])) {
            $fields['is_active'] = (int)(bool)$fields['is_active'];
        }
        if (isset($fields['sort_order'])) {
            $fields['sort_order'] = (int)$fields['sort_order'];
        }
        if (isset($fields['json_schema']) && is_array($fields['json_schema'])) {
            $fields['json_schema'] = json_encode($fields['json_schema'], JSON_UNESCAPED_UNICODE);
        }

        $this->db->update(SeoBlockType::TABLE, 'code = :code', $fields, [':code' => $code]);

        $updated = $this->db->fetchOne("SELECT * FROM " . SeoBlockType::TABLE . " WHERE code = :code", [':code' => $code]);
        $this->success((new SeoBlockType($updated))->toFullArray());
    }

    private function delete(string $code): void {
        $existing = $this->db->fetchOne(
            "SELECT * FROM " . SeoBlockType::TABLE . " WHERE code = :code",
            [':code' => $code]
        );
        if ($existing === null) $this->notFound('Тип блока');

        $this->db->delete(SeoBlockType::TABLE, 'code = :code', [':code' => $code]);
        $this->success(['deleted' => true, 'code' => $code]);
    }

    private function codeExists(string $code): bool {
        return $this->db->fetchOne(
            "SELECT code FROM " . SeoBlockType::TABLE . " WHERE code = :code",
            [':code' => $code]
        ) !== null;
    }
}
