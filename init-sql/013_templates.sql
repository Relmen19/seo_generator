-- ═══════════════════════════════════════════════════════
-- Migration 010: Интент-ориентированные шаблоны
-- Группа: info (Справочный) + life_context (Жизненная ситуация)
-- 6 шаблонов: ID 10-15
--
-- Принципы:
--   1. Каждый шаблон — STORYTELLING, не набор виджетов
--   2. richtext-мостики между визуальными блоками
--   3. «Правило территорий» — блоки не дублируют друг друга
--   4. hint содержит ЗАПРЕТ на чужую территорию
--   5. Новые блоки (value_checker, info_cards, expert_panel и др.)
--      активно используются наряду со старыми
-- ═══════════════════════════════════════════════════════

SET NAMES utf8mb4;


-- ═══════════════════════════════════════════════════════
--  INTENT: info
-- Человек хочет понять что это за анализ/показатель.
-- Тон: энциклопедический, но доступный.
-- ═══════════════════════════════════════════════════════


-- ───────────────────────────────────────────────────────
-- ШАБЛОН 10: «Справочник анализа — визуальный»
-- Один показатель/анализ. Максимум визуализации.
-- hero → key_takeaways → richtext → info_cards → norms_table
-- → richtext-мостик → gauge_chart → richtext-мостик
-- → value_checker → timeline → expert_panel → faq → cta
-- ───────────────────────────────────────────────────────

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (1, 'Справочник анализа — визуальный',
     'info-visual',
     'Полная справочная статья об одном анализе/показателе с интерактивами: проверка значения, шкалы, карточки.',
     'Ты — медицинский SEO-копирайтер. Генерируй JSON-блоки для СПРАВОЧНОЙ статьи об анализе/показателе.
    ТОН: энциклопедический, но человечный. Объясняй сложное простыми словами. Читатель — обычный человек, не врач.
    ПРАВИЛО ТЕРРИТОРИЙ:
    - key_takeaways: ТОЛЬКО краткое саммари статьи (5 пунктов). НЕ расшифровка, НЕ нормы.
    - info_cards: ФАКТОРЫ, влияющие на показатель (питание, спорт, лекарства и т.д.). НЕ нормы, НЕ симптомы.
    - norms_table: ЧИСЛОВЫЕ НОРМЫ по полу/возрасту со states. НЕ описание анализа.
    - gauge_chart: СВЯЗАННЫЕ ПОКАЗАТЕЛИ (дополняющие norms_table, не дублирующие). Если статья про ферритин — здесь железо сыворотки, трансферрин.
    - value_checker: ИНТЕРАКТИВНАЯ ПРОВЕРКА — читатель вводит своё значение и видит зону. 4-5 зон с цветами и пояснениями.
    - timeline: ПРОЦЕСС СДАЧИ АНАЛИЗА (запись → подготовка → забор → результат). НЕ нормы.
    - expert_panel: ЦИТАТА ВРАЧА — реальное экспертное мнение. НЕ повтор richtext.
    - faq: ТОЛЬКО вопросы, ответы на которые НЕ даны выше.
    ПРАВИЛО RICHTEXT-МОСТИКОВ: каждый richtext между визуальными блоками — это связующий абзац (2-3 предложения), который объясняет ПЕРЕХОД от предыдущего блока к следующему. НЕ новая тема.
    ПРАВИЛО ЗАГОЛОВКОВ:
      ✗ «Мониторинг показателей» → ✓ «Ваш результат на шкале»
      ✗ «Параметры исследования» → ✓ «Что влияет на результат»
      ✗ «Референсные значения» → ✓ «Норма для вашего возраста и пола»
    ФОРМАТЫ:
    For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    hero: ПЛОСКИЙ {title, subtitle, cta_text, cta_link_key}
    key_takeaways: {title, items:[строки], style:"numbered"}
    info_cards: {title, layout:"grid-3", items:[{icon, title, text, color}]}
    norms_table: {caption, rows:[{name, unit, active, states:[{key, label, range, pct, description}]}]}
    gauge_chart: {title, items:[{name, value, min, max, unit, color, description}]}
    value_checker: {title, description, input_label, input_placeholder, zones:[{key, from, to, color, label, icon, text}], disclaimer}
    timeline: {title, items:[{step, title, summary, detail, meta}]}
    expert_panel: {name, credentials, experience, photo_placeholder, text, highlight}
    richtext: {blocks:[{type:"heading"|"paragraph"|"list"|"highlight"|"quote", ...}]}
    faq: {items:[{question, answer}]}
    cta: ПЛОСКИЙ {title, text, primary_btn_text, primary_btn_link_key}',
     'tpl-info-visual');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(1, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', 'H1: «[Название анализа]: что показывает и зачем сдавать». subtitle: перечисление — нормы, расшифровка, подготовка. ПЛОСКИЙ JSON: {title, subtitle, cta_text:"Расшифровать анализы", cta_link_key:"ORDER_ANALYSIS"}',
     'fields', JSON_ARRAY('title', 'subtitle', 'cta_text', 'cta_link_key')
                      ), 1, 1),

(1, 'key_takeaways', 'Главное за 30 секунд', JSON_OBJECT(
     'hint', '5 ключевых фактов о теме статьи. Формат: {title:"Главное за 30 секунд", items:["факт1","факт2",...], style:"numbered"}. Пример пунктов: что это за анализ, норма, главные причины отклонений, когда сдавать, сколько ждать результат. НЕ дублируй norms_table — здесь только ориентир, одна строка про норму.',
     'fields', JSON_ARRAY('title', 'items', 'style')
                                          ), 2, 1),

(1, 'richtext', 'Введение и определение', JSON_OBJECT(
     'hint', 'ПЛАН (мин. 8 подблоков):
1. H2 «Что такое [тема]?» → paragraph: определение простым языком + зачем организму
2. H3 «Зачем назначают анализ» → list: 4-6 показаний с пояснениями
3. highlight: ключевой факт (например, «30% россиян имеют скрытый дефицит»)
4. H3 «Кто в группе риска» → paragraph + list
НЕ ПИШИ числовые нормы (для этого norms_table), НЕ ПИШИ FAQ (для этого faq).',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight', 'quote')
                                       ), 3, 1),

(1, 'info_cards', 'Что влияет на результат', JSON_OBJECT(
     'hint', '6 карточек: факторы, влияющие на уровень показателя. Каждая: icon(эмодзи), title(2-3 слова), text(1-2 предложения), color(hex). Примеры факторов: питание, лекарства, физ.нагрузки, стресс, возраст, сопутствующие болезни. НЕ ПИШИ нормы — для этого norms_table. Формат: {title:"Что влияет на [показатель]", layout:"grid-3", items:[...]}',
     'fields', JSON_ARRAY('title', 'layout', 'items'),
     'items_fields', JSON_ARRAY('icon', 'title', 'text', 'color')
                                          ), 4, 1),

(1, 'norms_table', 'Нормы показателя', JSON_OBJECT(
     'hint', '3-5 групп (мужчины, женщины, дети, беременные, пожилые). У каждой 3-5 states. caption: «Нормы [показателя] по полу и возрасту». state.description: для «Норма» — позитивно; для крайних — совет к какому врачу идти. НЕ повторяй info_cards и richtext. ПРАВИЛО pct: для states с понижением/дефицитом — pct:10; для нормы — pct:100; для умеренного превышения — pct:60; для высокого превышения — pct:30. pct отражает заполненность шкалы на UI.',
     'fields', JSON_ARRAY('caption', 'rows'),
     'row_fields', JSON_ARRAY('name', 'unit', 'active', 'states')
                                    ), 5, 1),

(1, 'richtext', 'Мостик: от норм к связанным показателям', JSON_OBJECT(
     'hint', '2-3 предложения ПЕРЕХОДА: «Нормы — это ориентир, но один анализ редко даёт полную картину. Вот какие ещё показатели стоит проверить вместе с [тема]...». НЕ дублируй данные из norms_table.',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph')
                                                        ), 6, 0),

(1, 'gauge_chart', 'Связанные показатели', JSON_OBJECT(
     'hint', '2-3 ДОПОЛНЯЮЩИХ показателя (НЕ те же, что в norms_table). Если статья про ферритин — здесь железо сыворотки, трансферрин, ОЖСС. title: «Полная картина: смежные анализы». description каждого — что он добавляет к основному анализу.',
     'fields', JSON_ARRAY('title', 'items'),
     'items_fields', JSON_ARRAY('name', 'value', 'min', 'max', 'unit', 'color', 'description')
                                        ), 7, 1),

(1, 'richtext', 'Мостик: от шкалы к проверке', JSON_OBJECT(
     'hint', '2-3 предложения ПЕРЕХОДА: «Если у вас уже есть результат на руках — проверьте прямо сейчас, в какую зону попадает ваше значение.»',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph')
                                            ), 8, 0),

(1, 'value_checker', 'Проверьте свой результат', JSON_OBJECT(
     'hint', 'Интерактивный инструмент: читатель вводит число → видит зону. 4-5 зон (critical_low, low, optimal, elevated, high) с цветами, иконками и пояснениями. input_label: «[Название], [единица]». disclaimer: «Это не диагноз...». Зоны должны быть МЕДИЦИНСКИ КОРРЕКТНЫ — используй реальные пороговые значения. ПРАВИЛО ЦВЕТОВ И ИКОНОК: critical_low → color:"#DC2626", icon:"🔴"; low → color:"#F97316", icon:"🟠"; optimal → color:"#16A34A", icon:"🟢"; elevated → color:"#F97316", icon:"🟠"; high → color:"#DC2626", icon:"🔴". Всегда используй emoji-иконку в поле icon, никогда не пиши слова вместо иконок.',
     'fields', JSON_ARRAY('title', 'description', 'input_label', 'input_placeholder', 'zones', 'disclaimer'),
     'zone_fields', JSON_ARRAY('key', 'from', 'to', 'color', 'label', 'icon', 'text')
                                              ), 9, 1),

(1, 'timeline', 'Как сдать анализ', JSON_OBJECT(
     'hint', '4-5 шагов от решения до результата. title: «Как сдать анализ: шаг за шагом». Примеры шагов: записаться, подготовиться, прийти в лабораторию, забор крови, получить результат. summary — 3-5 слов. detail — 2-3 предложения. meta — время. НЕ нормы.',
     'fields', JSON_ARRAY('title', 'items'),
     'items_fields', JSON_ARRAY('step', 'title', 'summary', 'detail', 'meta')
                                 ), 10, 1),

(1, 'expert_panel', 'Мнение эксперта', JSON_OBJECT(
     'hint', 'Цитата врача (терапевт, гематолог, эндокринолог — по теме). Придумай реалистичного врача с ФИО, должностью и стажем. text: 3-5 предложений личного опыта. highlight: одна ключевая фраза. photo_placeholder: инициалы. НЕ повторяй richtext — здесь личный взгляд врача, а не справка.',
     'fields', JSON_ARRAY('name', 'credentials', 'experience', 'photo_placeholder', 'text', 'highlight')
                                    ), 11, 1),

(1, 'faq', 'Частые вопросы', JSON_OBJECT(
     'hint', '5-7 вопросов, ответы на которые НЕ ДАНЫ выше. Хорошие: «Влияет ли стресс?», «Как часто пересдавать?», «Можно ли ребёнку?», «Влияют ли месячные?», «Что если на границе нормы?», «Сколько стоит?». Плохие: дублирование richtext или norms_table.',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
                          ), 12, 1),

(1, 'cta', 'Призыв к действию', JSON_OBJECT(
     'hint', 'ПЛОСКИЙ JSON: {title:"Проверьте свой [показатель]", text:"Почему стоит сдать + что получите", primary_btn_text:"Расшифровать анализы", primary_btn_link_key:"ORDER_ANALYSIS"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')
                             ), 13, 1);


-- ───────────────────────────────────────────────────────
-- ШАБЛОН 11: «Справочник анализа — текстовый»
-- Один показатель. Упор на текст и экспертизу, меньше графиков.
-- hero → stats_counter → richtext(большой) → story_block
-- → norms_table → richtext-мостик → value_checker
-- → expert_panel → faq → cta
-- ───────────────────────────────────────────────────────

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (2, 'Справочник анализа — текстовый',
     'info-textual',
     'Справочная статья с упором на глубокий текст, экспертное мнение и интерактивную проверку. Меньше графиков.',
     'Ты — медицинский SEO-копирайтер. Генерируй JSON для ТЕКСТОВОЙ справочной статьи.
    ТОН: глубокий, экспертный, но без зауми. Как статья в хорошем медицинском журнале для широкой аудитории.
    ПРАВИЛО ТЕРРИТОРИЙ:
    - stats_counter: МАСШТАБ (сколько людей сдают, точность, скорость, цена). НЕ нормы.
    - richtext: РАЗВЁРНУТЫЙ ТЕКСТ — определение, биология, показания, подготовка, интерпретация. Мин. 12 подблоков.
    - story_block: ИСТОРИЯ ПАЦИЕНТА (вымышленная, но медицински реалистичная). НЕ повтор richtext.
    - norms_table: ЧИСЛОВЫЕ НОРМЫ. Только цифры.
    - value_checker: ИНТЕРАКТИВНАЯ ПРОВЕРКА значения.
    - expert_panel: МНЕНИЕ ВРАЧА — дополняет, а не дублирует richtext.
    - faq: ТОЛЬКО вопросы, не раскрытые выше.
    For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    ФОРМАТЫ:
    hero: ПЛОСКИЙ {title, subtitle}
    stats_counter: {items:[{value, label, suffix}]}
    richtext: {blocks:[...]} — мин. 12 подблоков
    story_block: {variant:"patient_story", icon, accent_color, lead, text, highlight, footnote}
    norms_table: {caption, rows:[{name, unit, active, states:[...]}]}
    value_checker: {title, description, input_label, input_placeholder, zones:[...], disclaimer}
    expert_panel: {name, credentials, experience, photo_placeholder, text, highlight}
    faq: {items:[{question, answer}]}
    cta: ПЛОСКИЙ {title, text, primary_btn_text, primary_btn_link_key}',
     'tpl-info-text');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(2, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', 'H1 + подзаголовок. Без CTA в hero (он внизу). {title:"[Анализ]: всё, что нужно знать", subtitle:"От биологии до расшифровки — полное руководство"}',
     'fields', JSON_ARRAY('title', 'subtitle')
                      ), 1, 1),

(2, 'stats_counter', 'Ключевые цифры', JSON_OBJECT(
     'hint', '3-4 факта о МАСШТАБЕ: сколько людей сдают, какая точность, сколько стоит, как быстро результат. НЕ нормы анализа. {items:[{value:"98", label:"точность результатов", suffix:"%"}, ...]}',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('value', 'label', 'suffix')
                                    ), 2, 1),

(2, 'richtext', 'Основной текст', JSON_OBJECT(
     'hint', 'РАЗВЁРНУТЫЙ ТЕКСТ, мин. 12 подблоков. ПЛАН:
1. H2 «Что такое [тема]» → 2 paragraph: определение + биология
2. H3 «Роль в организме» → paragraph + list: 4-6 функций
3. highlight: ключевой факт
4. H2 «Когда назначают анализ» → paragraph → list: 5-7 показаний
5. H3 «Как подготовиться к сдаче» → paragraph → list: пошагово
6. quote: рекомендация из клин. руководств
7. H2 «Как расшифровать результат» → paragraph: общие принципы
НЕ ПИШИ числовые нормы (для norms_table), НЕ ПИШИ FAQ.',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight', 'quote')
                               ), 3, 1),

(2, 'story_block', 'История пациента', JSON_OBJECT(
     'hint', 'Вымышленная, но медицински реалистичная история. variant:"patient_story". lead: «История [Имя], [возраст]». text: 3-5 предложений — как человек обнаружил проблему через анализ. highlight: ключевое значение анализа. footnote: «Имя изменено. Основано на типичном клиническом случае.» accent_color: hex цвет.',
     'fields', JSON_ARRAY('variant', 'icon', 'accent_color', 'lead', 'text', 'highlight', 'footnote')
                                    ), 4, 1),

(2, 'norms_table', 'Нормы', JSON_OBJECT(
     'hint', '3-5 групп по полу/возрасту. 3-5 states у каждой. caption: понятный заголовок. НЕ дублируй richtext. ПРАВИЛО pct: для states с понижением/дефицитом — pct:10; для нормы — pct:100; для умеренного превышения — pct:60; для высокого превышения — pct:30. pct отражает заполненность шкалы на UI.',
     'fields', JSON_ARRAY('caption', 'rows'),
     'row_fields', JSON_ARRAY('name', 'unit', 'active', 'states')
                         ), 5, 1),

(2, 'richtext', 'Мостик к проверке', JSON_OBJECT(
     'hint', '2-3 предложения: «Теперь, когда вы знаете нормы, проверьте своё значение прямо здесь.»',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph')
                                  ), 6, 0),

(2, 'value_checker', 'Проверка результата', JSON_OBJECT(
     'hint', '4-5 зон с медицински корректными порогами. input_label: «[Название], [единица]». ПРАВИЛО ЦВЕТОВ И ИКОНОК: critical_low → color:"#DC2626", icon:"🔴"; low → color:"#F97316", icon:"🟠"; optimal → color:"#16A34A", icon:"🟢"; elevated → color:"#F97316", icon:"🟠"; high → color:"#DC2626", icon:"🔴". Всегда используй emoji-иконку в поле icon, никогда не пиши слова вместо иконок.',
     'fields', JSON_ARRAY('title', 'description', 'input_label', 'input_placeholder', 'zones', 'disclaimer'),
     'zone_fields', JSON_ARRAY('key', 'from', 'to', 'color', 'label', 'icon', 'text')
                                         ), 7, 1),

(2, 'expert_panel', 'Мнение врача', JSON_OBJECT(
     'hint', 'Реалистичный врач. text: 3-5 предложений — личный опыт, чего не скажет справочник. highlight: ключевая цитата. НЕ повторяй richtext.',
     'fields', JSON_ARRAY('name', 'credentials', 'experience', 'photo_placeholder', 'text', 'highlight')
                                 ), 8, 1),

(2, 'faq', 'FAQ', JSON_OBJECT(
     'hint', '5-7 вопросов, НЕ раскрытых в richtext. Хорошие: про повторную сдачу, влияние еды/спорта, дети, комбинация с другими анализами.',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
               ), 9, 1),

(2, 'cta', 'CTA', JSON_OBJECT(
     'hint', 'ПЛОСКИЙ: {title:"Сдайте [анализ] сегодня", text:"Результат за 24 часа", primary_btn_text:"Записаться", primary_btn_link_key:"ORDER_ANALYSIS"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')
               ), 10, 1);


-- ───────────────────────────────────────────────────────
-- ШАБЛОН 12: «Комплексный профиль — справочный»
-- Группа показателей (гормоны, липидный профиль, коагулограмма).
-- hero → key_takeaways → richtext → chart(donut) → richtext-мостик
-- → spark_metrics → range_comparison → info_cards
-- → expert_panel → faq → cta
-- ───────────────────────────────────────────────────────

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (3, 'Комплексный профиль — справочный',
     'info-complex-profile',
     'Справочная статья о группе показателей с аналитическими виджетами.',
     'Ты — медицинский аналитик. Генерируй JSON для справочной статьи о ГРУППЕ анализов (профиль, панель).
    ТОН: аналитический, структурированный. Читатель хочет понять, что входит в профиль и зачем каждый компонент.
    ПРАВИЛО ТЕРРИТОРИЙ:
    - key_takeaways: КРАТКОЕ САММАРИ профиля (5 пунктов).
    - richtext: ТЕКСТ — что входит, зачем, кому назначают.
    - chart(doughnut): ПРОПОРЦИИ — как компоненты соотносятся. НЕ нормы.
    - spark_metrics: ТЕКУЩИЕ ТИПИЧНЫЕ значения + тренд. НЕ нормы по полу — для этого range_comparison.
    - range_comparison: НОРМЫ ДЛЯ РАЗНЫХ ГРУПП (М/Ж или взрослые/дети). Числовые диапазоны.
    - info_cards: ФАКТОРЫ, влияющие на весь профиль. НЕ нормы.
    - expert_panel: МНЕНИЕ ВРАЧА.
    - faq: ТОЛЬКО вопросы, не раскрытые выше.
    For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    ФОРМАТЫ:
    chart: {chart_type:"doughnut", title, description, labels:[...], datasets:[{data:[...], colors:[...], descriptions:[...]}]}
    spark_metrics: {title, items:[{icon, icon_bg, name, value, unit, trend, trend_up, points:[8-12 чисел], color, details:[[key,val],...]}]}
    range_comparison: {title, groups:[{key, tag}], rows:[{name, unit, ranges:[[min,max],...], values:[val,...], min, max, description}]}',
     'tpl-info-complex');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(3, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', 'subtitle — перечисление показателей. {title:"[Профиль]: полный разбор", subtitle:"[Показатель1], [Показатель2], [Показатель3] — нормы, расшифровка, взаимосвязи"}',
     'fields', JSON_ARRAY('title', 'subtitle')
                      ), 1, 1),

(3, 'key_takeaways', 'Главное', JSON_OBJECT(
     'hint', '5 пунктов: что входит, кому нужно, зачем комплексно, что покажет, стоимость. {title:"Коротко о [профиле]", items:[...], style:"numbered"}',
     'fields', JSON_ARRAY('title', 'items', 'style')
                             ), 2, 1),

(3, 'richtext', 'Обзор профиля', JSON_OBJECT(
     'hint', 'ПЛАН (мин. 10 подблоков):
1. H2 «Что входит в [профиль]» → paragraph: каждый показатель и его роль
2. H3 «Кому назначают» → list: 4-6 показаний
3. highlight: ключевой факт
4. H2 «Подготовка к сдаче» → paragraph → list: пошагово
5. H3 «Как часто сдавать» → paragraph
НЕ ПИШИ числовые нормы — для этого range_comparison и spark_metrics.',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight', 'quote')
                              ), 3, 1),

(3, 'chart', 'Состав профиля', JSON_OBJECT(
     'hint', 'Doughnut: ПРОПОРЦИИ целого (%). title: «Из чего состоит [профиль]». Для крови: лейкоцитарная формула. Для липидов: ЛПНП/ЛПВП/триглицериды. ОБЯЗАТЕЛЬНО: description + descriptions в datasets. chart_type:"doughnut".',
     'fields', JSON_ARRAY('chart_type', 'title', 'description', 'labels', 'datasets'),
     'chart_types', JSON_ARRAY('doughnut', 'pie')
                            ), 4, 1),

