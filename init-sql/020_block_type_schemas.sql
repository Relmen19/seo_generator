-- ═══════════════════════════════════════════════════════
-- Migration 020: Populate json_schema and gpt_hint for all block types
-- Adds strict JSON structure definitions based on HtmlRendererService
-- ═══════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ── Layout blocks ─────────────────────────────────────

-- hero: flat object
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'title',        JSON_OBJECT('type', 'string', 'required', true),
      'subtitle',     JSON_OBJECT('type', 'string', 'required', true),
      'cta_text',     JSON_OBJECT('type', 'string'),
      'cta_link_key', JSON_OBJECT('type', 'string')
    ),
    'example', CAST('{"title":"Заголовок страницы","subtitle":"Подзаголовок с ключевым сообщением","cta_text":"Узнать больше","cta_link_key":"main_cta"}' AS JSON)
  ),
  gpt_hint = 'ПЛОСКИЙ JSON: {title, subtitle, cta_text, cta_link_key}. Без вложенных объектов и массивов!'
WHERE code = 'hero';

-- cta: flat object
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'title',                  JSON_OBJECT('type', 'string'),
      'text',                   JSON_OBJECT('type', 'string', 'required', true),
      'primary_btn_text',       JSON_OBJECT('type', 'string', 'required', true),
      'primary_btn_link_key',   JSON_OBJECT('type', 'string'),
      'secondary_btn_text',     JSON_OBJECT('type', 'string'),
      'secondary_btn_link_key', JSON_OBJECT('type', 'string')
    ),
    'example', CAST('{"title":"Готовы начать?","text":"Запишитесь на консультацию","primary_btn_text":"Записаться","primary_btn_link_key":"cta_main","secondary_btn_text":"Подробнее","secondary_btn_link_key":"cta_info"}' AS JSON)
  ),
  gpt_hint = 'ПЛОСКИЙ JSON: {title, text, primary_btn_text, primary_btn_link_key, secondary_btn_text, secondary_btn_link_key}. Без вложенных объектов!'
WHERE code = 'cta';

-- image_section: flat object
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'title',         JSON_OBJECT('type', 'string', 'required', true),
      'text',          JSON_OBJECT('type', 'string', 'required', true),
      'image_id',      JSON_OBJECT('type', 'integer'),
      'image_alt',     JSON_OBJECT('type', 'string'),
      'image_caption', JSON_OBJECT('type', 'string'),
      'layout',        JSON_OBJECT('type', 'string', 'enum', JSON_ARRAY('image-left', 'image-right'))
    ),
    'example', CAST('{"title":"Наш подход","text":"Описание методики...","image_id":null,"image_alt":"","image_caption":"","layout":"image-right"}' AS JSON)
  ),
  gpt_hint = 'ПЛОСКИЙ JSON: {title, text, layout("image-left"|"image-right")}. text — только текст, без HTML!'
WHERE code = 'image_section';

-- ── Content blocks ────────────────────────────────────

-- richtext: blocks array
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'blocks', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'type', 'string:paragraph|heading|list|highlight|quote',
          'text', 'string (для paragraph/heading/highlight/quote)',
          'level', 'integer:2-6 (только для heading)',
          'items', 'array of strings (только для list)'
        )
      )
    ),
    'example', CAST('{"blocks":[{"type":"heading","text":"Заголовок раздела","level":2},{"type":"paragraph","text":"Подробный текст параграфа..."},{"type":"list","items":["Первый пункт","Второй пункт","Третий пункт"]},{"type":"highlight","text":"Важная мысль"},{"type":"paragraph","text":"Ещё один параграф..."},{"type":"quote","text":"Цитата эксперта"}]}' AS JSON)
  ),
  gpt_hint = 'Формат: {"blocks":[{"type":"...","text":"..."},...]}.  type: paragraph|heading|list|highlight|quote. heading→text+level(2/3). list→items(массив строк). Остальные→text. НЕ вкладывай в content! Мин. 6 подблоков, чередуй типы.'
