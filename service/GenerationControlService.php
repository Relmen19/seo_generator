<?php

declare(strict_types=1);

namespace Seo\Service;

use Seo\Database;
use Seo\Entity\SeoArticle;

/**
 * Cooperative cancellation for long-running pipelines.
 * Server polls isCancelled() at safe checkpoints (between blocks/phases).
 * Client posts /generate/{id}/cancel, which sets the flag.
 */
class GenerationControlService
{
    private Database $db;
    /** Per-process cache to avoid querying DB on every checkpoint. */
    private array $cache = [];
    private int $cacheTtlSec = 1;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function requestCancel(int $articleId): void
    {
        $this->db->update(
            SeoArticle::SEO_ARTICLE_TABLE,
            'id = :id',
            ['generation_cancel_requested_at' => date('Y-m-d H:i:s')],
            [':id' => $articleId]
        );
        unset($this->cache[$articleId]);
    }

    public function clearCancel(int $articleId): void
    {
        $this->db->update(
            SeoArticle::SEO_ARTICLE_TABLE,
            'id = :id',
            ['generation_cancel_requested_at' => null],
            [':id' => $articleId]
        );
        unset($this->cache[$articleId]);
    }

    public function isCancelled(int $articleId): bool
    {
        $now = time();
        if (isset($this->cache[$articleId])) {
            [$flag, $at] = $this->cache[$articleId];
            if (($now - $at) < $this->cacheTtlSec) return $flag;
        }
        $row = $this->db->fetchOne(
            'SELECT generation_cancel_requested_at FROM ' . SeoArticle::SEO_ARTICLE_TABLE . ' WHERE id = ?',
            [$articleId]
        );
        $flag = !empty($row['generation_cancel_requested_at']);
        $this->cache[$articleId] = [$flag, $now];
        return $flag;
    }

    /**
     * Throws CancelledException if cancel was requested. Use at safe checkpoints.
     * @throws CancelledException
     */
    public function throwIfCancelled(int $articleId, string $phase = ''): void
    {
        if ($this->isCancelled($articleId)) {
            throw new CancelledException("Generation cancelled at phase: {$phase}");
        }
    }
}
