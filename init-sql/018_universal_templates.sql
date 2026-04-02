-- ═══════════════════════════════════════════════════════
-- Migration 018: Universal Templates (profile_id = NULL)
-- 5 starter templates usable by any profile
-- ═══════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ── Template 100: Информационная — визуальная ───────────

INSERT INTO `seo_templates` (`id`, `profile_id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
(100, NULL, 'Информационная — визуальная',
 'universal-info-visual',
 'Универсальная справочная статья с интерактивами: карточки, FAQ, CTA.',
 'Ты — профессиональный SEO-копирайтер. Генерируй JSON-блоки для СПРАВОЧНОЙ статьи.
ТОН: энциклопедический, но человечный. Объясняй сложное простым языком.
ПРАВИЛО ТЕРРИТОРИЙ:
- key_takeaways: ТОЛЬКО краткое саммари (5 пунктов). НЕ подробности.
- richtext: основное содержание. НЕ дублируй другие блоки.
- info_cards: ФАКТОРЫ или АСПЕКТЫ темы. НЕ повтор richtext.
- faq: ТОЛЬКО вопросы, ответы на которые НЕ даны выше.
ПРАВИЛО RICHTEXT-МОСТИКОВ: каждый richtext между визуальными блоками — связующий абзац.
ФОРМАТЫ:
hero: ПЛОСКИЙ {title, subtitle, cta_text, cta_link_key}
key_takeaways: {title, items:[строки], style:"numbered"}
info_cards: {title, layout:"grid-3", items:[{icon, title, text, color}]}
richtext: {blocks:[{type:"heading"|"paragraph"|"list"|"highlight", ...}]}
faq: {items:[{question, answer}]}
cta: ПЛОСКИЙ {title, text, primary_btn_text, primary_btn_link_key}',
 'tpl-universal-info-visual');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES
(100, 'hero', 'Заголовок', JSON_OBJECT('hint', 'H1: заголовок статьи. subtitle: краткое описание. ПЛОСКИЙ JSON.', 'fields', JSON_ARRAY('title', 'subtitle', 'cta_text', 'cta_link_key')), 1, 1),
(100, 'key_takeaways', 'Главное за 30 секунд', JSON_OBJECT('hint', '5 ключевых фактов. style:"numbered".', 'fields', JSON_ARRAY('title', 'items', 'style')), 2, 1),
(100, 'richtext', 'Основное содержание', JSON_OBJECT('hint', 'Мин. 8 подблоков: H2 + параграфы + списки + highlight. Полное раскрытие темы.', 'fields', JSON_ARRAY('blocks'), 'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight', 'quote')), 3, 1),
(100, 'info_cards', 'Ключевые аспекты', JSON_OBJECT('hint', '6 карточек: аспекты/факторы темы. icon(emoji), title, text, color(hex).', 'fields', JSON_ARRAY('title', 'layout', 'items'), 'items_fields', JSON_ARRAY('icon', 'title', 'text', 'color')), 4, 1),
(100, 'faq', 'Частые вопросы', JSON_OBJECT('hint', '5-7 вопросов. Ответы 2-3 предложения с конкретикой. НЕ повторяй richtext.', 'fields', JSON_ARRAY('items')), 5, 1),
(100, 'cta', 'Призыв к действию', JSON_OBJECT('hint', 'ПЛОСКИЙ JSON. Мотивирующий текст + кнопка.', 'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')), 6, 1);


-- ── Template 101: Информационная — текстовая ────────────

INSERT INTO `seo_templates` (`id`, `profile_id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
(101, NULL, 'Информационная — текстовая',
 'universal-info-text',
 'Текстовая статья с аккордеоном и FAQ. Минимум визуальных элементов.',
 'Ты — профессиональный SEO-копирайтер. Генерируй JSON для ТЕКСТОВОЙ статьи.
ТОН: информативный, структурированный. Много текста, глубокое раскрытие темы.
richtext: основной контент, мин. 10 подблоков.
accordion: дополнительные детали, раскрывающиеся по клику.
faq: вопросы, не покрытые в richtext и accordion.',
 'tpl-universal-info-text');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES
(101, 'hero', 'Заголовок', JSON_OBJECT('hint', 'H1 + subtitle. ПЛОСКИЙ JSON.', 'fields', JSON_ARRAY('title', 'subtitle', 'cta_text', 'cta_link_key')), 1, 1),
(101, 'richtext', 'Введение и основа', JSON_OBJECT('hint', 'Мин. 10 подблоков. Полное раскрытие темы.', 'fields', JSON_ARRAY('blocks'), 'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight', 'quote')), 2, 1),
(101, 'accordion', 'Подробности', JSON_OBJECT('hint', '5-7 секций. title + content (2-4 предложения).', 'fields', JSON_ARRAY('items')), 3, 1),
(101, 'richtext', 'Дополнение', JSON_OBJECT('hint', '4-6 подблоков. Дополнительная информация, не дублирующая основной richtext.', 'fields', JSON_ARRAY('blocks'), 'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight')), 4, 1),
(101, 'faq', 'FAQ', JSON_OBJECT('hint', '5-7 вопросов. НЕ повторяй accordion и richtext.', 'fields', JSON_ARRAY('items')), 5, 1),
(101, 'cta', 'Призыв к действию', JSON_OBJECT('hint', 'ПЛОСКИЙ JSON. Мотивирующий текст + кнопка.', 'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')), 6, 1);


