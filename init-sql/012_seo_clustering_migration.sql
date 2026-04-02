SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `seo_intent_types` (
    `code`          VARCHAR(30)     NOT NULL COMMENT 'Код интента (PK)',
    `label_ru`      VARCHAR(100)    NOT NULL COMMENT 'Человекочитаемое название на русском',
    `label_en`      VARCHAR(100)    NOT NULL COMMENT 'Человекочитаемое название на английском',
    `color`         VARCHAR(7)      NOT NULL DEFAULT '#6366f1' COMMENT 'HEX-цвет для UI (badge)',
    `sort_order`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `description`   TEXT            NOT NULL COMMENT 'Что означает этот интент — для людей',
    `gpt_hint`      TEXT            NOT NULL COMMENT 'Инструкция для GPT при кластеризации: когда назначать этот интент',
    `article_tone`  TEXT            NULL COMMENT 'Тон и структура статьи для этого интента',
    `article_open`  TEXT            NULL COMMENT 'Пример открытия статьи для этого интента',
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`code`),
    KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Справочник типов поискового интента';

INSERT INTO `seo_intent_types` (`code`, `label_ru`, `label_en`, `color`, `sort_order`, `description`, `gpt_hint`, `article_tone`, `article_open`) VALUES

    ('info', 'Справочный', 'Informational', '#60a5fa', 1,
    'Человек хочет понять, что это за анализ / показатель / вещество. Чисто познавательный интерес, ещё нет конкретной проблемы.',
    'Назначай intent=info когда запрос — общее определение или функция: "что такое X", "X за что отвечает", "X функции в организме". НЕ назначай, если есть признак проблемы (повышен/понижен/симптомы).',
    'Тон — энциклопедический, но доступный. Структура: определение → роль в организме → почему важно → когда стоит обратить внимание.',
    'Ферритин — это белок, который запасает железо в тканях. Без него ваши клетки буквально голодают, даже если железо в крови в норме.'),

    ('symptom_check', 'Проверка симптомов', 'Symptom Check', '#f472b6', 2,
    'Человек замечает у себя симптомы (выпадение волос, усталость, головокружение) и хочет понять, связано ли это с показателем. Ищет объяснение своему состоянию.',
    'Назначай intent=symptom_check когда запрос содержит описание симптома или состояния + название анализа/показателя: "ферритин выпадение волос", "низкий ферритин усталость", "ферритин ломкие ногти", "ферритин бледная кожа". Ключевое: человек идёт ОТ симптома К показателю.',
    'Тон — эмпатичный, "я понимаю, что вы чувствуете". Структура: описание симптома → как связано с показателем → механизм → что проверить → когда к врачу.',
    'Если волосы остаются на подушке и расчёске больше обычного — возможно, дело не в шампуне. Одна из самых частых причин — скрытый дефицит железа, который покажет анализ на ферритин.'),

    ('diagnosis_interpret', 'Расшифровка результатов', 'Diagnosis Interpret', '#a78bfa', 3,
    'Человек получил результат анализа и не понимает, что значат цифры. Держит бумажку из лаборатории и гуглит.',
    'Назначай intent=diagnosis_interpret когда запрос содержит конкретные цифры, слова "расшифровка", "что значит результат", "что означает", фразы типа "ферритин 5 что значит", "ферритин 300 опасно ли", "ферритин выше/ниже нормы что делать". Ключевое: человек УЖЕ сдал анализ.',
    'Тон — спокойный, разъясняющий, без паники. Структура: что показывает число → норма для вашей группы → возможные причины отклонения → следующие шаги.',
    'Вы получили результат анализа — ферритин 12 мкг/л. Лаборатория, возможно, даже не пометила его как отклонение. Но для женщины 30 лет это уже серьёзный сигнал.'),

    ('action_plan', 'План действий', 'Action Plan', '#34d399', 4,
    'Человек уже знает проблему и ищет конкретное решение: как повысить, как снизить, что принимать, какую диету соблюдать.',
    'Назначай intent=action_plan когда запрос начинается с "как повысить", "как снизить", "как восстановить", "как нормализовать", содержит слова "лечение", "препараты", "диета для повышения", "таблетки", "добавки". Ключевое: человек хочет ДЕЙСТВОВАТЬ.',
    'Тон — практичный, пошаговый. Структура: краткая суть проблемы → план по шагам (питание → добавки → контроль) → чего не делать → когда ждать результат → когда к врачу.',
    'Врач сказал повысить ферритин — а как именно? Вот пошаговый план, который реально работает: от питания до выбора препарата и контрольного анализа.'),

    ('risk_assessment', 'Оценка рисков', 'Risk Assessment', '#fb923c', 5,
    'Человек оценивает опасность своего состояния. Главный вопрос: "насколько это серьёзно и стоит ли паниковать?"',
    'Назначай intent=risk_assessment когда запрос содержит слова "опасно ли", "чем грозит", "последствия", "осложнения", "к чему приводит", "ферритин при онкологии", "ферритин при раке". Ключевое: человек оценивает СТЕПЕНЬ угрозы.',
    'Тон — взвешенный, без драматизации и без обесценивания. Структура: что может означать → когда это не опасно → когда это серьёзно → красные флаги (срочно к врачу) → что делать прямо сейчас.',
    'Ферритин 500 — это опасно? Короткий ответ: зависит от контекста. Давайте разберёмся, когда это временная реакция организма, а когда — сигнал, который нельзя игнорировать.'),

    ('life_context', 'Жизненная ситуация', 'Life Context', '#2dd4bf', 6,
    'Запрос привязан к конкретной жизненной ситуации: беременность, спорт, возраст, диета, конкретное заболевание. Человеку нужна информация применительно к ЕГО контексту.',
    'Назначай intent=life_context когда запрос содержит конкретную группу/ситуацию: "при беременности", "у спортсменов", "при вегетарианстве", "после родов", "при ГВ", "у детей до года", "при диабете", "после химиотерапии", "при похудении". Ключевое: есть КОНКРЕТНЫЙ контекст жизни.',
    'Тон — адресный, "именно для вашей ситуации". Структура: почему в этой ситуации показатель особенно важен → нормы для этой группы → типичные проблемы → конкретные рекомендации → мониторинг.',
    'Во время беременности потребность в железе вырастает в 3 раза. И стандартная "норма" ферритина из бланка анализа вам не подходит — для будущих мам нужны совсем другие цифры.'),

    ('doctor_prep', 'Подготовка к врачу/анализу', 'Doctor Prep', '#fbbf24', 7,
    'Человек готовится к сдаче анализа или визиту к врачу. Хочет сделать всё правильно, не выглядеть глупо.',
    'Назначай intent=doctor_prep когда запрос содержит: "как сдавать", "подготовка к анализу", "натощак или нет", "когда сдавать", "к какому врачу", "какие анализы сдать вместе", "что спросить у врача". Ключевое: человек ГОТОВИТСЯ к медицинскому действию.',
    'Тон — инструкция-чеклист, практичный. Структура: когда и зачем сдавать → подготовка (за N дней / накануне / в день сдачи) → какие анализы сдать вместе → к какому врачу с результатами.',
    'Собираетесь сдать ферритин? Есть несколько простых правил, которые помогут получить точный результат — и не тратить деньги на пересдачу.'),

    ('comparison', 'Сравнение', 'Comparison', '#c084fc', 8,
    'Человек сравнивает два показателя, метода, препарата. Хочет понять разницу.',
    'Назначай intent=comparison когда запрос содержит "vs", "или", "отличие", "разница", "что лучше", "ферритин и железо", "ферритин и гемоглобин". Ключевое: человек СРАВНИВАЕТ два понятия.',
    'Тон — аналитический, со структурой "A vs B". Структура: что каждый показывает → в чём ключевая разница → когда нужен один, когда другой → таблица сравнения.',
    'Ферритин и сывороточное железо — это не одно и то же, хотя оба связаны с железом. Один показывает запасы, другой — то, что циркулирует прямо сейчас.'),

    ('transactional', 'Транзакционный', 'Transactional', '#4ade80', 9,
    'Человек готов к действию: купить, записаться, заказать анализ. Ближе всего к конверсии.',
    'Назначай intent=transactional когда запрос содержит: "цена", "стоимость", "купить", "заказать", "записаться", "где сдать", конкретные названия лабораторий (Инвитро, Гемотест). Ключевое: человек готов ПЛАТИТЬ или ДЕЙСТВОВАТЬ.',
    'Тон — конкретный, сервисный. Структура: что входит в анализ → средняя цена → где сдать → как записаться → наше предложение (CTA).',
    'Анализ на ферритин стоит от 350 до 900 рублей в зависимости от лаборатории. Вот что влияет на цену и где можно сдать выгоднее.'),

    ('navigational', 'Навигационный', 'Navigational', '#94a3b8', 10,
    'Человек ищет конкретный ресурс, страницу, организацию.',
    'Назначай intent=navigational когда запрос содержит название конкретного бренда, лаборатории, сайта: "ферритин инвитро", "анализ ферритин гемотест". Ключевое: человек ищет КОНКРЕТНОЕ место.',
    'Тон — информативный, быстрый. Минимум воды, максимум полезных ссылок и практических деталей.',
    NULL),

    ('myth_debunk', 'Разоблачение мифов', 'Myth Debunk', '#f87171', 11,
    'Человек сомневается в распространённых убеждениях или столкнулся с противоречивой информацией. Хочет разобраться, где правда.',
    'Назначай intent=myth_debunk когда запрос содержит: "правда ли", "миф", "на самом деле", "вредно ли", "можно ли", "опасен ли", а также спорные темы типа "ферритин и ковид", "ферритин и вакцинация". Ключевое: человек СОМНЕВАЕТСЯ в информации.',
    'Тон — объективный, доказательный, без менторства. Структура: миф/утверждение → откуда пошло → что говорит наука → что на самом деле → вывод.',
    'В интернете часто пишут, что ферритин "должен быть равен вашему весу". Давайте разберёмся, откуда взялась эта формула и насколько ей можно доверять.');

