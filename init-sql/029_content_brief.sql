-- 029: Content brief — structured JSON brief for richer persona/rules/template generation
-- Stores: audience, value_prop, voice, topics, compliance, competitors, evidence
ALTER TABLE seo_site_profiles
    ADD COLUMN content_brief JSON DEFAULT NULL COMMENT 'Структурированный бриф для генерации контента (audience, usps, voice, compliance и т.д.)' AFTER gpt_rules;
