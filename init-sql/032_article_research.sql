-- ═══════════════════════════════════════════════════════
-- Migration 032: Article research dossier
-- Stores markdown research collected before meta/blocks generation.
-- ═══════════════════════════════════════════════════════

SET NAMES utf8mb4;

ALTER TABLE `seo_articles`
    ADD COLUMN `research_dossier` MEDIUMTEXT NULL AFTER `article_plan`,
    ADD COLUMN `research_status`  VARCHAR(16) NOT NULL DEFAULT 'none' AFTER `research_dossier`,
    ADD COLUMN `research_at`      TIMESTAMP NULL AFTER `research_status`;

-- research_status: none | draft | ready | stale
