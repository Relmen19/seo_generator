-- ═══════════════════════════════════════════════════════
-- Migration 028: Heatmap schema & hint redesign
-- Adds `unit` field, richer example (7×6), improved hint
-- ═══════════════════════════════════════════════════════

SET NAMES utf8mb4;

UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'title',       JSON_OBJECT('type', 'string'),
      'rows',        JSON_OBJECT('type', 'array of strings', 'required', true,  'note', '5-8 строк, до 14 симв каждая'),
      'columns',     JSON_OBJECT('type', 'array of strings', 'required', true,  'note', '5-10 колонок, до 8 симв каждая'),
      'data',        JSON_OBJECT('type', 'array of arrays (numbers)', 'required', true, 'note', 'матрица rows×columns — реальные числа в единицах unit'),
      'unit',        JSON_OBJECT('type', 'string', 'note', 'опц.: %, ч, руб, шт — отображается рядом с числом'),
      'description', JSON_OBJECT('type', 'string', 'note', 'опц. подпись под таблицей')
    ),
    'example', CAST(
      '{"title":"Активность по дням и часам","rows":["Пн","Вт","Ср","Чт","Пт","Сб","Вс"],"columns":["00-04","04-08","08-12","12-16","16-20","20-24"],"data":[[2,4,42,35,18,6],[3,5,55,48,22,9],[4,6,60,52,28,11],[3,5,58,50,26,10],[5,8,50,44,30,14],[10,12,18,22,35,28],[8,10,12,16,32,22]],"unit":"%","description":"Доля активных пользователей от суточного трафика"}'
      AS JSON)
  ),
  gpt_hint = 'title — по теме статьи. rows: 5-8 строк (до 14 симв). columns: 5-10 колонок (до 8 симв). data: матрица rows×columns с реальными числами в единицах unit. unit: символ единицы (%, ч, руб и т.д.). description: опц. подпись. ВАЖНО: числа должны быть репрезентабельными — неравномерное распределение с явными пиками и провалами, а не случайные значения. НЕ оборачивай в {"heatmap":{...}}.'
WHERE code = 'heatmap';
