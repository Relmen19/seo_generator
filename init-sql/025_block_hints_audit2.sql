-- ═══════════════════════════════════════════════════════
-- Migration 025: Hint refinement for blocks that gained
-- title/section-heading support in renderers (stats_counter,
-- accordion, faq, feature_grid, timeline).
-- ═══════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- stats_counter: теперь поддерживает title + sec-title
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'title', JSON_OBJECT('type', 'string'),
      'items', JSON_OBJECT(
        'type', 'array', 'required', true,
        'items', JSON_OBJECT('value', 'string|number', 'suffix', 'string', 'label', 'string')
      )
    ),
    'example', CAST('{"title":"Ключевые показатели","items":[{"value":"95","suffix":"%","label":"Точность"},{"value":"24","suffix":"ч","label":"Срок"},{"value":"4.8","suffix":"/5","label":"Рейтинг"}]}' AS JSON)
  ),
  gpt_hint = 'title — короткий заголовок блока (по теме). items: [{value(число/строка), suffix(%/ч/шт...), label}]. 3-6 items. НЕ оборачивай в {"stats_counter":{...}}.'
WHERE code = 'stats_counter';

-- accordion: поддерживается title
UPDATE `seo_block_types` SET
  gpt_hint = 'title — опциональный заголовок секции (по теме). items: [{title, content}]. content — строка (не массив). Мин. 4 items. НЕ оборачивай в {"accordion":{...}}.'
WHERE code = 'accordion';

-- faq: title переопределяет дефолт «Часто задаваемые вопросы»
UPDATE `seo_block_types` SET
  gpt_hint = 'title — опционально (по умолчанию «Часто задаваемые вопросы»). items: [{question, answer}]. answer — строка, не массив! Мин. 5 items. НЕ оборачивай в {"faq":{...}}.'
WHERE code = 'faq';

-- feature_grid: теперь рендерит h2 если title есть
UPDATE `seo_block_types` SET
  gpt_hint = 'title — заголовок секции (по теме). items: [{icon(emoji), title, description}]. 3-6 items. icon — ОДИН emoji. НЕ оборачивай в {"feature_grid":{...}}.'
WHERE code = 'feature_grid';

-- timeline: mac_title опционально, step — строка/число
UPDATE `seo_block_types` SET
  gpt_hint = 'title — ОБЯЗАТЕЛЕН (по теме статьи). mac_title — опциональный (окно «Процесс»). items: [{step(1,2,3...), title, summary, detail, meta, color(hex)}]. detail — 2-3 предложения, meta — длительность. НЕ оборачивай в {"timeline":{...}}.'
WHERE code = 'timeline';
