-- ═══════════════════════════════════════════════════════
-- Migration 015: Block Types Registry + Code Renaming
-- Creates seo_block_types table and renames medical-specific codes
-- ═══════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ── 1. Create block types registry ──────────────────────

CREATE TABLE IF NOT EXISTS `seo_block_types` (
    `code`          VARCHAR(50)     NOT NULL,
    `display_name`  VARCHAR(255)    NOT NULL,
    `description`   TEXT            NULL,
    `category`      VARCHAR(50)     NOT NULL DEFAULT 'content' COMMENT 'layout|content|data|interactive|cta',
    `icon`          VARCHAR(10)     NULL COMMENT 'Emoji icon',
    `json_schema`   JSON            NULL COMMENT 'Field schema for UI and validation',
    `gpt_hint`      TEXT            NULL COMMENT 'Hint for GPT when generating this block type',
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `sort_order`    INT             NOT NULL DEFAULT 0,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`code`),
    KEY `idx_category` (`category`),
    KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Registry of all available block types with metadata';

-- ── 2. Insert all block types ───────────────────────────

INSERT INTO `seo_block_types` (`code`, `display_name`, `description`, `category`, `icon`, `sort_order`) VALUES
-- Layout blocks
('hero',              'Hero-секция',              'Главный заголовок и подзаголовок страницы',                   'layout',      '🎯', 1),
('cta',               'Призыв к действию',        'Кнопка или блок с призывом к действию',                       'cta',         '🚀', 2),
('image_section',     'Изображение',              'Блок с изображением и текстом',                               'layout',      '🖼️', 3),

-- Content blocks
('richtext',          'Текстовый блок',           'Форматированный текст с подблоками (параграфы, списки, заголовки)', 'content', '📝', 10),
('accordion',         'Аккордеон',                'Раскрывающиеся секции',                                       'content',     '📂', 11),
('faq',               'FAQ',                      'Вопросы и ответы',                                            'content',     '❓', 12),
('feature_grid',      'Сетка возможностей',       'Карточки с иконками и описанием',                             'content',     '🔲', 13),
('info_cards',        'Информационные карточки',   'Набор цветных карточек с краткой информацией',                'content',     '🃏', 14),
('key_takeaways',     'Ключевые выводы',           'Главные тезисы статьи в краткой форме',                      'content',     '💡', 15),
('story_block',       'Кейс / История',            'Цитата, факт или история (пациента/клиента/эксперта)',       'content',     '📖', 16),
('testimonial',       'Отзыв',                     'Отзывы с именем, ролью и рейтингом',                         'content',     '⭐', 17),
('expert_panel',      'Мнение эксперта',           'Экспертный комментарий с фото и регалиями',                  'content',     '👨‍⚕️', 18),
('numbered_steps',    'Пошаговая инструкция',      'Нумерованные шаги с описанием и советами',                   'content',     '📋', 19),
('verdict_card',      'Вердикт: миф/правда',       'Карточки разоблачения мифов',                                'content',     '⚖️', 20),
('warning_block',     'Предупреждение',            'Блок предупреждений (красные флаги, осторожно, хорошие знаки)', 'content',  '⚠️', 21),
('comparison_cards',  'Карточки сравнения',        'Сравнение двух вариантов (A vs B)',                           'content',     '🔀', 22),
('progress_tracker',  'Трекер прогресса',          'Шкала с вехами и описаниями',                                'content',     '📊', 23),

-- Data visualization blocks
('stats_counter',     'Счётчик статистики',        'Числовые метрики с анимацией',                               'data',        '🔢', 30),
('range_table',       'Таблица диапазонов',        'Таблица с диапазонами значений по группам/категориям',       'data',        '📊', 31),
('chart',             'Диаграмма',                 'Круговая, столбчатая или линейная диаграмма',                 'data',        '📈', 32),
('gauge_chart',       'Шкала-датчик',              'Показатели со шкалой min/max',                               'data',        '🎛️', 33),
('timeline',          'Таймлайн',                  'Хронологическая последовательность шагов',                   'data',        '⏱️', 34),
('heatmap',           'Тепловая карта',            'Двумерная таблица с цветовой кодировкой',                    'data',        '🌡️', 35),
('funnel',            'Воронка',                   'Убывающая визуализация (этапы/конверсия)',                    'data',        '🔻', 36),
('spark_metrics',     'Спарк-метрики',             'Компактные метрики с мини-графиками',                        'data',        '✨', 37),
('radar_chart',       'Радарная диаграмма',        'Многоосевая оценка показателей',                             'data',        '🕸️', 38),
('before_after',      'До / После',                'Сравнение метрик до и после',                                'data',        '↔️', 39),
('stacked_area',      'Площадная диаграмма',       'Динамика нескольких серий с наложением',                     'data',        '📉', 40),
('score_rings',       'Кольца оценок',             'Круговые прогресс-бары с оценками',                          'data',        '🎯', 41),
('range_comparison',  'Сравнение диапазонов',      'Сравнение числовых диапазонов по группам',                   'data',        '📏', 42),
('comparison_table',  'Таблица сравнения',         'Сравнительная таблица с headers и rows',                     'data',        '📋', 43),

-- Interactive blocks
('value_checker',     'Проверка значения',         'Ввод числа и определение зоны (нормы/отклонения)',           'interactive', '🔍', 50),
('criteria_checklist','Чек-лист критериев',        'Взвешенный чек-лист с подсчётом баллов и порогами',          'interactive', '✅', 51),
('prep_checklist',    'Чек-лист подготовки',       'Секционный чек-лист для подготовки к действию',              'interactive', '📝', 52),
('mini_calculator',   'Мини-калькулятор',          'Простой калькулятор с условной логикой',                     'interactive', '🔢', 53);


-- ── 3. Rename block codes: norms_table → range_table ────

UPDATE `seo_template_blocks` SET `type` = 'range_table' WHERE `type` = 'norms_table';
UPDATE `seo_article_blocks` SET `type` = 'range_table' WHERE `type` = 'norms_table';

-- ── 4. Rename block codes: symptom_checklist → criteria_checklist ──

UPDATE `seo_template_blocks` SET `type` = 'criteria_checklist' WHERE `type` = 'symptom_checklist';
UPDATE `seo_article_blocks` SET `type` = 'criteria_checklist' WHERE `type` = 'symptom_checklist';
