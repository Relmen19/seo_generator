SET NAMES utf8mb4;

-- Add 'gpt' to seo_raw_keywords.source ENUM (for existing databases)
ALTER TABLE `seo_raw_keywords`
    MODIFY COLUMN `source` ENUM('yandex','google','manual','gpt') NOT NULL DEFAULT 'manual';