(3, 'richtext', 'Мостик к метрикам', JSON_OBJECT(
     'hint', '2-3 предложения: «Теперь посмотрим на каждый показатель подробнее — какие значения типичны и как они меняются.»',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph')
                                  ), 5, 0),

(3, 'spark_metrics', 'Ключевые показатели', JSON_OBJECT(
     'hint', '3-4 карточки с ТИПИЧНЫМИ значениями и трендом. title: «Показатели профиля». Каждая: icon(эмодзи), name, value(строка), unit, trend("+5%"), points(8-12 чисел), details(3 пары ключ-значение). НЕ нормы по полу — для этого range_comparison.',
     'fields', JSON_ARRAY('title', 'items'),
     'items_fields', JSON_ARRAY('icon', 'icon_bg', 'name', 'value', 'unit', 'trend', 'trend_up', 'points', 'color', 'details')
                                         ), 6, 1),

(3, 'range_comparison', 'Нормы по группам', JSON_OBJECT(
     'hint', 'Диапазоны М/Ж или взрослые/дети. title: «Нормы для мужчин и женщин». 3-5 показателей. description — чем отличаются нормы между группами. НЕ дублируй spark_metrics (там типичные значения, здесь диапазоны).',
     'fields', JSON_ARRAY('title', 'groups', 'rows'),
     'group_fields', JSON_ARRAY('key', 'tag'),
     'row_fields', JSON_ARRAY('name', 'unit', 'ranges', 'values', 'min', 'max', 'description')
                                         ), 7, 1),

(3, 'info_cards', 'Что влияет на профиль', JSON_OBJECT(
     'hint', '4-6 карточек: факторы, влияющие на весь набор показателей. {title:"Что влияет на [профиль]", layout:"grid-3", items:[{icon, title, text, color}]}',
     'fields', JSON_ARRAY('title', 'layout', 'items'),
     'items_fields', JSON_ARRAY('icon', 'title', 'text', 'color')
                                        ), 8, 1),

(3, 'expert_panel', 'Мнение специалиста', JSON_OBJECT(
     'hint', 'Врач-специалист по теме профиля. text: почему важно смотреть показатели В КОМПЛЕКСЕ, а не по одному. highlight: ключевая мысль.',
     'fields', JSON_ARRAY('name', 'credentials', 'experience', 'photo_placeholder', 'text', 'highlight')
                                       ), 9, 1),

(3, 'faq', 'FAQ', JSON_OBJECT(
     'hint', '5-7 вопросов. Фокус: «можно ли без направления», «как часто пересдавать», «что если один в норме а другой нет», «влияют ли лекарства». НЕ дублируй richtext.',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
               ), 10, 1),

(3, 'cta', 'Призыв', JSON_OBJECT(
     'hint', '{title:"Сдайте [профиль] комплексно", text:"Все показатели за один визит", primary_btn_text:"Заказать", primary_btn_link_key:"ORDER_ANALYSIS"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')
                  ), 11, 1);


-- ═══════════════════════════════════════════════════════
--  INTENT: life_context
-- Запрос привязан к жизненной ситуации.
-- Тон: адресный, «именно для вашей ситуации».
-- ═══════════════════════════════════════════════════════


-- ───────────────────────────────────────────────────────
-- ШАБЛОН 13: «Анализ в контексте ситуации — полный»
-- Фокус: один показатель + конкретная ситуация
-- hero → key_takeaways → story_block → richtext
-- → norms_table(специфичные) → richtext-мостик
-- → value_checker → info_cards → warning_block
-- → numbered_steps → expert_panel → faq → cta
-- ───────────────────────────────────────────────────────

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (4, 'Анализ в контексте ситуации — полный',
     'life-context-full',
     'Показатель применительно к жизненной ситуации: беременность, спорт, диета, возраст. С чеклистами и планом.',
     'Ты — медицинский копирайтер. Генерируй JSON для статьи о ПОКАЗАТЕЛЕ в контексте ЖИЗНЕННОЙ СИТУАЦИИ (беременность, спорт, возраст, диета, заболевание).
    ТОН: адресный — «именно для вашей ситуации». Читатель пришёл с КОНКРЕТНЫМ контекстом, не за общей справкой.
    ГЛАВНОЕ ПРАВИЛО: ВСЕ блоки привязаны к СИТУАЦИИ из заголовка. Если статья «Ферритин при беременности» — каждый блок говорит про беременных, а не про ферритин вообще.
    ПРАВИЛО ТЕРРИТОРИЙ:
    - key_takeaways: КЛЮЧЕВОЕ для этой ситуации (5 пунктов).
    - story_block: ИСТОРИЯ ЧЕЛОВЕКА в этой ситуации. Эмпатия.
    - richtext: ТЕКСТ — почему в этой ситуации показатель особенно важен, чем отличается от «обычного».
    - norms_table: НОРМЫ СПЕЦИФИЧНЫЕ для этой группы (не общие!). Если беременность — по триместрам. Если спорт — для разных нагрузок.
    - value_checker: ПРОВЕРКА с порогами ДЛЯ ЭТОЙ ГРУППЫ (не стандартные пороги!).
    - info_cards: СПЕЦИФИЧЕСКИЕ факторы для этой ситуации.
    - warning_block: КРАСНЫЕ ФЛАГИ именно для этой группы.
    - numbered_steps: КОНКРЕТНЫЙ ПЛАН действий для этой ситуации.
    - expert_panel: МНЕНИЕ ВРАЧА, специализирующегося на этой группе.
    - faq: ВОПРОСЫ специфичные для ситуации.
    For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    ФОРМАТЫ:
    story_block: {variant:"patient_story", icon, accent_color, lead, text, highlight, footnote}
    warning_block: {variant:"red_flags"|"caution", title, subtitle, items:[{text, severity:"urgent"|"emergency"|"warning"}], footer}
    numbered_steps: {title, steps:[{number, title, text, tip, duration}]}
    value_checker: {title, description, input_label, input_placeholder, zones:[{key, from, to, color, label, icon, text}], disclaimer}
    info_cards: {title, layout:"grid-3", items:[{icon, title, text, color}]}',
     'tpl-life-full');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(4, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', 'H1 чётко обозначает СИТУАЦИЮ: «[Показатель] при [ситуации]: нормы, риски и что делать». subtitle: «Всё, что нужно знать [группе] об [показателе]». ПЛОСКИЙ: {title, subtitle, cta_text, cta_link_key}',
     'fields', JSON_ARRAY('title', 'subtitle', 'cta_text', 'cta_link_key')
                      ), 1, 1),

(4, 'key_takeaways', 'Главное для вашей ситуации', JSON_OBJECT(
     'hint', '5 ключевых фактов СПЕЦИФИЧНЫХ для ситуации. НЕ общие знания. Пример для беременных: «Потребность в железе вырастает в 3 раза», «Стандартные нормы ферритина вам не подходят». {title:"Главное для [ситуация]", items:[...], style:"numbered"}',
     'fields', JSON_ARRAY('title', 'items', 'style')
                                                ), 2, 1),

(4, 'story_block', 'История из жизни', JSON_OBJECT(
     'hint', 'История человека ИМЕННО в этой ситуации: как показатель повлиял на его/её жизнь. variant:"patient_story". Эмпатичный тон. Конкретные значения анализа в highlight. footnote: дисклеймер.',
     'fields', JSON_ARRAY('variant', 'icon', 'accent_color', 'lead', 'text', 'highlight', 'footnote')
                                    ), 3, 1),

(4, 'richtext', 'Почему это важно именно вам', JSON_OBJECT(
     'hint', 'ПЛАН (мин. 8 подблоков):
1. H2 «Почему [показатель] критически важен при [ситуации]» → paragraph: механизм
2. H3 «Чем отличается от обычного» → paragraph + list: 3-4 отличия
3. highlight: ключевой факт для этой группы
4. H3 «Как часто контролировать» → paragraph: график мониторинга
5. quote: цитата из клинических рекомендаций для этой группы
НЕ ПИШИ числовые нормы — для этого norms_table.',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight', 'quote')
                                            ), 4, 1),

(4, 'norms_table', 'Нормы для вашей группы', JSON_OBJECT(
     'hint', 'СПЕЦИФИЧНЫЕ нормы для ситуации из заголовка. НЕ стандартные общие нормы. Если беременность — по триместрам. Если спорт — для разных уровней нагрузки. Если дети — по возрастам. caption: «Нормы [показателя] для [группы]». 3-5 rows. ПРАВИЛО pct: для states с понижением/дефицитом — pct:10; для нормы — pct:100; для умеренного превышения — pct:60; для высокого превышения — pct:30. pct отражает заполненность шкалы на UI.',
     'fields', JSON_ARRAY('caption', 'rows'),
     'row_fields', JSON_ARRAY('name', 'unit', 'active', 'states')
                                          ), 5, 1),

(4, 'richtext', 'Мостик к проверке', JSON_OBJECT(
     'hint', '2-3 предложения: «Если у вас уже есть результат — проверьте его по меркам именно ВАШЕЙ ситуации, а не стандартных норм из бланка.»',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph')
                                  ), 6, 0),

(4, 'value_checker', 'Проверьте свой результат', JSON_OBJECT(
     'hint', 'Пороги СПЕЦИФИЧНЫЕ для этой группы (не стандартные!). Для беременных: ферритин <30 уже «пониженный», а не <10. description: «Нормы адаптированы для [группы]». 4-5 зон. ПРАВИЛО ЦВЕТОВ И ИКОНОК: critical_low → color:"#DC2626", icon:"🔴"; low → color:"#F97316", icon:"🟠"; optimal → color:"#16A34A", icon:"🟢"; elevated → color:"#F97316", icon:"🟠"; high → color:"#DC2626", icon:"🔴". Всегда используй emoji-иконку в поле icon, никогда не пиши слова вместо иконок.',
     'fields', JSON_ARRAY('title', 'description', 'input_label', 'input_placeholder', 'zones', 'disclaimer'),
     'zone_fields', JSON_ARRAY('key', 'from', 'to', 'color', 'label', 'icon', 'text')
                                              ), 7, 1),

(4, 'info_cards', 'Специфические факторы', JSON_OBJECT(
     'hint', '4-6 карточек: факторы, УНИКАЛЬНЫЕ для этой ситуации. Для беременных: токсикоз, многоплодность, ГВ после родов. Для спортсменов: потоотделение, foot-strike hemolysis. НЕ общие факторы (еда, стресс) — они для intent=info. {title:"Что влияет на [показатель] при [ситуации]", layout:"grid-3"}',
     'fields', JSON_ARRAY('title', 'layout', 'items'),
     'items_fields', JSON_ARRAY('icon', 'title', 'text', 'color')
                                        ), 8, 1),

(4, 'warning_block', 'Красные флаги', JSON_OBJECT(
     'hint', 'Экстренные признаки СПЕЦИФИЧНЫЕ для этой группы. variant:"red_flags". title: «Когда срочно к врачу при [ситуации]». 3-5 пунктов с severity: urgent или emergency. footer: телефон экстренной помощи. НЕ общие флаги.',
     'fields', JSON_ARRAY('variant', 'title', 'subtitle', 'items', 'footer'),
     'items_fields', JSON_ARRAY('text', 'severity')
                                   ), 9, 1),

(4, 'numbered_steps', 'План действий', JSON_OBJECT(
     'hint', 'КОНКРЕТНЫЙ ПЛАН для этой ситуации: 4-5 шагов. title: «Что делать [группе] с [показателем]». Каждый шаг: number, title, text, tip, duration. Пример для беременных: 1) Сдать анализ в 1 триместре, 2) Скорректировать питание, 3) Начать препарат по назначению, 4) Контроль каждый триместр.',
     'fields', JSON_ARRAY('title', 'steps'),
     'steps_fields', JSON_ARRAY('number', 'title', 'text', 'tip', 'duration')
                                    ), 10, 1),

(4, 'expert_panel', 'Мнение специалиста', JSON_OBJECT(
     'hint', 'Врач, СПЕЦИАЛИЗИРУЮЩИЙСЯ на этой группе: гинеколог для беременных, спортивный врач для спортсменов, педиатр для детей. text: 3-5 предложений о нюансах показателя именно в этой ситуации.',
     'fields', JSON_ARRAY('name', 'credentials', 'experience', 'photo_placeholder', 'text', 'highlight')
                                       ), 11, 1),

(4, 'faq', 'Вопросы по вашей ситуации', JSON_OBJECT(
     'hint', '5-7 вопросов СПЕЦИФИЧНЫХ для ситуации. Для беременных: «Какой ферритин нужен для ЭКО?», «Можно ли Мальтофер при ГВ?». НЕ общие вопросы.',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
                                     ), 12, 1),

(4, 'cta', 'Призыв', JSON_OBJECT(
     'hint', '{title:"Проверьте [показатель] для [ситуации]", text:"Специальная норма для вашей группы", primary_btn_text:"Расшифровать анализы", primary_btn_link_key:"ORDER_ANALYSIS"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')
                  ), 13, 1);


-- ───────────────────────────────────────────────────────
-- ШАБЛОН 14: «Жизненная ситуация — компактный»
-- Более лёгкий вариант. Без тяжёлых графиков.
-- hero → key_takeaways → richtext → story_block
-- → comparison_cards → warning_block → prep_checklist
-- → expert_panel → faq → cta
-- ───────────────────────────────────────────────────────

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (5, 'Жизненная ситуация — компактный',
     'life-context-compact',
     'Компактная статья о показателе в жизненной ситуации. Упор на практику: карточки сравнения, чеклист подготовки.',
     'Ты — медицинский копирайтер. Генерируй JSON для КОМПАКТНОЙ статьи о показателе в контексте ЖИЗНЕННОЙ СИТУАЦИИ.
    ТОН: практичный, конкретный. Минимум теории — максимум применимого.
    ГЛАВНОЕ ПРАВИЛО: ВСЕ блоки привязаны к СИТУАЦИИ из заголовка.
    ПРАВИЛО ТЕРРИТОРИЙ:
    - key_takeaways: КРАТКОЕ САММАРИ для этой ситуации.
    - richtext: КОНТЕКСТ — почему важно + чем отличается.
    - story_block: КОРОТКАЯ ИСТОРИЯ из этой ситуации.
    - comparison_cards: СРАВНЕНИЕ двух подходов/препаратов/методов ПРИМЕНИТЕЛЬНО к ситуации.
    - warning_block: КРАСНЫЕ ФЛАГИ для этой группы.
    - prep_checklist: ЧЕКЛИСТ — подготовка к анализу ИЛИ план действий ДЛЯ ЭТОЙ ГРУППЫ.
    - expert_panel: МНЕНИЕ ПРОФИЛЬНОГО врача.
    - faq: СПЕЦИФИЧНЫЕ вопросы.
    For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    ФОРМАТЫ:
    comparison_cards: {title, card_a:{name, badge, color, pros:[], cons:[], price, verdict}, card_b:{...}}
    prep_checklist: {title, subtitle, sections:[{name, icon, items:[{text, important:bool}]}]}
    warning_block: {variant:"red_flags", title, subtitle, items:[{text, severity}], footer}',
     'tpl-life-compact');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(5, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', 'Чётко обозначает ситуацию. {title:"[Показатель] при [ситуации]", subtitle:"Практическое руководство: нормы, препараты, план"}',
     'fields', JSON_ARRAY('title', 'subtitle')
                      ), 1, 1),

(5, 'key_takeaways', 'Коротко', JSON_OBJECT(
     'hint', '5 практических пунктов для этой ситуации. Не теория — конкретные цифры и действия. {title:"5 фактов для [группы]", items:[...], style:"numbered"}',
     'fields', JSON_ARRAY('title', 'items', 'style')
                             ), 2, 1),

(5, 'richtext', 'Контекст', JSON_OBJECT(
     'hint', 'ПЛАН (мин. 6 подблоков):
1. H2 «Почему [показатель] важен при [ситуации]» → paragraph
2. H3 «Чем отличаются нормы» → paragraph + list: ключевые отличия
3. highlight: главное отличие
4. H3 «Когда сдавать» → paragraph: график
НЕ ПИШИ развёрнутую теорию — это компактный шаблон.',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight', 'quote')
                         ), 3, 1),

(5, 'story_block', 'Короткая история', JSON_OBJECT(
     'hint', 'Краткая история: 2-3 предложения. variant:"patient_story". highlight: ключевое значение. Эмпатичный, но компактный.',
     'fields', JSON_ARRAY('variant', 'icon', 'accent_color', 'lead', 'text', 'highlight', 'footnote')
                                    ), 4, 1),

(5, 'comparison_cards', 'Два подхода', JSON_OBJECT(
     'hint', 'Сравнение двух вариантов ПРИМЕНИТЕЛЬНО к ситуации. Для беременных: «Таблетки vs капельницы железа». Для спортсменов: «Гемовое vs негемовое железо». card_a и card_b: name, badge, color(hex), pros:[], cons:[], price, verdict.',
     'fields', JSON_ARRAY('title', 'card_a', 'card_b'),
     'card_fields', JSON_ARRAY('name', 'badge', 'color', 'pros', 'cons', 'price', 'verdict')
                                    ), 5, 1),

(5, 'warning_block', 'Красные флаги', JSON_OBJECT(
     'hint', 'variant:"red_flags". 3-5 экстренных признаков СПЕЦИФИЧНЫХ для этой группы. severity: urgent/emergency.',
     'fields', JSON_ARRAY('variant', 'title', 'subtitle', 'items', 'footer'),
     'items_fields', JSON_ARRAY('text', 'severity')
                                   ), 6, 1),

(5, 'prep_checklist', 'Чек-лист', JSON_OBJECT(
     'hint', 'ПРАКТИЧЕСКИЙ ЧЕКЛИСТ для этой группы. Можно: подготовка к анализу ИЛИ план по восстановлению показателя. 2-3 секции (по периодам), каждая с 2-4 пунктами. important=true для критичных.',
     'fields', JSON_ARRAY('title', 'subtitle', 'sections'),
     'section_fields', JSON_ARRAY('name', 'icon', 'items')
                               ), 7, 1),

(5, 'expert_panel', 'Мнение врача', JSON_OBJECT(
     'hint', 'ПРОФИЛЬНЫЙ врач для этой ситуации. Конкретный совет: что делать и чего НЕ делать.',
     'fields', JSON_ARRAY('name', 'credentials', 'experience', 'photo_placeholder', 'text', 'highlight')
                                 ), 8, 1),

(5, 'faq', 'FAQ', JSON_OBJECT(
     'hint', '4-6 вопросов СПЕЦИФИЧНЫХ для ситуации.',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
               ), 9, 1),

(5, 'cta', 'CTA', JSON_OBJECT(
     'hint', '{title:"Проверьте [показатель]", text:"Адаптированные нормы для [группы]", primary_btn_text:"Расшифровать анализы", primary_btn_link_key:"ORDER_ANALYSIS"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')
               ), 10, 1);


-- ───────────────────────────────────────────────────────
-- ШАБЛОН 15: «Жизненная ситуация — мониторинг»
-- Для долгосрочных ситуаций: хроническое заболевание, спорт, диета.
-- Фокус: отслеживание динамики.
-- hero → key_takeaways → richtext → before_after
-- → progress_tracker → richtext-мостик → norms_table
-- → mini_calculator → warning_block → expert_panel → faq → cta
-- ───────────────────────────────────────────────────────

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (6, 'Жизненная ситуация — мониторинг',
     'life-context-monitor',
     'Для долгосрочных ситуаций: показатель при хронической болезни, спорте, диете. Фокус на динамику и прогноз.',
     'Ты — медицинский копирайтер. Генерируй JSON для статьи о МОНИТОРИНГЕ показателя в ДОЛГОСРОЧНОЙ ситуации (хроническое заболевание, регулярный спорт, длительная диета, возрастные изменения).
    ТОН: поддерживающий, мотивирующий. Читатель уже знает проблему и хочет видеть прогресс.
    ГЛАВНОЕ ПРАВИЛО: ВСЕ блоки привязаны к МОНИТОРИНГУ и ДИНАМИКЕ, а не к первичной диагностике.
    ПРАВИЛО ТЕРРИТОРИЙ:
    - key_takeaways: КЛЮЧЕВЫЕ ТОЧКИ мониторинга.
    - richtext: КОНТЕКСТ — зачем мониторить, как часто, что отслеживать.
    - before_after: ТИПИЧНАЯ ДИНАМИКА до/после лечения или коррекции. Реалистичные числа.
    - progress_tracker: ОЖИДАЕМЫЙ ПРОГРЕСС по месяцам/неделям. Вехи с метриками.
    - norms_table: ЦЕЛЕВЫЕ значения (не стандартные нормы, а цели для этой ситуации).
    - mini_calculator: РАСЧЁТ персональной дозировки или потребности.
    - warning_block: КОГДА СРОЧНО К ВРАЧУ при мониторинге.
    - expert_panel: МНЕНИЕ врача о стратегии мониторинга.
    - faq: ВОПРОСЫ про мониторинг.
    For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    ФОРМАТЫ:
    before_after: {title, metrics:[{name, before, after, unit, max}]}
    progress_tracker: {title, timeline_unit, milestones:[{period, marker(0-100), text, metric}], note}
    mini_calculator: {title, description, inputs:[{key, label, type, options, min, max, unit, placeholder, show_if}], results:[{condition, value, text}], formula_description, disclaimer}',
     'tpl-life-monitor');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(6, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', 'Фокус на мониторинге. {title:"[Показатель] при [ситуации]: контроль и динамика", subtitle:"Как отслеживать, когда пересдавать, чего ожидать"}',
     'fields', JSON_ARRAY('title', 'subtitle')
                      ), 1, 1),

(6, 'key_takeaways', 'Ключевые точки мониторинга', JSON_OBJECT(
     'hint', '5 пунктов про МОНИТОРИНГ: как часто сдавать, целевые значения, когда бить тревогу, сколько ждать эффект, как интерпретировать динамику. {title:"Мониторинг [показателя]: ключевое", items:[...], style:"numbered"}',
     'fields', JSON_ARRAY('title', 'items', 'style')
                                                ), 2, 1),