WHERE code = 'richtext';

-- accordion: items array
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'items', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'title', 'string',
          'content', 'string'
        )
      )
    ),
    'example', CAST('{"items":[{"title":"Заголовок секции","content":"Развёрнутый текст, 2-4 предложения с конкретикой и фактами."}]}' AS JSON)
  ),
  gpt_hint = 'items: [{title, content}]. content — развёрнутый текст, 2-4 предложения! Мин. 4 секции.'
WHERE code = 'accordion';

-- faq: items array
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'items', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'question', 'string',
          'answer', 'string'
        )
      )
    ),
    'example', CAST('{"items":[{"question":"Как это работает?","answer":"Подробный ответ в 2-3 предложениях с конкретикой."}]}' AS JSON)
  ),
  gpt_hint = 'items: [{question, answer}]. Ответы — 2-3 предложения с конкретикой! Мин. 4 вопроса.'
WHERE code = 'faq';

-- feature_grid: items array
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'items', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'icon', 'string (emoji)',
          'title', 'string',
          'description', 'string'
        )
      )
    ),
    'example', CAST('{"items":[{"icon":"🔬","title":"Точная диагностика","description":"Описание возможности в 1-2 предложениях."}]}' AS JSON)
  ),
  gpt_hint = 'items: [{icon(emoji), title, description}]. Мин. 4 карточки! Поле именно description, не text.'
WHERE code = 'feature_grid';

-- info_cards: items array + layout
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'layout', JSON_OBJECT('type', 'string', 'enum', JSON_ARRAY('grid-2', 'grid-3')),
      'items', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'icon', 'string (emoji)',
          'title', 'string',
          'text', 'string',
          'color', 'string (hex, например #3B82F6)'
        )
      )
    ),
    'example', CAST('{"layout":"grid-3","items":[{"icon":"💡","title":"Важный факт","text":"Краткое описание карточки.","color":"#3B82F6"}]}' AS JSON)
  ),
  gpt_hint = 'items: [{icon(emoji), title, text, color(hex)}]. layout: "grid-2"|"grid-3". Мин. 4 карточки!'
WHERE code = 'info_cards';

-- key_takeaways: items as strings
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'title', JSON_OBJECT('type', 'string'),
      'style', JSON_OBJECT('type', 'string', 'enum', JSON_ARRAY('numbered', 'bullets', 'cards')),
      'items', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', 'string'
      )
    ),
    'example', CAST('{"title":"Главное","items":["Первый ключевой вывод","Второй ключевой вывод","Третий ключевой вывод"],"style":"numbered"}' AS JSON)
  ),
  gpt_hint = '{title, items:[строки], style:"numbered"|"bullets"|"cards"}. items — простые строки, НЕ объекты! Мин. 3 пункта.'
WHERE code = 'key_takeaways';

-- story_block: flat object
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'variant',      JSON_OBJECT('type', 'string', 'enum', JSON_ARRAY('patient_story', 'expert_quote', 'key_fact')),
      'icon',         JSON_OBJECT('type', 'string (emoji)'),
      'accent_color', JSON_OBJECT('type', 'string (hex)'),
      'lead',         JSON_OBJECT('type', 'string', 'required', true),
      'text',         JSON_OBJECT('type', 'string', 'required', true),
      'highlight',    JSON_OBJECT('type', 'string'),
      'footnote',     JSON_OBJECT('type', 'string')
    ),
    'example', CAST('{"variant":"patient_story","icon":"📖","accent_color":"#10B981","lead":"Вводная фраза","text":"Основной текст истории в 3-5 предложений.","highlight":"Ключевая цитата или вывод","footnote":"Примечание"}' AS JSON)
  ),
  gpt_hint = '{variant:"patient_story"|"expert_quote"|"key_fact", icon(emoji), accent_color(hex), lead, text(3-5 предл.), highlight, footnote}. ПЛОСКИЙ JSON!'
