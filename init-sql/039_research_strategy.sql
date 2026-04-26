-- Research v2: split outline/fill (+ optional web search)
ALTER TABLE seo_site_profiles
    ADD COLUMN research_strategy ENUM('single','split','split_search') NOT NULL DEFAULT 'single'
    AFTER default_theme_code;
