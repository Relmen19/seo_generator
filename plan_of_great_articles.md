# SEO Generator — Roadmap

**Snapshot:** 2026-04-26 · branch `main` @ `b9b81a8`
**Stack:** PHP 8.0 (`php:8.0-apache`), кастомный MVC, namespace `Seo\` (PSR-4 → `entity/`, `controller/`, `service/`, `enum/`).
**Контейнер:** docker-compose, MySQL 8, Puppeteer-сервис.

Документ — основа для следующей сессии. В каждом разделе: цель, текущее состояние, ограничения, шаги, критерии готовности.

---

## 0. Краткий статус (что уже сделано)

| Промпт | Что | Статус |
|--------|-----|--------|
| 1. Outline-first | `OutlinePrompt`, `ArticleOutlineService`, валидация ссылок на dossier id | ✅ |
| 2. RichText редизайн | расширенные форматы (code/callout/footnote/figure/quote/table/inline_code) | ✅ |
| 3. Иллюстрации | hero/og pipeline, brand kit в профиле | ✅ (база) |
| 4. Theming | `seo_themes` таблица, `ThemeService`, legacy bridge `--blue→--color-accent`, demo `/public/article-demo.php` | ⚠️ частично — 22 рендера всё ещё используют legacy aliases |
| 5. Editorial QA | 6 правил, `EditorialQaService`, `ArticleQaController`, UI секция, force-override gate, cron `qa_worker.php`, workflow status select | ✅ |
| 6. Research v2 | `research_strategy` ENUM, 2-фазный pipeline (single/split/split_search), `WebSearchClient` (Brave), `GptClient::chatJsonWithTools` | ✅ pipeline · ⚠️ UI прогресса по фазам нет |

**Незакрытые опции из исходных промптов:**
- 5.3 — AI-review pass (категория токенов `CATEGORY_ARTICLE_AI_REVIEW`)
- 6.4 — Prune phase (мини-проход чистит off-topic items)
- 6.5 — UI прогресс по фазам research

---

## 1. Архитектурный обзор (для контекста)

### Слои
- `controllers/` — REST через `router.php?r={resource}` диспатчер. 18 контроллеров, наследуют `AbstractController`. JSON-only.
- `service/` — бизнес-логика. Основные: `ArticleGeneratorService`, `ArticleResearchService`, `ArticleOutlineService`, `EditorialQaService`, `HtmlRendererService`, `PromptBuilder`, `GptClient`, `TokenUsageLogger`.
- `service/HtmlRenderer/` — рендер HTML. `BlockRegistry` диспатчит 44 типа блоков → `Block/*BlockRenderer.php`. Темы через `ThemeService` + `Theme/*`. Компоненты (Navbar, TOC, Footer, Tracking, Parallax) — `Component/*`.
- `service/Editorial/Rule/` — 6 QA-правил (полиморфизм через `RuleInterface`).
- `entity/` — 21 PDO-hydrated DTO. `AbstractEntity` базовый.
- `enum/` — 7 prompt-наборов (`ArticlePrompt`, `OutlinePrompt`, `ResearchPrompt`, ...).
- `init-sql/` — 39 миграций (последние: `037_themes.sql`, `038_editorial_qa.sql`, `039_research_strategy.sql`).
- `admin_simple/` — упрощённый UI (`articles.php` 2234 LOC, `profiles.php` 2905 LOC).
- `admin_advanced/` — расширенный UI (`seo_page.php` 6041 LOC, `seo_profile_page.php` 3497 LOC, `seo_clustering_page.php` 1643 LOC, `seo_themes_page.php`).
- `cron/` — `telegram_sender.php`, `qa_worker.php`. CLI, file-lock, `logMessage()`.
- `public/` — `article-demo.php` (демо тем).

### Ключевые таблицы
| Таблица | Назначение |
|---------|-----------|
| `seo_articles` | статьи + workflow status + research_dossier (JSON) + article_outline (JSON) |
| `seo_article_blocks` | блоки (type, content JSON, sort_order, is_visible) |
| `seo_article_illustrations` | hero/og (article_id, image_id, status: pending/ready/stale) |
| `seo_article_issues` | результаты QA (severity, code, message, block_id, resolved_at) |
| `seo_block_types` | реестр 44 типов с JSON-schema |
| `seo_site_profiles` | профили + brand_palette + research_strategy + default_theme_code |
| `seo_themes` | темы (code, name, tokens JSON) |
| `seo_intent_types` | code, label_ru, tone, structure |
| `seo_token_usage` | расход GPT (category, operation, prompt/completion/total) |
| `seo_audit_log` | actor, action, entity, details JSON |
| `seo_telegram_posts` | посты в TG-канал |

---

## 2. Критические узкие места

### 2.1 Гигантские UI-файлы (15-20% codebase, technical debt)

| Файл | LOC | Проблема |
|------|-----|---------|
| `admin_advanced/seo_page.php` | 6041 | Ключи + кластеризация + шаблоны + интенты + генерация в одном HTML+JS |
| `admin_advanced/seo_profile_page.php` | 3497 | 8 вкладок профиля inline |
| `admin_simple/profiles.php` | 2905 | CRUD + бренд-кит + интенты |
| `admin_simple/articles.php` | 2234 | список + редактор + генератор + превью + QA + workflow |
| `service/TelegramPostService.php` | 1988 | 18 методов: drafts/images/send/schedule/sync |
| `service/TelegramBlockFormatterService.php` | 1373 | форматирование 40+ типов блоков для TG |

**Эффект:** долгие правки, мердж-конфликты, нельзя кешировать JS отдельно.

### 2.2 Legacy CSS bridge (Stage 4 не закрыт)

22 файла содержат `var(--blue)`, `var(--dark)`, `var(--muted)`, `var(--white)`, `var(--red)`, `var(--fh)`, `var(--fb)`. Bridge в `ThemeService` маппит их на `--color-accent` etc., но:
- темы кастомных профилей не могут переопределить часть переменных без знания обоих имён;
- `seo_themes_page.php` редактирует только новые токены — legacy остаются жёсткими.

**Список файлов:**
- `service/HtmlRenderer/Block/`: BeforeAfter, ComparisonCards, Cta, ExpertPanel, Hero, MiniCalculator, NormsTable, NumberedSteps, PrepChecklist, ProgressTracker, RangeComparison, SparkMetrics, Story, SymptomChecklist, Testimonial, ValueChecker, VerdictCard.
- `service/HtmlRenderer/Component/`: Navbar, Toc.
- `service/HtmlRenderer/Theme/`: Default, Editorial, Brutalist (это producers — переопределяют).

### 2.3 Path traversal в иконках профиля

`controllers/SiteProfileController.php` (~strok 379, 396):
- `move_uploaded_file($file['tmp_name'], UPLOADS_DIR . $relPath)` — `$relPath` собирается из user-input части (filename), нет `realpath()` проверки.
- `readfile(UPLOADS_DIR . $row['icon_path'])` — если icon_path в БД содержит `../../etc/passwd` (пишется через тот же endpoint), утечка.

**Mitigation:** валидировать `$relPath` через `realpath()` и проверять `str_starts_with(realpath, UPLOADS_DIR)`.

### 2.4 N+1 запросы

- `TelegramBlockFormatterService` — на каждый блок: `SELECT data_base64 FROM seo_images WHERE id = ?`. 100 блоков = 100 запросов.
- `KeywordCollectorService` — `foreach ($keywords as $kw) file_get_contents($url)` без rate-limit.
- `cron/telegram_sender.php` — `processScheduledPosts()` берёт всё без LIMIT.

### 2.5 Дублирование json_decode блоков

44 рендера повторяют:
```php
$content = is_string($block['content']) ? json_decode($block['content'], true) : ($block['content'] ?? []);
```

Уже есть `Editorial\TextExtractor::blockContent()` — переиспользовать или ввести `AbstractBlockRenderer::getContent()`.

### 2.6 Pre-existing bug, частично закрыт

`ResearchPrompt::SKELETON` ссылается из старого кода — был undefined, в Stage 6a добавлен alias на `FORMAT`. Убрать alias и переименовать вызов в `single`-pipeline на `FORMAT` напрямую.

---

## 3. Очередь промптов

Каждый промпт — атомарная сессия. Зависимости явно прописаны. Размер S/M/L (≈ 1-3 часа / полдня / день).

---

### Prompt 7 — Block Fixer: автокоррекция QA-замечаний (M)

**Цель:** замкнуть петлю QA — после `runChecks` есть кнопка «Исправить автоматически», которая регенерит проблемные блоки под constraints.

**Зависит от:** Stage 5 (QA), Stage 6 (Research v2).

**Шаги:**

1. `service/Editorial/Fixer/FixerInterface.php`:
   ```php
   interface FixerInterface {
       public function code(): string;       // "repetition", "banned_phrase", "empty_chart"
       public function fix(array $article, array $blocks, array $issues): array; // returns updated blocks
   }
   ```

2. Конкретные fixer'ы:
   - `RepetitionFixer` — собирает повторяющиеся фразы из issues, GPT-промпт «перепиши блок без этих фраз, сохрани смысл», обновляет `content` JSON.
   - `BannedPhraseFixer` — то же для штампов.
   - `EmptyChartFixer` — реагирует только если есть `research_dossier` — извлекает benchmarks по теме блока, заполняет `data` структуру (для chart/gauge_chart/heatmap по schema из `seo_block_types.content_schema`).

3. `service/EditorialFixerService.php`:
   - `applyFixes(int $articleId, array $codes = []): array` — фильтрует issues, диспатчит fixer'ы, пишет блоки, аудит.
   - Лимит 1 проход на код, чтобы не зацикливаться.
   - Категория токенов `CATEGORY_ARTICLE_FIXER` (новая в `TokenUsageLogger`).

4. UI:
   - В секции «Редакторские проверки» (`admin_simple/articles.php`) кнопка «🛠 Исправить автоматически» рядом с «Прогнать проверки».
   - После fix — auto-rerun `runChecks`.

5. API: `POST /controllers/router.php?r=qa/{articleId}/fix` с `{codes: ["repetition","banned_phrase"]}`.

**DoD:** на статье `id=11` (тест-статья «aurum vector») после Fixer количество repetition-issues уменьшается ≥ 70%, banned_phrase = 0, empty_chart = 0.

---

### Prompt 8 — UI декомпозиция admin_simple (L)

**Цель:** разбить `admin_simple/articles.php` (2234 LOC) на отдельные JS-модули + единый CSS-файл.

**Зависит от:** —

**Шаги:**

1. Создать `admin_simple/assets/`:
   - `articles.css` — все стили из inline `<style>`.
   - `articles/state.js` — `S` (state) + `api()` helper.
   - `articles/editor.js` — `openArticle`, `renderEditor`, autosave, status pill.
   - `articles/blocks.js` — block CRUD, regenerate.
   - `articles/qa.js` — refresh/run/resolve/fix.
   - `articles/publish.js` — publish modal + force-override.
   - `articles/workflow.js` — status select.
   - `articles/wizard.js` — create modal.
   - `articles/preview.js` — full preview iframe.

2. `articles.php` остаётся ~300 LOC HTML + `<script type="module">import` каждого модуля.

3. Не переходим на сборщик (vite/webpack) — нативные ES modules. Совместимо с PHP-серверной отдачей.

**Risk:** API helper и `S` global должны экспортироваться/импортироваться без циклических зависимостей. Делать `api.js` чистым, `state.js` зависимым от `api.js`, всё остальное — от обоих.

**DoD:** функциональность не меняется. `articles.php` ≤ 400 LOC. Каждый модуль ≤ 350 LOC.

---

### Prompt 9 — Path traversal fix + security pass (S)

**Цель:** закрыть 2.3.

**Шаги:**

1. `controllers/SiteProfileController.php`:
   - В upload: `$filename = basename($filename)` (убирает `../`), generate slug-only filename `profile_{id}_{md5(rand)}.ext`.
   - В serve: `$abs = realpath(UPLOADS_DIR . $row['icon_path']); if (!$abs || strpos($abs, realpath(UPLOADS_DIR)) !== 0) abort(404);`.

2. То же для остальных endpoint'ов с `readfile()`/`move_uploaded_file()` — найти grep'ом, поправить.

3. `service/TelegramApiClient.php` — `tempnam(sys_get_temp_dir(), 'tg_')` вместо `md5(time())`.

**DoD:** ручной тест с `icon_path = "../../config.php"` возвращает 404.

---

### Prompt 10 — Theming closure: миграция legacy CSS vars (M)

**Цель:** закрыть 2.2 — все рендеры используют только новые токены, `ThemeService` bridge удаляется.

**Зависит от:** Stage 4 (готов).

**Шаги:**

1. Карта замен (фиксированная):
   ```
   --blue        → --color-accent
   --blue-light  → --color-accent-soft
   --dark        → --color-text
   --dark2       → --color-surface
   --muted       → --color-text-3
   --slate       → --color-text-2
   --border      → --color-border
   --fh          → --type-font-heading
   --fb          → --type-font-text
   --white       → --color-surface
   --red         → --color-danger
   ```

2. Прогон по 19 файлам из 2.2 (Block/* + Component/*) через `Edit replace_all`.

3. Удалить bridge-блок из `ThemeService::renderCssVars()`.

4. Тесты: открыть `/public/article-demo.php?theme=default|editorial|brutalist` — визуальная парность с baseline-скриншотами (сделать перед миграцией).

**DoD:** `grep -rn "var(--blue\|--dark\|--muted\|--border\|--white\|--red\|--fh\|--fb)" service/HtmlRenderer/` пусто.

---

### Prompt 11 — Per-article theme override (S)

**Цель:** закрыть Stage 4 — на статью можно поставить тему отдельно от профильной.

**Шаги:**

1. Миграция `init-sql/040_article_theme.sql`: `ALTER TABLE seo_articles ADD COLUMN theme_code VARCHAR(64) NULL AFTER status;`
2. `entity/SeoArticle.php` — поле + accessor.
3. `ThemeService::resolveForArticle($article, $profile)` уже учитывает это — проверить порядок: article → profile.default_theme_code → profile.theme → 'default'.
4. UI: select в `admin_simple/articles.php` (advanced) рядом с workflow status. Опция «— наследовать от профиля —».
5. `ArticleController` PUT: разрешить `theme_code` в whitelist полей.

**DoD:** на одну статью можно поставить `editorial`, на соседнюю в том же профиле — `brutalist`. Превью отражает.

---

### Prompt 12 — Research progress UI + Prune phase (M)

**Цель:** закрыть незакрытые опции 6.4 + 6.5.

**Шаги:**

1. **Prune phase** (опц 6.4):
   - В `ArticleResearchService::buildSplit` после fill — третий вызов: дешёвая модель (`gpt-4.1-mini`) получает angle + собранные items, возвращает `{remove_ids: ["f3","b1",...]}`.
   - Удалить items из collected, перевалидировать.
   - Operation: `research_prune`.
   - Toggle через opts['prune'] / профиль (опц).

2. **UI прогресса** (6.5):
   - SSE-endpoint `/controllers/router.php?r=generate/{id}/research-stream` (или JSON polling, что проще).
   - В service писать промежуточный статус в `seo_articles.research_status` enum: `none|outline|fill|prune|ready|stale`.
   - В `admin_simple/articles.php` (research card в advanced): прогресс-бар с фазами, рядом — счётчик токенов на каждую (из `seo_token_usage` group by operation для текущей entity_id).

**DoD:** при `strategy=split_search` пользователь видит outline → fill (по секциям с цифрами) → prune → ready, и токены.

---

### Prompt 13 — N+1 batch + rate limits (S)

**Цель:** 2.4.

**Шаги:**

1. `TelegramBlockFormatterService`:
   - Соберать все `image_id` из блоков на старте. Один `SELECT id, data_base64 FROM seo_images WHERE id IN (...)`. Кэш в private array.
   - Пройтись по блокам с готовым кэшем.

2. `cron/telegram_sender.php`:
   - В `processScheduledPosts` добавить `LIMIT 50` + `usleep(200000)` между API-вызовами.

3. `KeywordCollectorService::*` — найти циклы с `file_get_contents`/HTTP, добавить `usleep(100000)`.

**DoD:** профилировать форматирование TG-поста на статье с 50+ блоками: было N+1, стало 2 запроса.

---

### Prompt 14 — AbstractBlockRenderer + dedupe (S)

**Цель:** 2.5.

**Шаги:**

1. `service/HtmlRenderer/Block/AbstractBlockRenderer.php`:
   ```php
   abstract class AbstractBlockRenderer {
       protected function getContent(array $block): array {
           if (is_array($block['content'] ?? null)) return $block['content'];
           if (is_string($block['content'] ?? null)) {
               $d = json_decode($block['content'], true);
               return is_array($d) ? $d : [];
           }
           return [];
       }
       abstract public function render(array $block, array $ctx): string;
   }
   ```

2. Все 44 рендера extends его. Заменить inline `json_decode($block['content'], true) ?? []` на `$this->getContent($block)`.

3. `BlockRegistry` уже работает по interface — без изменений.

**DoD:** механический рефактор. Diff большой, поведение идентично.

---

### Prompt 15 — AI-review pass (опц 5.3) (M)

**Цель:** последняя опциональная задача из Stage 5.

**Шаги:**

1. `service/Editorial/AiReviewService.php`:
   - Получает article + blocks + текущие issues.
   - GPT-промпт (gpt-4.1-mini): «оцени связность 0-10, найди логические разрывы, дублирования смысла, скачки темы. Верни JSON: `{coherence: 0-10, gaps: [...], notes: "..."}`».
   - Запись `info|warn` issue с code `ai_review` в `seo_article_issues`.

2. `EditorialQaService::runChecks` — добавить опц параметр `$includeAiReview = false`. Если true — после правил вызвать `AiReviewService`.

3. UI: чекбокс «Включить AI-ревью» рядом с «Прогнать проверки».

4. Категория токенов: `TokenUsageLogger::CATEGORY_ARTICLE_AI_REVIEW = 'article_ai_review'`.

**Стоимость:** ~$0.01 на статью (gpt-4.1-mini, ~3k токенов входа).

**DoD:** AI выдаёт оценку и 1-3 заметки, видны в UI.

---

### Prompt 16 — Декомпозиция admin_advanced/seo_page.php (L)

**Цель:** 2.1, самый большой файл (6041 LOC).

**Шаги:**

1. По вкладкам разнести в `admin_advanced/keywords.php`, `admin_advanced/clustering.php`, `admin_advanced/intents.php`, `admin_advanced/templates.php`, `admin_advanced/generation.php`.
2. Общий topbar и роутинг через querystring `?tab=keywords`.
3. Альтернатива: оставить hub-страницу, грузить вкладки через `<iframe>` или fetch'ом.

**Обсуждение:** до этого можно дойти после 8/9/10. Возможно overkill — обсудить с пользователем.

**Risk:** сильно меняет навигацию. Сначала согласовать UX.

---

### Prompt 17 — Метрики стоимости research до/после (S)

**Цель:** проверить тезис плана 6: «⩽30% рост стоимости при существенно меньше галлюцинаций».

**Шаги:**

1. Скрипт `cron/cost_report.php`:
   - Вычислить из `seo_token_usage`: avg(prompt+completion) по category=`article_research` group by operation, за 30 дней.
   - Сравнить single vs split vs split_search.
2. SQL view + страница `admin_advanced/cost_report.php` с графиком (Chart.js inline).

**DoD:** видно дашборд: 5 статей в single = $X, 5 в split = $Y. Если split на ≥30% дороже — пересмотреть.

---

## 4. Незапланированные но ценные

| ID | Что | Размер | Зачем |
|----|-----|--------|-------|
| X1 | Тесты PHPUnit для критичных сервисов (`EditorialQaService`, `ArticleResearchService::buildSplit`, `ThemeService::resolveForArticle`) | M | Сейчас 0 покрытия. Граф анализ показывает все правила untested. |
| X2 | E2E playwright для admin_simple (открыть статью, прогнать QA, опубликовать) | M | UI меняется часто, регрессии незаметны |
| X3 | Backup-скрипт перед prod-публикацией (dump `seo_articles`, `seo_article_blocks`, `seo_themes`) | S | Сейчас публикация записывает на удалённый сервер без отката |
| X4 | Перевод `init-sql` в управляемые миграции (записывать применённые версии в `migrations` таблицу) | S | Сейчас ручной порядок, легко пропустить |

---

## 5. Карта зависимостей

```
P7 Fixer ─────────────────┐
                          ↓
P15 AI-review ─────► QA loop closed

P8 admin_simple split ┬─► P11 per-article theme select
                      └─► P12 research progress UI

P10 Theming closure ──► P11 per-article theme

P9 security ───── independent
P13 N+1 ───── independent
P14 AbstractBlockRenderer ───── independent

P16 admin_advanced split ── only after P8 validates pattern

P17 cost report ── after several articles run on split/split_search
```

Рекомендуемый порядок: **9 → 14 → 13 → 7 → 10 → 11 → 12 → 15 → 8 → 17 → 16**.

(Сначала чистка/безопасность/dedupe, потом фичи QA-loop, потом темы/UI, потом метрики, в конце — большой UI рефактор.)

---

## 6. Договорённости по работе

- **Коммитить после каждого подэтапа.** Conventional/русский тон сохранить как в истории (см. `git log` за Stage 5/6 — формат `Stage Nx: <что>` принят).
- **PHP 8.0** — никаких enums/readonly/never. Тестировать в `php:8.0-apache`.
- **Не создавать `.md` без запроса.** Этот файл — единственное исключение, как точка входа.
- **CSS/тексты UI** на русском, код — английский.
- **Брендинг и темы** не трогать без обсуждения.
- **Cron-задачи** проверять `*/5 * * * *` интервал, не чаще.
- **Code-review-graph MCP** — использовать BEFORE Grep/Read для исследования (`semantic_search_nodes`, `query_graph`, `get_impact_radius`, `detect_changes`). Граф доступен по умолчанию.

---

## 7. Точка входа в новую сессию

> «Открой `plan_of_great_articles.md`. Прошлая сессия закрыла Stage 1-6. Берём **Prompt 9** (security pass, path traversal в SiteProfileController). Размер S, без зависимостей.»

или

> «Открой `plan_of_great_articles.md`. Хочу пройти весь раздел 3 в порядке 9 → 14 → 13. Делай по одному, коммить после каждого, между ними жди подтверждения.»

---

## 8. Ревью stage 1-6 (2026-04-26)

Прошёл по коду закрытых stage. Ниже — найденные баги/недочёты, сгруппированные по stage и severity. Severity: **H** = функциональный баг или утечка стоимости; **M** = деградация качества/UX; **L** = техдолг/мелочь.

### Stage 6 — Research v2

- **[H] `sources` никогда не заполняется в split/split_search.** `ArticleResearchService::buildSplit` line 196: `$sectionsOrder` = `['facts','entities','benchmarks','comparisons','counter_theses','quotes_cases','terms']` — **без `sources`**. Дальше `useSearch = ... && in_array($sec, ['benchmarks','sources'], true)` — проверка на `sources` мёртвый код, цикл туда не доходит. `OUTLINE_FORMAT` тоже не выдаёт `questions.sources`. Итого: dossier из split-пайплайна всегда имеет `sources: []`, даже в `split_search`. Фикс: либо добавить `sources` в `sectionsOrder` + `questions.sources` в outline, либо собирать sources из `source` полей facts/benchmarks в отдельный пост-проход.
- **[M] Минимум `facts` рассинхрон.** `validateDossier` бросает на `< 3`, а `SYSTEM` промпт требует `facts ≥ 8`. Модель будет считать что 3 ОК, валидация пропустит — статья выйдет хилая. Поднять порог до 5-6 или явно прописать в обоих местах.
- **[M] `WebSearchClient` пересоздаётся в цикле.** Line 216 внутри `foreach ($sectionsOrder as $sec)`. Не критично (нет I/O в конструкторе), но `disabled()` дёргается per-секция; вынести экземпляр перед циклом.
- **[L] `ResearchPrompt::SKELETON` alias не убран.** Plan 2.6 уже фиксирует — alias на `FORMAT`. После Stage 6a `single`-pipeline всё ещё ссылается через alias (line 507). При cleanup переписать вызов на `FORMAT`.
- **[L] Audit не разделяет фазы.** В `buildSplit` audit пишется один раз с `mode=build_dossier` и аггрегатом токенов. Чтобы понимать стоимость outline vs fill, лучше писать отдельные events per-operation — данные уже есть в `seo_token_usage`, audit дублирует, но читать его удобнее.
- **[L] Дубликат id в split-pipeline ловится поздно.** Если модель в двух fill-вызовах вернёт `f1`, `validateDossier` бросит исключение и всё досье потеряно (вместе с оплаченными токенами). Лучше пере-нумеровать collisions перед валидацией: `{prefix}{global_counter}`.
- **[L] `validateDossier` отбрасывает item при пустом required-поле, но `i++` уже инкрементнут.** ID-нумерация получает дыры (`f1, f3, f5`). Не баг, но косметика.

### Stage 5 — Editorial QA

- **[H] `qa_worker.php`: placeholder в `INTERVAL ? MINUTE`.** Line 50: `(NOW() - INTERVAL ? MINUTE)`. PDO с emulated prepares кавычит число → `INTERVAL '30' MINUTE` → MySQL может ругаться или игнорить. Безопаснее inline `(int)$staleMinutes`. Сейчас не падает только потому что emulate=true и MySQL parses '30' как int.
- **[M] `BrokenLinksRule` — false positives на HEAD.** `CURLOPT_NOBODY = true`. Многие CDN/lambda возвращают 405/403 на HEAD. Получаем `broken_link` warn на живые ссылки. Фикс: при `>= 400` и `!= 404` повторить GET с Range 0-1024.
- **[M] `BrokenLinksRule` — `SSL_VERIFYPEER=0`.** Принципиально допустимо для линкчека, но залогировать в комменте *почему* отключено. Альтернатива — `CURLOPT_CAINFO` с системным bundle.
- **[M] `EmptyChartRule::DATA_KEYS` неполный.** Проверяет `items|data|datasets|rings|axes|rows|columns`. Реальные content_schema у charts: `bars`, `series`, `points`, `cells`, `slices` (надо сверить с `seo_block_types.content_schema`). Сейчас валидный график с `bars: [...]` помечается `error: empty_chart`. Нужен таблично-точный per-type ключ, не общий список.
- **[M] `EditorialQaService::runChecks` без try/catch на rule.** Если `BrokenLinksRule` падает на curl-таймаут → исключение пробрасывается, остальные правила не отрабатывают. Обернуть `try { … } catch(Throwable $e) { error_log; continue; }` на каждый rule.
- **[M] `ArticleQaController` resolve route несоответствие.** Docblock: `POST /qa/{articleId}/resolve/{issueId}`. Код: читает `issue_id` из query/JSON-body, **path-parameter игнорируется**. Либо обновить роутер и доставать из `$action` второй сегмент, либо удалить путь из доки.
- **[M] `BannedPhrasesRule` спамит дубликатами.** Один штамп на N блоках = N issues. На длинных статьях UI забивается. Сгруппировать по фразе с `block_ids[]`, либо severity=info OK как сейчас, но один issue с listing.
- **[L] `runChecks` мечтит ВСЕ unresolved → CURRENT_TIMESTAMP перед прогоном.** Если правило времянно отключено / упало — issue потеряется. Альтернатива: `DELETE` только то что найдено снова (по hash code+block_id+message), но это рефактор. Сейчас приемлемо, отметить.
- **[L] `RepetitionRule` без стоп-слов.** 4-граммы из частотных служебных слов («в этом случае при», «как правило это»). Добавить blacklist-стоп-слов или поднять `n` до 5.
- **[L] `UnknownInDossierRule` ловит шаблонные значения.** Если в досье попали placeholder-строки типа `"url|null"` (модель повторила схему дословно), они не покрыты `unknownPatterns`. Расширить паттерн: `"|null"`, `"http://example"`, `"..."`.

### Stage 4 — Theming

- **[M] Bridge всё ещё активен** (это уже в плане 2.2 / Prompt 10). 22 рендера используют legacy `var(--blue)` и т.п. Cancel.

### Stage 3 — Illustrations

- **[L] `og` иллюстрации не маркируются `stale` при изменении дайджеста.** В `ArticleResearchService::buildDossier` после persist обновляется только `kind = HERO`. OG картинка title-driven — формально допустимо, но если профильный бренд-кит изменили, og остаётся прежней. Не критично.

### Stage 1 — Outline

- **[L] `ALLOWED_ROLES` дублируется в коде и промпте.** `ArticleOutlineService::ALLOWED_ROLES` и `OutlinePrompt::SYSTEM` (строка 24) — синхронизированы вручную. Вынести единым константным массивом, форматировать в SYSTEM через `implode(' | ', …)`.
- **[L] `dossierIndex` усечение до 8000 байт.** При большом досье часть items режется из контекста, но `validateOutline` использует ПОЛНЫЙ индекс. Модель не видит item → не ссылается → меньше связей с этим item. Не баг, но эффект: длинные дайджесты дают «слепые зоны». Метрика: считать сколько item ушло за горизонт, лог.

### Stage 2 — RichText

- Без претензий по коду рендеров не открывал каждый из 44. Замечание: `AbstractBlockRenderer` отсутствует (Plan 2.5 / Prompt 14 это фиксит).

### Сводка приоритетов (что чинить вне очереди Prompt 7+)

| # | Severity | Stage | Что | Объём |
|---|----------|-------|-----|-------|
| R1 | H | 6 | sources не заполняется в split | S |
| R2 | H | 5 | `INTERVAL ? MINUTE` placeholder в qa_worker | XS |
| R3 | M | 5 | BrokenLinksRule fallback HEAD→GET | S |
| R4 | M | 5 | EmptyChartRule per-type data keys | S |
| R5 | M | 5 | runChecks try/catch per rule | XS |
| R6 | M | 5 | ArticleQaController resolve path/body согласовать | XS |
| R7 | M | 6 | facts минимум: 3 vs 8 рассинхрон | XS |
| R8 | M | 5 | BannedPhrasesRule группировка | XS |

R1+R2+R5+R7+R6 = ~1 час, можно сделать одним hotfix-промптом перед очередью.

---

**Конец roadmap.**
