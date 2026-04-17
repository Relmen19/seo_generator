-- 022: Telegram channel integration

-- ── Profile-level Telegram settings ──────────────────────────────────────────
ALTER TABLE seo_site_profiles
    ADD COLUMN tg_bot_token VARCHAR(100) DEFAULT NULL
        COMMENT 'Telegram Bot API token' AFTER theme,
    ADD COLUMN tg_channel_id VARCHAR(100) DEFAULT NULL
        COMMENT '@username or numeric chat_id' AFTER tg_bot_token,
    ADD COLUMN tg_post_format VARCHAR(20) NOT NULL DEFAULT 'auto'
        COMMENT 'auto|single|series' AFTER tg_channel_id,
    ADD COLUMN tg_render_blocks JSON DEFAULT NULL
        COMMENT 'Block types to render as images' AFTER tg_post_format,
    ADD COLUMN tg_channel_name VARCHAR(255) DEFAULT NULL
        COMMENT 'Cached channel display name' AFTER tg_render_blocks,
    ADD COLUMN tg_channel_avatar VARCHAR(500) DEFAULT NULL
        COMMENT 'Cached channel avatar (base64 or path)' AFTER tg_channel_name;

-- ── Article-level Telegram export flag ───────────────────────────────────────
ALTER TABLE seo_articles
    ADD COLUMN tg_export TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Flagged for Telegram export' AFTER published_url;

-- ── Telegram posts ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS seo_telegram_posts (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    article_id      INT UNSIGNED    NOT NULL,
    profile_id      INT UNSIGNED    NOT NULL,
    status          VARCHAR(20)     NOT NULL DEFAULT 'draft'
        COMMENT 'draft|scheduled|sending|sent|failed',
    post_format     VARCHAR(20)     NOT NULL DEFAULT 'auto'
        COMMENT 'auto|single|series',
    scheduled_at    DATETIME        DEFAULT NULL,
    sent_at         DATETIME        DEFAULT NULL,
    post_data       JSON            NOT NULL
        COMMENT 'Structured post content',
    tg_message_ids  JSON            DEFAULT NULL
        COMMENT 'Telegram message_id array',
    tg_post_url     VARCHAR(500)    DEFAULT NULL,
    error_message   TEXT            DEFAULT NULL,
    attempts        INT UNSIGNED    NOT NULL DEFAULT 0,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tgp_article (article_id),
    KEY idx_tgp_profile (profile_id),
    KEY idx_tgp_status_sched (status, scheduled_at),
    CONSTRAINT fk_tgp_article FOREIGN KEY (article_id)
        REFERENCES seo_articles(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_tgp_profile FOREIGN KEY (profile_id)
        REFERENCES seo_site_profiles(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Rendered block images for posts ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS seo_telegram_rendered_images (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tg_post_id  BIGINT UNSIGNED NOT NULL,
    block_id    BIGINT UNSIGNED DEFAULT NULL,
    block_type  VARCHAR(50)     NOT NULL,
    image_data  MEDIUMTEXT      NOT NULL COMMENT 'Base64 PNG',
    width       INT UNSIGNED    DEFAULT NULL,
    height      INT UNSIGNED    DEFAULT NULL,
    sort_order  INT             NOT NULL DEFAULT 0,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tgri_post (tg_post_id),
    CONSTRAINT fk_tgri_post FOREIGN KEY (tg_post_id)
        REFERENCES seo_telegram_posts(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
