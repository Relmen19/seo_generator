<?php

declare(strict_types=1);

namespace Seo\Controller;

use Seo\Database;

abstract class AbstractController {
    protected Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    abstract public function dispatch(string $method, ?string $action, ?int $id): void;

    protected function getJsonBody(): array {
        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    protected function getParam(string $key, $default = null) {
        return $_GET[$key] ?? $default;
    }

    protected function getIntParam(string $key, int $default = 0): int {
        return (int)($this->getParam($key, $default));
    }

    protected function validateRequired(array $data, array $required): array {
        $errors = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                $errors[] = "Поле '{$field}' обязательно";
            }
        }
        return $errors;
    }

    protected function abortIfErrors(array $errors): void {
        if (!empty($errors)) {
            $this->error(implode('; ', $errors), 422);
        }
    }

    protected function success($data = null, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function paginated(array $items, int $total, int $page, int $perPage): void {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'data' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 0,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function error(string $message, int $code = 400): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => $message,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function notFound(string $entity = 'Запись'): void {
        $this->error("{$entity} не найден(а)", 404);
    }

    protected function methodNotAllowed(): void {
        $this->error('Метод не поддерживается', 405);
    }

    protected function getPage(): int {
        return max(1, $this->getIntParam('page', 1));
    }

    protected function getPerPage(int $default = 20, int $max = 100): int {
        $val = $this->getIntParam('per_page', $default);
        return min(max(1, $val), $max);
    }

    protected function getOffset(): int {
        return ($this->getPage() - 1) * $this->getPerPage();
    }
}
