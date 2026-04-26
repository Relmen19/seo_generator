-- Editorial QA + extended status workflow.

ALTER TABLE seo_articles
    MODIFY COLUMN status ENUM(
        'draft',
        'research_done',
        'outline_done',
        'blocks_done',
        'ai_review',
        'human_review',
        'review',
        'published',
        'unpublished',
        'archived'
    ) NOT NULL DEFAULT 'draft';

CREATE TABLE IF NOT EXISTS seo_article_issues (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    article_id  BIGINT UNSIGNED NOT NULL,
    severity    ENUM('info','warn','error') NOT NULL DEFAULT 'warn',
    code        VARCHAR(64) NOT NULL,
    message     TEXT NOT NULL,
    block_id    BIGINT UNSIGNED NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX idx_aqi_article (article_id),
    INDEX idx_aqi_unresolved (article_id, resolved_at),
    INDEX idx_aqi_severity (severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