(6, 'richtext', 'Зачем мониторить', JSON_OBJECT(
     'hint', 'ПЛАН (мин. 8 подблоков):
1. H2 «Почему важен регулярный контроль [показателя] при [ситуации]» → paragraph
2. H3 «Как часто сдавать» → paragraph + list: конкретный график
3. highlight: «Однократный анализ не показывает тренд — нужна серия»
4. H3 «Что отслеживать кроме [основного показателя]» → list: сопутствующие анализы
5. H2 «Как понять, что лечение работает» → paragraph
НЕ ПИШИ числовые нормы — для этого norms_table.',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight', 'quote')
                                 ), 3, 1),

(6, 'before_after', 'Типичная динамика', JSON_OBJECT(
     'hint', 'РЕАЛИСТИЧНЫЕ значения ДО и ПОСЛЕ коррекции для этой ситуации. title: «Результат лечения за [срок]». 3-5 показателей. Числа должны быть медицински корректными. max > before и after.',
     'fields', JSON_ARRAY('title', 'metrics'),
     'metric_fields', JSON_ARRAY('name', 'before', 'after', 'unit', 'max')
                                      ), 4, 1),

(6, 'progress_tracker', 'Ожидаемый прогресс', JSON_OBJECT(
     'hint', '4-5 вех с ожидаемым прогрессом. title: «Чего ожидать при [лечении/коррекции]». Каждая веха: period(«2 недели»), marker(0-100 — позиция на шкале), text(что происходит), metric(конкретное значение показателя). note: «Скорость индивидуальна».',
     'fields', JSON_ARRAY('title', 'timeline_unit', 'milestones', 'note'),
     'milestone_fields', JSON_ARRAY('period', 'marker', 'text', 'metric')
                                           ), 5, 1),

(6, 'richtext', 'Мостик к нормам', JSON_OBJECT(
     'hint', '2-3 предложения: «Вот какие ЦЕЛЕВЫЕ значения стоит ставить при [ситуации] — они отличаются от стандартных.»',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph')
                                ), 6, 0),

(6, 'norms_table', 'Целевые значения', JSON_OBJECT(
     'hint', 'НЕ стандартные нормы, а ЦЕЛЕВЫЕ значения для мониторинга при этой ситуации. caption: «Целевые значения при [ситуации]». Пример: для спортсменов ферритин >50, а не стандартные >12. 3-5 rows. ПРАВИЛО pct: для states с понижением/дефицитом — pct:10; для нормы — pct:100; для умеренного превышения — pct:60; для высокого превышения — pct:30. pct отражает заполненность шкалы на UI.',
     'fields', JSON_ARRAY('caption', 'rows'),
     'row_fields', JSON_ARRAY('name', 'unit', 'active', 'states')
                                    ), 7, 1),

(6, 'mini_calculator', 'Персональный расчёт', JSON_OBJECT(
     'hint', 'Калькулятор дозировки или потребности ДЛЯ ЭТОЙ СИТУАЦИИ. Например: суточная потребность в железе для беременной с учётом триместра и веса. 2-4 inputs (select/number), 3-5 results с conditions. Всё медицински корректно.',
     'fields', JSON_ARRAY('title', 'description', 'inputs', 'results', 'formula_description', 'disclaimer'),
     'input_fields', JSON_ARRAY('key', 'label', 'type', 'options', 'min', 'max', 'unit', 'placeholder', 'show_if')
                                           ), 8, 1),

(6, 'warning_block', 'Когда бить тревогу', JSON_OBJECT(
     'hint', 'variant:"red_flags". СПЕЦИФИЧНЫЕ для мониторинга: «Если [показатель] не растёт за 3 месяца», «Если появилась новая симптоматика», «Резкое падение после улучшения». 3-5 пунктов.',
     'fields', JSON_ARRAY('variant', 'title', 'subtitle', 'items', 'footer'),
     'items_fields', JSON_ARRAY('text', 'severity')
                                        ), 9, 1),

(6, 'expert_panel', 'Мнение о стратегии', JSON_OBJECT(
     'hint', 'Врач о СТРАТЕГИИ мониторинга: как часто, какие комбинации анализов, когда менять тактику. Не общая справка.',
     'fields', JSON_ARRAY('name', 'credentials', 'experience', 'photo_placeholder', 'text', 'highlight')
                                       ), 10, 1),

(6, 'faq', 'FAQ по мониторингу', JSON_OBJECT(
     'hint', '5-7 вопросов про МОНИТОРИНГ: «Как часто пересдавать?», «Нормально ли что показатель скачет?», «Когда можно прекратить приём?», «Что если забыл вовремя сдать?».',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
                              ), 11, 1),

(6, 'cta', 'CTA', JSON_OBJECT(
     'hint', '{title:"Контрольный анализ [показателя]", text:"Отслеживайте динамику", primary_btn_text:"Записаться на пересдачу", primary_btn_link_key:"ORDER_ANALYSIS"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')
               ), 12, 1);





-- ═══════════════════════════════════════════════════════
-- Migration 011: Интент-ориентированные шаблоны
-- Группа: symptom_check + diagnosis_interpret + risk_assessment
-- 9 шаблонов: ID 20-28
--
-- Разница точек входа:
--   symptom_check:       ОТ СИМПТОМА → к показателю (эмпатия)
--   diagnosis_interpret: ОТ ЦИФРЫ → к пониманию (спокойствие)
--   risk_assessment:     ОТ СТРАХА → к ясности (взвешенность)
--
-- ═══════════════════════════════════════════════════════

-- ═══════════════════════════════════════════════════════
--  INTENT: symptom_check
-- Человек замечает симптомы и хочет понять, связано ли это
-- с показателем. Идёт ОТ СИМПТОМА К АНАЛИЗУ.
-- Тон: эмпатичный, «я понимаю, что вы чувствуете».
-- ═══════════════════════════════════════════════════════


-- ───────────────────────────────────────────────────────
-- ШАБЛОН 20: «Симптом → анализ — полный»
-- Эмпатичный разбор: от симптома к диагностике.
-- hero → story_block → symptom_checklist → richtext
-- → info_cards → norms_table → richtext-мостик
-- → value_checker → warning_block → numbered_steps
-- → expert_panel → faq → cta
-- ───────────────────────────────────────────────────────

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (7, 'Симптом → анализ — полный',
     'symptom-full',
     'Эмпатичная статья: читатель пришёл с симптомом, мы ведём его к пониманию и действию.',
     'Ты — медицинский копирайтер с эмпатичным стилем. Генерируй JSON для статьи, где читатель ПРИШЁЛ С СИМПТОМОМ и хочет понять, связано ли это с показателем.
    ТОН: эмпатичный, тёплый. «Я понимаю, как это тревожит». НЕ клинический, НЕ сухой. Начинай с описания того, что чувствует человек, а не с определения анализа.
    СТРУКТУРА НАРРАТИВА: симптом → «вы не одни» → механизм связи → проверьте себя → что делать.
    ПРАВИЛО ТЕРРИТОРИЙ:
    - story_block: ИСТОРИЯ ЧЕЛОВЕКА с этим симптомом. Эмпатия и узнавание.
    - symptom_checklist: ИНТЕРАКТИВНЫЙ ТЕСТ — читатель отмечает свои симптомы, получает оценку.
    - richtext: МЕХАНИЗМ — как показатель вызывает симптом. НЕ нормы, НЕ FAQ.
    - info_cards: ДРУГИЕ ВОЗМОЖНЫЕ ПРИЧИНЫ этого симптома (не только этот показатель). Дифференциальная диагностика для пациента.
    - norms_table: НОРМЫ показателя. Только цифры.
    - value_checker: ПРОВЕРКА значения.
    - warning_block: КОГДА К ВРАЧУ СРОЧНО с этим симптомом.
    - numbered_steps: ЧТО ДЕЛАТЬ — от записи к врачу до получения результата.
    - expert_panel: МНЕНИЕ ВРАЧА о связи симптома с показателем.
    - faq: ВОПРОСЫ о симптоме и показателе.
    For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    ФОРМАТЫ:
    hero: ПЛОСКИЙ {title, subtitle, cta_text, cta_link_key}
    story_block: {variant:"patient_story", icon, accent_color, lead, text, highlight, footnote}
    symptom_checklist: {title, subtitle, items:[{text, weight(1-3), group}], thresholds:[{min, max, label, color, text}], cta_text, cta_link_key}
    info_cards: {title, layout:"grid-3", items:[{icon, title, text, color}]}
    norms_table: {caption, rows:[{name, unit, active, states:[{key, label, range, pct, description}]}]}
    value_checker: {title, description, input_label, input_placeholder, zones:[{key, from, to, color, label, icon, text}], disclaimer}
    warning_block: {variant, title, subtitle, items:[{text, severity}], footer}
    numbered_steps: {title, steps:[{number, title, text, tip, duration}]}
    expert_panel: {name, credentials, experience, photo_placeholder, text, highlight}
    richtext: {blocks:[...]}
    faq: {items:[{question, answer}]}
    cta: ПЛОСКИЙ {title, text, primary_btn_text, primary_btn_link_key}',
     'tpl-symptom-full');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(7, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', 'H1 НАЧИНАЕТСЯ С СИМПТОМА, не с анализа. ✗ «Ферритин: показания к сдаче» → ✓ «Выпадают волосы? Возможно, дело в ферритине». subtitle: «Как понять, связан ли ваш симптом с [показателем], и что с этим делать». ПЛОСКИЙ: {title, subtitle, cta_text:"Расшифровать анализы", cta_link_key:"ORDER_ANALYSIS"}',
     'fields', JSON_ARRAY('title', 'subtitle', 'cta_text', 'cta_link_key')
                      ), 1, 1),

(7, 'story_block', 'История: «Я тоже через это прошла»', JSON_OBJECT(
     'hint', 'variant:"patient_story". История НАЧИНАЕТСЯ С СИМПТОМА, а не с диагноза. lead: «История [Имя], [возраст]». text: описание страданий от симптома → обнаружение причины → решение. highlight: ключевой результат анализа. Читатель должен подумать: «это же про меня». accent_color: hex.',
     'fields', JSON_ARRAY('variant', 'icon', 'accent_color', 'lead', 'text', 'highlight', 'footnote')
                                                      ), 2, 1),

(7, 'symptom_checklist', 'Проверьте свои симптомы', JSON_OBJECT(
     'hint', 'Интерактивный тест: 6-8 симптомов, связанных с показателем. Группы: «основные», «косвенные», «специфические». weight: 1-3 (чем специфичнее — тем больше). 3 порога: маловероятно / возможно / высокая вероятность. cta_text: «Расшифровать анализы». cta_link_key: ORDER_ANALYSIS.',
     'fields', JSON_ARRAY('title', 'subtitle', 'items', 'thresholds', 'cta_text', 'cta_link_key'),
     'items_fields', JSON_ARRAY('text', 'weight', 'group'),
     'threshold_fields', JSON_ARRAY('min', 'max', 'label', 'color', 'text')
                                                 ), 3, 1),

(7, 'richtext', 'Как симптом связан с показателем', JSON_OBJECT(
     'hint', 'ПЛАН (мин. 8 подблоков):
1. H2 «Почему [симптом] связан с [показателем]» → paragraph: простое объяснение механизма
2. H3 «Как это работает в организме» → paragraph: биохимия простыми словами
3. highlight: «[X]% людей с этим симптомом имеют дефицит [показателя]»
4. H3 «Почему обычный анализ может не показать проблему» → paragraph: скрытый дефицит
5. quote: из исследования или клин. рекомендаций
НЕ ПИШИ числовые нормы — для этого norms_table. НЕ ПИШИ про другие причины — для этого info_cards.',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight', 'quote')
                                                 ), 4, 1),

(7, 'info_cards', 'Другие возможные причины', JSON_OBJECT(
     'hint', '4-6 карточек: ДРУГИЕ причины этого симптома (не только данный показатель). Дифференциальная диагностика для пациента. Пример для выпадения волос: щитовидная, стресс, витамин D, андрогены, аутоиммунные. icon + title + text + color. {title:"Другие причины [симптома]", layout:"grid-3"}',
     'fields', JSON_ARRAY('title', 'layout', 'items'),
     'items_fields', JSON_ARRAY('icon', 'title', 'text', 'color')
                                           ), 5, 1),

(7, 'norms_table', 'Нормы показателя', JSON_OBJECT(
     'hint', 'Стандартные нормы по полу/возрасту. caption: «Нормы [показателя]». 3-5 групп, 3-5 states. В description для «Понижен» — привязка К СИМПТОМУ из заголовка. ПРАВИЛО pct: для states с понижением/дефицитом — pct:10; для нормы — pct:100; для умеренного превышения — pct:60; для высокого превышения — pct:30. pct отражает заполненность шкалы на UI.',
     'fields', JSON_ARRAY('caption', 'rows'),
     'row_fields', JSON_ARRAY('name', 'unit', 'active', 'states')
                                    ), 6, 1),

(7, 'richtext', 'Мостик к проверке', JSON_OBJECT(
     'hint', '2-3 предложения: «Если у вас уже есть результат анализа — проверьте прямо сейчас, может ли ваш [симптом] быть связан с этим значением.»',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph')
                                  ), 7, 0),

(7, 'value_checker', 'Проверьте свой результат', JSON_OBJECT(
     'hint', '4-5 зон. В text каждой зоны — ПРИВЯЗКА К СИМПТОМУ: «При таком уровне [симптом] очень вероятен» / «Маловероятно что [симптом] вызван этим показателем». disclaimer обязателен. ПРАВИЛО ЦВЕТОВ И ИКОНОК: critical_low → color:"#DC2626", icon:"🔴"; low → color:"#F97316", icon:"🟠"; optimal → color:"#16A34A", icon:"🟢"; elevated → color:"#F97316", icon:"🟠"; high → color:"#DC2626", icon:"🔴". Всегда используй emoji-иконку в поле icon, никогда не пиши слова вместо иконок.',
     'fields', JSON_ARRAY('title', 'description', 'input_label', 'input_placeholder', 'zones', 'disclaimer'),
     'zone_fields', JSON_ARRAY('key', 'from', 'to', 'color', 'label', 'icon', 'text')
                                              ), 8, 1),

(7, 'warning_block', 'Когда к врачу срочно', JSON_OBJECT(
     'hint', 'variant:"red_flags". ЭКСТРЕННЫЕ СИМПТОМЫ, связанные с основным симптомом из заголовка. Не общие. Для выпадения волос + ферритин: «Резкое облысение за неделю», «Гемоглобин ниже 80». 3-5 пунктов, severity: urgent/emergency. footer: телефон скорой.',
     'fields', JSON_ARRAY('variant', 'title', 'subtitle', 'items', 'footer'),
     'items_fields', JSON_ARRAY('text', 'severity')
                                          ), 9, 1),

(7, 'numbered_steps', 'Что делать прямо сейчас', JSON_OBJECT(
     'hint', '4-5 шагов ОТ СИМПТОМА К РЕШЕНИЮ. title: «Что делать, если [симптом]». 1) Записаться к терапевту, 2) Сдать анализ, 3) Дождаться результата, 4) К профильному специалисту, 5) Начать лечение. tip в каждом шаге.',
     'fields', JSON_ARRAY('title', 'steps'),
     'steps_fields', JSON_ARRAY('number', 'title', 'text', 'tip', 'duration')
                                              ), 10, 1),

(7, 'expert_panel', 'Мнение врача', JSON_OBJECT(
     'hint', 'Врач о связи КОНКРЕТНОГО симптома с показателем. «В моей практике каждый второй пациент с [симптомом] имеет дефицит [показателя]». highlight: ключевая мысль.',
     'fields', JSON_ARRAY('name', 'credentials', 'experience', 'photo_placeholder', 'text', 'highlight')
                                 ), 11, 1),

(7, 'faq', 'Частые вопросы', JSON_OBJECT(
     'hint', '5-7 вопросов О СВЯЗИ СИМПТОМА И ПОКАЗАТЕЛЯ: «Может ли [симптом] пройти после нормализации?», «Через сколько улучшится?», «Какие ещё анализы сдать?», «Может ли быть другая причина?». НЕ общие.',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
                          ), 12, 1),

(7, 'cta', 'Призыв', JSON_OBJECT(
     'hint', '{title:"Узнайте причину [симптома]", text:"Анализ покажет, связан ли [симптом] с [показателем]", primary_btn_text:"Расшифровать анализы", primary_btn_link_key:"ORDER_ANALYSIS"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')
                  ), 13, 1);


-- ───────────────────────────────────────────────────────
-- ШАБЛОН 21: «Симптом → анализ — компактный»
-- Лёгкая версия: история + тест + план. Без графиков.
-- hero → story_block → symptom_checklist → richtext
-- → key_takeaways → warning_block → expert_panel → faq → cta
-- ───────────────────────────────────────────────────────

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (8, 'Симптом → анализ — компактный',
     'symptom-compact',
     'Короткая эмпатичная статья: история, тест симптомов, план действий.',
     'Ты — медицинский копирайтер. Компактная эмпатичная статья: от симптома к пониманию.
    ТОН: тёплый, без избыточной теории. Быстро к сути.
    СТРУКТУРА: симптом → «вы не одни» → проверьте себя → главное → когда к врачу → что делать.
    For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    ПРАВИЛО ТЕРРИТОРИЙ:
    - story_block: УЗНАВАНИЕ — «это же про меня».
    - symptom_checklist: ТЕСТ.
    - richtext: КРАТКИЙ МЕХАНИЗМ (не больше 6 подблоков).
    - key_takeaways: САММАРИ — 5 главных фактов.
    - warning_block: КРАСНЫЕ ФЛАГИ.
    - expert_panel: МНЕНИЕ ВРАЧА.
    - faq: ВОПРОСЫ О СИМПТОМЕ + ПОКАЗАТЕЛЕ.
    ФОРМАТЫ:
    hero: ПЛОСКИЙ {title, subtitle, cta_text?, cta_link_key?}
    story_block: {variant:"patient_story", icon, accent_color, lead, text, highlight, footnote}
    symptom_checklist: {title, subtitle, items:[{text, weight, group}], thresholds:[{min, max, label, color, text}], cta_text, cta_link_key}
    richtext: {blocks:[{type:"heading"|"paragraph"|"list"|"highlight"|"quote", ...}]}
    key_takeaways: {title, items:[строки], style:"numbered"}
    warning_block: {variant:"red_flags"|"caution", title, subtitle, items:[{text, severity:"urgent"|"emergency"|"warning"}], footer}
    expert_panel: {name, credentials, experience, photo_placeholder, text, highlight}
    faq: {items:[{question, answer}]}
    cta: ПЛОСКИЙ {title, text, primary_btn_text, primary_btn_link_key}',
     'tpl-symptom-compact');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(8, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', 'НАЧИНАЕТСЯ С СИМПТОМА. {title:"[Симптом]? Проверьте [показатель]", subtitle:"Быстрый тест и план действий"}',
     'fields', JSON_ARRAY('title', 'subtitle')
                      ), 1, 1),

(8, 'story_block', 'Узнаёте себя?', JSON_OBJECT(
     'hint', 'КРАТКАЯ история: 2-3 предложения. variant:"patient_story". Читатель должен узнать свою ситуацию.',
     'fields', JSON_ARRAY('variant', 'icon', 'accent_color', 'lead', 'text', 'highlight', 'footnote')
                                 ), 2, 1),

(8, 'symptom_checklist', 'Экспресс-тест', JSON_OBJECT(
     'hint', '5-6 симптомов, 2 группы. 3 порога. Компактный, без перегрузки. cta_text и cta_link_key обязательны.',
     'fields', JSON_ARRAY('title', 'subtitle', 'items', 'thresholds', 'cta_text', 'cta_link_key'),
     'items_fields', JSON_ARRAY('text', 'weight', 'group'),
     'threshold_fields', JSON_ARRAY('min', 'max', 'label', 'color', 'text')
                                       ), 3, 1),

(8, 'richtext', 'Почему связано', JSON_OBJECT(
     'hint', 'КРАТКИЙ МЕХАНИЗМ, мин. 4 подблока:
1. H2 «Как [показатель] вызывает [симптом]» → paragraph: 2-3 предложения
2. highlight: ключевой факт
3. H3 «Что ещё проверить» → list: 3-4 сопутствующих анализа',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight')
                               ), 4, 1),

(8, 'key_takeaways', 'Главное', JSON_OBJECT(
     'hint', '5 пунктов: связь симптома с показателем, какой анализ сдать, норма, когда бить тревогу, через сколько ждать улучшение. {title:"Главное о [симптоме] и [показателе]", style:"numbered"}',
     'fields', JSON_ARRAY('title', 'items', 'style')
                             ), 5, 1),

(8, 'warning_block', 'Красные флаги', JSON_OBJECT(
     'hint', 'variant:"red_flags". 3-4 экстренных признака. Привязаны к СИМПТОМУ, не к показателю.',
     'fields', JSON_ARRAY('variant', 'title', 'subtitle', 'items', 'footer'),
     'items_fields', JSON_ARRAY('text', 'severity')
                                   ), 6, 1),

(8, 'expert_panel', 'Мнение врача', JSON_OBJECT(
     'hint', 'Короткая цитата: 2-3 предложения о связи симптома с показателем.',
     'fields', JSON_ARRAY('name', 'credentials', 'experience', 'photo_placeholder', 'text', 'highlight')
                                 ), 7, 1),

(8, 'faq', 'FAQ', JSON_OBJECT(
     'hint', '4-5 вопросов. «Пройдёт ли [симптом] сам?», «За сколько восстановится?», «Какой врач лечит?».',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
               ), 8, 1),

(8, 'cta', 'CTA', JSON_OBJECT(
     'hint', '{title:"Выясните причину [симптома]", text:"Результат за 24 часа", primary_btn_text:"Расшифровать анализы", primary_btn_link_key:"ORDER_ANALYSIS"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')
               ), 9, 1);


