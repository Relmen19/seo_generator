-- ═══════════════════════════════════════════════════════
-- Migration 027: Hint refinement for batch 4 (final)
-- heatmap, funnel, spark_metrics, radar_chart, before_after,
-- stacked_area, range_comparison, value_checker,
-- prep_checklist, mini_calculator
-- ═══════════════════════════════════════════════════════

SET NAMES utf8mb4;

UPDATE `seo_block_types` SET
  gpt_hint = 'title — заголовок блока (по теме, до 40 симв. — рендерится в окне и TOC). rows: [строки (до 20 симв.)]. columns: [колонки (до 10 симв.)]. data: матрица rows×columns с числами 0-100. description — опц. НЕ оборачивай в {"heatmap":{...}}.'
WHERE code = 'heatmap';

UPDATE `seo_block_types` SET
  gpt_hint = 'title — заголовок воронки (по теме). description — опц. подзаголовок. items: [{label, value(число), description(опц.), color(опц. CSS)}]. Первый item — максимум; value должны убывать. 3-7 шагов. label до 22 симв. НЕ оборачивай в {"funnel":{...}}.'
WHERE code = 'funnel';

UPDATE `seo_block_types` SET
  gpt_hint = 'title — заголовок блока метрик (по теме). items: [{name, value, unit(опц.), trend(напр. "+12%"), trend_up(bool), icon(emoji), icon_bg(CSS цвет), points: [числа 5-8 шт для спарклайна], details: [[label,value],...]}]. 3-8 карточек. НЕ оборачивай в {"spark_metrics":{...}}.'
WHERE code = 'spark_metrics';

UPDATE `seo_block_types` SET
  gpt_hint = 'title — заголовок радара (по теме, до 40 симв.). axes: [{name(короткое, до 14 симв.!), value(0-100), description}]. 4-7 осей. color — опц. hex. Длинные названия осей будут обрезаны — выбирай краткие. НЕ оборачивай в {"radar_chart":{...}}.'
WHERE code = 'radar_chart';

UPDATE `seo_block_types` SET
  gpt_hint = 'title — заголовок (по теме). metrics: [{name, before(число), after(число), max(число ≥ before и after), unit(опц.)}]. max — обязателен для масштаба шкал. 2-5 метрик. НЕ оборачивай в {"before_after":{...}}.'
WHERE code = 'before_after';

UPDATE `seo_block_types` SET
  gpt_hint = 'title — заголовок динамики (по теме). labels: [метки X-оси, 4-12 шт]. series: [{name, color(hex), data: [числа для каждой label], description}]. 2-5 серий. data.length == labels.length. НЕ оборачивай в {"stacked_area":{...}}.'
WHERE code = 'stacked_area';

UPDATE `seo_block_types` SET
  gpt_hint = 'title — заголовок (по теме, напр. «Референсные диапазоны»). groups: [{key(короткий, напр. «Мужчины»), tag(2-3 симв.)}]. rows: [{name, unit, min, max, ranges: [[lo,hi],...] — для каждой группы, values: [val,...] — для каждой группы, description}]. ranges/values.length == groups.length. НЕ оборачивай в {"range_comparison":{...}}.'
WHERE code = 'range_comparison';

UPDATE `seo_block_types` SET
  gpt_hint = 'title — заголовок интерактивной проверки (по теме). description — опц. подзаголовок. input_label — метка поля. input_placeholder, disclaimer — опц. zones: [{from, to, color(hex), icon(emoji), label, text}]. Zones по возрастанию from/to, без разрывов. 3-5 зон. НЕ оборачивай в {"value_checker":{...}}.'
WHERE code = 'value_checker';

UPDATE `seo_block_types` SET
  gpt_hint = 'title — заголовок (по теме). subtitle — опц. mac_title — опц. (окно «Чек-лист»). sections: [{icon(emoji), name, items: [{text, important(bool)}]}]. 2-5 секций, 3-8 items в каждой. НЕ оборачивай в {"prep_checklist":{...}}.'
WHERE code = 'prep_checklist';

UPDATE `seo_block_types` SET
  gpt_hint = 'title — заголовок калькулятора (по теме). description — опц. mac_title — опц. (окно «Расчёт»). inputs: [{key, label, type("number"|"select"), unit(опц.), placeholder(опц.), min/max(опц.), options(для select): [{value,label}], show_if(опц.): {key,value}}]. results: [{condition("key=val && key2=val2"), value, text}]. formula_description, disclaimer — опц. НЕ оборачивай в {"mini_calculator":{...}}.'
WHERE code = 'mini_calculator';
