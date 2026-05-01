-- 043_catalog_allow_similar_name.sql
-- Persist user opt-in to keep a similar/duplicate-looking catalog name.
-- Stores the normalized (lowercased, alnum-only) name that was approved,
-- so renaming away from it automatically re-enables the warning.

ALTER TABLE seo_catalogs
    ADD COLUMN allow_similar_name VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Normalized name the user explicitly allowed despite duplicate hint; NULL = warn as usual';