-- ───────────────────────────────────────────────────────
-- ШАБЛОН 22: «Симптом → комплексная проверка»
-- Симптом может быть вызван НЕСКОЛЬКИМИ показателями.
-- hero → story_block → richtext → comparison_table
-- → symptom_checklist → richtext-мостик → spark_metrics
-- → warning_block → expert_panel → faq → cta
-- ───────────────────────────────────────────────────────

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (9, 'Симптом → комплексная проверка',
     'symptom-complex',
     'Симптом может быть вызван несколькими показателями: сравнительный анализ причин.',
     'Ты — медицинский копирайтер. Генерируй JSON для статьи, где ОДИН СИМПТОМ может быть вызван НЕСКОЛЬКИМИ показателями.
    ТОН: эмпатичный, но аналитический. «Давайте разберёмся, какая из причин ваша».
    СТРУКТУРА: симптом → возможные причины (таблица) → проверьте себя → какие анализы сдать.
    ПРАВИЛО ТЕРРИТОРИЙ:
    - story_block: ИСТОРИЯ — человек долго не мог найти причину.
    - richtext: ОБЗОР — почему один симптом имеет много причин.
    - comparison_table: СРАВНЕНИЕ ПРИЧИН — какой показатель что вызывает, чем отличается.
    - symptom_checklist: ТЕСТ — помогает сузить круг причин.
    - spark_metrics: КЛЮЧЕВЫЕ АНАЛИЗЫ, которые стоит сдать (3-4 штуки, с типичными значениями).
    - warning_block: КРАСНЫЕ ФЛАГИ.
    - expert_panel: МНЕНИЕ ВРАЧА.
    For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    ФОРМАТЫ:
    comparison_table: {title, description, headers:["Критерий","Причина A","Причина B","Причина C"], rows:[["Параметр","знач1","знач2","знач3"]]}
    spark_metrics: {title, items:[{icon, icon_bg, name, value, unit, trend, trend_up, points:[8-12], color, details:[[k,v],...]}]}',
     'tpl-symptom-complex');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(9, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', '{title:"[Симптом]: какой анализ покажет причину?", subtitle:"Ферритин, витамин D, щитовидная или что-то ещё — разбираемся"}',
     'fields', JSON_ARRAY('title', 'subtitle')
                      ), 1, 1),

(9, 'story_block', 'История поиска причины', JSON_OBJECT(
     'hint', 'variant:"patient_story". История человека, который ДОЛГО ИСКАЛ причину симптома — ходил к разным врачам, сдавал анализы. lead: «Год в поисках причины». Эмпатия + мотивация сдать анализы.',
     'fields', JSON_ARRAY('variant', 'icon', 'accent_color', 'lead', 'text', 'highlight', 'footnote')
                                          ), 2, 1),

(9, 'richtext', 'Почему одного анализа мало', JSON_OBJECT(
     'hint', 'ПЛАН (мин. 6 подблоков):
1. H2 «[Симптом]: 5 возможных причин» → paragraph
2. list: перечень причин с кратким пояснением
3. highlight: «Один анализ может не показать проблему»
4. H3 «Как разобраться» → paragraph: стратегия диагностики',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight', 'quote')
                                           ), 3, 1),

(9, 'comparison_table', 'Сравнение причин', JSON_OBJECT(
     'hint', 'Таблица: ПРИЧИНЫ СИМПТОМА В СРАВНЕНИИ. headers: [«Параметр», «Причина 1», «Причина 2», «Причина 3»]. Строки: какой анализ сдать, типичное значение при проблеме, доп. симптомы, к какому врачу, скорость восстановления. Мин. 5 строк, 3-4 причины. title: «[Симптом]: возможные причины». description: 1-2 предложения.',
     'fields', JSON_ARRAY('title', 'description', 'headers', 'rows')
                                         ), 4, 1),

(9, 'symptom_checklist', 'Сузьте круг причин', JSON_OBJECT(
     'hint', 'Тест, который помогает ОПРЕДЕЛИТЬ НАИБОЛЕЕ ВЕРОЯТНУЮ причину. Группы = причины. weight по 1-2. Пороги: «Скорее всего причина A» / «Нужна комплексная проверка» / «Возможны несколько причин». subtitle: «Отметьте все симптомы, которые вы наблюдаете».',
     'fields', JSON_ARRAY('title', 'subtitle', 'items', 'thresholds', 'cta_text', 'cta_link_key'),
     'items_fields', JSON_ARRAY('text', 'weight', 'group'),
     'threshold_fields', JSON_ARRAY('min', 'max', 'label', 'color', 'text')
                                            ), 5, 1),

(9, 'richtext', 'Мостик к анализам', JSON_OBJECT(
     'hint', '2-3 предложения: «Вот минимальный набор анализов, который поможет найти причину.»',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph')
                                  ), 6, 0),

(9, 'spark_metrics', 'Какие анализы сдать', JSON_OBJECT(
     'hint', '3-4 карточки: ОСНОВНЫЕ АНАЛИЗЫ при этом симптоме. Каждая: icon(эмодзи), name(название анализа), value(типичная норма), unit, details([[«Что покажет», «...»],[«Цена», «от X ₽»],[«Срок», «1 день»]]). points: типичная динамика.',
     'fields', JSON_ARRAY('title', 'items'),
     'items_fields', JSON_ARRAY('icon', 'icon_bg', 'name', 'value', 'unit', 'trend', 'trend_up', 'points', 'color', 'details')
                                         ), 7, 1),

(9, 'warning_block', 'Красные флаги', JSON_OBJECT(
     'hint', 'variant:"red_flags". Когда [симптом] требует СРОЧНОЙ диагностики. 3-5 пунктов.',
     'fields', JSON_ARRAY('variant', 'title', 'subtitle', 'items', 'footer'),
     'items_fields', JSON_ARRAY('text', 'severity')
                                   ), 8, 1),

(9, 'expert_panel', 'Совет врача', JSON_OBJECT(
     'hint', 'Врач о стратегии: «С чего начать обследование при [симптоме]». Какой анализ сдать первым.',
     'fields', JSON_ARRAY('name', 'credentials', 'experience', 'photo_placeholder', 'text', 'highlight')
                                ), 9, 1),

(9, 'faq', 'FAQ', JSON_OBJECT(
     'hint', '4-6 вопросов: «Какой анализ сдать первым?», «Можно ли всё сразу?», «К какому врачу?», «Сколько стоит комплекс?».',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
               ), 10, 1),

(9, 'cta', 'CTA', JSON_OBJECT(
     'hint', '{title:"Найдите причину [симптома]", text:"Комплексная проверка — все анализы за один визит", primary_btn_text:"Заказать комплекс", primary_btn_link_key:"ORDER_ANALYSIS"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')
               ), 11, 1);


-- ═══════════════════════════════════════════════════════
--  INTENT: diagnosis_interpret
-- Человек УЖЕ сдал анализ, держит бумажку, не понимает цифры.
-- Тон: спокойный, разъясняющий, без паники.
-- ═══════════════════════════════════════════════════════


-- ───────────────────────────────────────────────────────
-- ШАБЛОН 23: «Расшифровка результата — полная»
-- hero → key_takeaways → value_checker → richtext
-- → norms_table → richtext-мостик → gauge_chart
-- → info_cards(причины отклонений) → numbered_steps
-- → expert_panel → faq → cta
-- ───────────────────────────────────────────────────────

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (10, 'Расшифровка результата — полная',
     'diagnosis-full',
     'Человек держит бумажку с результатом. Полная расшифровка: проверка значения, нормы, причины, план.',
     'Ты — медицинский копирайтер. Генерируй JSON для статьи-РАСШИФРОВКИ. Читатель УЖЕ СДАЛ АНАЛИЗ и не понимает цифры.
    ТОН: спокойный, разъясняющий. БЕЗ ПАНИКИ. Даже если значение критическое — объясняй без драматизации, но с ясными рекомендациями.
    СТРУКТУРА: «Вот ваше число» → что оно значит → норма для вас → возможные причины → что делать.
    ГЛАВНОЕ: value_checker ИДЁТ ПЕРВЫМ (после hero) — читатель пришёл с ЧИСЛОМ в руках, дайте ему сразу проверить.
    ПРАВИЛО ТЕРРИТОРИЙ:
    - value_checker: ПЕРВЫМ ДЕЛОМ — проверка значения. Подробные пояснения в каждой зоне.
    - key_takeaways: САММАРИ расшифровки.
    - richtext: КАК ИНТЕРПРЕТИРОВАТЬ — контекст, ложноположительные, ложноотрицательные. НЕ нормы.
    - norms_table: НОРМЫ по полу/возрасту с подробными states.
    - gauge_chart: СВЯЗАННЫЕ ПОКАЗАТЕЛИ — что ещё посмотреть в бланке.
    - info_cards: ПРИЧИНЫ ОТКЛОНЕНИЙ — повышение и понижение. НЕ нормы.
    - numbered_steps: ЧТО ДЕЛАТЬ — от получения результата до визита к врачу.
    - expert_panel: МНЕНИЕ о типичных ошибках интерпретации.
    For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    ФОРМАТЫ:
    hero: ПЛОСКИЙ {title, subtitle, cta_text?, cta_link_key?}
    value_checker: {title, description, input_label, input_placeholder, zones:[{key, from, to, color, label, icon, text}], disclaimer}
    key_takeaways: {title, items:[строки], style:"numbered"}
    richtext: {blocks:[{type:"heading"|"paragraph"|"list"|"highlight"|"quote", ...}]}
    norms_table: {caption, rows:[{name, unit, active, states:[{key, label, range, pct, description}]}]}
    gauge_chart: {title, items:[{name, value, min, max, unit, color, description}]}
    info_cards: {title, layout:"grid-3", items:[{icon, title, text, color}]}
    numbered_steps: {title, steps:[{number, title, text, tip, duration}]}
    expert_panel: {name, credentials, experience, photo_placeholder, text, highlight}
    faq: {items:[{question, answer}]}
    cta: ПЛОСКИЙ {title, text, primary_btn_text, primary_btn_link_key}',
     'tpl-diagnosis-full');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(10, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', 'H1 обращается к человеку С РЕЗУЛЬТАТОМ. ✗ «Что такое ферритин» → ✓ «Ферритин: расшифровка результата анализа». subtitle: «Введите своё значение — мы объясним, что оно означает и что делать дальше». ПЛОСКИЙ: {title, subtitle}',
     'fields', JSON_ARRAY('title', 'subtitle')
                       ), 1, 1),

(10, 'value_checker', 'Проверьте свой результат', JSON_OBJECT(
     'hint', 'ПЕРВЫЙ ИНТЕРАКТИВ — читатель пришёл с числом! 5 зон с ПОДРОБНЫМИ пояснениями: не просто «понижен», а конкретно — что это значит, насколько серьёзно, к какому врачу. title: «Введите ваш результат». description: «Укажите значение из бланка анализа». ПРАВИЛО ЦВЕТОВ И ИКОНОК: critical_low → color:"#DC2626", icon:"🔴"; low → color:"#F97316", icon:"🟠"; optimal → color:"#16A34A", icon:"🟢"; elevated → color:"#F97316", icon:"🟠"; high → color:"#DC2626", icon:"🔴". Всегда используй emoji-иконку в поле icon, никогда не пиши слова вместо иконок.',
     'fields', JSON_ARRAY('title', 'description', 'input_label', 'input_placeholder', 'zones', 'disclaimer'),
     'zone_fields', JSON_ARRAY('key', 'from', 'to', 'color', 'label', 'icon', 'text')
                                               ), 2, 1),

(10, 'key_takeaways', 'Как читать результат', JSON_OBJECT(
     'hint', '5 пунктов о РАСШИФРОВКЕ: на что смотреть, чему не доверять, что значат пометки лаборатории, когда «в норме» ≠ хорошо, какие ещё анализы сдать. {title:"5 правил расшифровки [показателя]", style:"numbered"}',
     'fields', JSON_ARRAY('title', 'items', 'style')
                                           ), 3, 1),

(10, 'richtext', 'Контекст расшифровки', JSON_OBJECT(
     'hint', 'ПЛАН (мин. 8 подблоков):
1. H2 «Что показывает [показатель]» → paragraph: кратко (читатель уже знает зачем сдавал)
2. H3 «Почему лабораторная норма может вводить в заблуждение» → paragraph: широкие диапазоны, разные лаборатории
3. highlight: «Ферритин 15 формально «в норме», но клинически — уже дефицит»
4. H3 «Что ещё посмотреть в бланке» → list: сопутствующие показатели
5. H2 «Когда нужно пересдать» → paragraph: ложные результаты, влияние болезней
НЕ ПИШИ числовые нормы — для этого norms_table.',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight', 'quote')
                                      ), 4, 1),

(10, 'norms_table', 'Нормы по полу и возрасту', JSON_OBJECT(
     'hint', 'ПОДРОБНЫЕ нормы. 4-5 групп. В description для каждого state — «Если ваш результат в этой зоне: [конкретное действие]». caption: «Нормы [показателя]: найдите свою группу». ПРАВИЛО pct: для states с понижением/дефицитом — pct:10; для нормы — pct:100; для умеренного превышения — pct:60; для высокого превышения — pct:30. pct отражает заполненность шкалы на UI.',
     'fields', JSON_ARRAY('caption', 'rows'),
     'row_fields', JSON_ARRAY('name', 'unit', 'active', 'states')
                                             ), 5, 1),

(10, 'richtext', 'Мостик к связанным показателям', JSON_OBJECT(
     'hint', '2-3 предложения: «Один показатель не даёт полной картины. Вот что ещё стоит проверить.»',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph')
                                                ), 6, 0),

(10, 'gauge_chart', 'Что ещё проверить', JSON_OBJECT(
     'hint', '2-3 СВЯЗАННЫХ показателя из того же бланка или дополнительных. title: «Полная картина: смежные анализы». description: что каждый добавляет к расшифровке.',
     'fields', JSON_ARRAY('title', 'items'),
     'items_fields', JSON_ARRAY('name', 'value', 'min', 'max', 'unit', 'color', 'description')
                                      ), 7, 1),

(10, 'info_cards', 'Причины отклонений', JSON_OBJECT(
     'hint', '6 карточек: 3 причины ПОВЫШЕНИЯ + 3 причины ПОНИЖЕНИЯ. Конкретные: не «болезни», а «воспаление (ОРВИ, инфекция)», «приём препаратов железа». {title:"Почему [показатель] может быть повышен или понижен", layout:"grid-3"}',
     'fields', JSON_ARRAY('title', 'layout', 'items'),
     'items_fields', JSON_ARRAY('icon', 'title', 'text', 'color')
                                      ), 8, 1),

(10, 'numbered_steps', 'Что делать с результатом', JSON_OBJECT(
     'hint', '4-5 шагов ПОСЛЕ ПОЛУЧЕНИЯ результата. 1) Не паниковать, 2) Сверить с нормами для своей группы, 3) Проверить сопутствующие показатели, 4) Записаться к врачу, 5) Подготовить вопросы для визита.',
     'fields', JSON_ARRAY('title', 'steps'),
     'steps_fields', JSON_ARRAY('number', 'title', 'text', 'tip', 'duration')
                                                ), 9, 1),

(10, 'expert_panel', 'Ошибки интерпретации', JSON_OBJECT(
     'hint', 'Врач о ТИПИЧНЫХ ОШИБКАХ пациентов при расшифровке. «Не гуглите диагноз по одному числу», «Нормы в интернете могут отличаться от вашей лаборатории». highlight: ключевая ошибка.',
     'fields', JSON_ARRAY('name', 'credentials', 'experience', 'photo_placeholder', 'text', 'highlight')
                                          ), 10, 1),

(10, 'faq', 'FAQ', JSON_OBJECT(
     'hint', '5-7 вопросов О РАСШИФРОВКЕ: «Мой результат на границе нормы — это нормально?», «Почему в разных лабораториях разные нормы?», «Нужно ли пересдать?», «Могла ли еда повлиять?».',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
                ), 11, 1),

(10, 'cta', 'CTA', JSON_OBJECT(
     'hint', '{title:"Нужна расшифровка?", text:"Врач объяснит ваш результат", primary_btn_text:"Консультация", primary_btn_link_key:"CONSULTATION", secondary_btn_text:"Пересдать анализ", secondary_btn_link_key:"ORDER_ANALYSIS"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key', 'secondary_btn_text', 'secondary_btn_link_key')
                ), 12, 1);


-- ───────────────────────────────────────────────────────
-- ШАБЛОН 24: «Расшифровка — конкретное значение»
-- Для запросов «ферритин 5 что значит», «ферритин 300 опасно ли».
-- hero → value_checker → story_block → richtext(короткий)
-- → norms_table → warning_block → numbered_steps
-- → expert_panel → faq → cta
-- ───────────────────────────────────────────────────────

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (11, 'Расшифровка — конкретное значение',
     'diagnosis-value',
     'Для запросов с конкретным числом: «ферритин 5 что значит». Value checker сразу, потом объяснение.',
     'Ты — медицинский копирайтер. Статья для человека, который ГУГЛИТ КОНКРЕТНОЕ ЧИСЛО: «ферритин 5», «ферритин 300».
    ТОН: прямой, без воды. Человек хочет БЫСТРЫЙ ОТВЕТ: «это нормально или нет?»
    СТРУКТУРА: проверьте число → это значит вот что → история похожего случая → что делать.
    ПРАВИЛО: value_checker ПЕРВЫМ. Все блоки КОМПАКТНЫЕ.
    For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    ФОРМАТЫ:
    hero: ПЛОСКИЙ {title, subtitle, cta_text?, cta_link_key?}
    value_checker: {title, description, input_label, input_placeholder, zones:[{key, from, to, color, label, icon, text}], disclaimer}
    story_block: {variant:"patient_story", icon, accent_color, lead, text, highlight, footnote}
    richtext: {blocks:[{type:"heading"|"paragraph"|"list"|"highlight"|"quote", ...}]}
    norms_table: {caption, rows:[{name, unit, active, states:[{key, label, range, pct, description}]}]}
    warning_block: {variant:"red_flags"|"caution", title, subtitle, items:[{text, severity:"urgent"|"emergency"|"warning"}], footer}
    numbered_steps: {title, steps:[{number, title, text, tip, duration}]}
    expert_panel: {name, credentials, experience, photo_placeholder, text, highlight}
    faq: {items:[{question, answer}]}
    cta: ПЛОСКИЙ {title, text, primary_btn_text, primary_btn_link_key}',
     'tpl-diagnosis-value');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(11, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', 'H1 содержит ПАТТЕРН ЧИСЛА. {title:"[Показатель] [значение] [единица]: что это значит?", subtitle:"Проверьте своё значение и узнайте, нужно ли обращаться к врачу"}',
     'fields', JSON_ARRAY('title', 'subtitle')
                       ), 1, 1),

(11, 'value_checker', 'Проверка', JSON_OBJECT(
     'hint', 'СРАЗУ ПОСЛЕ HERO. 5 зон с максимально ПОДРОБНЫМИ пояснениями. Каждая зона: что значит, насколько серьёзно, что делать ПРЯМО СЕЙЧАС, к какому врачу. ПРАВИЛО ЦВЕТОВ И ИКОНОК: critical_low → color:"#DC2626", icon:"🔴"; low → color:"#F97316", icon:"🟠"; optimal → color:"#16A34A", icon:"🟢"; elevated → color:"#F97316", icon:"🟠"; high → color:"#DC2626", icon:"🔴". Всегда используй emoji-иконку в поле icon, никогда не пиши слова вместо иконок.',
     'fields', JSON_ARRAY('title', 'description', 'input_label', 'input_placeholder', 'zones', 'disclaimer'),
     'zone_fields', JSON_ARRAY('key', 'from', 'to', 'color', 'label', 'icon', 'text')
                               ), 2, 1),

(11, 'story_block', 'Похожий случай', JSON_OBJECT(
     'hint', 'variant:"patient_story". КОРОТКАЯ история с ПОХОЖИМ значением: «У Марии тоже был ферритин 5. Вот что случилось.» 2-3 предложения. highlight: значение и исход.',
     'fields', JSON_ARRAY('variant', 'icon', 'accent_color', 'lead', 'text', 'highlight', 'footnote')
                                   ), 3, 1),

(11, 'richtext', 'Что означает это значение', JSON_OBJECT(
     'hint', 'КОМПАКТНЫЙ ТЕКСТ, мин. 5 подблоков:
1. H2 «Что означает [показатель] [значение]» → paragraph: прямой ответ
2. H3 «Возможные причины такого результата» → list: 3-5 причин
3. highlight: главное
4. H3 «Что делать дальше» → paragraph: конкретные шаги',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight')
                                           ), 4, 1),

(11, 'norms_table', 'Нормы для сравнения', JSON_OBJECT(
     'hint', 'КОМПАКТНАЯ таблица: 2-3 группы (мужчины, женщины, дети). Чтобы человек увидел, куда попадает его значение. caption: «Найдите свою группу». ПРАВИЛО pct: для states с понижением/дефицитом — pct:10; для нормы — pct:100; для умеренного превышения — pct:60; для высокого превышения — pct:30. pct отражает заполненность шкалы на UI.',
     'fields', JSON_ARRAY('caption', 'rows'),
     'row_fields', JSON_ARRAY('name', 'unit', 'active', 'states')
                                        ), 5, 1),

(11, 'warning_block', 'Когда это опасно', JSON_OBJECT(
     'hint', 'variant:"red_flags". КОНКРЕТНО для этого диапазона значений. 3-4 пункта: когда результат указывает на серьёзную проблему.',
     'fields', JSON_ARRAY('variant', 'title', 'subtitle', 'items', 'footer'),
     'items_fields', JSON_ARRAY('text', 'severity')
                                       ), 6, 1),

(11, 'numbered_steps', 'Ваши следующие шаги', JSON_OBJECT(
     'hint', '3-4 КОНКРЕТНЫХ шага после получения этого результата. Без лишнего.',
     'fields', JSON_ARRAY('title', 'steps'),
     'steps_fields', JSON_ARRAY('number', 'title', 'text', 'tip', 'duration')
                                           ), 7, 1),

(11, 'expert_panel', 'Мнение врача', JSON_OBJECT(
     'hint', 'КОРОТКИЙ комментарий: «Такое значение я вижу у N% пациентов, обычно это означает...»',
     'fields', JSON_ARRAY('name', 'credentials', 'experience', 'photo_placeholder', 'text', 'highlight')
                                  ), 8, 1),

(11, 'faq', 'FAQ', JSON_OBJECT(
     'hint', '4-5 вопросов ПРИВЯЗАННЫХ К ДИАПАЗОНУ ЗНАЧЕНИЯ: «Нужна ли госпитализация?», «Может ли это быть ошибкой?», «Через сколько пересдать?».',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
                ), 9, 1),

