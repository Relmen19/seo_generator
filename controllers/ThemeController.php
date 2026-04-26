<?php

declare(strict_types=1);

namespace Seo\Controller;

class ThemeController extends AbstractController {

    public function dispatch(string $method, ?string $action, ?int $id): void {
        switch ($method) {
            case 'GET':
                $action !== null && $id === null
                    ? $this->showByCode($action)
                    : $this->index();
                break;
            case 'POST':
                $this->save();
                break;
            case 'PUT':
            case 'PATCH':
                $action !== null ? $this->saveByCode($action) : $this->error('code обязателен');
                break;
            case 'DELETE':
                $action !== null ? $this->deleteByCode($action) : $this->error('code обязателен');
                break;
            default:
                $this->methodNotAllowed();
        }
    }

    private function index(): void {
        $rows = $this->db->fetchAll('SELECT code, name, tokens, is_active, created_at FROM seo_themes ORDER BY code');
        foreach ($rows as &$r) {
            $r['tokens'] = json_decode($r['tokens'] ?? '{}', true) ?: [];
            $r['is_active'] = (int)$r['is_active'];
        }
        $this->success($rows);
    }

    private function showByCode(string $code): void {
        $row = $this->db->fetchOne('SELECT code, name, tokens, is_active, created_at FROM seo_themes WHERE code = ?', [$code]);
        if (!$row) { $this->notFound('Тема'); }
        $row['tokens'] = json_decode($row['tokens'] ?? '{}', true) ?: [];
        $row['is_active'] = (int)$row['is_active'];
        $this->success($row);
    }

    private function save(): void {
        $data = $this->getJsonBody();
        $this->abortIfErrors($this->validateRequired($data, ['code', 'name']));
        $code = preg_replace('/[^a-z0-9_-]/i', '', (string)$data['code']);
        if ($code === '') { $this->error('code некорректен'); }
        $this->upsert($code, $data);
    }

    private function saveByCode(string $code): void {
        $data = $this->getJsonBody();
        $code = preg_replace('/[^a-z0-9_-]/i', '', $code);
        if ($code === '') { $this->error('code некорректен'); }
        $this->upsert($code, $data);
    }

    private function upsert(string $code, array $data): void {
        $tokens = isset($data['tokens']) && is_array($data['tokens']) ? $data['tokens'] : [];
        $name = isset($data['name']) ? (string)$data['name'] : $code;
        $isActive = isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1;
        $tokensJson = json_encode($tokens, JSON_UNESCAPED_UNICODE);

        $exists = $this->db->fetchOne('SELECT code FROM seo_themes WHERE code = ?', [$code]);
        if ($exists) {
            $this->db->execute(
                'UPDATE seo_themes SET name = ?, tokens = ?, is_active = ? WHERE code = ?',
                [$name, $tokensJson, $isActive, $code]
            );
        } else {
            $this->db->execute(
                'INSERT INTO seo_themes (code, name, tokens, is_active) VALUES (?, ?, ?, ?)',
                [$code, $name, $tokensJson, $isActive]
            );
        }
        $this->showByCode($code);
    }

    private function deleteByCode(string $code): void {
        $code = preg_replace('/[^a-z0-9_-]/i', '', $code);
        if (in_array($code, ['default', 'editorial', 'brutalist'], true)) {
            $this->error('Системную тему нельзя удалить', 422);
        }
        $this->db->execute('DELETE FROM seo_themes WHERE code = ?', [$code]);
        $this->success(['code' => $code, 'deleted' => true]);
    }
}
