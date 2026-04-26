<?php

declare(strict_types=1);

/**
 * Cron: run editorial QA checks for articles in workflow stages
 * blocks_done / ai_review / human_review / review.
 * Run every 5 minutes: */5 * * * * php /path/to/cron/qa_worker.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'CLI only';
    exit(1);
}

require_once __DIR__ . '/../config.php';

use Seo\Database;
use Seo\Service\EditorialQaService;

$lockFile = sys_get_temp_dir() . '/seo_qa_worker.lock';
$lockFp = fopen($lockFile, 'c');
if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    fclose($lockFp);
    exit(0);
}

$batchLimit = 20;
$staleMinutes = 30;

try {
    $db = Database::getInstance();
    $svc = new EditorialQaService($db);

    $rows = $db->fetchAll(
        "SELECT a.id
         FROM seo_articles a
         LEFT JOIN (
            SELECT article_id, MAX(created_at) AS last_check
            FROM seo_article_issues
            GROUP BY article_id
         ) i ON i.article_id = a.id
         WHERE a.status IN ('blocks_done','ai_review','human_review','review')
           AND a.is_active = 1
           AND (i.last_check IS NULL OR i.last_check < (NOW() - INTERVAL ? MINUTE))
         ORDER BY i.last_check IS NULL DESC, i.last_check ASC
         LIMIT ?",
        [$staleMinutes, $batchLimit]
    );

    $processed = 0;
    foreach ($rows as $row) {
        $aid = (int)$row['id'];
        try {
            $svc->runChecks($aid);
            $processed++;
        } catch (Throwable $e) {
            logMessage("QA worker: article {$aid} failed — " . $e->getMessage());
        }
    }

    if ($processed > 0) {
        logMessage("QA worker: проверено {$processed} статей", 'INFO');
    }
} catch (Throwable $e) {
    logMessage('QA worker error: ' . $e->getMessage());
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(1);
}

flock($lockFp, LOCK_UN);
fclose($lockFp);
exit(0);