WHERE code = 'story_block';

-- testimonial: items array
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'items', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'name', 'string',
          'role', 'string',
          'text', 'string',
          'rating', 'integer (1-5)'
        )
      )
    ),
    'example', CAST('{"items":[{"name":"Анна К.","role":"пациент","text":"Отличный результат, рекомендую!","rating":5},{"name":"Сергей М.","role":"клиент","text":"Профессиональный подход.","rating":4}]}' AS JSON)
  ),
  gpt_hint = 'items: [{name, role, text, rating(1-5)}]. Мин. 3 отзыва. Поле name, НЕ author!'
WHERE code = 'testimonial';

-- expert_panel: flat object
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'name',              JSON_OBJECT('type', 'string', 'required', true),
      'credentials',       JSON_OBJECT('type', 'string', 'required', true),
      'experience',        JSON_OBJECT('type', 'string'),
      'photo_placeholder', JSON_OBJECT('type', 'string'),
      'text',              JSON_OBJECT('type', 'string', 'required', true),
      'highlight',         JSON_OBJECT('type', 'string')
    ),
    'example', CAST('{"name":"Д-р Иванов А.С.","credentials":"к.м.н., стаж 15 лет","experience":"кардиология, терапия","photo_placeholder":"ИА","text":"Экспертное мнение в 3-5 предложениях.","highlight":"Ключевая фраза эксперта"}' AS JSON)
  ),
  gpt_hint = 'ПЛОСКИЙ JSON: {name, credentials, experience, photo_placeholder(инициалы), text(3-5 предл.), highlight(ключевая фраза)}. Без массивов!'
WHERE code = 'expert_panel';

-- numbered_steps: steps array
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'steps', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'number', 'integer',
          'title', 'string',
          'text', 'string',
          'tip', 'string',
          'duration', 'string'
        )
      )
    ),
    'example', CAST('{"steps":[{"number":1,"title":"Подготовка","text":"Описание шага в 2-3 предложения.","tip":"Полезный совет","duration":"5 мин"}]}' AS JSON)
  ),
  gpt_hint = 'steps: [{number(int), title, text, tip, duration}]. 4-5 шагов. Поле steps, НЕ items!'
WHERE code = 'numbered_steps';

-- verdict_card: items array
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'items', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'claim', 'string',
          'verdict', 'string: myth|truth|partial',
          'explanation', 'string',
          'source', 'string'
        )
      )
    ),
    'example', CAST('{"items":[{"claim":"Утверждение","verdict":"myth","explanation":"Подробное объяснение почему это миф.","source":"Источник"}]}' AS JSON)
  ),
  gpt_hint = 'items: [{claim, verdict:"myth"|"truth"|"partial", explanation, source}]. Мин. 3 карточки, баланс вердиктов!'
WHERE code = 'verdict_card';

-- warning_block: items array + flat fields
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'variant',  JSON_OBJECT('type', 'string', 'required', true, 'enum', JSON_ARRAY('red_flags', 'caution', 'good_signs')),
      'title',    JSON_OBJECT('type', 'string', 'required', true),
      'subtitle', JSON_OBJECT('type', 'string'),
      'items', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'text', 'string',
          'severity', 'string: urgent|emergency|warning'
        )
      ),
      'footer', JSON_OBJECT('type', 'string')
    ),
    'example', CAST('{"variant":"red_flags","title":"Когда обратиться к врачу","subtitle":"Тревожные сигналы","items":[{"text":"Описание симптома","severity":"urgent"}],"footer":"При любых сомнениях обратитесь к специалисту"}' AS JSON)
  ),
  gpt_hint = '{variant:"red_flags"|"caution"|"good_signs", title, subtitle, items:[{text, severity:"urgent"|"emergency"|"warning"}], footer}!'
WHERE code = 'warning_block';

