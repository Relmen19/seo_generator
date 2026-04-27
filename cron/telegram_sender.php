<?php

declare(strict_types=1);

/**
 * Cron script: send scheduled Telegram posts.
 * Run every minute: * * * * * php /path/to/cron/telegram_sender.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'CLI only';
    exit(1);
}

require_once __DIR__ . '/../config.php';

use Seo\Database;
use Seo\Service\TelegramPostService;

// Global kill-switch: cron is registered in docker/crontab and ticks every
// minute, but sending only happens when the user has explicitly enabled it
// via seo_settings (UI toggle / SQL update).
try {
    $row = Database::getInstance()->fetchOne(
        "SELECT `value` FROM seo_settings WHERE `key` = 'scheduled_publish_enabled'"
    );
    if (!$row || (string)$row['value'] !== '1') {
        exit(0);
    }
} catch (Throwable $e) {
    // Settings table missing => behave as disabled
    exit(0);
}

// File lock to prevent overlapping runs
$lockFile = sys_get_temp_dir() . '/seo_tg_sender.lock';
$lockFp = fopen($lockFile, 'c');

if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    // Another instance is running
    fclose($lockFp);
    exit(0);
}

try {
    $service = new TelegramPostService();
    $count = $service->processScheduledPosts();

    if ($count > 0) {
        logMessage("Telegram cron: отправлено {$count} постов", 'INFO');
    }
} catch (Throwable $e) {
    logMessage('Telegram cron error: ' . $e->getMessage());
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(1);
}

flock($lockFp, LOCK_UN);
fclose($lockFp);
exit(0);