CREATE TABLE IF NOT EXISTS `seo_keyword_jobs` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `seed_keyword`  VARCHAR(255)    NOT NULL COMMENT 'Исходное ключевое слово',
    `source`        VARCHAR(50)     NOT NULL DEFAULT 'manual' COMMENT 'yandex|google|manual',
    `status`        ENUM('pending','collecting','clustering','done','error') NOT NULL DEFAULT 'pending',
    `total_found`   INT UNSIGNED    NOT NULL DEFAULT 0,
    `total_clusters` INT UNSIGNED   NOT NULL DEFAULT 0,
    `config`        JSON            NULL COMMENT 'Параметры: region, language, depth, min_volume',
    `error_log`     TEXT            NULL,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_seed` (`seed_keyword`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Джобы сбора ключевых слов';



CREATE TABLE IF NOT EXISTS `seo_raw_keywords` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `job_id`        INT UNSIGNED    NOT NULL,
    `keyword`       VARCHAR(500)    NOT NULL,
    `source`        ENUM('yandex','google','manual','gpt') NOT NULL DEFAULT 'manual',
    `volume`        INT UNSIGNED    NULL COMMENT 'Частотность (месячная)',
    `competition`   DECIMAL(5,4)    NULL COMMENT 'Конкуренция 0.0000-1.0000',
    `cpc`           DECIMAL(8,2)    NULL COMMENT 'Цена клика',
    `cluster_id`    INT UNSIGNED    NULL COMMENT 'Заполняется при кластеризации',
    `is_processed`  TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_job` (`job_id`),
    KEY `idx_cluster` (`cluster_id`),
    KEY `idx_keyword` (`keyword`(191)),
    KEY `idx_volume` (`volume`),
    CONSTRAINT `fk_rawkw_job`
      FOREIGN KEY (`job_id`) REFERENCES `seo_keyword_jobs` (`id`)
          ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Сырые ключевые слова из API';




