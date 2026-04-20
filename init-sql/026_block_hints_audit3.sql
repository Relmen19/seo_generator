-- ═══════════════════════════════════════════════════════
-- Migration 026: Hint refinement for batch 3 blocks
-- (testimonial, progress_tracker, expert_panel)
-- ═══════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- testimonial: теперь поддерживает title (рендерится h2)
UPDATE `seo_block_types` SET
  gpt_hint = 'title — опциональный заголовок секции отзывов (по теме, напр. «Что говорят клиенты»). items: [{text, author, role(опц.)}]. 2-6 items. text — цитата в 1-3 предложения. НЕ оборачивай в {"testimonial":{...}}.'
WHERE code = 'testimonial';

-- progress_tracker: mac_title теперь конфигурируется
UPDATE `seo_block_types` SET
  gpt_hint = 'title — заголовок секции (по теме). mac_title — опциональный (окно «Прогресс»). timeline_unit — ед. времени («месяц», «неделя»). milestones: [{marker(0-100 % позиция), period, text, metric(опц.)}]. 3-6 вех. period — короткий (до 20 симв.), text — 1-2 предложения. НЕ оборачивай в {"progress_tracker":{...}}.'
WHERE code = 'progress_tracker';

-- expert_panel: name опционален, но рекомендуется
UPDATE `seo_block_types` SET
  gpt_hint = 'name — имя эксперта (опц., но желательно). credentials — должность/регалии. experience — опыт (опц.). photo_placeholder — 1-2 инициала. text — цитата эксперта 2-4 предложения. highlight — ключевая фраза (опц.). НЕ оборачивай в {"expert_panel":{...}}.'
WHERE code = 'expert_panel';
