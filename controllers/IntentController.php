<?php

declare(strict_types=1);

namespace Seo\Controller;

use Seo\Entity\SeoIntentType;

/*
 *   GET    intents              → список всех типов
 *   GET    intents/{code}       → один тип по коду    ($action = code)
 *   POST   intents              → создать новый тип
 *   PUT    intents/{code}       → полное обновление
 *   PATCH  intents/{code}       → частичное обновление
 *   DELETE intents/{code}       → удалить тип
 */
class IntentController extends AbstractController {

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
                $action !== null ? $this->patch($action) : $this->error('Код интента обязателен');
                break;
            case 'DELETE':
                $action !== null ? $this->delete($action) : $this->error('Код интента обязателен');
                break;
            default:
                $this->methodNotAllowed();
        }
    }

    private function index(): void {
        $activeOnly = (bool)(int)$this->getParam('active_only', 0);
        $conditions = [];
        $params = [];

        if ($activeOnly) {
            $conditions[] = 'is_active = 1';
        }

        $profileId = $this->getParam('profile_id');
        if ($profileId !== null && $profileId !== '') {
            $conditions[] = '(profile_id = :profile_id OR profile_id IS NULL)';
            $params[':profile_id'] = (int)$profileId;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $rows = $this->db->fetchAll(
            "SELECT * FROM " . SeoIntentType::TABLE . " $where ORDER BY sort_order ASC, code ASC",
            $params
        );

        $items = array_map(
            static function (array $row) {
                return (new SeoIntentType($row))->toFullArray();
            },
            $rows
        );

        $this->success($items);
    }

    private function show(string $code): void {
        $this->success($this->findByCodeOrFail($code)->toFullArray());
    }

    private function create(): void {
        $data = $this->getJsonBody();
        $this->abortIfErrors($this->validateRequired($data, ['code', 'label_ru', 'label_en', 'description', 'gpt_hint']));

        $code = trim((string)($data['code'] ?? ''));
        if (!SeoIntentType::isValidCode($code)) {
            $this->error("Поле 'code' должно содержать только строчные латинские буквы, цифры и '_', длина 1–30", 422);
        }
        if ($this->codeExists($code)) {
            $this->error("Интент с кодом '{$code}' уже существует", 409);
        }

        $intent = new SeoIntentType();
        $intent->setCode($code);
        $this->applyFields($intent, $data);

        $this->db->insert(
            SeoIntentType::TABLE,
            array_merge(['code' => $code], $intent->toArray())
        );

        $this->success($this->findByCodeOrFail($code)->toFullArray(), 201);
    }

    private function patch(string $code): void {
        $this->findByCodeOrFail($code);

        $data   = $this->getJsonBody();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'PATCH';

        if ($method === 'PUT') {
            $this->abortIfErrors($this->validateRequired($data, ['label_ru', 'label_en', 'description', 'gpt_hint']));
        }

        if (empty($data)) {
            $this->error('Тело запроса пустое', 422);
        }

        $allowed = ['label_ru', 'label_en', 'color', 'sort_order', 'description', 'gpt_hint', 'article_tone', 'article_open', 'is_active'];
        $fields  = [];
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
        if (isset($fields['color'])) {
            $color = trim((string)$fields['color']);
            if (!preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
                $this->error("Поле 'color' должно быть валидным HEX-цветом (#RGB или #RRGGBB)", 422);
            }
            $fields['color'] = $color;
        }

        $this->db->update(
            SeoIntentType::TABLE,
            $fields,
            'code = :code',
            [':code' => $code]
        );

        $this->success($this->findByCodeOrFail($code)->toFullArray());
    }

    private function delete(string $code): void {
        $this->findByCodeOrFail($code);

        $this->db->delete(
            SeoIntentType::TABLE,
            'code = :code',
            [':code' => $code]
        );

        $this->success(['deleted' => true, 'code' => $code]);
    }

    private function findByCodeOrFail(string $code): SeoIntentType {
        $row = $this->db->fetchOne(
            "SELECT * FROM `" . SeoIntentType::TABLE . "` WHERE code = :code",
            [':code' => $code]
        );
        if ($row === null) {
            $this->notFound("Тип интента '{$code}'");
        }
        return new SeoIntentType($row);
    }

    private function codeExists(string $code): bool {
        return $this->db->fetchOne(
                "SELECT code FROM `" . SeoIntentType::TABLE . "` WHERE code = :code",
                [':code' => $code]
            ) !== null;
    }

    private function applyFields(SeoIntentType $intent, array $data): void {
        if (isset($data['label_ru'])) {
            $intent->setLabelRu(trim((string)$data['label_ru']));
        }
        if (isset($data['label_en'])) {
            $intent->setLabelEn(trim((string)$data['label_en']));
        }
        if (isset($data['color'])) {
            $color = trim((string)$data['color']);
            if (!preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
                $this->error("Поле 'color' должно быть валидным HEX-цветом (#RGB или #RRGGBB)", 422);
            }
            $intent->setColor($color);
        }
        if (array_key_exists('sort_order', $data)) {
            $intent->setSortOrder((int)$data['sort_order']);
        }
        if (isset($data['description'])) {
            $intent->setDescription(trim((string)$data['description']));
        }
        if (isset($data['gpt_hint'])) {
            $intent->setGptHint(trim((string)$data['gpt_hint']));
        }
        if (array_key_exists('article_tone', $data)) {
            $intent->setArticleTone($data['article_tone'] !== null ? trim((string)$data['article_tone']) : null);
        }
        if (array_key_exists('article_open', $data)) {
            $intent->setArticleOpen($data['article_open'] !== null ? trim((string)$data['article_open']) : null);
        }
        if (array_key_exists('is_active', $data)) {
            $intent->setIsActive((bool)$data['is_active']);
        }
    }
}