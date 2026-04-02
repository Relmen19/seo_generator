<?php

declare(strict_types=1);

/**
 * SEO Generator — Трекер посещений.
 *
 * Лёгкий эндпоинт для записи хитов. Два режима:
 *
 * 1. Пиксель (1×1 GIF):
 *    <img src="/seo/api/track.php?aid=123" width="1" height="1" />
 *
 * 2. JS-бикон:
 *    fetch('/seo/api/track.php', {
 *      method: 'POST',
 *      headers: {'Content-Type': 'application/json'},
 *      body: JSON.stringify({article_id: 123})
 *    });
 *
 * Или через navigator.sendBeacon:
 *    navigator.sendBeacon('/seo/api/track.php?aid=123');
 */

require_once __DIR__ . '/../config.php';

use Seo\Database;
use Seo\Entity\SeoPageStat;

// ─── Определяем article_id ──────────────────────────────────
$articleId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '', true);
    $articleId = (int)($json['article_id'] ?? $_GET['aid'] ?? $_GET['article_id'] ?? 0);
} else {
    $articleId = (int)($_GET['aid'] ?? $_GET['article_id'] ?? 0);
}

if ($articleId <= 0) {
    // Молча отдаём пиксель / 204 — не ломаем страницу пользователя
    sendPixelAndExit();
}

// ─── Записываем хит ─────────────────────────────────────────
try {
    $stat = SeoPageStat::fromRequest($articleId);
    $db = Database::getInstance();

    // Проверяем что статья существует (лёгкий запрос)
    $exists = $db->fetchColumn(
        "SELECT 1 FROM seo_articles WHERE id = :id AND is_active = 1 LIMIT 1",
        [':id' => $articleId]
    );

    if ($exists) {
        $db->insert('seo_page_stats', $stat->toArray());
    }
} catch (\Throwable $e) {
    // Трекер никогда не должен падать — молча логируем
    if (SEO_DEBUG) {
        error_log('SEO track error: ' . $e->getMessage());
    }
}

// ─── Ответ ──────────────────────────────────────────────────
sendPixelAndExit();

// =================================================================

/**
 * Отправляет 1×1 прозрачный GIF (для режима пикселя)
 * или 204 No Content (для JS/beacon).
 */
function sendPixelAndExit(): void
{
    // Запрет кеширования
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(204);
        exit;
    }

    // 1×1 прозрачный GIF (43 байта)
    header('Content-Type: image/gif');
    header('Content-Length: 43');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}
