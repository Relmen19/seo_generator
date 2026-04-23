<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';

use Seo\Database;

$db = Database::getInstance();

if (isset($_GET['link'])) {
    $linkKey   = trim((string)$_GET['link']);
    $articleId = (int)($_GET['aid'] ?? 0);

    $row = null;
    if ($linkKey) {
        try {
            $rows = $db->fetchAll(
                "SELECT url FROM seo_link_constants WHERE `key` = :k AND is_active != 0 LIMIT 1",
                [':k' => $linkKey]
            );
            $row = $rows[0] ?? null;
        } catch (Exception $e) { $row = null; }
    }

    $targetUrl = $row['url'] ?? '/';

    if ($articleId > 0) {
        try {
            $db->fetchAll(
                "INSERT INTO seo_page_stats (article_id, ip, user_agent, referer, device_type)
                 VALUES (:aid, :ip, :ua, :ref, 'link_click')",
                [
                    ':aid' => $articleId,
                    ':ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
                    ':ua'  => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                    ':ref' => $targetUrl,
                ]
            );
        } catch (Exception $e) {}
    }

    header('Location: ' . $targetUrl, true, 302);
    header('Content-Type: text/html; charset=utf-8');
    exit;
}

if (isset($_GET['related'])) {
    header('Content-Type: application/json; charset=utf-8');
    $catalogId = (int)($_GET['catalog_id'] ?? 0);
    $exclude   = (int)($_GET['exclude'] ?? 0);
    $profileId = (int)($_GET['profile_id'] ?? 0);
    $limit     = min(8, max(1, (int)($_GET['limit'] ?? 4)));

    $related = [];
    $pidSql  = $profileId ? 'AND a.profile_id = :pid' : '';

    if ($catalogId) {
        try {
            $params = [':cid' => $catalogId, ':lim' => $limit];
            $exclSql = $exclude ? 'AND a.id != :excl' : '';
            if ($exclude) $params[':excl'] = $exclude;
            if ($profileId) $params[':pid'] = $profileId;

            $related = $db->fetchAll(
                "SELECT a.id, a.title, a.meta_description AS description, a.published_url AS url
                 FROM seo_articles a
                 WHERE a.status = 'published' AND a.is_active = 1 AND a.published_url IS NOT NULL
                   AND a.catalog_id = :cid $pidSql $exclSql
                 ORDER BY a.published_at DESC LIMIT :lim",
                $params
            );
        } catch (Exception $e) { $related = []; }
    }

    if (count($related) < $limit) {
        $need    = $limit - count($related);
        $exclude_ids = array_merge(
            $exclude ? [$exclude] : [],
            array_column($related, 'id')
        );
        try {
            $notIn = implode(',', array_map('intval', $exclude_ids)) ?: '0';
            $params2 = $profileId ? [':pid' => $profileId] : [];
            $extra = $db->fetchAll(
                "SELECT a.id, a.title, a.meta_description AS description, a.published_url AS url
                 FROM seo_articles a
                 WHERE a.status = 'published' AND a.is_active = 1 AND a.published_url IS NOT NULL
                   $pidSql AND a.id NOT IN ($notIn)
                 ORDER BY a.published_at DESC LIMIT " . (int)$need,
                $params2
            );
            $related = array_merge($related, $extra);
        } catch (Exception $e) {}
    }

    echo json_encode([
        'results' => array_values($related),
        'count'   => count($related),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}


$q = trim((string)($_GET['q'] ?? ''));
$limit     = min(20, max(1, (int)($_GET['limit'] ?? 8)));
$exclude   = (int)($_GET['exclude'] ?? 0);
$profileId = (int)($_GET['profile_id'] ?? 0);

if (mb_strlen($q) < 2) {
    echo json_encode(['results' => [], 'query' => $q, 'count' => 0]);
    exit;
}


$results = [];
$words   = array_values(array_filter(explode(' ', preg_replace('/\s+/', ' ', $q))));
$pidSql  = $profileId ? 'AND a.profile_id = :pid' : '';

try {
    $ftQuery = implode(' ', array_map(fn($w) => '+' . $w . '*', $words));
    $exclSql = $exclude ? 'AND a.id != :excl' : '';

    $params = [':ft' => $ftQuery, ':ft2' => $ftQuery, ':lim' => $limit];
    if ($exclude) $params[':excl'] = $exclude;
    if ($profileId) $params[':pid'] = $profileId;

    $results = $db->fetchAll(
        "SELECT a.id, a.title, a.meta_description, a.published_url,
                MATCH(a.title, a.keywords, a.meta_description) AGAINST (:ft IN BOOLEAN MODE) AS relevance
         FROM seo_articles a
         WHERE a.status = 'published' AND a.is_active = 1 AND a.published_url IS NOT NULL
           $pidSql $exclSql
           AND MATCH(a.title, a.keywords, a.meta_description) AGAINST (:ft2 IN BOOLEAN MODE)
         ORDER BY relevance DESC LIMIT :lim",
        $params);
} catch (PDOException $e) {
    $results = [];
}

if (empty($results)) {
    $params = [];
    $likes = [];
    foreach (['title', 'keywords', 'meta_description'] as $col) {
        $k = ':col_' . $col;
        $likes[] = "a.$col LIKE $k";
        $params[$k] = '%' . $q . '%';
    }
    foreach ($words as $i => $word) {
        if (mb_strlen($word) < 2) continue;
        $k = ':w' . $i;
        $likes[] = "a.title LIKE $k";
        $params[$k] = '%' . $word . '%';
    }
    $params[':lim'] = $limit;
    if ($profileId) $params[':pid'] = $profileId;
    $exclSql = $exclude ? 'AND a.id != ' . (int)$exclude : '';
    $orSql = implode(' OR ', $likes);

    $results = $db->fetchAll(
        "SELECT a.id, a.title, a.meta_description, a.published_url, 0 AS relevance FROM seo_articles a
         WHERE a.status = 'published' AND a.is_active = 1 AND a.published_url IS NOT NULL
         $pidSql $exclSql AND ($orSql) ORDER BY a.title ASC LIMIT :lim",
        $params);
}

$out = array_map(fn($r) => [
    'id' => (int)$r['id'],
    'title' => $r['title'],
    'description' => $r['meta_description'] ?? '',
    'url' => $r['published_url'],
], $results);

echo json_encode(['results' => $out, 'query' => $q, 'count' => count($out)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