-- comparison_cards: one or many card pairs (accordion when multiple)
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'title', JSON_OBJECT('type', 'string', 'required', true),
      'comparisons', JSON_OBJECT(
        'type', 'array',
        'note', 'Предпочтительный формат. Если > 1 — рендерится как аккордеон с label/description.',
        'items', JSON_OBJECT(
          'label', 'string (заголовок аккордеона)',
          'description', 'string (краткое описание кейса)',
          'card_a', 'object {name, badge, color, pros[], cons[], price, verdict}',
          'card_b', 'object {name, badge, color, pros[], cons[], price, verdict}'
        )
      ),
      'card_a', JSON_OBJECT('type', 'object', 'note', 'Legacy: используется только если comparisons отсутствует'),
      'card_b', JSON_OBJECT('type', 'object', 'note', 'Legacy: используется только если comparisons отсутствует')
    ),
    'example', CAST('{"title":"Сравнение портфелей","comparisons":[{"label":"Портфель Акций","description":"Для долгосрочных инвесторов","card_a":{"name":"Aurum Vector","badge":"Популярный","color":"#FFD700","pros":["Низкие комиссии"],"cons":["Рыночный риск"],"price":"0.5%","verdict":"Лучший для диверсификации"},"card_b":{"name":"Конкурент","badge":"Выбор","color":"#0000FF","pros":["Большой выбор"],"cons":["Дорого"],"price":"1%","verdict":"Удобно, но дорого"}}]}' AS JSON)
  ),
  gpt_hint = '{title, comparisons:[{label, description, card_a:{name, badge, color(hex), pros:[], cons:[], price, verdict}, card_b:{...}}]}. Если одно сравнение — один элемент в массиве. pros/cons — массивы строк!'
WHERE code = 'comparison_cards';

-- progress_tracker: milestones array
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'title',         JSON_OBJECT('type', 'string', 'required', true),
      'timeline_unit', JSON_OBJECT('type', 'string'),
      'note',          JSON_OBJECT('type', 'string'),
      'milestones', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'period', 'string',
          'marker', 'integer (0-100)',
          'text', 'string',
          'metric', 'string'
        )
      )
    ),
    'example', CAST('{"title":"Ожидаемый прогресс","timeline_unit":"неделя","note":"Индивидуальные результаты могут отличаться","milestones":[{"period":"Неделя 1","marker":15,"text":"Описание этапа","metric":"Первые улучшения"}]}' AS JSON)
  ),
  gpt_hint = 'milestones: [{period, marker(0-100), text, metric}]. marker — позиция на шкале (%). note обязателен! timeline_unit — единица времени.'
WHERE code = 'progress_tracker';

-- ── Data visualization blocks ─────────────────────────

-- stats_counter: items array
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'items', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'value', 'string|number',
          'suffix', 'string (например %, K+, млн)',
          'label', 'string'
        )
      )
    ),
    'example', CAST('{"items":[{"value":"95","suffix":"%","label":"Успешных случаев"},{"value":"10","suffix":"K+","label":"Довольных клиентов"},{"value":"15","suffix":"лет","label":"Опыта работы"}]}' AS JSON)
  ),
  gpt_hint = 'items: [{value, suffix, label}]. Мин. 3 метрики. value — число или строка. Сразу массив items на верхнем уровне!'
WHERE code = 'stats_counter';

-- range_table: rows with states
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'caption', JSON_OBJECT('type', 'string'),
      'rows', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'name', 'string',
          'unit', 'string',
          'active', 'integer (индекс активного state, 0-based)',
          'states', 'array of {key, label, range, pct, description}'
        )
      )
    ),
    'example', CAST('{"caption":"Показатели и нормы","rows":[{"name":"Показатель","unit":"ед.","active":2,"states":[{"key":"low","label":"Низкий","range":"< 50","pct":20,"description":"Ниже нормы"},{"key":"normal","label":"Норма","range":"50-100","pct":50,"description":"Оптимальный диапазон"},{"key":"high","label":"Высокий","range":"> 100","pct":30,"description":"Выше нормы"}]}]}' AS JSON)
  ),
  gpt_hint = 'rows[].states — ОБЯЗАТЕЛЕН (массив {key, label, range, pct, description})! active — индекс текущего state. pct — процент (сумма = 100).'
