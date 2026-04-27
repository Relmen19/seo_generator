-- Generation mode toggle and validation/error tracking.
-- Simple = template -> plan -> GPT (predictable, default).
-- Advanced = adds research + outline before blocks.
ALTER TABLE seo_articles
    ADD COLUMN generation_mode ENUM('simple','advanced') NOT NULL DEFAULT 'simple'
        AFTER gpt_model,
    ADD COLUMN generation_error TEXT NULL DEFAULT NULL
        AFTER generation_log;

-- Global flag controlling whether the telegram_sender cron actually posts.
-- Off by default: cron file ships with the project but does nothing until
-- the user explicitly enables scheduled posting.
CREATE TABLE IF NOT EXISTS seo_settings (
    `key`   VARCHAR(64)  NOT NULL PRIMARY KEY,
    `value` VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO seo_settings (`key`, `value`) VALUES ('scheduled_publish_enabled', '0')
ON DUPLICATE KEY UPDATE `key` = `key`;
