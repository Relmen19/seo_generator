-- 023: Telegram rendered images — track source + metadata

ALTER TABLE seo_telegram_rendered_images
    ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'block_render'
        COMMENT 'block_render|article_image|upload|ai_generated' AFTER block_type,
    ADD COLUMN custom_meta JSON DEFAULT NULL
        COMMENT 'Optional metadata (filename, prompt, source image id, etc.)' AFTER image_data;

-- Backfill existing rows: they were all rendered from article blocks
UPDATE seo_telegram_rendered_images SET source = 'block_render' WHERE source = '';

-- block_type is NOT NULL but uploaded/ai images have no type — allow empty string via default
ALTER TABLE seo_telegram_rendered_images
    MODIFY COLUMN block_type VARCHAR(50) NOT NULL DEFAULT '';
