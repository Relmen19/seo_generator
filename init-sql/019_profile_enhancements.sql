-- 019: Profile enhancements — icon, description fields
ALTER TABLE seo_site_profiles
    ADD COLUMN description TEXT DEFAULT NULL COMMENT 'Подробное описание проекта' AFTER niche,
    ADD COLUMN icon_path VARCHAR(500) DEFAULT NULL COMMENT 'Путь к иконке профиля (uploads/)' AFTER logo_url;
