<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer;

use Seo\Database;

/**
 * Per-render shared cache of seo_images rows.
 * PageAssembler builds and primes a single instance, then injects it into
 * BlockRegistry so all block renderers share lookups instead of issuing
 * one query per block image.
 */
class ImageCache
{
    /** @var array<int, array{mime_type: string, data_base64: string}|null> */
    private array $rows = [];

    public function prime(Database $db, array $ids): void
    {
        $clean = [];
        foreach ($ids as $id) {
            $iid = (int)$id;
            if ($iid > 0 && !array_key_exists($iid, $this->rows)) {
                $clean[$iid] = true;
            }
        }
        if (empty($clean)) return;

        $idList = array_keys($clean);
        $placeholders = implode(',', array_fill(0, count($idList), '?'));
        $rows = $db->fetchAll(
            "SELECT id, mime_type, data_base64 FROM seo_images WHERE id IN ($placeholders)",
            $idList
        );
        foreach ($rows as $row) {
            $rid = (int)$row['id'];
            $this->rows[$rid] = [
                'mime_type'   => $row['mime_type'],
                'data_base64' => $row['data_base64'],
            ];
        }
        // Mark missing as null so we don't re-query.
        foreach ($idList as $iid) {
            if (!array_key_exists($iid, $this->rows)) {
                $this->rows[$iid] = null;
            }
        }
    }

    public function has(int $id): bool
    {
        return array_key_exists($id, $this->rows);
    }

    /**
     * @return array{mime_type: string, data_base64: string}|null
     */
    public function get(int $id): ?array
    {
        return $this->rows[$id] ?? null;
    }
}
