-- Brand kit fields on site profiles.
-- brand_palette: JSON with named colors, e.g. {"primary":"#2563eb","accent":"#22c55e","ink":"#0f172a","bg":"#f8fafc"}
-- brand_illustration_style: free-text style hint passed to image prompt
--   (e.g. "flat vector isometric, ytsaurus.tech style, soft gradients, blue/purple accents")

ALTER TABLE `seo_site_profiles`
    ADD COLUMN `brand_palette`            JSON NULL COMMENT 'Named brand colors',
    ADD COLUMN `brand_illustration_style` TEXT NULL COMMENT 'Style hint for AI illustration prompts',
    ADD COLUMN `brand_logo_image_id`      INT UNSIGNED NULL COMMENT 'FK seo_images.id for OG/hero logo overlay',
    ADD CONSTRAINT `fk_profile_brand_logo`
        FOREIGN KEY (`brand_logo_image_id`) REFERENCES `seo_images`(`id`) ON DELETE SET NULL;
