<?php

declare(strict_types=1);

namespace Seo\Service;

use RuntimeException;
use Seo\Database;
use Seo\Entity\SeoArticle;
use Seo\Entity\SeoAuditLog;
use Seo\Entity\SeoPublishTarget;
use Throwable;

class PublishService {

    const BASE_PUBLISH_URL = '/admin/seo_generator/deploy/publish.php';
    private Database $db;
    private HtmlRendererService $renderer;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->renderer = new HtmlRendererService();
    }

    public function publish(int $articleId, int $targetId): array {
        $article = $this->loadArticle($articleId);
        $target = $this->loadTarget($targetId);

        if (!(int)($target['is_active'] ?? 0)) {
            throw new RuntimeException('Целевой хост неактивен');
        }

        $html = $this->renderer->render($articleId);

        $catalogPath = $this->buildCatalogPath($article);
        $slug = $article['slug'];
        $remotePath = ltrim(trim($catalogPath, '/') . '/' . $slug . '/index.html', '/');

        $publicUrl = rtrim($target['base_url'], '/') . '/uploads/articles/' . $remotePath;

        $config = is_string($target['config'])
            ? json_decode($target['config'], true) ?? []
            : ($target['config'] ?? []);

        $type = $target['type'] === 'hostia' ? 'selfhosted' : $target['type'];
        switch ($type) {
            case 'selfhosted':
                $this->deploySelfhosted($html, $remotePath, $config, $target);
                break;
            case 'ftp':
                $this->deployFtp($html, $remotePath, $config);
                break;
            default:
                throw new RuntimeException("Неизвестный тип хоста: {$target['type']}");
        }

        $now = date('Y-m-d H:i:s');
        $this->db->getPdo()->prepare(
            "UPDATE seo_articles
             SET status='published', published_at=?, published_path=?,
                 published_url=?, published_target_id=?
             WHERE id=?"
        )->execute([$now, $remotePath, $publicUrl, $targetId, $articleId]);

        $this->writeAudit($articleId, 'publish', [
            'target_id'   => $targetId,
            'target_name' => $target['name'],
            'path'        => $remotePath,
            'url'         => $publicUrl,
            'html_size'   => strlen($html),
        ]);

        return [
            'published_url'  => $publicUrl,
            'published_path' => $remotePath,
            'html_size'      => strlen($html),
            'published_at'   => $now,
            'target_name'    => $target['name'],
        ];
    }

    public function unpublish(int $articleId): array {
        $article       = $this->loadArticle($articleId);
        $publishedPath = $article['published_path'] ?? '';
        $targetId      = (int)($article['published_target_id'] ?? 0);

        if ($publishedPath && $targetId > 0) {
            $target = $this->db->fetchOne("SELECT * FROM seo_publish_targets WHERE id = ?", [$targetId]);
            if ($target) {
                $config = is_string($target['config'])
                    ? json_decode($target['config'], true) ?? []
                    : ($target['config'] ?? []);
                try {
                    $type = $target['type'] === 'hostia' ? 'selfhosted' : $target['type'];
                    switch ($type) {
                        case 'selfhosted': $this->deleteSelfhosted($publishedPath, $config, $target); break;
                        case 'ftp':        $this->deleteFtp($publishedPath, $config); break;
                    }
                } catch (Throwable $e) {
                    logMessage("Unpublish delete failed for target {$target['name']}: " . $e->getMessage());
                }
            }
        } elseif ($publishedPath && $targetId === 0) {
            // Fallback для старых записей без published_target_id
            $this->deleteFromTargets($article, $publishedPath);
        }

        $this->db->getPdo()->prepare(
            "UPDATE seo_articles
             SET status='unpublished', published_url=NULL,
                 published_path=NULL, published_target_id=NULL
             WHERE id=?"
        )->execute([$articleId]);

        $this->writeAudit($articleId, 'unpublish', [
            'target_id'     => $targetId ?: null,
            'previous_url'  => $article['published_url'] ?? null,
            'previous_path' => $publishedPath,
        ]);

        return ['status' => 'unpublished'];
    }

    public function preview(int $articleId): string {
        return $this->renderer->render($articleId, true);
    }

    private function deploySelfhosted(string $html, string $path, array $config, array $target): void {
        $baseUrl  = rtrim($target['base_url'], '/');
        $endpoint = !empty($config['publish_endpoint'])
            ? $config['publish_endpoint']
            : $baseUrl . self::BASE_PUBLISH_URL;

        $payload = json_encode([
            'path'    => $path,
            'content' => $html,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Publish-Secret: ' . PUBLISH_SECRET,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => SEO_PUBLISH_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => SEO_PUBLISH_CONNECT_TIMEOUT,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("Hostia deploy failed: {$err}");
        }
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new RuntimeException("Hostia deploy error: HTTP {$httpCode}, response: " . mb_substr($response, 0, 300));
        }
    }

    private function deployFtp(string $html, string $path, array $config): void {
        $host = $config['host'] ?? '';
        $user = $config['username'] ?? '';
        $pass = $config['password'] ?? '';
        $port = (int)($config['port'] ?? 21);
        $ssl  = !empty($config['ssl']);
        $root = rtrim($config['document_root'] ?? '', '/');
        if (!$host || !$user) {
            throw new RuntimeException('FTP: host и username обязательны в config');
        }

        $ftp = $ssl ? ftp_ssl_connect($host, $port, 10) : ftp_connect($host, $port, 10);
        if (!$ftp) {
            throw new RuntimeException("FTP: не удалось подключиться к {$host}:{$port}");
        }

        if (!ftp_login($ftp, $user, $pass)) {
            ftp_close($ftp);
            throw new RuntimeException('FTP: ошибка авторизации');
        }

        ftp_pasv($ftp, true);

        $fullPath = $root . '/' . $path;
        $dir = dirname($fullPath);
        $this->ftpMkdirRecursive($ftp, $dir);

        $tmpFile = tempnam(sys_get_temp_dir(), 'seo_publish_');
        file_put_contents($tmpFile, $html);

        $success = ftp_put($ftp, $fullPath, $tmpFile, FTP_BINARY);
        unlink($tmpFile);
        ftp_close($ftp);

        if (!$success) {
            throw new RuntimeException("FTP: не удалось загрузить файл в {$fullPath}");
        }
    }

    private function ftpMkdirRecursive($ftp, string $dir): void {
        $parts = explode('/', trim($dir, '/'));
        $current = '';
        foreach ($parts as $part) {
            $current .= '/' . $part;
            @ftp_mkdir($ftp, $current);
        }
    }


    private function deleteFromTargets(array $article, string $path): void {
        $targets = $this->db->fetchAll("SELECT * FROM seo_publish_targets WHERE is_active = 1");

        foreach ($targets as $target) {
            $config = is_string($target['config'])
                ? json_decode($target['config'], true) ?? []
                : ($target['config'] ?? []);

            try {
                $type = $target['type'] === 'hostia' ? 'selfhosted' : $target['type'];
                switch ($type) {
                    case 'selfhosted':
                        $this->deleteSelfhosted($path, $config, $target);
                        break;
                    case 'ftp':
                        $this->deleteFtp($path, $config);
                        break;
                }
            } catch (Throwable $e) {
                logMessage("Unpublish delete failed for target {$target['name']}: " . $e->getMessage());
            }
        }
    }

    private function deleteSelfhosted(string $path, array $config, array $target): void {
        $baseUrl  = rtrim($target['base_url'], '/');
        $endpoint = !empty($config['publish_endpoint'])
            ? $config['publish_endpoint']
            : $baseUrl . self::BASE_PUBLISH_URL;

        $payload = json_encode([
            'action' => 'delete',
            'path'   => $path,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Publish-Secret: ' . PUBLISH_SECRET,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => SEO_PUBLISH_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => SEO_PUBLISH_CONNECT_TIMEOUT,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("Hostia delete failed: {$err}");
        }
        curl_close($ch);

        if ($httpCode >= 400 && $httpCode !== 404) {
            throw new RuntimeException("Hostia delete error: HTTP {$httpCode}");
        }
    }

    private function deleteFtp(string $path, array $config): void {
        $host = $config['host'] ?? '';
        $user = $config['username'] ?? '';
        $pass = $config['password'] ?? '';
        $port = (int)($config['port'] ?? 21);
        $ssl  = !empty($config['ssl']);
        $root = rtrim($config['document_root'] ?? '', '/');

        if (!$host || !$user) return;

        $ftp = $ssl ? ftp_ssl_connect($host, $port, 10) : ftp_connect($host, $port, 10);
        if (!$ftp) return;

        if (!ftp_login($ftp, $user, $pass)) {
            ftp_close($ftp);
            return;
        }

        ftp_pasv($ftp, true);
        $fullPath = $root . '/' . $path;
        @ftp_delete($ftp, $fullPath);

        @ftp_rmdir($ftp, dirname($fullPath));

        ftp_close($ftp);
    }

    private function buildCatalogPath(array $article): string {
        if (empty($article['catalog_id'])) return '';
        $parts = [];
        $id = (int)$article['catalog_id'];
        $max = 10;
        while ($id > 0 && $max-- > 0) {
            $cat = $this->db->fetchOne("SELECT id, parent_id, slug FROM seo_catalogs WHERE id = ?", [$id]);
            if (!$cat) break;
            array_unshift($parts, $cat['slug']);
            $id = (int)($cat['parent_id'] ?? 0);
        }
        return implode('/', $parts);
    }

    private function loadArticle(int $id): array {
        $r = $this->db->fetchOne("SELECT * FROM " . SeoArticle::SEO_ARTICLE_TABLE . " WHERE id = ?", [$id]);
        if (!$r) throw new RuntimeException("Статья #{$id} не найдена");
        return $r;
    }

    private function loadTarget(int $id): array {
        $r = $this->db->fetchOne("SELECT * FROM " . SeoPublishTarget::SEO_PUBLISH_TARGET_TABLE . " WHERE id = ?", [$id]);
        if (!$r) throw new RuntimeException("Таргет #{$id} не найден");
        return $r;
    }

    private function writeAudit(int $articleId, string $action, array $details): void {
        $json = json_encode($details, JSON_UNESCAPED_UNICODE);
        $this->db->insert(SeoAuditLog::SEO_AUDIT_LOG_TABLE,
            SeoAuditLog::articleAction($articleId, $action, 'system/publish', ['details' => $json])->toArray());
    }
}