(11, 'cta', 'CTA', JSON_OBJECT(
     'hint', '{title:"Разберитесь в результате", text:"Врач объяснит, что означает ваше значение", primary_btn_text:"Консультация", primary_btn_link_key:"CONSULTATION"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')
                ), 10, 1);


-- ───────────────────────────────────────────────────────
-- ШАБЛОН 25: «Расшифровка — повышен/понижен»
-- Для запросов «ферритин повышен что делать»
-- hero → key_takeaways → richtext → norms_table
-- → info_cards(причины) → richtext-мостик → before_after
-- → warning_block → numbered_steps → expert_panel → faq → cta
-- ───────────────────────────────────────────────────────

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (12, 'Расшифровка — повышен/понижен',
     'diagnosis-direction',
     'Для запросов «повышен/понижен что делать». Фокус на причинах отклонения и плане коррекции.',
     'Ты — медицинский копирайтер. Статья для человека, чей результат ПОВЫШЕН или ПОНИЖЕН. Он уже знает направление отклонения.
    ТОН: спокойный, конструктивный. «Да, отклонение есть — вот что с этим делать.»
    СТРУКТУРА: что значит отклонение → почему так → насколько серьёзно → план действий → ожидаемый результат.
    ПРАВИЛО ТЕРРИТОРИЙ:
    - key_takeaways: 5 КЛЮЧЕВЫХ фактов об этом отклонении.
    - richtext: ОБЪЯСНЕНИЕ отклонения — механизм, почему так.
    - norms_table: НОРМЫ — чтобы понять, насколько отклонение велико.
    - info_cards: ПРИЧИНЫ отклонения (6 карточек). КОНКРЕТНЫЕ, не абстрактные.
    - before_after: ОЖИДАЕМАЯ ДИНАМИКА при лечении — до и после.
    - warning_block: КОГДА ОТКЛОНЕНИЕ ОПАСНО.
    - numbered_steps: ПЛАН КОРРЕКЦИИ.
    - expert_panel: МНЕНИЕ ВРАЧА.
    For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    ФОРМАТЫ:
    hero: ПЛОСКИЙ {title, subtitle, cta_text?, cta_link_key?}
    key_takeaways: {title, items:[строки], style:"numbered"}
    richtext: {blocks:[{type:"heading"|"paragraph"|"list"|"highlight"|"quote", ...}]}
    norms_table: {caption, rows:[{name, unit, active, states:[{key, label, range, pct, description}]}]}
    info_cards: {title, layout:"grid-3", items:[{icon, title, text, color}]}
    before_after: {title, metrics:[{name, before, after, unit, max}]}
    warning_block: {variant:"red_flags"|"caution", title, subtitle, items:[{text, severity:"urgent"|"emergency"|"warning"}], footer}
    numbered_steps: {title, steps:[{number, title, text, tip, duration}]}
    expert_panel: {name, credentials, experience, photo_placeholder, text, highlight}
    faq: {items:[{question, answer}]}
    cta: ПЛОСКИЙ {title, text, primary_btn_text, primary_btn_link_key}',
     'tpl-diagnosis-direction');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(12, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', 'H1 содержит НАПРАВЛЕНИЕ отклонения. {title:"[Показатель] повышен/понижен: причины и что делать", subtitle:"Разбираем, почему так вышло, насколько серьёзно и как вернуть в норму"}',
     'fields', JSON_ARRAY('title', 'subtitle')
                       ), 1, 1),

(12, 'key_takeaways', 'Главное об отклонении', JSON_OBJECT(
     'hint', '5 пунктов КОНКРЕТНО об этом отклонении: что значит, главные причины, насколько серьёзно, что делать, сколько ждать нормализации. {title:"[Показатель] [повышен/понижен]: ключевое"}',
     'fields', JSON_ARRAY('title', 'items', 'style')
                                            ), 2, 1),

(12, 'richtext', 'Что это значит', JSON_OBJECT(
     'hint', 'ПЛАН (мин. 8 подблоков):
1. H2 «Что значит [повышенный/пониженный] [показатель]» → paragraph: объяснение
2. H3 «Механизм отклонения» → paragraph: биохимия просто
3. highlight: «[X]% случаев [повышения/понижения] вызваны [причиной]»
4. H3 «Ложное отклонение» → paragraph: когда результат неточен
5. H2 «Степени отклонения» → paragraph: лёгкое/умеренное/тяжёлое
НЕ ПИШИ числовые нормы — для этого norms_table.',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight', 'quote')
                                ), 3, 1),

(12, 'norms_table', 'Нормы и степени отклонения', JSON_OBJECT(
     'hint', 'states должны отражать СТЕПЕНИ ОТКЛОНЕНИЯ: лёгкое, умеренное, тяжёлое + норма. description в каждом state: что делать при такой степени. 3-5 групп по полу/возрасту. ПРАВИЛО pct: для states с понижением/дефицитом — pct:10; для нормы — pct:100; для умеренного превышения — pct:60; для высокого превышения — pct:30. pct отражает заполненность шкалы на UI.',
     'fields', JSON_ARRAY('caption', 'rows'),
     'row_fields', JSON_ARRAY('name', 'unit', 'active', 'states')
                                               ), 4, 1),

(12, 'info_cards', 'Причины отклонения', JSON_OBJECT(
     'hint', '6 карточек: КОНКРЕТНЫЕ причины этого отклонения. Не «заболевания» абстрактно, а конкретно: «Обильные менструации», «Приём аспирина», «Вегетарианство», «Хроническое воспаление». {title:"Причины [повышения/понижения] [показателя]", layout:"grid-3"}',
     'fields', JSON_ARRAY('title', 'layout', 'items'),
     'items_fields', JSON_ARRAY('icon', 'title', 'text', 'color')
                                      ), 5, 1),

(12, 'richtext', 'Мостик к динамике', JSON_OBJECT(
     'hint', '2-3 предложения: «Хорошая новость — в большинстве случаев [показатель] можно вернуть в норму. Вот как меняются значения при лечении.»',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph')
                                   ), 6, 0),

(12, 'before_after', 'Динамика при лечении', JSON_OBJECT(
     'hint', 'РЕАЛИСТИЧНЫЕ значения ДО и ПОСЛЕ коррекции. title: «Типичный результат за 3 месяца». 3-5 показателей (основной + сопутствующие). Числа медицински корректны. max > before и after.',
     'fields', JSON_ARRAY('title', 'metrics'),
     'metric_fields', JSON_ARRAY('name', 'before', 'after', 'unit', 'max')
                                          ), 7, 1),

(12, 'warning_block', 'Когда отклонение опасно', JSON_OBJECT(
     'hint', 'variant:"red_flags". КОНКРЕТНО для этого направления отклонения. 3-5 пунктов: критические значения, сочетание с другими симптомами. severity: urgent/emergency.',
     'fields', JSON_ARRAY('variant', 'title', 'subtitle', 'items', 'footer'),
     'items_fields', JSON_ARRAY('text', 'severity')
                                              ), 8, 1),

(12, 'numbered_steps', 'План коррекции', JSON_OBJECT(
     'hint', '4-5 шагов ВОЗВРАЩЕНИЯ В НОРМУ. Конкретный план: питание → добавки → контроль → когда к врачу. tip в каждом шаге.',
     'fields', JSON_ARRAY('title', 'steps'),
     'steps_fields', JSON_ARRAY('number', 'title', 'text', 'tip', 'duration')
                                      ), 9, 1),

(12, 'expert_panel', 'Мнение врача', JSON_OBJECT(
     'hint', 'Врач о ТИПИЧНЫХ СЛУЧАЯХ этого отклонения: «Чаще всего я вижу такой результат у [группы], и причина — [X].»',
     'fields', JSON_ARRAY('name', 'credentials', 'experience', 'photo_placeholder', 'text', 'highlight')
                                  ), 10, 1),

(12, 'faq', 'FAQ', JSON_OBJECT(
     'hint', '5-7 вопросов ОБ ОТКЛОНЕНИИ: «Может ли нормализоваться сам?», «Какие препараты?», «Диета поможет?», «Через сколько пересдать?», «Опасно ли откладывать лечение?».',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
                ), 11, 1),

(12, 'cta', 'CTA', JSON_OBJECT(
     'hint', '{title:"Верните [показатель] в норму", text:"Начните с точной диагностики", primary_btn_text:"Расшифровать анализы", primary_btn_link_key:"ORDER_ANALYSIS"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')
                ), 12, 1);


-- ═══════════════════════════════════════════════════════
--  INTENT: risk_assessment
-- Главный вопрос: «Насколько это серьёзно? Стоит ли паниковать?»
-- Тон: взвешенный, без драматизации и без обесценивания.
-- ═══════════════════════════════════════════════════════


-- ───────────────────────────────────────────────────────
-- ШАБЛОН 26: «Оценка рисков — полная»
-- hero → key_takeaways → value_checker → richtext
-- → verdict_card(мифы об опасности) → norms_table
-- → info_cards(когда опасно / когда нет) → warning_block
-- → numbered_steps → expert_panel → faq → cta
-- ───────────────────────────────────────────────────────

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (13, 'Оценка рисков — полная',
     'risk-full',
     'Человек спрашивает «опасно ли?». Полная оценка: от паники к ясности.',
     'Ты — медицинский копирайтер. Генерируй JSON для статьи-ОЦЕНКИ РИСКОВ. Читатель НАПУГАН и спрашивает: «опасно ли?», «чем грозит?».
    ТОН: ВЗВЕШЕННЫЙ. Не драматизируй — но и не обесценивай тревогу. «Давайте спокойно разберёмся.»
    СТРУКТУРА: «Давайте без паники» → проверьте значение → когда это НЕ опасно → когда ОПАСНО → мифы об опасности → что делать.
    ПРАВИЛО ТЕРРИТОРИЙ:
    - key_takeaways: 5 КЛЮЧЕВЫХ фактов о рисках.
    - value_checker: ПРОВЕРКА — насколько серьёзно конкретное значение.
    - richtext: КОНТЕКСТ — от чего зависит серьёзность. НЕ нормы.
    - verdict_card: МИФЫ ОБ ОПАСНОСТИ — развеивает страхи. «Ферритин 300 = рак?» → МИФ.
    - norms_table: НОРМЫ — чтобы понять уровень риска.
    - info_cards: КОГДА ОПАСНО / КОГДА НЕТ — визуальное разграничение.
    - warning_block: НАСТОЯЩИЕ КРАСНЫЕ ФЛАГИ (не мифические).
    - numbered_steps: ЧТО ДЕЛАТЬ, чтобы снизить риск.
    - expert_panel: МНЕНИЕ ВРАЧА — успокаивающее, но честное.
    For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    ФОРМАТЫ:
    verdict_card: {title, items:[{claim, verdict:"myth"|"truth"|"partial", explanation, source}]}',
     'tpl-risk-full');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(13, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', 'H1 отвечает на ВОПРОС «опасно ли?». {title:"[Показатель] [повышен/понижен] — это опасно?", subtitle:"Разбираемся без паники: когда стоит беспокоиться, а когда — нет"}',
     'fields', JSON_ARRAY('title', 'subtitle')
                       ), 1, 1),

(13, 'key_takeaways', 'Главное о рисках', JSON_OBJECT(
     'hint', '5 УСПОКАИВАЮЩИХ, но ЧЕСТНЫХ пунктов. Баланс: «чаще всего не опасно» + «но бывает серьёзно». {title:"[Показатель] [повышен/понижен]: стоит ли паниковать?", style:"numbered"}',
     'fields', JSON_ARRAY('title', 'items', 'style')
                                       ), 2, 1),

(13, 'value_checker', 'Насколько серьёзно ваше значение', JSON_OBJECT(
     'hint', '5 зон: от «норма, не беспокойтесь» до «требуется срочная помощь». Тон каждой зоны — ВЗВЕШЕННЫЙ: в зелёной не обесценивай, в красной не паникуй. description: «Введите значение — мы скажем, стоит ли волноваться». ПРАВИЛО ЦВЕТОВ И ИКОНОК: critical_low → color:"#DC2626", icon:"🔴"; low → color:"#F97316", icon:"🟠"; optimal → color:"#16A34A", icon:"🟢"; elevated → color:"#F97316", icon:"🟠"; high → color:"#DC2626", icon:"🔴". Всегда используй emoji-иконку в поле icon, никогда не пиши слова вместо иконок.',
     'fields', JSON_ARRAY('title', 'description', 'input_label', 'input_placeholder', 'zones', 'disclaimer'),
     'zone_fields', JSON_ARRAY('key', 'from', 'to', 'color', 'label', 'icon', 'text')
                                                       ), 3, 1),

(13, 'richtext', 'От чего зависит опасность', JSON_OBJECT(
     'hint', 'ПЛАН (мин. 8 подблоков):
1. H2 «От чего зависит, опасно ли [значение]» → paragraph: контекст решает
2. H3 «Когда это просто временная реакция» → list: 3-4 безобидных причины
3. H3 «Когда это сигнал проблемы» → list: 3-4 серьёзных причины
4. highlight: «Само по себе число — не диагноз. Важен контекст.»
5. H3 «Какие дополнительные анализы нужны» → list: что проверить для полной картины',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight', 'quote')
                                           ), 4, 1),

(13, 'verdict_card', 'Мифы об опасности', JSON_OBJECT(
     'hint', '3-4 карточки: РАСПРОСТРАНЁННЫЕ СТРАХИ, связанные с показателем. Вердикты: myth/truth/partial. Примеры: «Высокий ферритин = онкология» → partial, «Низкий ферритин — это не страшно» → myth. Explanation: спокойное разъяснение. source: ссылка на исследование.',
     'fields', JSON_ARRAY('title', 'items'),
     'items_fields', JSON_ARRAY('claim', 'verdict', 'explanation', 'source')
                                       ), 5, 1),

(13, 'norms_table', 'Шкала риска', JSON_OBJECT(
     'hint', 'states отражают УРОВНИ РИСКА, а не просто нормы: «Минимальный риск», «Умеренный риск», «Высокий риск», «Критический». description: что именно грозит на каждом уровне и что делать. caption: «Уровни риска при отклонении [показателя]». ПРАВИЛО pct: для states с понижением/дефицитом — pct:10; для нормы — pct:100; для умеренного превышения — pct:60; для высокого превышения — pct:30. pct отражает заполненность шкалы на UI.',
     'fields', JSON_ARRAY('caption', 'rows'),
     'row_fields', JSON_ARRAY('name', 'unit', 'active', 'states')
                                ), 6, 1),

(13, 'info_cards', 'Опасно vs не опасно', JSON_OBJECT(
     'hint', '6 карточек: 3 «КОГДА НЕ ОПАСНО» (зелёный цвет) + 3 «КОГДА ОПАСНО» (красный цвет). Конкретные ситуации, не абстрактные. {title:"Когда беспокоиться, а когда — нет", layout:"grid-3"}',
     'fields', JSON_ARRAY('title', 'layout', 'items'),
     'items_fields', JSON_ARRAY('icon', 'title', 'text', 'color')
                                       ), 7, 1),

(13, 'warning_block', 'Настоящие красные флаги', JSON_OBJECT(
     'hint', 'variant:"red_flags". РЕАЛЬНЫЕ экстренные ситуации (не мифические). title: «Когда действительно нужна срочная помощь». 3-5 пунктов. Контраст с verdict_card: там мифы, здесь реальность.',
     'fields', JSON_ARRAY('variant', 'title', 'subtitle', 'items', 'footer'),
     'items_fields', JSON_ARRAY('text', 'severity')
                                              ), 8, 1),

(13, 'numbered_steps', 'Как снизить риск', JSON_OBJECT(
     'hint', '4-5 шагов: от диагностики к снижению риска. Практичные, без воды.',
     'fields', JSON_ARRAY('title', 'steps'),
     'steps_fields', JSON_ARRAY('number', 'title', 'text', 'tip', 'duration')
                                        ), 9, 1),

(13, 'expert_panel', 'Мнение врача', JSON_OBJECT(
     'hint', 'УСПОКАИВАЮЩЕЕ, но ЧЕСТНОЕ мнение: «Большинство моих пациентов с [значением] успешно нормализуют показатель за N месяцев. Но есть случаи, когда нужно быстро действовать.»',
     'fields', JSON_ARRAY('name', 'credentials', 'experience', 'photo_placeholder', 'text', 'highlight')
                                  ), 10, 1),

(13, 'faq', 'FAQ', JSON_OBJECT(
     'hint', '5-7 вопросов О РИСКАХ: «Это может быть рак?», «Чем грозит если не лечить?», «Через сколько станет опасно?», «Могу ли я умереть?», «Это наследственное?». ЧЕСТНЫЕ ответы без паники.',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
                ), 11, 1),

(13, 'cta', 'CTA', JSON_OBJECT(
     'hint', '{title:"Оцените свой риск точно", text:"Врач проанализирует результат в контексте вашего здоровья", primary_btn_text:"Консультация врача", primary_btn_link_key:"CONSULTATION"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')
                ), 12, 1);


-- ───────────────────────────────────────────────────────
-- ШАБЛОН 27: «Оценка рисков — компактная»
-- Быстрый ответ: опасно или нет + что делать.
-- hero → value_checker → verdict_card → key_takeaways
-- → warning_block → expert_panel → faq → cta
-- ───────────────────────────────────────────────────────

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (14, 'Оценка рисков — компактная',
     'risk-compact',
     'Быстрый ответ: введи число, узнай опасно ли, развей мифы, получи план.',
     'Ты — медицинский копирайтер. БЫСТРЫЙ ОТВЕТ на вопрос «опасно ли?».
    ТОН: прямой, взвешенный. Минимум текста — максимум пользы.
    For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    СТРУКТУРА: проверь число → мифы → главное → когда опасно → что делать.
    ФОРМАТЫ:
    hero: ПЛОСКИЙ {title, subtitle, cta_text?, cta_link_key?}
    value_checker: {title, description, input_label, input_placeholder, zones:[{key, from, to, color, label, icon, text}], disclaimer}
    verdict_card: {title, items:[{claim, verdict:"myth"|"truth"|"partial", explanation, source}]}
    key_takeaways: {title, items:[строки], style:"numbered"}
    warning_block: {variant:"red_flags"|"caution", title, subtitle, items:[{text, severity:"urgent"|"emergency"|"warning"}], footer}
    expert_panel: {name, credentials, experience, photo_placeholder, text, highlight}
    faq: {items:[{question, answer}]}
    cta: ПЛОСКИЙ {title, text, primary_btn_text, primary_btn_link_key}',
     'tpl-risk-compact');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(14, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', '{title:"[Показатель] [значение]: опасно ли это?", subtitle:"Быстрая проверка и план действий"}',
     'fields', JSON_ARRAY('title', 'subtitle')
                       ), 1, 1),

(14, 'value_checker', 'Проверка', JSON_OBJECT(
     'hint', 'СРАЗУ ПОСЛЕ HERO. Подробные зоны с ОЦЕНКОЙ РИСКА (не просто норма/не норма, а безопасно/настораживает/опасно). ПРАВИЛО ЦВЕТОВ И ИКОНОК: critical_low → color:"#DC2626", icon:"🔴"; low → color:"#F97316", icon:"🟠"; optimal → color:"#16A34A", icon:"🟢"; elevated → color:"#F97316", icon:"🟠"; high → color:"#DC2626", icon:"🔴". Всегда используй emoji-иконку в поле icon, никогда не пиши слова вместо иконок.',
     'fields', JSON_ARRAY('title', 'description', 'input_label', 'input_placeholder', 'zones', 'disclaimer'),
     'zone_fields', JSON_ARRAY('key', 'from', 'to', 'color', 'label', 'icon', 'text')
                               ), 2, 1),

(14, 'verdict_card', 'Мифы и факты', JSON_OBJECT(
     'hint', '3-4 карточки: самые ЧАСТЫЕ СТРАХИ. Быстрое «миф/правда/полуправда». Короткие explanation.',
     'fields', JSON_ARRAY('title', 'items'),
     'items_fields', JSON_ARRAY('claim', 'verdict', 'explanation', 'source')
                                  ), 3, 1),

(14, 'key_takeaways', 'Итог', JSON_OBJECT(
     'hint', '5 пунктов — РЕЗЮМЕ оценки рисков. Конкретные выводы, без воды.',
     'fields', JSON_ARRAY('title', 'items', 'style')
                           ), 4, 1),

(14, 'warning_block', 'Реальные красные флаги', JSON_OBJECT(
     'hint', 'variant:"red_flags". 3-4 пункта: КОГДА ДЕЙСТВИТЕЛЬНО ОПАСНО.',
     'fields', JSON_ARRAY('variant', 'title', 'subtitle', 'items', 'footer'),
     'items_fields', JSON_ARRAY('text', 'severity')
                                             ), 5, 1),

(14, 'expert_panel', 'Мнение врача', JSON_OBJECT(
     'hint', 'ОДНО ПРЕДЛОЖЕНИЕ-highlight + 2 предложения контекста. Успокаивающее, но честное.',
     'fields', JSON_ARRAY('name', 'credentials', 'experience', 'photo_placeholder', 'text', 'highlight')
                                  ), 6, 1),

(14, 'faq', 'FAQ', JSON_OBJECT(
     'hint', '4-5 вопросов о РИСКАХ. Прямые честные ответы.',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
                ), 7, 1),

(14, 'cta', 'CTA', JSON_OBJECT(
     'hint', '{title:"Уточните свой риск", text:"Консультация врача — 15 минут", primary_btn_text:"Записаться", primary_btn_link_key:"CONSULTATION"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')
                ), 8, 1);