-- ── Template 102: Сравнительная ─────────────────────────

INSERT INTO `seo_templates` (`id`, `profile_id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
(102, NULL, 'Сравнительная',
 'universal-comparison',
 'Статья-сравнение двух+ вариантов. Таблица сравнения + карточки.',
 'Ты — профессиональный SEO-копирайтер. Генерируй JSON для СРАВНИТЕЛЬНОЙ статьи.
ТОН: аналитический, объективный. Структура A vs B.
richtext: вводный контекст, почему важно сравнение.
comparison_table: таблица ✓/✗ по критериям.
info_cards: ключевые различия визуально.
faq: вопросы о выборе между вариантами.',
 'tpl-universal-comparison');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES
(102, 'hero', 'Заголовок', JSON_OBJECT('hint', 'H1: "A vs B: что выбрать?" + subtitle. ПЛОСКИЙ JSON.', 'fields', JSON_ARRAY('title', 'subtitle', 'cta_text', 'cta_link_key')), 1, 1),
(102, 'richtext', 'Контекст сравнения', JSON_OBJECT('hint', '6-8 подблоков. Зачем сравниваем, критерии выбора.', 'fields', JSON_ARRAY('blocks'), 'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight')), 2, 1),
(102, 'comparison_table', 'Таблица сравнения', JSON_OBJECT('hint', 'headers[0] — критерий, остальные — варианты. rows — массив массивов. ✓/✗ для бинарных.', 'fields', JSON_ARRAY('title', 'headers', 'rows')), 3, 1),
(102, 'info_cards', 'Ключевые различия', JSON_OBJECT('hint', '4-6 карточек: по одному ключевому различию на карточку.', 'fields', JSON_ARRAY('title', 'layout', 'items'), 'items_fields', JSON_ARRAY('icon', 'title', 'text', 'color')), 4, 1),
(102, 'faq', 'FAQ', JSON_OBJECT('hint', '5-7 вопросов о выборе. "Когда лучше A?", "Когда лучше B?"', 'fields', JSON_ARRAY('items')), 5, 1),
(102, 'cta', 'Призыв к действию', JSON_OBJECT('hint', 'ПЛОСКИЙ JSON.', 'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')), 6, 1);


-- ── Template 103: Руководство / How-to ──────────────────

INSERT INTO `seo_templates` (`id`, `profile_id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
(103, NULL, 'Руководство / How-to',
 'universal-howto',
 'Пошаговое руководство с чек-листом подготовки.',
 'Ты — профессиональный SEO-копирайтер. Генерируй JSON для ПОШАГОВОГО РУКОВОДСТВА.
ТОН: практичный, ориентированный на действие.
key_takeaways: что получит читатель после выполнения.
numbered_steps: пошаговая инструкция (4-6 шагов).
prep_checklist: что подготовить перед началом.
richtext: дополнительные советы.',
 'tpl-universal-howto');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES
(103, 'hero', 'Заголовок', JSON_OBJECT('hint', 'H1: "Как сделать X: пошаговое руководство". ПЛОСКИЙ JSON.', 'fields', JSON_ARRAY('title', 'subtitle', 'cta_text', 'cta_link_key')), 1, 1),
(103, 'key_takeaways', 'Что вы получите', JSON_OBJECT('hint', '5 пунктов: результаты после выполнения руководства.', 'fields', JSON_ARRAY('title', 'items', 'style')), 2, 1),
(103, 'numbered_steps', 'Пошаговая инструкция', JSON_OBJECT('hint', '4-6 шагов. number, title, text, tip, duration.', 'fields', JSON_ARRAY('steps')), 3, 1),
(103, 'richtext', 'Дополнительные советы', JSON_OBJECT('hint', '6 подблоков. Советы, нюансы, распространённые ошибки.', 'fields', JSON_ARRAY('blocks'), 'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight')), 4, 1),
(103, 'prep_checklist', 'Чек-лист подготовки', JSON_OBJECT('hint', '2-3 секции, 2-4 items в каждой. Что подготовить перед началом.', 'fields', JSON_ARRAY('sections')), 5, 1),
(103, 'faq', 'FAQ', JSON_OBJECT('hint', '4-6 вопросов о процессе.', 'fields', JSON_ARRAY('items')), 6, 1),
(103, 'cta', 'Призыв к действию', JSON_OBJECT('hint', 'ПЛОСКИЙ JSON.', 'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')), 7, 1);


