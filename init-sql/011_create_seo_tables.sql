SET NAMES utf8mb4;
SET character_set_client = utf8mb4;
SET character_set_connection = utf8mb4;
SET character_set_results = utf8mb4;


CREATE TABLE IF NOT EXISTS `seo_catalogs` (
          `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
          `parent_id`   INT UNSIGNED    NULL DEFAULT NULL,
          `name`        VARCHAR(255)    NOT NULL,
          `slug`        VARCHAR(255)    NOT NULL,
          `description` TEXT            NULL,
          `sort_order`  INT             NOT NULL DEFAULT 0 COMMENT 'Порядок сортировки среди siblings',
          `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
          `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_parent` (`parent_id`),
          UNIQUE KEY `uq_slug_parent` (`parent_id`, `slug`),
          CONSTRAINT `fk_catalog_parent`
              FOREIGN KEY (`parent_id`) REFERENCES `seo_catalogs` (`id`)
                  ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Дерево каталогов для SEO-статей';

CREATE TABLE IF NOT EXISTS `seo_publish_targets` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(255)    NOT NULL,
    `type`        ENUM('hostia','ftp','ssh','api') NOT NULL DEFAULT 'hostia',
    `config`      JSON            NOT NULL COMMENT 'Настройки подключения (host, path, credentials ref)',
    `base_url`    VARCHAR(1000)   NOT NULL,
    `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Таргеты публикации (хостинги)';

CREATE TABLE IF NOT EXISTS `seo_templates` (
       `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
       `name`          VARCHAR(255)    NOT NULL,
       `slug`          VARCHAR(100)    NOT NULL,
       `description`   TEXT            NULL,
       `preview_image` MEDIUMTEXT      NULL,
       `gpt_system_prompt` TEXT        NULL,
       `css_class`     VARCHAR(100)    NULL,
       `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
       `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
       `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
       PRIMARY KEY (`id`),
       UNIQUE KEY `uq_template_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Шаблоны страниц для SEO-статей';


CREATE TABLE IF NOT EXISTS `seo_template_blocks` (
         `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
         `template_id` INT UNSIGNED    NOT NULL,
         `type`        VARCHAR(50)     NOT NULL,
         `name`        VARCHAR(255)    NOT NULL,
         `config`      JSON            NULL,
         `sort_order`  INT             NOT NULL DEFAULT 0,
         `is_required` TINYINT(1)      NOT NULL DEFAULT 1 COMMENT 'Обязательный ли блок',
         PRIMARY KEY (`id`),
         KEY `idx_template` (`template_id`),
         CONSTRAINT `fk_tpl_block_template`
             FOREIGN KEY (`template_id`) REFERENCES `seo_templates` (`id`)
                 ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Блоки, входящие в шаблон страницы';


CREATE TABLE IF NOT EXISTS `seo_articles` (
          `id`                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
          `catalog_id`          INT UNSIGNED    NULL,
          `template_id`         INT UNSIGNED    NULL,
          `title`               VARCHAR(500)    NOT NULL,
          `slug`                VARCHAR(500)    NOT NULL,
          `keywords`            TEXT            NULL,
          `meta_title`          VARCHAR(255)    NULL,
          `meta_description`    VARCHAR(500)    NULL,
          `article_plan`        VARCHAR(3000)   NULL,
          `meta_keywords`       VARCHAR(500)    NULL,
          `status`              ENUM('draft','review','published','unpublished')
                                            NOT NULL DEFAULT 'draft',
          `is_active`           TINYINT(1)      NOT NULL DEFAULT 1,
          `published_at`        TIMESTAMP       NULL,
          `published_target_id` INT UNSIGNED NULL DEFAULT NULL,
          `published_path`      VARCHAR(1000)   NULL,
          `published_url`       VARCHAR(1000)   NULL,
          `gpt_model`           VARCHAR(50)     NULL DEFAULT 'gpt-4o',
          `generation_log`      JSON            NULL,
          `version`             INT UNSIGNED    NOT NULL DEFAULT 1,
          `created_by`          VARCHAR(100)    NULL,
          `created_at`          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at`          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_catalog` (`catalog_id`),
          KEY `idx_template` (`template_id`),
          KEY `idx_status` (`status`),
          KEY `idx_slug` (`slug`(191)),
          CONSTRAINT `fk_article_catalog`
              FOREIGN KEY (`catalog_id`) REFERENCES `seo_catalogs` (`id`)
                  ON DELETE SET NULL ON UPDATE CASCADE,
          CONSTRAINT `fk_article_template`
              FOREIGN KEY (`template_id`) REFERENCES `seo_templates` (`id`)
                  ON DELETE SET NULL ON UPDATE CASCADE,
          CONSTRAINT `fk_article_pub_target`
              FOREIGN KEY (`published_target_id`) REFERENCES `seo_publish_targets` (`id`)
                  ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='SEO-статьи';


CREATE TABLE IF NOT EXISTS `seo_article_blocks` (
        `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `article_id`  INT UNSIGNED    NOT NULL,
        `type`        VARCHAR(50)     NOT NULL,
        `name`        VARCHAR(255)    NULL,
        `content`     JSON            NOT NULL,
        `sort_order`  INT             NOT NULL DEFAULT 0,
        `is_visible`  TINYINT(1)      NOT NULL DEFAULT 1,
        `gpt_prompt`  TEXT            NULL,
        `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_article` (`article_id`),
        CONSTRAINT `fk_block_article`
            FOREIGN KEY (`article_id`) REFERENCES `seo_articles` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Контентные блоки статей (структурированный JSON)';


CREATE TABLE IF NOT EXISTS `seo_images` (
        `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `article_id`  INT UNSIGNED    NULL,
        `block_id`    BIGINT UNSIGNED NULL,
        `name`        VARCHAR(255)    NULL,
        `alt_text`    VARCHAR(500)    NULL,
        `mime_type`   VARCHAR(50)     NOT NULL DEFAULT 'image/png',
        `layout`      VARCHAR(20)     NULL,
        `width`       INT UNSIGNED    NULL,
        `height`      INT UNSIGNED    NULL,
        `data_base64` MEDIUMTEXT      NOT NULL,
        `source`      ENUM('generated','uploaded','rendered')
                                      NOT NULL DEFAULT 'generated',
        `gpt_prompt`  TEXT            NULL,
        `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_article` (`article_id`),
        KEY `idx_block` (`block_id`),
        CONSTRAINT `fk_image_article`
            FOREIGN KEY (`article_id`) REFERENCES `seo_articles` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT `fk_image_block`
            FOREIGN KEY (`block_id`) REFERENCES `seo_article_blocks` (`id`)
                ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Изображения для статей (base64)';


CREATE TABLE IF NOT EXISTS `seo_link_constants` (
        `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
        `article_id`  INT UNSIGNED    NULL,
        `key`         VARCHAR(100)    NOT NULL,
        `url`         VARCHAR(2000)   NOT NULL,
        `label`       VARCHAR(255)    NULL,
        `is_active`   BOOLEAN         NOT NULL DEFAULT 1,
        `target`      VARCHAR(20)     NOT NULL DEFAULT '_blank',
        `nofollow`    TINYINT(1)      NOT NULL DEFAULT 0,
        `description` VARCHAR(500)    NULL,
        `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_article` (`article_id`),
        UNIQUE KEY `uq_key_article` (`key`, `article_id`),
        CONSTRAINT `fk_link_article`
            FOREIGN KEY (`article_id`) REFERENCES `seo_articles` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Константы ссылок (плейсхолдеры {{link:KEY}})';


CREATE TABLE IF NOT EXISTS `seo_page_stats` (
        `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `article_id`  INT UNSIGNED    NOT NULL,
        `ip`          VARCHAR(45)     NULL COMMENT 'IPv4/IPv6',
        `user_agent`  VARCHAR(500)    NULL,
        `referer`     VARCHAR(2000)   NULL,
        `country`     VARCHAR(2)      NULL COMMENT 'ISO 3166-1 alpha-2 (если определяем)',
        `device_type` ENUM('desktop','mobile','tablet','bot','unknown')
                                      NOT NULL DEFAULT 'unknown',
        `visited_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_article_date` (`article_id`, `visited_at`),
        KEY `idx_visited` (`visited_at`),
        CONSTRAINT `fk_stats_article`
            FOREIGN KEY (`article_id`) REFERENCES `seo_articles` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Сырые хиты посещений SEO-страниц';


CREATE TABLE IF NOT EXISTS `seo_page_stats_daily` (
      `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `article_id`    INT UNSIGNED    NOT NULL,
      `date`          DATE            NOT NULL,
      `views_total`   INT UNSIGNED    NOT NULL DEFAULT 0,
      `views_unique`  INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'Уникальные по IP',
      `views_desktop` INT UNSIGNED    NOT NULL DEFAULT 0,
      `views_mobile`  INT UNSIGNED    NOT NULL DEFAULT 0,
      `views_tablet`  INT UNSIGNED    NOT NULL DEFAULT 0,
      `views_bot`     INT UNSIGNED    NOT NULL DEFAULT 0,
      `top_referers`  JSON            NULL COMMENT 'Топ-5 реферреров за день',
      `top_countries` JSON            NULL COMMENT 'Топ-5 стран за день',
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_article_date` (`article_id`, `date`),
      KEY `idx_date` (`date`),
      CONSTRAINT `fk_daily_stats_article`
          FOREIGN KEY (`article_id`) REFERENCES `seo_articles` (`id`)
              ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Агрегированная дневная статистика посещений';


CREATE TABLE IF NOT EXISTS `seo_audit_log` (
       `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
       `entity_type` VARCHAR(50)     NOT NULL COMMENT 'article|template|catalog|image|link',
       `entity_id`   INT UNSIGNED    NOT NULL,
       `action`      VARCHAR(50)     NOT NULL COMMENT 'create|update|delete|publish|unpublish|generate|regenerate',
       `actor`       VARCHAR(100)    NULL,
       `details`     JSON            NULL,
       `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
       PRIMARY KEY (`id`),
       KEY `idx_entity` (`entity_type`, `entity_id`),
       KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Аудит-лог действий в SEO-модуле';


INSERT INTO `seo_catalogs` (`id`, `parent_id`, `name`, `slug`, `description`, `sort_order`) VALUES
(1,  NULL, 'Анализы крови',         'analizy-krovi',         'Статьи о различных анализах крови',               1),
(2,  NULL, 'Биохимия',              'biokhimiya',            'Биохимические исследования',                      2),
(3,  NULL, 'Гормоны',               'gormony',               'Гормональные исследования',                       3),
(4,  NULL, 'Инфекции',              'infektsii',             'Инфекционные заболевания и анализы',              4),
(5,  NULL, 'Общие исследования',    'obshchie-issledovaniya','Общие клинические исследования',                  5),
(6,  NULL, 'Онкомаркеры',           'onkomarkery',           'Онкологические маркеры',                          6),
(7,  NULL, 'Витамины и микроэлементы','vitaminy',            'Анализы на витамины и микроэлементы',             7),

(10, 1,    'Общий анализ крови',    'oak',                   'ОАК и его показатели',                            1),
(11, 1,    'Коагулограмма',         'koagulogramma',         'Свёртываемость крови',                            2),
(12, 1,    'Группа крови',          'gruppa-krovi',          'Определение группы крови и резус-фактора',        3),


(20, 2,    'Печёночные пробы',      'pechenochnye-proby',    'АЛТ, АСТ, билирубин, ГГТ',                       1),
(21, 2,    'Почечные показатели',   'pochechnye-pokazateli', 'Креатинин, мочевина, СКФ',                        2),
(22, 2,    'Липидный профиль',      'lipidnyj-profil',       'Холестерин, триглицериды, ЛПНП, ЛПВП',           3),
(23, 2,    'Глюкоза и диабет',      'glyukoza-diabet',       'Глюкоза, гликированный гемоглобин, инсулин',     4),


(30, 3,    'Щитовидная железа',     'shchitovidnaya',        'ТТГ, Т3, Т4, антитела',                          1),
(31, 3,    'Половые гормоны',       'polovye-gormony',       'Тестостерон, эстрадиол, прогестерон, ФСГ, ЛГ',   2),
(32, 3,    'Надпочечники',          'nadpochechniki',        'Кортизол, ДГЭА, альдостерон',                     3),

(40, 4,    'ВИЧ и гепатиты',        'vich-gepatity',         'ВИЧ, гепатит B, гепатит C',                      1),
(41, 4,    'ЗППП',                  'zppp',                  'Хламидии, микоплазма, уреаплазма и др.',          2),
(42, 4,    'Бактериальные инфекции','bakterialnye',          'Стрептококки, стафилококки, хеликобактер',        3);


INSERT INTO `seo_link_constants` (`article_id`, `key`, `url`, `label`, `target`, `nofollow`, `description`) VALUES
    (NULL, 'main', 'http://localhost:8080/', 'Главная', '_self',  0, 'Главная страница'),
    (NULL, 'analyse', 'http://localhost:8080/dashboard', 'Каталог', '_self', 0, 'Каталог услуг');



INSERT INTO `seo_publish_targets` (`name`, `type`, `config`, `base_url`) VALUES
    ('Hostia LAMP',
     'hostia',
     JSON_OBJECT(
             'host', 'localhost',
             'document_root', '/var/www/html/articles',
             'publish_endpoint', 'http://localhost/admin/seo_generator/deploy/publish.php',
             'method', 'POST',
             'note', 'Заметки'
     ),
     'http://localhost:8080');