-- ───────────────────────────────────────────────────────
-- ШАБЛОН 28: «Риски при заболевании»
-- Для запросов «ферритин при онкологии», «ферритин при раке».
-- hero → key_takeaways → story_block → richtext
-- → verdict_card → norms_table → info_cards
-- → warning_block → numbered_steps → expert_panel → faq → cta
-- ───────────────────────────────────────────────────────

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (15, 'Риски при заболевании',
     'risk-disease',
     'Для запросов «показатель при [заболевании]» в контексте оценки рисков: онкология, диабет, сердце.',
     'Ты — медицинский копирайтер. Статья о РИСКАХ ПОКАЗАТЕЛЯ в контексте КОНКРЕТНОГО ЗАБОЛЕВАНИЯ.
    ТОН: максимально взвешенный. Читатель НАПУГАН — возможно, у него или близкого серьёзный диагноз.
    ПРАВИЛА:
    1. НЕ СТАВЬ ДИАГНОЗЫ. «Повышенный ферритин может быть связан с...» — НЕ «Повышенный ферритин означает рак».
    2. РАЗВЕЙ МИФЫ — но не обесценивай тревогу.
    3. КОНКРЕТНЫЕ следующие шаги: к какому врачу, какие анализы.
    For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    СТРУКТУРА: «Давайте спокойно разберёмся» → главное → мифы → реальные риски → когда опасно → план.
    ФОРМАТЫ:
    hero: ПЛОСКИЙ {title, subtitle, cta_text?, cta_link_key?}
    key_takeaways: {title, items:[строки], style:"numbered"}
    story_block: {variant:"patient_story", icon, accent_color, lead, text, highlight, footnote}
    richtext: {blocks:[{type:"heading"|"paragraph"|"list"|"highlight"|"quote", ...}]}
    verdict_card: {title, items:[{claim, verdict:"myth"|"truth"|"partial", explanation, source}]}
    norms_table: {caption, rows:[{name, unit, active, states:[{key, label, range, pct, description}]}]}
    info_cards: {title, layout:"grid-3", items:[{icon, title, text, color}]}
    warning_block: {variant:"red_flags"|"caution", title, subtitle, items:[{text, severity:"urgent"|"emergency"|"warning"}], footer}
    numbered_steps: {title, steps:[{number, title, text, tip, duration}]}
    expert_panel: {name, credentials, experience, photo_placeholder, text, highlight}
    faq: {items:[{question, answer}]}
    cta: ПЛОСКИЙ {title, text, primary_btn_text, primary_btn_link_key}',
     'tpl-risk-disease');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(15, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', '{title:"[Показатель] при [заболевании]: что нужно знать", subtitle:"Спокойный разбор: связь, риски и следующие шаги"}',
     'fields', JSON_ARRAY('title', 'subtitle')
                       ), 1, 1),

(15, 'key_takeaways', 'Главное', JSON_OBJECT(
     'hint', '5 ВЗВЕШЕННЫХ пунктов: связь показателя с заболеванием, когда стоит/не стоит беспокоиться, что делать. БЕЗ паники.',
     'fields', JSON_ARRAY('title', 'items', 'style')
                              ), 2, 1),

(15, 'story_block', 'История', JSON_OBJECT(
     'hint', 'variant:"patient_story". История человека, который ИСПУГАЛСЯ связи показателя с заболеванием — но разобрался и взял ситуацию под контроль. Позитивный исход. НИКАКИХ реальных диагнозов.',
     'fields', JSON_ARRAY('variant', 'icon', 'accent_color', 'lead', 'text', 'highlight', 'footnote')
                            ), 3, 1),

(15, 'richtext', 'Связь показателя с заболеванием', JSON_OBJECT(
     'hint', 'ПЛАН (мин. 8 подблоков):
1. H2 «Как [показатель] связан с [заболеванием]» → paragraph: механизм
2. H3 «Означает ли отклонение диагноз?» → paragraph: НЕТ, один анализ не = диагноз
3. highlight: «Отклонение [показателя] — НЕ диагноз. Это повод для обследования.»
4. H3 «Какие ещё анализы нужны» → list: комплексная диагностика
5. H2 «Что говорят исследования» → paragraph: доказательная база
6. quote: из клинических рекомендаций',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight', 'quote')
                                                 ), 4, 1),

(15, 'verdict_card', 'Мифы и правда', JSON_OBJECT(
     'hint', '3-4 карточки: САМЫЕ ПУГАЮЩИЕ МИФЫ о связи показателя с заболеванием. Пример: «Высокий ферритин = рак» → partial (может быть маркером, но не диагнозом). ОБЯЗАТЕЛЬНО source.',
     'fields', JSON_ARRAY('title', 'items'),
     'items_fields', JSON_ARRAY('claim', 'verdict', 'explanation', 'source')
                                   ), 5, 1),

(15, 'norms_table', 'Значения при заболевании', JSON_OBJECT(
     'hint', 'СПЕЦИФИЧНЫЕ нормы/диапазоны при этом заболевании. Не стандартные. States отражают: «типично при заболевании», «выше ожидаемого», «критично». caption: «[Показатель] при [заболевании]: на что ориентироваться». ПРАВИЛО pct: для states с понижением/дефицитом — pct:10; для нормы — pct:100; для умеренного превышения — pct:60; для высокого превышения — pct:30. pct отражает заполненность шкалы на UI.',
     'fields', JSON_ARRAY('caption', 'rows'),
     'row_fields', JSON_ARRAY('name', 'unit', 'active', 'states')
                                             ), 6, 1),

(15, 'info_cards', 'Когда беспокоиться', JSON_OBJECT(
     'hint', '6 карточек: 3 «НЕ ОЗНАЧАЕТ [заболевание]» (зелёные) + 3 «ПОВОД ОБСЛЕДОВАТЬСЯ» (жёлтые). Конкретные ситуации. НЕ красные — не нагнетаем.',
     'fields', JSON_ARRAY('title', 'layout', 'items'),
     'items_fields', JSON_ARRAY('icon', 'title', 'text', 'color')
                                      ), 7, 1),

(15, 'warning_block', 'Когда срочно к врачу', JSON_OBJECT(
     'hint', 'variant:"red_flags". РЕАЛЬНЫЕ ситуации, когда НЕЛЬЗЯ ОТКЛАДЫВАТЬ. 3-4 пункта. severity: urgent. НЕ пугай без причины.',
     'fields', JSON_ARRAY('variant', 'title', 'subtitle', 'items', 'footer'),
     'items_fields', JSON_ARRAY('text', 'severity')
                                           ), 8, 1),

(15, 'numbered_steps', 'Ваш план действий', JSON_OBJECT(
     'hint', '4-5 шагов: 1) Не паниковать, 2) Сдать дополнительные анализы, 3) К профильному врачу, 4) Обсудить результаты, 5) Мониторинг.',
     'fields', JSON_ARRAY('title', 'steps'),
     'steps_fields', JSON_ARRAY('number', 'title', 'text', 'tip', 'duration')
                                         ), 9, 1),

(15, 'expert_panel', 'Мнение онколога/специалиста', JSON_OBJECT(
     'hint', 'ПРОФИЛЬНЫЙ врач по заболеванию. УСПОКАИВАЮЩЕЕ мнение: «В большинстве случаев [отклонение] при [заболевании] — это [причина], а не [страх].» highlight: ключевая мысль.',
     'fields', JSON_ARRAY('name', 'credentials', 'experience', 'photo_placeholder', 'text', 'highlight')
                                                 ), 10, 1),

(15, 'faq', 'FAQ', JSON_OBJECT(
     'hint', '5-7 вопросов О СВЯЗИ С ЗАБОЛЕВАНИЕМ: «Значит ли это что у меня [заболевание]?», «Какие ещё анализы сдать?», «К какому врачу?», «Можно ли по одному анализу поставить диагноз?». ЧЕСТНЫЕ, НЕ ПУГАЮЩИЕ ответы.',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
                ), 11, 1),

(15, 'cta', 'CTA', JSON_OBJECT(
     'hint', '{title:"Разберитесь в ситуации", text:"Комплексное обследование даст полную картину", primary_btn_text:"Консультация специалиста", primary_btn_link_key:"CONSULTATION"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')
                ), 12, 1);




-- ═══════════════════════════════════════════════════════
-- Migration 012: Интент-ориентированные шаблоны (финал)
-- Группы: action_plan + doctor_prep + comparison +
--         myth_debunk + transactional + navigational
-- 16 шаблонов: ID 30-45
-- ═══════════════════════════════════════════════════════

-- ═══════════════════════════════════════════════════════
--  INTENT: action_plan
-- Человек знает проблему, ищет решение.
-- «Как повысить», «как снизить», «что принимать».
-- Тон: практичный, пошаговый.
-- ═══════════════════════════════════════════════════════

-- ── ШАБЛОН 30: «План действий — полный» ──
-- hero → key_takeaways → richtext → numbered_steps
-- → comparison_cards → richtext-мостик → progress_tracker
-- → info_cards(питание) → prep_checklist → warning_block
-- → expert_panel → faq → cta

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (16, 'План действий — полный',
     'action-full',
     'Пошаговый план коррекции показателя: питание, препараты, контроль, прогноз.',
     'Ты — медицинский копирайтер. Статья-ПЛАН ДЕЙСТВИЙ. Читатель ЗНАЕТ проблему и хочет РЕШЕНИЕ.
    ТОН: практичный, пошаговый. Не объясняй что такое анализ — читатель уже знает. Сразу к делу.
    СТРУКТУРА: краткая суть → пошаговый план → чем лечить → чем кормить → прогноз → когда к врачу.
    For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    ПРАВИЛО ТЕРРИТОРИЙ:
    - numbered_steps: ПОШАГОВЫЙ ПЛАН коррекции (4-5 шагов). Главный блок.
    - comparison_cards: СРАВНЕНИЕ ПРЕПАРАТОВ/МЕТОДОВ — два варианта с pros/cons.
    - progress_tracker: ОЖИДАЕМЫЙ ПРОГРЕСС по месяцам.
    - info_cards: ПРОДУКТЫ/ФАКТОРЫ для коррекции (питание, добавки, образ жизни).
    - prep_checklist: ЧЕКЛИСТ ежедневных действий.
    - warning_block: ЧЕГО НЕ ДЕЛАТЬ + когда к врачу.
    - expert_panel: ПРАКТИЧЕСКИЙ совет врача.
    ФОРМАТЫ:
    hero: ПЛОСКИЙ {title, subtitle, cta_text?, cta_link_key?}
    key_takeaways: {title, items:[строки], style:"numbered"}
    richtext: {blocks:[{type:"heading"|"paragraph"|"list"|"highlight"|"quote", ...}]}
    numbered_steps: {title, steps:[{number, title, text, tip, duration}]}
    comparison_cards: {title, card_a:{name, badge, color, pros:[], cons:[], price, verdict}, card_b:{...}}
    progress_tracker: {title, timeline_unit, milestones:[{period, marker(0-100), text, metric}], note}
    info_cards: {title, layout:"grid-3", items:[{icon, title, text, color}]}
    prep_checklist: {title, subtitle, sections:[{name, icon, items:[{text, important:bool}]}]}
    warning_block: {variant:"red_flags"|"caution", title, subtitle, items:[{text, severity:"urgent"|"emergency"|"warning"}], footer}
    expert_panel: {name, credentials, experience, photo_placeholder, text, highlight}
    faq: {items:[{question, answer}]}
    cta: ПЛОСКИЙ {title, text, primary_btn_text, primary_btn_link_key}',
     'tpl-action-full');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(16, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', '{title:"Как повысить/снизить [показатель]: пошаговый план", subtitle:"Питание, препараты, контроль — всё что нужно для результата"}',
     'fields', JSON_ARRAY('title', 'subtitle', 'cta_text', 'cta_link_key')
                       ), 1, 1),

(16, 'key_takeaways', 'План за 30 секунд', JSON_OBJECT(
     'hint', '5 пунктов-ДЕЙСТВИЙ (не теория): что есть, что пить, какой препарат, когда пересдать, сколько ждать. {title:"Краткий план", style:"numbered"}',
     'fields', JSON_ARRAY('title', 'items', 'style')
                                        ), 2, 1),

(16, 'richtext', 'Суть проблемы', JSON_OBJECT(
     'hint', 'КРАТКИЙ контекст (мин. 6 подблоков):
1. H2 «Почему [показатель] отклонён и что с этим делать» → paragraph: 2-3 предложения
2. H3 «Два пути: питание + препараты» → paragraph
3. highlight: «Результат будет через N месяцев — это нормально»
НЕ ПИШИ историю показателя — читатель уже знает. Сразу к практике.',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight', 'quote')
                               ), 3, 1),

(16, 'numbered_steps', 'Пошаговый план', JSON_OBJECT(
     'hint', '5 КОНКРЕТНЫХ шагов. title: «Как вернуть [показатель] в норму». Пример: 1) Скорректировать питание (tip: что добавить), 2) Выбрать препарат (tip: какую форму), 3) Правильно принимать (tip: время, совместимость), 4) Контрольный анализ через 3 мес., 5) Поддерживающая доза. duration в каждом.',
     'fields', JSON_ARRAY('title', 'steps'),
     'steps_fields', JSON_ARRAY('number', 'title', 'text', 'tip', 'duration')
                                      ), 4, 1),

(16, 'comparison_cards', 'Выбор препарата', JSON_OBJECT(
     'hint', 'Два варианта лечения/коррекции. Для железа: бисглицинат vs сульфат. Для витамина D: масляный vs водный. card_a и card_b: name, badge, color, pros[], cons[], price, verdict. title: «Что выбрать: [A] или [B]».',
     'fields', JSON_ARRAY('title', 'card_a', 'card_b'),
     'card_fields', JSON_ARRAY('name', 'badge', 'color', 'pros', 'cons', 'price', 'verdict')
                                         ), 5, 1),

(16, 'richtext', 'Мостик к прогнозу', JSON_OBJECT(
     'hint', '2-3 предложения: «Если следовать плану, вот что можно ожидать по месяцам.»',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph')
                                   ), 6, 0),

(16, 'progress_tracker', 'Ожидаемый прогресс', JSON_OBJECT(
     'hint', '4-5 вех: 2 недели, 1 мес., 2 мес., 3 мес., 6 мес. В каждой — что изменится (самочувствие + значение анализа). note: «Скорость индивидуальна».',
     'fields', JSON_ARRAY('title', 'timeline_unit', 'milestones', 'note'),
     'milestone_fields', JSON_ARRAY('period', 'marker', 'text', 'metric')
                                            ), 7, 1),

(16, 'info_cards', 'Питание и образ жизни', JSON_OBJECT(
     'hint', '6 карточек: КОНКРЕТНЫЕ продукты/действия для коррекции. Не «ешьте здоровую пищу», а «Печень говяжья — 6.5 мг железа на 100г». icon + title + text + color. {title:"Что есть/делать для [показателя]", layout:"grid-3"}',
     'fields', JSON_ARRAY('title', 'layout', 'items'),
     'items_fields', JSON_ARRAY('icon', 'title', 'text', 'color')
                                         ), 8, 1),

(16, 'prep_checklist', 'Ежедневный чеклист', JSON_OBJECT(
     'hint', 'Чеклист ЕЖЕДНЕВНЫХ ДЕЙСТВИЙ для коррекции. 2-3 секции: «Утро» (приём препарата), «Питание» (что включить), «Вечер» (что исключить). 2-3 пункта в каждой. important=true для критичных.',
     'fields', JSON_ARRAY('title', 'subtitle', 'sections'),
     'section_fields', JSON_ARRAY('name', 'icon', 'items')
                                          ), 9, 1),

(16, 'warning_block', 'Чего НЕ делать', JSON_OBJECT(
     'hint', 'variant:"caution". ТИПИЧНЫЕ ОШИБКИ при коррекции: «Не принимайте железо с кофе», «Не превышайте дозу», «Не бросайте через 2 недели». 4-5 пунктов.',
     'fields', JSON_ARRAY('variant', 'title', 'subtitle', 'items', 'footer'),
     'items_fields', JSON_ARRAY('text', 'severity')
                                     ), 10, 1),

(16, 'expert_panel', 'Совет врача', JSON_OBJECT(
     'hint', 'ПРАКТИЧЕСКИЙ совет: «Самая частая ошибка моих пациентов — [X]. Вот как правильно.»',
     'fields', JSON_ARRAY('name', 'credentials', 'experience', 'photo_placeholder', 'text', 'highlight')
                                 ), 11, 1),

(16, 'faq', 'FAQ', JSON_OBJECT(
     'hint', '5-7 вопросов О КОРРЕКЦИИ: «Сколько принимать препарат?», «Можно ли только диетой?», «Совместимость с другими», «Побочные эффекты», «Когда пересдать».',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
                ), 12, 1),

(16, 'cta', 'CTA', JSON_OBJECT(
     'hint', '{title:"Начните восстановление сегодня", text:"Контрольный анализ покажет прогресс", primary_btn_text:"Расшифровать анализы", primary_btn_link_key:"ORDER_ANALYSIS"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')
                ), 13, 1);


-- ── ШАБЛОН 31: «План действий — компактный» ──

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (17, 'План действий — компактный',
     'action-compact',
     'Короткий план: шаги + сравнение + чеклист. Без длинных текстов.',
     'Ты — медицинский копирайтер. КОМПАКТНЫЙ план действий. Минимум теории — максимум практики.
    For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    ТОН: прямой, как инструкция. Читатель хочет ДЕЛАТЬ, а не ЧИТАТЬ.
    ФОРМАТЫ:
    hero: ПЛОСКИЙ {title, subtitle, cta_text?, cta_link_key?}
    key_takeaways: {title, items:[строки], style:"numbered"}
    numbered_steps: {title, steps:[{number, title, text, tip, duration}]}
    comparison_cards: {title, card_a:{name, badge, color, pros:[], cons:[], price, verdict}, card_b:{...}}
    prep_checklist: {title, subtitle, sections:[{name, icon, items:[{text, important:bool}]}]}
    warning_block: {variant:"red_flags"|"caution", title, subtitle, items:[{text, severity:"urgent"|"emergency"|"warning"}], footer}
    expert_panel: {name, credentials, experience, photo_placeholder, text, highlight}
    faq: {items:[{question, answer}]}
    cta: ПЛОСКИЙ {title, text, primary_btn_text, primary_btn_link_key}',
     'tpl-action-compact');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(17, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', '{title:"Как [повысить/снизить] [показатель]: быстрый план", subtitle:"4 шага к нормализации"}',
     'fields', JSON_ARRAY('title', 'subtitle')
                       ), 1, 1),

(17, 'key_takeaways', 'План', JSON_OBJECT(
     'hint', '5 пунктов-действий. Конкретные. {title:"Краткий план действий", style:"numbered"}',
     'fields', JSON_ARRAY('title', 'items', 'style')
                           ), 2, 1),

(17, 'numbered_steps', 'Шаги', JSON_OBJECT(
     'hint', '4 шага с tip и duration. Компактно, без воды.',
     'fields', JSON_ARRAY('title', 'steps'),
     'steps_fields', JSON_ARRAY('number', 'title', 'text', 'tip', 'duration')
                            ), 3, 1),

(17, 'comparison_cards', 'Выбор метода', JSON_OBJECT(
     'hint', 'Два варианта коррекции. pros/cons/price/verdict.',
     'fields', JSON_ARRAY('title', 'card_a', 'card_b'),
     'card_fields', JSON_ARRAY('name', 'badge', 'color', 'pros', 'cons', 'price', 'verdict')
                                      ), 4, 1),

(17, 'prep_checklist', 'Ежедневный чеклист', JSON_OBJECT(
     'hint', '2 секции, по 3-4 пункта. Практичные действия.',
     'fields', JSON_ARRAY('title', 'subtitle', 'sections'),
     'section_fields', JSON_ARRAY('name', 'icon', 'items')
                                          ), 5, 1),

(17, 'warning_block', 'Ошибки', JSON_OBJECT(
     'hint', 'variant:"caution". 3-4 типичных ошибки при коррекции.',
     'fields', JSON_ARRAY('variant', 'title', 'subtitle', 'items', 'footer'),
     'items_fields', JSON_ARRAY('text', 'severity')
                             ), 6, 1),

(17, 'expert_panel', 'Совет', JSON_OBJECT(
     'hint', 'Короткий практический совет.',
     'fields', JSON_ARRAY('name', 'credentials', 'experience', 'photo_placeholder', 'text', 'highlight')
                           ), 7, 1),

(17, 'faq', 'FAQ', JSON_OBJECT(
     'hint', '4-5 вопросов о коррекции.',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
                ), 8, 1),

(17, 'cta', 'CTA', JSON_OBJECT(
     'hint', '{title:"Контрольный анализ", text:"Проверьте прогресс", primary_btn_text:"Расшифровать анализы", primary_btn_link_key:"ORDER_ANALYSIS"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')
                ), 9, 1);


-- ═══════════════════════════════════════════════════════
--  INTENT: doctor_prep
-- Готовится к анализу или визиту. Хочет всё сделать правильно.
-- Тон: инструкция-чеклист.
-- ═══════════════════════════════════════════════════════


-- ── ШАБЛОН 32: «Подготовка к анализу — полная» ──

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (18, 'Подготовка к анализу — полная',
     'prep-full',
     'Полная инструкция: чеклист подготовки, таймлайн, что сдать вместе, к какому врачу.',
     'Ты — медицинский копирайтер. Статья-ИНСТРУКЦИЯ по подготовке к анализу.
    ТОН: чеклист-стиль. Чётко, по пунктам. Читатель хочет НИЧЕГО НЕ ЗАБЫТЬ.
    For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    ПРАВИЛО ТЕРРИТОРИЙ:
    - prep_checklist: ГЛАВНЫЙ БЛОК — интерактивный чеклист подготовки по дням.
    - timeline: ХРОНОЛОГИЯ — за 3 дня / накануне / в день сдачи / после.
    - info_cards: ЧТО СДАТЬ ВМЕСТЕ — комплекс анализов.
    - richtext: КОНТЕКСТ — зачем готовиться, что может исказить результат.
    - mini_calculator: РАСЧЁТ стоимости комплекса или оптимального времени сдачи.
    - expert_panel: СОВЕТ ВРАЧА по подготовке.
    ФОРМАТЫ:
    hero: ПЛОСКИЙ {title, subtitle, cta_text?, cta_link_key?}
    key_takeaways: {title, items:[строки], style:"numbered"}
    prep_checklist: {title, subtitle, sections:[{name, icon, items:[{text, important:bool}]}]}
    richtext: {blocks:[{type:"heading"|"paragraph"|"list"|"highlight"|"quote", ...}]}
    timeline: {title, items:[{step, title, summary, detail, meta}]}
    info_cards: {title, layout:"grid-3", items:[{icon, title, text, color}]}
    mini_calculator: {title, description, inputs:[{key, label, type, options, min, max, unit, placeholder}], results:[{condition, value, text}], disclaimer}
    expert_panel: {name, credentials, experience, photo_placeholder, text, highlight}
    faq: {items:[{question, answer}]}
    cta: ПЛОСКИЙ {title, text, primary_btn_text, primary_btn_link_key}',
     'tpl-prep-full');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(18, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', '{title:"Как подготовиться к [анализу]: полная инструкция", subtitle:"Чеклист, таймлайн и всё, что нужно знать перед сдачей"}',
     'fields', JSON_ARRAY('title', 'subtitle')
                       ), 1, 1),

