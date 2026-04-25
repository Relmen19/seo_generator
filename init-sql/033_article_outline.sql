-- ═══════════════════════════════════════════════════════
-- Migration 033: Article structured outline
-- Replaces flat article_plan with JSON outline of sections
-- (h2_title, narrative_role, block_type, content_brief, source_facts).
-- article_plan retained for backwards compatibility.
-- ═══════════════════════════════════════════════════════

SET NAMES utf8mb4;

ALTER TABLE `seo_articles`
    ADD COLUMN `article_outline` MEDIUMTEXT NULL AFTER `article_plan`,
    ADD COLUMN `outline_status`  VARCHAR(16) NOT NULL DEFAULT 'none' AFTER `article_outline`;

-- outline_status: none | draft | ready | stale