CREATE TABLE IF NOT EXISTS `seo_keyword_clusters` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `job_id`          INT UNSIGNED    NOT NULL,
    `parent_id`       INT UNSIGNED    NULL,
    `name`            VARCHAR(255)    NOT NULL COMMENT 'Человеческое название кластера',
    `slug`            VARCHAR(255)    NOT NULL,
    `intent`          VARCHAR(30)     NULL COMMENT 'Код интента из seo_intent_types',
    `summary`         TEXT            NULL COMMENT 'GPT-описание кластера',
    `article_angle`   TEXT            NULL COMMENT 'Рекомендуемый угол статьи',
    `template_id`     INT UNSIGNED    NULL COMMENT 'Рекомендуемый шаблон',
    `total_volume`    INT UNSIGNED    NOT NULL DEFAULT 0,
    `keyword_count`   INT UNSIGNED    NOT NULL DEFAULT 0,
    `priority`        TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0-10 по volume+competition',
    `article_id`      INT UNSIGNED    NULL COMMENT 'Связь со статьёй когда создана',
    `status`          ENUM('new','approved','article_created','rejected') NOT NULL DEFAULT 'new',
    `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_job` (`job_id`),
    KEY `idx_parent` (`parent_id`),
    KEY `idx_article` (`article_id`),
    KEY `idx_status` (`status`),
    KEY `idx_priority` (`priority`),
    KEY `idx_intent` (`intent`),
    CONSTRAINT `fk_cluster_job`
      FOREIGN KEY (`job_id`) REFERENCES `seo_keyword_jobs` (`id`)
          ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_cluster_parent`
      FOREIGN KEY (`parent_id`) REFERENCES `seo_keyword_clusters` (`id`)
          ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_cluster_template`
      FOREIGN KEY (`template_id`) REFERENCES `seo_templates` (`id`)
          ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_cluster_article`
      FOREIGN KEY (`article_id`) REFERENCES `seo_articles` (`id`)
          ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_cluster_intent`
      FOREIGN KEY (`intent`) REFERENCES `seo_intent_types` (`code`)
          ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Семантические кластеры ключевых слов';