-- ── Template 104: Обзор продукта / услуги ───────────────

INSERT INTO `seo_templates` (`id`, `profile_id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
(104, NULL, 'Обзор продукта / услуги',
 'universal-product-review',
 'Обзор с метриками, преимуществами и отзывами.',
 'Ты — профессиональный SEO-копирайтер. Генерируй JSON для ОБЗОРНОЙ статьи о продукте/услуге.
ТОН: объективный, с конкретикой.
stats_counter: ключевые метрики (цена, рейтинг, пользователи).
richtext: подробное описание.
feature_grid: преимущества/особенности.
testimonial: отзывы пользователей.',
 'tpl-universal-product');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES
(104, 'hero', 'Заголовок', JSON_OBJECT('hint', 'H1: название продукта/услуги + ключевое преимущество. ПЛОСКИЙ JSON.', 'fields', JSON_ARRAY('title', 'subtitle', 'cta_text', 'cta_link_key')), 1, 1),
(104, 'stats_counter', 'Ключевые метрики', JSON_OBJECT('hint', '3-4 метрики: числа, единицы, описания. ПЛОСКИЙ JSON.', 'fields', JSON_ARRAY('items')), 2, 1),
(104, 'richtext', 'Описание', JSON_OBJECT('hint', '8 подблоков. Что это, для кого, как работает.', 'fields', JSON_ARRAY('blocks'), 'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight', 'quote')), 3, 1),
(104, 'feature_grid', 'Преимущества', JSON_OBJECT('hint', '6 карточек: icon(emoji), title, description. Ключевые особенности.', 'fields', JSON_ARRAY('items')), 4, 1),
(104, 'testimonial', 'Отзывы', JSON_OBJECT('hint', '3-4 отзыва: name, role, text, rating(1-5).', 'fields', JSON_ARRAY('items')), 5, 1),
(104, 'cta', 'Призыв к действию', JSON_OBJECT('hint', 'ПЛОСКИЙ JSON. Призыв попробовать/купить.', 'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')), 6, 1);