WHERE code = 'range_table';

-- chart: datasets array
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'title',      JSON_OBJECT('type', 'string'),
      'chart_type', JSON_OBJECT('type', 'string', 'enum', JSON_ARRAY('bar', 'horizontalBar', 'doughnut', 'pie', 'line')),
      'labels',     JSON_OBJECT('type', 'array of strings', 'required', true),
      'datasets', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'data', 'array of numbers',
          'backgroundColor', 'array of hex colors',
          'descriptions', 'array of strings'
        )
      ),
      'description', JSON_OBJECT('type', 'string')
    ),
    'example', CAST('{"title":"Статистика","chart_type":"doughnut","labels":["Категория A","Категория B","Категория C"],"datasets":[{"data":[45,30,25],"backgroundColor":["#3B82F6","#10B981","#F59E0B"],"descriptions":["Описание A","Описание B","Описание C"]}],"description":"Общее описание графика"}' AS JSON)
  ),
  gpt_hint = 'doughnut/pie: ОБЯЗАТЕЛЬНО description + datasets[0].descriptions (массив строк)! chart_type: bar|horizontalBar|doughnut|pie|line. labels и datasets[].data — одинаковой длины!'
WHERE code = 'chart';

-- gauge_chart: items array
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'title', JSON_OBJECT('type', 'string'),
      'items', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'name', 'string',
          'value', 'number',
          'min', 'number',
          'max', 'number',
          'unit', 'string',
          'color', 'string (hex)',
          'description', 'string'
        )
      )
    ),
    'example', CAST('{"title":"Показатели","items":[{"name":"Метрика","value":75,"min":0,"max":100,"unit":"%","color":"#3B82F6","description":"Описание показателя"}]}' AS JSON)
  ),
  gpt_hint = 'items: [{name, value(число!), min, max, unit, color(hex), description}]. value — число, НЕ строка!'
WHERE code = 'gauge_chart';

-- timeline: items array
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'title', JSON_OBJECT('type', 'string'),
      'items', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'step', 'string|integer',
          'title', 'string',
          'summary', 'string',
          'detail', 'string',
          'meta', 'string',
          'color', 'string (hex, опционально)'
        )
      )
    ),
    'example', CAST('{"title":"Этапы процесса","items":[{"step":1,"title":"Подготовка","summary":"Краткое описание","detail":"Развёрнутое описание в 2-3 предложениях.","meta":"1-2 дня"}]}' AS JSON)
  ),
  gpt_hint = 'items: [{step, title, summary, detail, meta}]. detail — 2-3 предложения! meta — длительность.'
WHERE code = 'timeline';

-- heatmap: 2D data grid
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'title',       JSON_OBJECT('type', 'string'),
      'rows',        JSON_OBJECT('type', 'array of strings', 'required', true),
      'columns',     JSON_OBJECT('type', 'array of strings', 'required', true),
      'data',        JSON_OBJECT('type', 'array of arrays (numbers)', 'required', true),
      'description', JSON_OBJECT('type', 'string')
    ),
    'example', CAST('{"title":"Карта активности","rows":["Пн","Вт","Ср"],"columns":["Утро","День","Вечер"],"data":[[3,7,5],[8,4,6],[2,9,3]],"description":"Описание данных"}' AS JSON)
  ),
  gpt_hint = 'data — двумерный массив чисел [rows × columns]. rows и columns — массивы строк-заголовков! Размеры должны совпадать.'
WHERE code = 'heatmap';

