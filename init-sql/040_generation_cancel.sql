-- Server-side cancel support for long-running generation pipelines.
ALTER TABLE seo_articles
    ADD COLUMN generation_cancel_requested_at TIMESTAMP NULL DEFAULT NULL
    AFTER research_at;
