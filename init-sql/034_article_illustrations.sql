-- Article illustrations: hero/og/inline pointers to seo_images.
-- One row per (article_id, kind). Re-generation overwrites image_id.

CREATE TABLE IF NOT EXISTS `seo_article_illustrations` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `article_id`  INT UNSIGNED    NOT NULL,
    `kind`        ENUM('hero','og','inline') NOT NULL,
    `prompt`      TEXT            NULL,
    `model`       VARCHAR(64)     NULL COMMENT 'imagen-3 / dall-e-3 / puppeteer / manual',
    `image_id`    BIGINT UNSIGNED    NULL COMMENT 'FK seo_images.id (base64 storage)',
    `status`      VARCHAR(32)     NOT NULL DEFAULT 'ready' COMMENT 'pending|ready|failed',
    `error`       TEXT            NULL,
    `cost_cents`  INT UNSIGNED    NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_article_kind` (`article_id`, `kind`),
    KEY `idx_article` (`article_id`),
    KEY `idx_image` (`image_id`),
    CONSTRAINT `fk_illust_article`
        FOREIGN KEY (`article_id`) REFERENCES `seo_articles`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_illust_image`
        FOREIGN KEY (`image_id`) REFERENCES `seo_images`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