-- funnel: items array (descending values)
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'title',       JSON_OBJECT('type', 'string'),
      'description', JSON_OBJECT('type', 'string'),
      'items', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'name', 'string',
          'value', 'number',
          'description', 'string',
          'color', 'string (hex)'
        )
      )
    ),
    'example', CAST('{"title":"Воронка","description":"Описание процесса","items":[{"name":"Этап 1","value":1000,"description":"Начальный этап","color":"#3B82F6"},{"name":"Этап 2","value":600,"description":"Промежуточный","color":"#10B981"},{"name":"Этап 3","value":200,"description":"Финальный","color":"#F59E0B"}]}' AS JSON)
  ),
  gpt_hint = 'items: [{name, value(число), description, color(hex)}]. value — числа, УБЫВАЮЩИЕ сверху вниз!'
WHERE code = 'funnel';

-- spark_metrics: items array
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'title', JSON_OBJECT('type', 'string'),
      'items', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'icon', 'string (emoji)',
          'name', 'string',
          'value', 'string',
          'unit', 'string',
          'trend', 'string',
          'trend_up', 'boolean',
          'points', 'array of 8-12 numbers',
          'color', 'string (hex)',
          'details', 'string'
        )
      )
    ),
    'example', CAST('{"title":"Ключевые метрики","items":[{"icon":"📈","name":"Показатель","value":"85","unit":"%","trend":"+5%","trend_up":true,"points":[60,65,70,72,75,78,80,83,85],"color":"#10B981","details":"Подробное описание метрики"}]}' AS JSON)
  ),
  gpt_hint = 'items: [{icon(emoji), name, value(строка), unit, trend, trend_up(bool), points(массив 8-12 чисел), color(hex), details}]. points обязательны!'
WHERE code = 'spark_metrics';

-- radar_chart: axes array
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'title', JSON_OBJECT('type', 'string'),
      'color', JSON_OBJECT('type', 'string (hex)'),
      'axes', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'name', 'string',
          'value', 'integer (0-100)',
          'description', 'string'
        )
      )
    ),
    'example', CAST('{"title":"Профиль оценки","color":"#3B82F6","axes":[{"name":"Критерий 1","value":80,"description":"Описание"},{"name":"Критерий 2","value":65,"description":"Описание"},{"name":"Критерий 3","value":90,"description":"Описание"},{"name":"Критерий 4","value":70,"description":"Описание"},{"name":"Критерий 5","value":85,"description":"Описание"}]}' AS JSON)
  ),
  gpt_hint = 'axes: [{name, value(0-100), description}]. Мин. 5 осей! color — hex цвет заливки.'
WHERE code = 'radar_chart';

-- before_after: metrics array
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'title', JSON_OBJECT('type', 'string'),
      'metrics', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'name', 'string',
          'before', 'number',
          'after', 'number',
          'unit', 'string',
          'max', 'number'
        )
      )
    ),
    'example', CAST('{"title":"Результаты","metrics":[{"name":"Показатель","before":30,"after":85,"unit":"%","max":100}]}' AS JSON)
  ),
  gpt_hint = 'metrics: [{name, before(число), after(число), unit, max}]. max > before и after! Поле metrics, НЕ items!'
WHERE code = 'before_after';

-- stacked_area: labels + series
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'title',  JSON_OBJECT('type', 'string'),
      'labels', JSON_OBJECT('type', 'array of strings', 'required', true),
      'series', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'name', 'string',
          'data', 'array of numbers (длина = длина labels)',
          'color', 'string (hex)',
          'description', 'string'
        )
      )
    ),
    'example', CAST('{"title":"Динамика","labels":["Янв","Фев","Мар","Апр"],"series":[{"name":"Серия 1","data":[10,20,30,25],"color":"#3B82F6","description":"Описание серии"}]}' AS JSON)
  ),
  gpt_hint = 'series: [{name, data(массив чисел = длина labels), color(hex), description}]. labels — периоды!'
