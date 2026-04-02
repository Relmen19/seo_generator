-- ═══════════════════════════════════════════════════════
-- Migration 016: Site Profiles — multi-project support
-- Creates seo_site_profiles + adds profile_id to all tables
-- ═══════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ── 1. Create site profiles table ───────────────────────

CREATE TABLE IF NOT EXISTS `seo_site_profiles` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(255)    NOT NULL COMMENT 'Human-readable profile name',
    `slug`          VARCHAR(100)    NOT NULL UNIQUE,
    `domain`        VARCHAR(255)    NULL,
    `niche`         VARCHAR(100)    NULL COMMENT 'Free text: медицина, e-commerce, etc.',
    `language`      VARCHAR(10)     NOT NULL DEFAULT 'ru',
    `brand_name`    VARCHAR(255)    NULL,
    `logo_url`      VARCHAR(500)    NULL,
    `base_url`      VARCHAR(500)    NULL,
    `gpt_persona`   TEXT            NULL COMMENT 'System prompt persona for GPT',
    `gpt_rules`     TEXT            NULL COMMENT 'Additional generation rules',
    `tone`          VARCHAR(50)     NOT NULL DEFAULT 'professional',
    `color_scheme`  VARCHAR(7)      NOT NULL DEFAULT '#6366f1',
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_profile_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Site profiles for multi-project support';


-- ── 2. Insert medical profile as first profile ──────────

INSERT INTO `seo_site_profiles` (`id`, `name`, `slug`, `domain`, `niche`, `language`, `brand_name`, `logo_url`, `base_url`, `gpt_persona`, `gpt_rules`, `tone`, `color_scheme`) VALUES
(1, 'Медицинский портал VerixLab', 'medical', NULL, 'медицина', 'ru', 'VerixLab', NULL, NULL,
 'Ты — медицинский SEO-копирайтер. JSON-формат. Профессиональный, но доступный стиль. Все данные медицински корректны. Конкретные цифры, нормы, проценты.',
 'Заголовки визуальных блоков — понятные пациенту, не техническая терминология. Все медицинские термины должны сопровождаться объяснением простым языком.',
 'professional', '#6366f1');


-- ── 3. Add profile_id to seo_catalogs ───────────────────

ALTER TABLE `seo_catalogs`
    ADD COLUMN `profile_id` INT UNSIGNED NULL DEFAULT NULL AFTER `id`,
    ADD KEY `idx_profile` (`profile_id`),
    ADD CONSTRAINT `fk_catalog_profile`
        FOREIGN KEY (`profile_id`) REFERENCES `seo_site_profiles` (`id`)
            ON DELETE SET NULL ON UPDATE CASCADE;

-- ── 4. Add profile_id to seo_templates ──────────────────

ALTER TABLE `seo_templates`
    ADD COLUMN `profile_id` INT UNSIGNED NULL DEFAULT NULL AFTER `id`,
    ADD KEY `idx_tpl_profile` (`profile_id`),
    ADD CONSTRAINT `fk_template_profile`
        FOREIGN KEY (`profile_id`) REFERENCES `seo_site_profiles` (`id`)
            ON DELETE SET NULL ON UPDATE CASCADE;

-- ── 5. Add profile_id to seo_articles ───────────────────

ALTER TABLE `seo_articles`
    ADD COLUMN `profile_id` INT UNSIGNED NULL DEFAULT NULL AFTER `id`,
    ADD KEY `idx_art_profile` (`profile_id`),
    ADD CONSTRAINT `fk_article_profile`
        FOREIGN KEY (`profile_id`) REFERENCES `seo_site_profiles` (`id`)
            ON DELETE SET NULL ON UPDATE CASCADE;

-- ── 6. Add profile_id to seo_keyword_jobs ───────────────

ALTER TABLE `seo_keyword_jobs`
    ADD COLUMN `profile_id` INT UNSIGNED NULL DEFAULT NULL AFTER `id`,
    ADD KEY `idx_job_profile` (`profile_id`),
    ADD CONSTRAINT `fk_job_profile`
        FOREIGN KEY (`profile_id`) REFERENCES `seo_site_profiles` (`id`)
            ON DELETE SET NULL ON UPDATE CASCADE;

-- ── 7. Add profile_id to seo_keyword_clusters ───────────

ALTER TABLE `seo_keyword_clusters`
    ADD COLUMN `profile_id` INT UNSIGNED NULL DEFAULT NULL AFTER `id`,
    ADD KEY `idx_cl_profile` (`profile_id`),
    ADD CONSTRAINT `fk_cluster_profile`
        FOREIGN KEY (`profile_id`) REFERENCES `seo_site_profiles` (`id`)
            ON DELETE SET NULL ON UPDATE CASCADE;

-- ── 8. Add profile_id to seo_intent_types ───────────────

ALTER TABLE `seo_intent_types`
    ADD COLUMN `profile_id` INT UNSIGNED NULL DEFAULT NULL AFTER `code`,
    ADD KEY `idx_intent_profile` (`profile_id`),
    ADD CONSTRAINT `fk_intent_profile`
        FOREIGN KEY (`profile_id`) REFERENCES `seo_site_profiles` (`id`)
            ON DELETE SET NULL ON UPDATE CASCADE;

-- ── 9. Add profile_id to seo_link_constants ─────────────

ALTER TABLE `seo_link_constants`
    ADD COLUMN `profile_id` INT UNSIGNED NULL DEFAULT NULL AFTER `id`,
    ADD KEY `idx_link_profile` (`profile_id`),
    ADD CONSTRAINT `fk_link_profile`
        FOREIGN KEY (`profile_id`) REFERENCES `seo_site_profiles` (`id`)
            ON DELETE SET NULL ON UPDATE CASCADE;

-- ── 10. Add profile_id to seo_publish_targets ───────────

ALTER TABLE `seo_publish_targets`
    ADD COLUMN `profile_id` INT UNSIGNED NULL DEFAULT NULL AFTER `id`,
    ADD KEY `idx_target_profile` (`profile_id`),
    ADD CONSTRAINT `fk_target_profile`
        FOREIGN KEY (`profile_id`) REFERENCES `seo_site_profiles` (`id`)
            ON DELETE SET NULL ON UPDATE CASCADE;


-- ── 11. Bind existing data to medical profile (id=1) ────

UPDATE `seo_catalogs`        SET `profile_id` = 1 WHERE `profile_id` IS NULL;
UPDATE `seo_templates`       SET `profile_id` = 1 WHERE `profile_id` IS NULL;
UPDATE `seo_articles`        SET `profile_id` = 1 WHERE `profile_id` IS NULL;
UPDATE `seo_keyword_jobs`    SET `profile_id` = 1 WHERE `profile_id` IS NULL;
UPDATE `seo_keyword_clusters` SET `profile_id` = 1 WHERE `profile_id` IS NULL;
UPDATE `seo_intent_types`    SET `profile_id` = 1 WHERE `profile_id` IS NULL;
UPDATE `seo_link_constants`  SET `profile_id` = 1 WHERE `article_id` IS NULL AND `profile_id` IS NULL;
UPDATE `seo_publish_targets` SET `profile_id` = 1 WHERE `profile_id` IS NULL;