(18, 'key_takeaways', 'Коротко', JSON_OBJECT(
     'hint', '5 пунктов: натощак/нет, за сколько не есть, можно ли воду, какие лекарства отменить, когда лучше сдавать.',
     'fields', JSON_ARRAY('title', 'items', 'style')
                              ), 2, 1),

(18, 'prep_checklist', 'Чеклист подготовки', JSON_OBJECT(
     'hint', 'ГЛАВНЫЙ БЛОК. 3 секции: «За 3 дня» (icon:📅), «Накануне вечером» (icon:🌙), «В день сдачи» (icon:☀️). 2-4 пункта в каждой, important=true для критичных. title: «Чеклист: подготовка к [анализу]».',
     'fields', JSON_ARRAY('title', 'subtitle', 'sections'),
     'section_fields', JSON_ARRAY('name', 'icon', 'items')
                                          ), 3, 1),

(18, 'richtext', 'Почему подготовка важна', JSON_OBJECT(
     'hint', 'ПЛАН (мин. 6 подблоков):
1. H2 «Что может исказить результат» → paragraph
2. list: 4-5 факторов (еда, стресс, лекарства, физнагрузки, время суток)
3. highlight: «Неправильная подготовка = пересдача = лишние деньги»
4. H3 «Особые случаи» → paragraph: беременные, дети, хронические болезни',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight', 'quote')
                                         ), 4, 1),

(18, 'timeline', 'Хронология подготовки', JSON_OBJECT(
     'hint', '5 шагов-периодов. title: «Таймлайн подготовки». step: «За 3 дня», «За 1 день», «Вечер перед», «Утро», «В лаборатории». summary + detail + meta(время).',
     'fields', JSON_ARRAY('title', 'items'),
     'items_fields', JSON_ARRAY('step', 'title', 'summary', 'detail', 'meta')
                                       ), 5, 1),

(18, 'info_cards', 'Что сдать вместе', JSON_OBJECT(
     'hint', '4-6 карточек: анализы, которые СТОИТ СДАТЬ ВМЕСТЕ для полной картины. icon + title(название анализа) + text(зачем в комплексе) + color. {title:"Сдайте вместе с [основным анализом]", layout:"grid-3"}',
     'fields', JSON_ARRAY('title', 'layout', 'items'),
     'items_fields', JSON_ARRAY('icon', 'title', 'text', 'color')
                                    ), 6, 1),

(18, 'mini_calculator', 'Расчёт комплекса', JSON_OBJECT(
     'hint', 'Калькулятор СТОИМОСТИ комплекса анализов. inputs: выбор анализов (чекбоксы через select). results: стоимость комбинаций. Или: расчёт оптимального дня цикла для женщин.',
     'fields', JSON_ARRAY('title', 'description', 'inputs', 'results', 'formula_description', 'disclaimer'),
     'input_fields', JSON_ARRAY('key', 'label', 'type', 'options', 'min', 'max', 'unit', 'placeholder', 'show_if')
                                         ), 7, 0),

(18, 'expert_panel', 'Совет врача', JSON_OBJECT(
     'hint', 'Врач о ТИПИЧНЫХ ОШИБКАХ подготовки: «Чаще всего мои пациенты забывают про [X], и результат приходится пересдавать.»',
     'fields', JSON_ARRAY('name', 'credentials', 'experience', 'photo_placeholder', 'text', 'highlight')
                                 ), 8, 1),

(18, 'faq', 'FAQ', JSON_OBJECT(
     'hint', '5-7 вопросов: «Натощак или нет?», «Можно ли воду?», «Влияют ли месячные?», «Можно ли с простудой?», «Во сколько лучше?», «Что взять с собой?».',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
                ), 9, 1),

(18, 'cta', 'CTA', JSON_OBJECT(
     'hint', '{title:"Готовы? Запишитесь на анализ", text:"Результат за 24 часа", primary_btn_text:"Записаться", primary_btn_link_key:"ORDER_ANALYSIS"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')
                ), 10, 1);


-- ── ШАБЛОН 33: «Подготовка — компактная» ──

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (19, 'Подготовка к анализу — компактная',
     'prep-compact',
     'Быстрый чеклист + FAQ. Минимум текста.',
     'Ты — медицинский копирайтер. КОМПАКТНАЯ инструкция по подготовке. Только самое важное.
      For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    ФОРМАТЫ:
    hero: ПЛОСКИЙ {title, subtitle, cta_text?, cta_link_key?}
    prep_checklist: {title, subtitle, sections:[{name, icon, items:[{text, important:bool}]}]}
    key_takeaways: {title, items:[строки], style:"numbered"}
    faq: {items:[{question, answer}]}
    cta: ПЛОСКИЙ {title, text, primary_btn_text, primary_btn_link_key}',
     'tpl-prep-compact');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(19, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', '{title:"Как сдать [анализ]: памятка", subtitle:"Быстрая инструкция подготовки"}',
     'fields', JSON_ARRAY('title', 'subtitle')
                       ), 1, 1),

(19, 'prep_checklist', 'Чеклист', JSON_OBJECT(
     'hint', '2 секции: «До анализа» и «В день сдачи». По 3 пункта. Компактно.',
     'fields', JSON_ARRAY('title', 'subtitle', 'sections'),
     'section_fields', JSON_ARRAY('name', 'icon', 'items')
                               ), 2, 1),

(19, 'key_takeaways', 'Памятка', JSON_OBJECT(
     'hint', '5 коротких правил подготовки. {title:"5 правил подготовки", style:"numbered"}',
     'fields', JSON_ARRAY('title', 'items', 'style')
                              ), 3, 1),

(19, 'faq', 'FAQ', JSON_OBJECT(
     'hint', '5-6 самых частых вопросов о подготовке.',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
                ), 4, 1),

(19, 'cta', 'CTA', JSON_OBJECT(
     'hint', '{title:"Записаться на [анализ]", text:"Подготовились? Пора сдавать", primary_btn_text:"Записаться", primary_btn_link_key:"ORDER_ANALYSIS"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')
                ), 5, 1);


-- ═══════════════════════════════════════════════════════
--  INTENT: comparison
-- A vs B: показатели, методы, препараты.
-- Тон: аналитический.
-- ═══════════════════════════════════════════════════════


-- ── ШАБЛОН 34: «Сравнение — полное» ──

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (20, 'Сравнение — полное',
     'compare-full',
     'Полное сравнение A vs B: таблица, карточки, радар, экспертное мнение.',
     'Ты — медицинский аналитик. Статья-СРАВНЕНИЕ: A vs B.
    ТОН: аналитический, объективный. Без предвзятости — факты за обе стороны.
    For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    ПРАВИЛО ТЕРРИТОРИЙ:
    - richtext: КОНТЕКСТ — зачем сравнивать, в каких ситуациях выбор актуален.
    - comparison_table: ТАБЛИЦА сравнения по 5+ критериям. Главный блок.
    - comparison_cards: ВИЗУАЛЬНЫЕ КАРТОЧКИ A vs B с pros/cons.
    - radar_chart: ПРОФИЛЬ КАЖДОГО варианта — 5-7 осей.
    - accordion: ДЕТАЛИ по каждому варианту.
    - expert_panel: МНЕНИЕ ВРАЧА — кому что подходит.
    ФОРМАТЫ:
    hero: ПЛОСКИЙ {title, subtitle, cta_text?, cta_link_key?}
    key_takeaways: {title, items:[строки], style:"numbered"}
    richtext: {blocks:[{type:"heading"|"paragraph"|"list"|"highlight"|"quote", ...}]}
    comparison_table: {title, description, headers:["Параметр","A","B",...], rows:[["Параметр","знач1","знач2"]]}
    comparison_cards: {title, card_a:{name, badge, color, pros:[], cons:[], price, verdict}, card_b:{...}}
    radar_chart: {title, axes:[{name, value(0-100), description}]}
    accordion: {items:[{title, content}]}
    expert_panel: {name, credentials, experience, photo_placeholder, text, highlight}
    faq: {items:[{question, answer}]}
    cta: ПЛОСКИЙ {title, text, primary_btn_text, primary_btn_link_key}',
     'tpl-compare-full');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(20, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', '{title:"[A] или [B]: в чём разница и что выбрать", subtitle:"Полное сравнение по всем параметрам"}',
     'fields', JSON_ARRAY('title', 'subtitle')
                       ), 1, 1),

(20, 'key_takeaways', 'Коротко', JSON_OBJECT(
     'hint', '5 пунктов: главная разница, когда нужен A, когда B, можно ли оба, стоимость.',
     'fields', JSON_ARRAY('title', 'items', 'style')
                              ), 2, 1),

(20, 'richtext', 'Зачем сравнивать', JSON_OBJECT(
     'hint', 'ПЛАН (мин. 6 подблоков):
1. H2 «[A] и [B]: в чём путаница?» → paragraph: почему люди путают
2. H3 «Что показывает каждый» → list: краткое описание каждого
3. highlight: «Главная разница в одном предложении»
4. H3 «Когда нужен один, когда другой» → paragraph',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight', 'quote')
                                  ), 3, 1),

(20, 'comparison_table', 'Таблица сравнения', JSON_OBJECT(
     'hint', 'ГЛАВНЫЙ БЛОК. headers: [«Параметр», «[A]», «[B]»]. Мин. 6 строк: что показывает, когда назначают, подготовка, цена, скорость, точность. ✓/✗ для бинарных. title + description.',
     'fields', JSON_ARRAY('title', 'description', 'headers', 'rows')
                                           ), 4, 1),

(20, 'comparison_cards', 'Карточки A vs B', JSON_OBJECT(
     'hint', 'Визуальное сравнение. card_a и card_b с pros/cons/price/verdict. Дополняет таблицу — другой ракурс.',
     'fields', JSON_ARRAY('title', 'card_a', 'card_b'),
     'card_fields', JSON_ARRAY('name', 'badge', 'color', 'pros', 'cons', 'price', 'verdict')
                                         ), 5, 1),

(20, 'radar_chart', 'Профиль каждого варианта', JSON_OBJECT(
     'hint', '5-7 осей (0-100): точность, цена, скорость, доступность, информативность, комфорт. title: «Профиль: [A] vs [B]». description каждой оси: что оценивает.',
     'fields', JSON_ARRAY('title', 'axes'),
     'axes_fields', JSON_ARRAY('name', 'value', 'description')
                                             ), 6, 1),

(20, 'accordion', 'Подробности по вариантам', JSON_OBJECT(
     'hint', '2-4 секции. Каждая = один ВАРИАНТ из сравнения. title: название варианта. content: плюсы, минусы, кому подходит, кому нет. 3-5 предложений.',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('title', 'content')
                                           ), 7, 1),

(20, 'expert_panel', 'Мнение врача', JSON_OBJECT(
     'hint', 'Врач о том, КОМУ ЧТО ПОДХОДИТ: «Я назначаю [A] когда..., а [B] когда...»',
     'fields', JSON_ARRAY('name', 'credentials', 'experience', 'photo_placeholder', 'text', 'highlight')
                                  ), 8, 1),

(20, 'faq', 'FAQ', JSON_OBJECT(
     'hint', '5-7 вопросов О СРАВНЕНИИ: «Можно ли сдать оба?», «Какой дешевле?», «Какой точнее?», «Можно ли заменить один другим?».',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
                ), 9, 1),

(20, 'cta', 'CTA', JSON_OBJECT(
     'hint', '{title:"Не можете выбрать?", text:"Врач подберёт оптимальный вариант", primary_btn_text:"Консультация", primary_btn_link_key:"CONSULTATION"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')
                ), 10, 1);


-- ── ШАБЛОН 35: «Сравнение — компактное» ──

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (21, 'Сравнение — компактное',
     'compare-compact',
     'Быстрое сравнение: таблица + карточки + вывод.',
     'Ты — медицинский аналитик. БЫСТРОЕ сравнение A vs B. Минимум текста, максимум наглядности.
      For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    ФОРМАТЫ:
    hero: ПЛОСКИЙ {title, subtitle, cta_text?, cta_link_key?}
    comparison_table: {title, description, headers:["Параметр","A","B",...], rows:[["Параметр","знач1","знач2"]]}
    comparison_cards: {title, card_a:{name, badge, color, pros:[], cons:[], price, verdict}, card_b:{...}}
    key_takeaways: {title, items:[строки], style:"numbered"}
    faq: {items:[{question, answer}]}
    cta: ПЛОСКИЙ {title, text, primary_btn_text, primary_btn_link_key}',
     'tpl-compare-compact');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(21, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', '{title:"[A] vs [B]: быстрое сравнение", subtitle:"Таблица, плюсы/минусы, вывод"}',
     'fields', JSON_ARRAY('title', 'subtitle')
                       ), 1, 1),

(21, 'comparison_table', 'Таблица', JSON_OBJECT(
     'hint', 'Компактная таблица. 5-6 строк. Главный блок.',
     'fields', JSON_ARRAY('title', 'description', 'headers', 'rows')
                                 ), 2, 1),

(21, 'comparison_cards', 'Карточки', JSON_OBJECT(
     'hint', 'A vs B с pros/cons/verdict.',
     'fields', JSON_ARRAY('title', 'card_a', 'card_b'),
     'card_fields', JSON_ARRAY('name', 'badge', 'color', 'pros', 'cons', 'price', 'verdict')
                                  ), 3, 1),

(21, 'key_takeaways', 'Вывод', JSON_OBJECT(
     'hint', '5 пунктов-выводов. Конкретно: кому A, кому B.',
     'fields', JSON_ARRAY('title', 'items', 'style')
                            ), 4, 1),

(21, 'faq', 'FAQ', JSON_OBJECT(
     'hint', '4-5 вопросов о выборе.',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
                ), 5, 1),

(21, 'cta', 'CTA', JSON_OBJECT(
     'hint', '{title:"Сдайте нужный анализ", primary_btn_text:"Записаться", primary_btn_link_key:"ORDER_ANALYSIS"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')
                ), 6, 1);


-- ═══════════════════════════════════════════════════════
--  INTENT: myth_debunk
-- «Правда ли что...?» Развенчание мифов.
-- Тон: объективный, доказательный, без менторства.
-- ═══════════════════════════════════════════════════════


-- ── ШАБЛОН 36: «Разоблачение мифов — полное» ──

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (22, 'Разоблачение мифов — полное',
     'myth-full',
     'Разбор мифов: вердикты, доказательства, экспертное мнение.',
     'Ты — медицинский журналист. Статья-РАЗОБЛАЧЕНИЕ. Разбираем мифы.
    ТОН: объективный, доказательный, БЕЗ МЕНТОРСТВА. Не «вы дурак что верите», а «давайте разберёмся откуда это пошло».
    СТРУКТУРА: миф → откуда взялся → что говорит наука → вердикт → что на самом деле.
    For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    ПРАВИЛО ТЕРРИТОРИЙ:
    - verdict_card: ГЛАВНЫЙ БЛОК — карточки мифов с вердиктами myth/truth/partial.
    - story_block: ИСТОРИЯ — как кто-то пострадал от мифа (variant:"myth_verdict").
    - richtext: КОНТЕКСТ — откуда берутся мифы, почему распространяются.
    - info_cards: ФАКТЫ vs МИФЫ — 3 мифа + 3 факта.
    - expert_panel: МНЕНИЕ УЧЁНОГО/ВРАЧА с ссылкой на исследования.
    ФОРМАТЫ:
    hero: ПЛОСКИЙ {title, subtitle, cta_text?, cta_link_key?}
    story_block: {variant:"patient_story", icon, accent_color, lead, text, highlight, footnote}
    verdict_card: {title, items:[{claim, verdict:"myth"|"truth"|"partial", explanation, source}]}
    richtext: {blocks:[{type:"heading"|"paragraph"|"list"|"highlight"|"quote", ...}]}
    info_cards: {title, layout:"grid-3", items:[{icon, title, text, color}]}
    expert_panel: {name, credentials, experience, photo_placeholder, text, highlight}
    key_takeaways: {title, items:[строки], style:"numbered"}
    faq: {items:[{question, answer}]}
    cta: ПЛОСКИЙ {title, text, primary_btn_text, primary_btn_link_key}',
     'tpl-myth-full');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(22, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', '{title:"[Тема]: мифы и правда", subtitle:"Разбираем популярные заблуждения с доказательствами"}',
     'fields', JSON_ARRAY('title', 'subtitle')
                       ), 1, 1),

(22, 'story_block', 'Цена мифа', JSON_OBJECT(
     'hint', 'variant:"patient_story". История человека, который ПОСТРАДАЛ от веры в миф: лечился народными средствами вместо препаратов, не сдал анализ потому что «это не нужно». Мотивация разобраться.',
     'fields', JSON_ARRAY('variant', 'icon', 'accent_color', 'lead', 'text', 'highlight', 'footnote')
                              ), 2, 1),

(22, 'verdict_card', 'Мифы под лупой', JSON_OBJECT(
     'hint', 'ГЛАВНЫЙ БЛОК. 4-6 карточек. Каждая: claim(миф), verdict(myth/truth/partial), explanation(3-5 предложений), source(исследование или клин. рекомендации). Баланс: не все myth — включи truth и partial.',
     'fields', JSON_ARRAY('title', 'items'),
     'items_fields', JSON_ARRAY('claim', 'verdict', 'explanation', 'source')
                                    ), 3, 1),

(22, 'richtext', 'Почему мифы живучи', JSON_OBJECT(
     'hint', 'ПЛАН (мин. 6 подблоков):
1. H2 «Откуда берутся мифы о [теме]» → paragraph: соцсети, «одна бабушка сказала», устаревшие данные
2. H3 «Почему даже врачи иногда ошибаются» → paragraph
3. highlight: «Медицина меняется — то, что было правдой 10 лет назад, сегодня может быть мифом»
4. H3 «Как отличить факт от мифа» → list: 4-5 правил проверки информации',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight', 'quote')
                                    ), 4, 1),

(22, 'info_cards', 'Факты vs мифы', JSON_OBJECT(
     'hint', '6 карточек: 3 с icon ✅ и зелёным цветом (ФАКТЫ) + 3 с icon ❌ и красным (МИФЫ). Визуальный контраст. {title:"Проверенные факты и распространённые мифы", layout:"grid-3"}',
     'fields', JSON_ARRAY('title', 'layout', 'items'),
     'items_fields', JSON_ARRAY('icon', 'title', 'text', 'color')
                                 ), 5, 1),

(22, 'expert_panel', 'Мнение учёного', JSON_OBJECT(
     'hint', 'Врач или исследователь с credentials. О ДОКАЗАТЕЛЬНОЙ БАЗЕ: «Ни одно качественное исследование не подтвердило, что [миф]». highlight: ключевой вывод.',
     'fields', JSON_ARRAY('name', 'credentials', 'experience', 'photo_placeholder', 'text', 'highlight')
                                    ), 6, 1),

(22, 'key_takeaways', 'Итог', JSON_OBJECT(
     'hint', '5 пунктов: что правда, что миф, что полуправда, как проверять, кого слушать.',
     'fields', JSON_ARRAY('title', 'items', 'style')
                           ), 7, 1),

(22, 'faq', 'FAQ', JSON_OBJECT(
     'hint', '5-7 вопросов в формате «Правда ли что...?»: каждый ответ — мини-разоблачение.',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
                ), 8, 1),

(22, 'cta', 'CTA', JSON_OBJECT(
     'hint', '{title:"Проверьте свой [показатель]", text:"Факты важнее мифов — сдайте анализ", primary_btn_text:"Расшифровать анализы", primary_btn_link_key:"ORDER_ANALYSIS"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')
                ), 9, 1);


-- ── ШАБЛОН 37: «Разоблачение — компактное» ──

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (23, 'Разоблачение мифов — компактное',
     'myth-compact',
     'Быстрый разбор: вердикты + факты + вывод.',
     'Ты — медицинский журналист. БЫСТРЫЙ разбор мифов. Карточки с вердиктами + итог.
    For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    ФОРМАТЫ:
    hero: ПЛОСКИЙ {title, subtitle, cta_text?, cta_link_key?}
    verdict_card: {title, items:[{claim, verdict:"myth"|"truth"|"partial", explanation, source}]}
    key_takeaways: {title, items:[строки], style:"numbered"}
    expert_panel: {name, credentials, experience, photo_placeholder, text, highlight}
    faq: {items:[{question, answer}]}
    cta: ПЛОСКИЙ {title, text, primary_btn_text, primary_btn_link_key}',
     'tpl-myth-compact');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(23, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', '{title:"[Тема]: правда или миф?", subtitle:"Быстрая проверка популярных утверждений"}',
     'fields', JSON_ARRAY('title', 'subtitle')
                       ), 1, 1),

(23, 'verdict_card', 'Вердикты', JSON_OBJECT(
     'hint', '4-5 карточек с claim/verdict/explanation/source. Главный блок.',
     'fields', JSON_ARRAY('title', 'items'),
     'items_fields', JSON_ARRAY('claim', 'verdict', 'explanation', 'source')
                              ), 2, 1),

(23, 'key_takeaways', 'Итог', JSON_OBJECT(
     'hint', '5 выводов: что запомнить.',
     'fields', JSON_ARRAY('title', 'items', 'style')
                           ), 3, 1),

(23, 'expert_panel', 'Мнение', JSON_OBJECT(
     'hint', 'Короткое экспертное мнение.',
     'fields', JSON_ARRAY('name', 'credentials', 'experience', 'photo_placeholder', 'text', 'highlight')
                            ), 4, 1),

(23, 'faq', 'FAQ', JSON_OBJECT(
     'hint', '4-5 вопросов «Правда ли что...?».',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
                ), 5, 1),

(23, 'cta', 'CTA', JSON_OBJECT(
     'hint', '{title:"Доверяйте фактам", text:"Сдайте анализ", primary_btn_text:"Записаться", primary_btn_link_key:"ORDER_ANALYSIS"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')
                ), 6, 1);


-- ═══════════════════════════════════════════════════════
--  INTENT: transactional
-- Готов платить/действовать. Ближе всего к конверсии.
-- Тон: конкретный, сервисный.
-- 2 шаблона (3 избыточно для коммерческих).
-- ═══════════════════════════════════════════════════════