WHERE code = 'stacked_area';

-- score_rings: rings array
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'title',       JSON_OBJECT('type', 'string'),
      'total_label', JSON_OBJECT('type', 'string', 'required', true),
      'rings', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'name', 'string',
          'subtitle', 'string',
          'value', 'integer (0-100)',
          'max', 'integer (обычно 100)',
          'color', 'string (hex)',
          'description', 'string'
        )
      )
    ),
    'example', CAST('{"title":"Индекс здоровья","total_label":"общий балл","rings":[{"name":"Категория","subtitle":"Подкатегория","value":78,"max":100,"color":"#10B981","description":"Описание показателя"}]}' AS JSON)
  ),
  gpt_hint = 'rings: [{name, subtitle, value(0-100), max(100), color(hex), description}]. total_label обязателен!'
WHERE code = 'score_rings';

-- range_comparison: groups + rows
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'title',  JSON_OBJECT('type', 'string'),
      'groups', JSON_OBJECT('type', 'array of strings', 'required', true),
      'rows', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'name', 'string',
          'ranges', 'array of [min, max] pairs',
          'values', 'array of numbers'
        )
      )
    ),
    'example', CAST('{"title":"Сравнение диапазонов","groups":["Группа A","Группа B"],"rows":[{"name":"Показатель","ranges":[[50,100],[60,110]],"values":[75,85]}]}' AS JSON)
  ),
  gpt_hint = 'rows[].ranges — массив пар [[min,max],[min,max]], rows[].values — массив чисел. groups обязательны! Длина groups = длина ranges = длина values.'
WHERE code = 'range_comparison';

-- comparison_table: headers + rows
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'title',       JSON_OBJECT('type', 'string'),
      'description', JSON_OBJECT('type', 'string'),
      'headers',     JSON_OBJECT('type', 'array of strings', 'required', true),
      'rows',        JSON_OBJECT('type', 'array of arrays', 'required', true)
    ),
    'example', CAST('{"title":"Сравнение","description":"Описание таблицы","headers":["Критерий","Вариант A","Вариант B"],"rows":[["Цена","✓","✗"],["Качество","✓","✓"]]}' AS JSON)
  ),
  gpt_hint = 'headers[0] — название критерия, остальные — варианты. rows — массив массивов. Бинарные значения: ✓/✗!'
WHERE code = 'comparison_table';

-- ── Interactive blocks ────────────────────────────────

-- value_checker: zones array
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'title',             JSON_OBJECT('type', 'string'),
      'description',       JSON_OBJECT('type', 'string'),
      'input_label',       JSON_OBJECT('type', 'string'),
      'input_placeholder', JSON_OBJECT('type', 'string'),
      'disclaimer',        JSON_OBJECT('type', 'string', 'required', true),
      'zones', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'key', 'string',
          'from', 'number',
          'to', 'number',
          'color', 'string (hex)',
          'label', 'string',
          'icon', 'string (emoji)',
          'text', 'string'
        )
      )
    ),
    'example', CAST('{"title":"Проверьте свой результат","description":"Введите значение","input_label":"Ваш показатель","input_placeholder":"например, 120","disclaimer":"Не является медицинской рекомендацией","zones":[{"key":"low","from":0,"to":50,"color":"#EF4444","label":"Низкий","icon":"⚠️","text":"Описание зоны"},{"key":"normal","from":50,"to":100,"color":"#10B981","label":"Норма","icon":"✅","text":"Описание зоны"}]}' AS JSON)
  ),
  gpt_hint = 'zones: [{key, from(число), to(число), color(hex), label, icon(emoji), text}]. from/to — числовые пороги! disclaimer обязателен!'
WHERE code = 'value_checker';

