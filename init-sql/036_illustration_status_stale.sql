-- Add `stale` to seo_article_illustrations.status valid set.
-- Hero illustrations become stale when research_dossier changes; UI re-prompts user to regenerate.

ALTER TABLE `seo_article_illustrations`
    MODIFY COLUMN `status` VARCHAR(32) NOT NULL DEFAULT 'ready'
    COMMENT 'pending|ready|failed|stale';
