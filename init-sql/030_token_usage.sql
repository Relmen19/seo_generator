-- ═══════════════════════════════════════════════════════
-- Migration 030: Per-profile token usage log
-- Categories: profile_create, profile_brief, template_create,
--             template_review, article_create, telegram_aggregate
-- ═══════════════════════════════════════════════════════

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `seo_token_usage` (
    `id`                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `profile_id`        INT UNSIGNED NULL,
    `category`          VARCHAR(32)  NOT NULL,
    `operation`         VARCHAR(64)  NULL,
    `entity_type`       VARCHAR(32)  NULL,
    `entity_id`         INT UNSIGNED NULL,
    `model`             VARCHAR(64)  NULL,
    `prompt_tokens`     INT UNSIGNED NOT NULL DEFAULT 0,
    `completion_tokens` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_tokens`      INT UNSIGNED NOT NULL DEFAULT 0,
    `cost_usd`          DECIMAL(12,6) NOT NULL DEFAULT 0,
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_profile_cat_date` (`profile_id`, `category`, `created_at`),
    INDEX `idx_category`          (`category`),
    INDEX `idx_entity`            (`entity_type`, `entity_id`),
    INDEX `idx_created`           (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