-- criteria_checklist: items + thresholds
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'title',    JSON_OBJECT('type', 'string'),
      'subtitle', JSON_OBJECT('type', 'string'),
      'items', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'text', 'string',
          'weight', 'integer (1-3)',
          'group', 'string'
        )
      ),
      'thresholds', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'min', 'number',
          'max', 'number',
          'label', 'string',
          'color', 'string (hex)',
          'text', 'string'
        )
      ),
      'cta_text',     JSON_OBJECT('type', 'string'),
      'cta_link_key', JSON_OBJECT('type', 'string')
    ),
    'example', CAST('{"title":"Оцените свой риск","subtitle":"Отметьте подходящие пункты","items":[{"text":"Первый критерий","weight":2,"group":"Основные"},{"text":"Второй критерий","weight":1,"group":"Основные"}],"thresholds":[{"min":0,"max":3,"label":"Низкий риск","color":"#10B981","text":"Описание"},{"min":4,"max":7,"label":"Средний риск","color":"#F59E0B","text":"Описание"},{"min":8,"max":100,"label":"Высокий риск","color":"#EF4444","text":"Описание"}]}' AS JSON)
  ),
  gpt_hint = 'items: [{text, weight(1-3), group}]. thresholds: [{min, max, label, color(hex), text}]. Мин. 6 items, 3 thresholds!'
WHERE code = 'criteria_checklist';

-- prep_checklist: sections with nested items
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'title',    JSON_OBJECT('type', 'string'),
      'subtitle', JSON_OBJECT('type', 'string'),
      'sections', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'name', 'string',
          'icon', 'string (emoji)',
          'items', 'array of {text: string, important: boolean}'
        )
      )
    ),
    'example', CAST('{"title":"Чек-лист подготовки","subtitle":"Не забудьте проверить","sections":[{"name":"Документы","icon":"📋","items":[{"text":"Паспорт","important":true},{"text":"Направление","important":false}]},{"name":"С собой","icon":"🎒","items":[{"text":"Вода","important":false},{"text":"Сменная обувь","important":true}]}]}' AS JSON)
  ),
  gpt_hint = 'sections: [{name, icon(emoji), items:[{text, important(bool)}]}]. 2-3 секции, 2-4 items в каждой!'
WHERE code = 'prep_checklist';

-- mini_calculator: inputs + results
UPDATE `seo_block_types` SET
  json_schema = JSON_OBJECT(
    'fields', JSON_OBJECT(
      'title',               JSON_OBJECT('type', 'string'),
      'description',         JSON_OBJECT('type', 'string'),
      'formula_description', JSON_OBJECT('type', 'string'),
      'disclaimer',          JSON_OBJECT('type', 'string'),
      'inputs', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'key', 'string',
          'label', 'string',
          'type', 'string: number|select',
          'placeholder', 'string',
          'unit', 'string',
          'min', 'number',
          'max', 'number',
          'options', 'array of {value, label} (если type=select)',
          'show_if', 'object {key, value} (опционально)'
        )
      ),
      'results', JSON_OBJECT(
        'type', 'array',
        'required', true,
        'items', JSON_OBJECT(
          'condition', 'string (формат: key=val&&key2=val2)',
          'value', 'string',
          'text', 'string'
        )
      )
    ),
    'example', CAST('{"title":"Калькулятор","description":"Расчёт показателя","inputs":[{"key":"weight","label":"Вес","type":"number","placeholder":"кг","unit":"кг","min":30,"max":200},{"key":"height","label":"Рост","type":"number","placeholder":"см","unit":"см","min":100,"max":250}],"results":[{"condition":"bmi<18.5","value":"Недовес","text":"Описание результата"}],"formula_description":"Индекс = вес / рост²","disclaimer":"Ориентировочный расчёт"}' AS JSON)
  ),
  gpt_hint = 'inputs: [{key, label, type:"select"|"number", options:[{value,label}], show_if:{key,value}}]. results: [{condition:"key=val&&key2=val2", value, text}]. disclaimer обязателен!'
WHERE code = 'mini_calculator';