-- ── ШАБЛОН 40: «Транзакционная — полная» ──

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (24, 'Транзакционная страница — полная',
     'transact-full',
     'Сервисная страница: что входит, цена, где сдать, как записаться.',
     'Ты — медицинский копирайтер. СЕРВИСНАЯ страница. Читатель ГОТОВ ДЕЙСТВОВАТЬ — ему нужны цена, место, время.
    ТОН: конкретный, сервисный. Минимум теории, максимум практических деталей.
    ПРАВИЛО: каждый блок подводит к КОНВЕРСИИ.
    For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    ФОРМАТЫ:
    hero: ПЛОСКИЙ {title, subtitle, cta_text?, cta_link_key?}
    stats_counter: {items:[{value, label, suffix}]}
    richtext: {blocks:[{type:"heading"|"paragraph"|"list"|"highlight"|"quote", ...}]}
    prep_checklist: {title, subtitle, sections:[{name, icon, items:[{text, important:bool}]}]}
    info_cards: {title, layout:"grid-3", items:[{icon, title, text, color}]}
    faq: {items:[{question, answer}]}
    cta: ПЛОСКИЙ {title, text, primary_btn_text, primary_btn_link_key}',
     'tpl-transact-full');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(24, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', '{title:"Сдать [анализ]: цена, подготовка, запись", subtitle:"Результат за 24 часа — от [цена] ₽", cta_text:"Записаться", cta_link_key:"ORDER_ANALYSIS"}',
     'fields', JSON_ARRAY('title', 'subtitle', 'cta_text', 'cta_link_key')
                       ), 1, 1),

(24, 'stats_counter', 'Ключевые цифры', JSON_OBJECT(
     'hint', '4 факта: цена (от X ₽), срок (1 день), точность (99%), удобство (без направления). {items:[...]}',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('value', 'label', 'suffix')
                                     ), 2, 1),

(24, 'richtext', 'Что входит', JSON_OBJECT(
     'hint', 'КОМПАКТНЫЙ (мин. 5 подблоков):
1. H2 «Что входит в анализ на [показатель]» → paragraph
2. list: что определяется
3. H3 «Как подготовиться» → paragraph: кратко
4. highlight: «Без направления. Без очереди. Результат онлайн.»',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight')
                            ), 3, 1),

(24, 'prep_checklist', 'Подготовка', JSON_OBJECT(
     'hint', 'КОРОТКИЙ чеклист: 1 секция «Перед сдачей», 3-4 пункта.',
     'fields', JSON_ARRAY('title', 'subtitle', 'sections'),
     'section_fields', JSON_ARRAY('name', 'icon', 'items')
                                  ), 4, 1),

(24, 'info_cards', 'Почему у нас', JSON_OBJECT(
     'hint', '4 карточки-преимущества: скорость, точность, удобство, цена. {title:"Почему сдать у нас", layout:"grid-2"}',
     'fields', JSON_ARRAY('title', 'layout', 'items'),
     'items_fields', JSON_ARRAY('icon', 'title', 'text', 'color')
                                ), 5, 1),

(24, 'faq', 'FAQ', JSON_OBJECT(
     'hint', '5-6 вопросов: «Нужно ли направление?», «Сколько стоит?», «Когда готов результат?», «Как записаться?», «Можно ли ребёнку?».',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
                ), 6, 1),

(24, 'cta', 'CTA', JSON_OBJECT(
     'hint', '{title:"Запишитесь на [анализ]", text:"От [цена] ₽ • Результат за 24 часа", primary_btn_text:"Записаться онлайн", primary_btn_link_key:"ORDER_ANALYSIS", secondary_btn_text:"Позвонить", secondary_btn_link_key:"PHONE"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key', 'secondary_btn_text', 'secondary_btn_link_key')
                ), 7, 1);


-- ── ШАБЛОН 41: «Транзакционная — минимальная» ──

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (25, 'Транзакционная — минимальная',
     'transact-mini',
     'Лендинг: hero + цифры + CTA. Максимальная конверсия.',
     'Ты — медицинский копирайтер. ЛЕНДИНГ для конверсии. Минимум текста — hero, цифры, CTA.
     For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    ФОРМАТЫ:
    hero: ПЛОСКИЙ {title, subtitle, cta_text?, cta_link_key?}
    stats_counter: {items:[{value, label, suffix}]}
    faq: {items:[{question, answer}]}
    cta: ПЛОСКИЙ {title, text, primary_btn_text, primary_btn_link_key}',
     'tpl-transact-mini');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(25, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', '{title:"Анализ на [показатель] — от [цена] ₽", subtitle:"Без очереди. Без направления. Результат за 24 часа.", cta_text:"Записаться", cta_link_key:"ORDER_ANALYSIS"}',
     'fields', JSON_ARRAY('title', 'subtitle', 'cta_text', 'cta_link_key')
                       ), 1, 1),

(25, 'stats_counter', 'Цифры', JSON_OBJECT(
     'hint', '3-4 ключевые цифры: цена, срок, точность, количество лабораторий.',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('value', 'label', 'suffix')
                            ), 2, 1),

(25, 'faq', 'FAQ', JSON_OBJECT(
     'hint', '3-4 вопроса: цена, подготовка, срок, нужно ли направление.',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
                ), 3, 1),

(25, 'cta', 'CTA', JSON_OBJECT(
     'hint', '{title:"Сдать [анализ] сейчас", text:"Результат завтра", primary_btn_text:"Записаться", primary_btn_link_key:"ORDER_ANALYSIS"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')
                ), 4, 1);


-- ═══════════════════════════════════════════════════════
--  INTENT: navigational
-- Ищет конкретное место / лабораторию.
-- Тон: информативный, быстрый.
-- 2 шаблона.
-- ═══════════════════════════════════════════════════════


-- ── ШАБЛОН 44: «Навигационная — информативная» ──

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (26, 'Навигационная — информативная',
     'nav-info',
     'Страница о конкретной лаборатории/месте: что сдать, цены, особенности.',
     'Ты — медицинский копирайтер. Навигационная статья: человек ищет КОНКРЕТНУЮ лабораторию/место для сдачи.
    For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    ТОН: информативный, быстрый. Минимум воды — факты, цены, адреса.
    ФОРМАТЫ:
    hero: ПЛОСКИЙ {title, subtitle, cta_text?, cta_link_key?}
    stats_counter: {items:[{value, label, suffix}]}
    richtext: {blocks:[{type:"heading"|"paragraph"|"list"|"highlight"|"quote", ...}]}
    info_cards: {title, layout:"grid-3", items:[{icon, title, text, color}]}
    faq: {items:[{question, answer}]}
    cta: ПЛОСКИЙ {title, text, primary_btn_text, primary_btn_link_key}',
     'tpl-nav-info');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

(26, 'hero', 'Заголовок', JSON_OBJECT(
     'hint', '{title:"[Анализ] в [лаборатория/место]: цены и условия", subtitle:"Всё о сдаче [анализа] в [место]"}',
     'fields', JSON_ARRAY('title', 'subtitle')
                       ), 1, 1),

(26, 'stats_counter', 'Ключевые факты', JSON_OBJECT(
     'hint', '3-4 факта: цена в этой лаборатории, срок, адреса, график работы.',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('value', 'label', 'suffix')
                                     ), 2, 1),

(26, 'richtext', 'Информация', JSON_OBJECT(
     'hint', 'ПЛАН (мин. 5 подблоков):
1. H2 «[Анализ] в [место]: что нужно знать» → paragraph
2. list: что входит в анализ
3. H3 «Подготовка» → paragraph: кратко
4. H3 «Как получить результат» → paragraph',
     'fields', JSON_ARRAY('blocks'),
     'block_types', JSON_ARRAY('paragraph', 'heading', 'list', 'highlight')
                            ), 3, 1),

(26, 'info_cards', 'Преимущества', JSON_OBJECT(
     'hint', '3-4 карточки: чем отличается эта лаборатория. {layout:"grid-2"}',
     'fields', JSON_ARRAY('title', 'layout', 'items'),
     'items_fields', JSON_ARRAY('icon', 'title', 'text', 'color')
                                ), 4, 0),

(26, 'faq', 'FAQ', JSON_OBJECT(
     'hint', '4-5 вопросов: «Нужна ли запись?», «Можно ли без направления?», «Есть ли скидки?», «Как добраться?».',
     'fields', JSON_ARRAY('items'),
     'items_fields', JSON_ARRAY('question', 'answer')
                ), 5, 1),

(26, 'cta', 'CTA', JSON_OBJECT(
     'hint', '{title:"Записаться в [место]", text:"Онлайн-запись без ожидания", primary_btn_text:"Записаться", primary_btn_link_key:"ORDER_ANALYSIS"}',
     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')
                ), 6, 1);


-- ── ШАБЛОН 45: «Навигационная — минимальная» ──

INSERT INTO `seo_templates` (`id`, `name`, `slug`, `description`, `gpt_system_prompt`, `css_class`) VALUES
    (27, 'Навигационная — минимальная',
     'nav-mini',
     'Мини-страница: hero + факты + CTA. Для узких навигационных запросов.',
     'Ты — медицинский копирайтер. Мини-страница для навигационного запроса. Факты + CTA.
    For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    ФОРМАТЫ:
    For the "icon" field use ONLY a single emoji character (e.g. "💬", "🤰", "❤️"). Never use URLs, image paths, or filenames.
    hero: ПЛОСКИЙ {title, subtitle, cta_text?, cta_link_key?}
    stats_counter: {items:[{value, label, suffix}]}
    faq: {items:[{question, answer}]}
    cta: ПЛОСКИЙ {title, text, primary_btn_text, primary_btn_link_key}',
     'tpl-nav-mini');

INSERT INTO `seo_template_blocks` (`template_id`, `type`, `name`, `config`, `sort_order`, `is_required`) VALUES

                                                                                                             (27, 'hero', 'Заголовок', JSON_OBJECT(
                                                                                                                     'hint', '{title:"[Анализ] в [место]", subtitle:"Цена, подготовка, запись"}',
                                                                                                                     'fields', JSON_ARRAY('title', 'subtitle')
                                                                                                                                       ), 1, 1),

                                                                                                             (27, 'stats_counter', 'Факты', JSON_OBJECT(
                                                                                                                     'hint', '3 факта: цена, срок, адрес.',
                                                                                                                     'fields', JSON_ARRAY('items'),
                                                                                                                     'items_fields', JSON_ARRAY('value', 'label', 'suffix')
                                                                                                                                            ), 2, 1),

                                                                                                             (27, 'faq', 'FAQ', JSON_OBJECT(
                                                                                                                     'hint', '3 вопроса: цена, подготовка, запись.',
                                                                                                                     'fields', JSON_ARRAY('items'),
                                                                                                                     'items_fields', JSON_ARRAY('question', 'answer')
                                                                                                                                ), 3, 1),

                                                                                                             (27, 'cta', 'CTA', JSON_OBJECT(
                                                                                                                     'hint', '{title:"Записаться", primary_btn_text:"Записаться", primary_btn_link_key:"ORDER_ANALYSIS"}',
                                                                                                                     'fields', JSON_ARRAY('title', 'text', 'primary_btn_text', 'primary_btn_link_key')
                                                                                                                                ), 4, 1);



ALTER TABLE `seo_templates`
    ADD COLUMN `intent` VARCHAR(30) NULL
        COMMENT 'Код интента из seo_intent_types'
        AFTER `css_class`,
    ADD KEY `idx_intent` (`intent`),
    ADD CONSTRAINT `fk_template_intent`
        FOREIGN KEY (`intent`) REFERENCES `seo_intent_types` (`code`)
            ON DELETE SET NULL ON UPDATE CASCADE;

UPDATE `seo_templates` SET `intent` = 'info' WHERE `id` IN (1, 2, 3);
UPDATE `seo_templates` SET `intent` = 'life_context' WHERE `id` IN (4, 5, 6);
UPDATE `seo_templates` SET `intent` = 'symptom_check' WHERE `id` IN (7, 8, 9);
UPDATE `seo_templates` SET `intent` = 'diagnosis_interpret' WHERE `id` IN (10, 11, 12);
UPDATE `seo_templates` SET `intent` = 'risk_assessment' WHERE `id` IN (13, 14, 15);
UPDATE `seo_templates` SET `intent` = 'action_plan' WHERE `id` IN (16, 17);
UPDATE `seo_templates` SET `intent` = 'doctor_prep' WHERE `id` IN (18, 19);
UPDATE `seo_templates` SET `intent` = 'comparison' WHERE `id` IN (20, 21);
UPDATE `seo_templates` SET `intent` = 'myth_debunk' WHERE `id` IN (22, 23);
UPDATE `seo_templates` SET `intent` = 'transactional' WHERE `id` IN (24, 25);
UPDATE `seo_templates` SET `intent` = 'navigational' WHERE `id` IN (26, 27);





UPDATE `seo_template_blocks`
SET `config` = JSON_SET(`config`, '$.hint',
                        '3-5 групп (мужчины, женщины, дети, беременные, пожилые). У каждой 3-5 states. caption: «Нормы [показателя] по полу и возрасту». state.description: для «Норма» — позитивно; для крайних — совет к какому врачу идти. НЕ повторяй info_cards и richtext. ПРАВИЛО КЛЮЧЕЙ STATES: используй ТОЛЬКО эти key: very_low, low, ok, high, very_high. НЕ используй «normal», «optimal», «elevated», «critical_low» — система их не понимает. ПРАВИЛО pct: very_low → pct:15; low → pct:40; ok → pct:100; high → pct:70; very_high → pct:90. pct отражает ВИЗУАЛЬНУЮ ДЛИНУ полоски на UI — она должна быть заметной для всех состояний, иначе пользователь не увидит шкалу.')
WHERE `template_id` = 1 AND `type` = 'norms_table';

-- ── Template 2 (info-textual): norms block ──
UPDATE `seo_template_blocks`
SET `config` = JSON_SET(`config`, '$.hint',
                        '3-5 групп по полу/возрасту. 3-5 states у каждой. caption: понятный заголовок. НЕ дублируй richtext. ПРАВИЛО КЛЮЧЕЙ STATES: используй ТОЛЬКО эти key: very_low, low, ok, high, very_high. НЕ используй «normal», «optimal», «elevated», «critical_low». ПРАВИЛО pct: very_low → pct:15; low → pct:40; ok → pct:100; high → pct:70; very_high → pct:90. pct отражает ВИЗУАЛЬНУЮ ДЛИНУ полоски на UI — она должна быть заметной для всех состояний.')
WHERE `template_id` = 2 AND `type` = 'norms_table';

-- ── Template 4 (life-context-full): norms_table block ──
UPDATE `seo_template_blocks`
SET `config` = JSON_SET(`config`, '$.hint',
                        'СПЕЦИФИЧНЫЕ нормы для ситуации из заголовка. НЕ стандартные общие нормы. Если беременность — по триместрам. Если спорт — для разных уровней нагрузки. Если дети — по возрастам. caption: «Нормы [показателя] для [группы]». 3-5 rows. ПРАВИЛО КЛЮЧЕЙ STATES: используй ТОЛЬКО эти key: very_low, low, ok, high, very_high. НЕ используй «normal», «optimal», «elevated», «critical_low». ПРАВИЛО pct: very_low → pct:15; low → pct:40; ok → pct:100; high → pct:70; very_high → pct:90. pct отражает ВИЗУАЛЬНУЮ ДЛИНУ полоски на UI.')
WHERE `template_id` = 4 AND `type` = 'norms_table';

-- ── Template 6 (life-context-monitor): norms_table block ──
UPDATE `seo_template_blocks`
SET `config` = JSON_SET(`config`, '$.hint',
                        'НЕ стандартные нормы, а ЦЕЛЕВЫЕ значения для мониторинга при этой ситуации. caption: «Целевые значения при [ситуации]». Пример: для спортсменов ферритин >50, а не стандартные >12. 3-5 rows. ПРАВИЛО КЛЮЧЕЙ STATES: используй ТОЛЬКО эти key: very_low, low, ok, high, very_high. НЕ используй «normal», «optimal», «elevated», «critical_low». ПРАВИЛО pct: very_low → pct:15; low → pct:40; ok → pct:100; high → pct:70; very_high → pct:90. pct отражает ВИЗУАЛЬНУЮ ДЛИНУ полоски на UI.')
WHERE `template_id` = 6 AND `type` = 'norms_table';

-- ── Template 7 (symptom-full): norms_table block ──
UPDATE `seo_template_blocks`
SET `config` = JSON_SET(`config`, '$.hint',
                        'Стандартные нормы по полу/возрасту. caption: «Нормы [показателя]». 3-5 групп, 3-5 states. В description для «Понижен» — привязка К СИМПТОМУ из заголовка. ПРАВИЛО КЛЮЧЕЙ STATES: используй ТОЛЬКО эти key: very_low, low, ok, high, very_high. НЕ используй «normal», «optimal», «elevated», «critical_low». ПРАВИЛО pct: very_low → pct:15; low → pct:40; ok → pct:100; high → pct:70; very_high → pct:90. pct отражает ВИЗУАЛЬНУЮ ДЛИНУ полоски на UI.')
WHERE `template_id` = 7 AND `type` = 'norms_table';

-- ── Template 10 (diagnosis-full): norms_table block ──
UPDATE `seo_template_blocks`
SET `config` = JSON_SET(`config`, '$.hint',
                        'ПОДРОБНЫЕ нормы. 4-5 групп. В description для каждого state — «Если ваш результат в этой зоне: [конкретное действие]». caption: «Нормы [показателя]: найдите свою группу». ПРАВИЛО КЛЮЧЕЙ STATES: используй ТОЛЬКО эти key: very_low, low, ok, high, very_high. НЕ используй «normal», «optimal», «elevated», «critical_low». ПРАВИЛО pct: very_low → pct:15; low → pct:40; ok → pct:100; high → pct:70; very_high → pct:90. pct отражает ВИЗУАЛЬНУЮ ДЛИНУ полоски на UI.')
WHERE `template_id` = 10 AND `type` = 'norms_table';

-- ── Template 11 (diagnosis-value): norms_table block ──
UPDATE `seo_template_blocks`
SET `config` = JSON_SET(`config`, '$.hint',
                        'КОМПАКТНАЯ таблица: 2-3 группы (мужчины, женщины, дети). Чтобы человек увидел, куда попадает его значение. caption: «Найдите свою группу». ПРАВИЛО КЛЮЧЕЙ STATES: используй ТОЛЬКО эти key: very_low, low, ok, high, very_high. НЕ используй «normal», «optimal», «elevated», «critical_low». ПРАВИЛО pct: very_low → pct:15; low → pct:40; ok → pct:100; high → pct:70; very_high → pct:90. pct отражает ВИЗУАЛЬНУЮ ДЛИНУ полоски на UI.')
WHERE `template_id` = 11 AND `type` = 'norms_table';

-- ── Template 12 (diagnosis-direction): norms_table block ──
UPDATE `seo_template_blocks`
SET `config` = JSON_SET(`config`, '$.hint',
                        'states должны отражать СТЕПЕНИ ОТКЛОНЕНИЯ: лёгкое, умеренное, тяжёлое + норма. description в каждом state: что делать при такой степени. 3-5 групп по полу/возрасту. ПРАВИЛО КЛЮЧЕЙ STATES: используй ТОЛЬКО эти key: very_low, low, ok, high, very_high. НЕ используй «normal», «optimal», «elevated», «critical_low». ПРАВИЛО pct: very_low → pct:15; low → pct:40; ok → pct:100; high → pct:70; very_high → pct:90. pct отражает ВИЗУАЛЬНУЮ ДЛИНУ полоски на UI.')
WHERE `template_id` = 12 AND `type` = 'norms_table';

-- ── Template 13 (risk-full): norms_table block ──
UPDATE `seo_template_blocks`
SET `config` = JSON_SET(`config`, '$.hint',
                        'states отражают УРОВНИ РИСКА, а не просто нормы: «Минимальный риск», «Умеренный риск», «Высокий риск», «Критический». description: что именно грозит на каждом уровне и что делать. caption: «Уровни риска при отклонении [показателя]». ПРАВИЛО КЛЮЧЕЙ STATES: используй ТОЛЬКО эти key: very_low, low, ok, high, very_high. НЕ используй «normal», «optimal», «elevated», «critical_low». ПРАВИЛО pct: very_low → pct:15; low → pct:40; ok → pct:100; high → pct:70; very_high → pct:90. pct отражает ВИЗУАЛЬНУЮ ДЛИНУ полоски на UI.')
WHERE `template_id` = 13 AND `type` = 'norms_table';

-- ── Template 15 (risk-disease): norms_table block ──
UPDATE `seo_template_blocks`
SET `config` = JSON_SET(`config`, '$.hint',
                        'СПЕЦИФИЧНЫЕ нормы/диапазоны при этом заболевании. Не стандартные. States отражают: «типично при заболевании», «выше ожидаемого», «критично». caption: «[Показатель] при [заболевании]: на что ориентироваться». ПРАВИЛО КЛЮЧЕЙ STATES: используй ТОЛЬКО эти key: very_low, low, ok, high, very_high. НЕ используй «normal», «optimal», «elevated», «critical_low». ПРАВИЛО pct: very_low → pct:15; low → pct:40; ok → pct:100; high → pct:70; very_high → pct:90. pct отражает ВИЗУАЛЬНУЮ ДЛИНУ полоски на UI.')
WHERE `template_id` = 15 AND `type` = 'norms_table';

UPDATE `seo_templates`
SET `gpt_system_prompt` = REPLACE(
        `gpt_system_prompt`,
        'norms_table: {caption, rows:[{name, unit, active, states:[{key, label, range, pct, description}]}]}',
        'norms_table: {caption, rows:[{name, unit, active, states:[{key, label, range, pct, description}]}}. ПРАВИЛО pct для norms_table: very_low→15, low→40, ok→100, high→70, very_high→90. Используй ТОЛЬКО ключи: very_low, low, ok, high, very_high'
                          )
WHERE `gpt_system_prompt` LIKE '%norms_table%';


ALTER TABLE seo_link_constants
    ADD COLUMN `is_tracked` TINYINT(1) NOT NULL DEFAULT 1;

INSERT INTO seo_link_constants (`key`, `url`, `is_tracked`, `article_id`) VALUES ('home', '/', 0, NULL);