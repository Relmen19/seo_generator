-- ═══════════════════════════════════════════════════════
-- Migration 024: Strengthen GPT hints — prevent wrapping,
-- require titles, clarify short labels for circular blocks.
-- ═══════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- score_rings: total_label — ВСЕГДА короткий, полное название — в title
UPDATE `seo_block_types` SET
  gpt_hint = 'title ОБЯЗАТЕЛЕН (напр. "Оценка продукта"). total_label — КОРОТКИЙ (≤20 симв): "общий балл", "индекс", "средний". rings: [{name, subtitle, value(0-100), max(100), color(hex), description}]. НЕ дублируй title в total_label!'
WHERE code = 'score_rings';

-- criteria_checklist: title обязателен, иначе fallback покажет "Чек-лист"
UPDATE `seo_block_types` SET
  gpt_hint = 'title ОБЯЗАТЕЛЕН и по теме статьи. subtitle — краткое пояснение. items: [{text, weight(1-3), group}]. thresholds: [{min, max, label, color(hex), text}]. Мин. 6 items, 3 thresholds! НЕ оборачивай в {"criteria_checklist":{...}}.'
WHERE code = 'criteria_checklist';

-- symptom_checklist: title обязателен
UPDATE `seo_block_types` SET
  gpt_hint = COALESCE(
    NULLIF(CONCAT('title ОБЯЗАТЕЛЕН и по теме статьи. ', COALESCE(gpt_hint, '')), 'title ОБЯЗАТЕЛЕН и по теме статьи. '),
    'title ОБЯЗАТЕЛЕН. items: [{text, weight(1-3), group}]. thresholds: [{min, max, label, color(hex), text}].'
  )
WHERE code = 'symptom_checklist';

-- chart: пояснить horizontalBar и запретить обёртки
UPDATE `seo_block_types` SET
  gpt_hint = 'chart_type: bar|horizontalBar|doughnut|pie|line. horizontalBar — для сравнения множества объектов по одной метрике. doughnut/pie: ОБЯЗАТЕЛЬНО description + datasets[0].descriptions. labels и datasets[].data — одинаковой длины! НЕ оборачивай в {"chart":{...}}.'
WHERE code = 'chart';

-- comparison_table: явный запрет обёртки и ограничение длины заголовков колонок
UPDATE `seo_block_types` SET
  gpt_hint = 'title ОБЯЗАТЕЛЕН. headers[0] — название критерия (≤25 симв), остальные — варианты (≤20 симв каждый, 2-5 колонок). rows — массив массивов, длина каждой строки = длине headers. Бинарные значения: ✓/✗. НЕ оборачивай в {"comparison_table":{...}} — поля сразу в block_N!'
WHERE code = 'comparison_table';

-- Глобально: всем блокам, у которых есть поле title в схеме, добавим общее примечание через префикс (только там где уже есть hint)
UPDATE `seo_block_types`
SET gpt_hint = CONCAT('title — по теме статьи, не пропускай. ', gpt_hint)
WHERE code IN (
  'hero','feature_grid','info_cards','key_takeaways','story_block','expert_panel',
  'numbered_steps','verdict_card','warning_block','comparison_cards','progress_tracker',
  'stats_counter','range_table','gauge_chart','timeline','heatmap','funnel','spark_metrics',
  'radar_chart','before_after','stacked_area','range_comparison','value_checker',
  'prep_checklist','mini_calculator'
)
  AND gpt_hint IS NOT NULL
  AND gpt_hint NOT LIKE 'title — по теме статьи%'
  AND gpt_hint NOT LIKE 'title ОБЯЗАТЕЛЕН%';
