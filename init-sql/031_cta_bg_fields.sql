-- 031_cta_bg_fields.sql
-- Add background customization fields to CTA block schema: bg_mode / bg_color / bg_color_2.
-- bg_mode: 'gradient' | 'solid' | 'transparent' (default: empty = use built-in gradient).
-- bg_color / bg_color_2: hex colors (#rgb / #rrggbb). For brand colors pick from site profile palette.

UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'title',                  JSON_OBJECT('type', 'string'),
      'text',                   JSON_OBJECT('type', 'string', 'required', true),
      'primary_btn_text',       JSON_OBJECT('type', 'string', 'required', true),
      'primary_btn_link_key',   JSON_OBJECT('type', 'string'),
      'secondary_btn_text',     JSON_OBJECT('type', 'string'),
      'secondary_btn_link_key', JSON_OBJECT('type', 'string'),
      'bg_mode',                JSON_OBJECT('type', 'string', 'enum', JSON_ARRAY('gradient', 'solid', 'transparent'), 'note', 'Фон CTA: градиент (по умолчанию), одноцветный или прозрачный'),
      'bg_color',               JSON_OBJECT('type', 'hex', 'note', 'Основной цвет фона (для solid и gradient)'),
      'bg_color_2',             JSON_OBJECT('type', 'hex', 'note', 'Второй цвет для gradient (если пусто — один тон)')
    ),
    'example', CAST('{"title":"Готовы начать?","text":"Запишитесь на консультацию","primary_btn_text":"Записаться","primary_btn_link_key":"cta_main","secondary_btn_text":"Подробнее","secondary_btn_link_key":"cta_info","bg_mode":"gradient","bg_color":"#2563eb","bg_color_2":"#0d9488"}' AS JSON)
  ),
  gpt_hint = 'ПЛОСКИЙ JSON: {title, text, primary_btn_text, primary_btn_link_key, secondary_btn_text, secondary_btn_link_key, bg_mode?, bg_color?, bg_color_2?}. bg_mode: gradient|solid|transparent. bg_color/bg_color_2 — hex. Без вложенных объектов!'
WHERE code = 'cta';
