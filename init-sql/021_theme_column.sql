-- 021: Add theme column to site profiles
ALTER TABLE seo_site_profiles
    ADD COLUMN theme VARCHAR(50) NOT NULL DEFAULT 'default' COMMENT 'Visual theme key (default, editorial, brutalist)' AFTER color_scheme;
