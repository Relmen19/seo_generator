-- Token-based theme system.
-- seo_themes: catalog of named token sets (color/type/space/radius/layout).
-- seo_articles.theme_code: per-article override of theme (NULL → fall back to profile default → 'default').
-- seo_site_profiles.default_theme_code: profile-level default theme code.

CREATE TABLE IF NOT EXISTS `seo_themes` (
    `code`        VARCHAR(64) NOT NULL PRIMARY KEY,
    `name`        VARCHAR(128) NOT NULL,
    `tokens`      JSON NOT NULL COMMENT 'Design tokens: color/type/space/radius/layout',
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `seo_articles`
    ADD COLUMN `theme_code` VARCHAR(64) NULL AFTER `template_id`;

ALTER TABLE `seo_site_profiles`
    ADD COLUMN `default_theme_code` VARCHAR(64) NULL AFTER `theme`;

-- Seeds aligned with existing PHP themes (DefaultTheme, EditorialTheme, BrutalistTheme).
-- 3 themes total. UI-driven creation of more comes in Stage 4.
INSERT INTO `seo_themes` (`code`,`name`,`tokens`) VALUES
('default','Default — Tech Blue', JSON_OBJECT(
    'color', JSON_OBJECT(
        'bg','#F8FAFC','surface','#FFFFFF',
        'text','#0F172A','text-2','#334155','text-3','#64748B',
        'accent','#2563EB','accent-soft','#EFF6FF',
        'success','#16A34A','warn','#F59E0B','danger','#EF4444',
        'border','rgba(0,0,0,0.08)',
        'chart-1','#2563EB','chart-2','#0D9488','chart-3','#16A34A','chart-4','#F59E0B',
        'chart-5','#EF4444','chart-6','#8B5CF6','chart-7','#EC4899','chart-8','#0EA5E9'
    ),
    'type', JSON_OBJECT(
        'font-text','"Onest",sans-serif',
        'font-heading','"Geologica",sans-serif',
        'font-mono','ui-monospace,SFMono-Regular,Menlo,monospace',
        'size-text','17px','line-text','1.7',
        'size-h2','clamp(1.5rem,3vw,2.2rem)',
        'size-h3','1.35rem'
    ),
    'space', JSON_OBJECT('scale', JSON_ARRAY(4,8,16,24,40,64)),
    'radius', JSON_OBJECT('sm','8px','md','14px','lg','20px'),
    'layout', JSON_OBJECT('col-max','960px','col-wide','1180px')
)),
('editorial','Editorial — Magazine', JSON_OBJECT(
    'color', JSON_OBJECT(
        'bg','#FBF9F6','surface','#FFFFFF',
        'text','#1A1A2E','text-2','#444466','text-3','#7A7A99',
        'accent','#B7312C','accent-soft','#FDF2F2',
        'success','#2E7D32','warn','#E65100','danger','#C62828',
        'border','rgba(26,26,46,0.08)',
        'chart-1','#B7312C','chart-2','#C8915A','chart-3','#9C274C','chart-4','#6A3C8C',
        'chart-5','#BE6428','chart-6','#2E7D32','chart-7','#444466','chart-8','#7A7A99'
    ),
    'type', JSON_OBJECT(
        'font-text','"Source Serif 4",Georgia,serif',
        'font-heading','"Playfair Display",Georgia,serif',
        'font-mono','"IBM Plex Mono",ui-monospace,monospace',
        'size-text','18px','line-text','1.85',
        'size-h2','clamp(1.6rem,3vw,2.4rem)',
        'size-h3','1.4rem'
    ),
    'space', JSON_OBJECT('scale', JSON_ARRAY(4,8,16,24,40,64)),
    'radius', JSON_OBJECT('sm','2px','md','2px','lg','4px'),
    'layout', JSON_OBJECT('col-max','840px','col-wide','1080px')
)),
('brutalist','Brutalist — Neo-Industrial', JSON_OBJECT(
    'color', JSON_OBJECT(
        'bg','#FFFFFF','surface','#FFFFFF',
        'text','#000000','text-2','#333333','text-3','#666666',
        'accent','#FF5722','accent-soft','#FFF3E0',
        'success','#2E7D32','warn','#FF9800','danger','#F44336',
        'border','rgba(0,0,0,0.12)',
        'chart-1','#FF5722','chart-2','#000000','chart-3','#666666','chart-4','#FF9800',
        'chart-5','#F44336','chart-6','#2E7D32','chart-7','#333333','chart-8','#888888'
    ),
    'type', JSON_OBJECT(
        'font-text','"JetBrains Mono","Fira Code",monospace',
        'font-heading','"Space Grotesk",sans-serif',
        'font-mono','"JetBrains Mono",ui-monospace,monospace',
        'size-text','14px','line-text','1.8',
        'size-h2','clamp(1.6rem,3vw,2.4rem)',
        'size-h3','1.3rem'
    ),
    'space', JSON_OBJECT('scale', JSON_ARRAY(4,8,16,24,40,64)),
    'radius', JSON_OBJECT('sm','0px','md','0px','lg','0px'),
    'layout', JSON_OBJECT('col-max','920px','col-wide','1120px')
));
