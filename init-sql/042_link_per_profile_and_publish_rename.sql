-- Migration 042: links scoped to profile only; publish target type 'hostia' renamed to 'selfhosted'.
--
-- 1. Backfill profile_id on links that were per-article.
UPDATE `seo_link_constants` l
JOIN   `seo_articles` a ON a.id = l.article_id
SET    l.profile_id = a.profile_id
WHERE  l.profile_id IS NULL AND l.article_id IS NOT NULL;

-- 2. Drop per-article binding entirely (column kept nullable for legacy reads, but no new writes).
UPDATE `seo_link_constants` SET `article_id` = NULL WHERE `article_id` IS NOT NULL;

-- 3. Rename publish target type. Widen ENUM first so UPDATE has 'selfhosted' as a valid value;
--    then drop legacy values once data is migrated.
ALTER TABLE `seo_publish_targets`
    MODIFY `type` ENUM('hostia','selfhosted','ftp','ssh','api') NOT NULL DEFAULT 'selfhosted';

UPDATE `seo_publish_targets` SET `type` = 'selfhosted' WHERE `type` IN ('hostia','ssh','api');

ALTER TABLE `seo_publish_targets`
    MODIFY `type` ENUM('selfhosted','ftp') NOT NULL DEFAULT 'selfhosted';
