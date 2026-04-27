# АУДИТ admin_advanced — материал для рерайта

> Источник: `admin_advanced/_old/*.php` (бэкап до рерайта).
> Цель: фиксация всех endpoint, форм, JS-функций для сохранения функционала.

## 1. cost_report.php (171 строк)

### Назначение
Финансовый отчёт по стоимости операций генерации. USD-расходы по стратегиям/типам операций за период, графики Chart.js.

### GET-параметры
- `days` (1–365, default 30)

### Серверные блоки
1. `requireAuth()`
2. `CostReportService()::build($days)` → `by_strategy`, `by_operation`, `comparison`, `generated_at`
3. Подготовка labels/cost/tokens для Chart.js

### AJAX
Нет. GET-form submit.

### UI
Filter form, двухосевой Chart.js (bar+line), 2 таблицы, badge-индикаторы дельт.

### Внешние либы
- Chart.js 4.4.0 CDN

### Ссылки навигации
- `../admin_simple/articles.php`

---

## 2. seo_themes_page.php (210 строк)

### Назначение
CMS для UI-тем (design tokens). CRUD theme + JSON-редактор + live preview палитры.

### GET-параметры
Нет.

### AJAX
```
GET    r=themes
GET    r=themes/{code}
POST   r=themes
PUT    r=themes/{code}
DELETE r=themes/{code}
```

### UI
Sidebar `.theme-item`, editor (code disabled при edit, name, is_active, JSON textarea), preview swatches, кнопки save/delete/preview/demo.

### JS-функции
`api`, `loadThemes`, `renderList`, `escapeHtml`, `selectTheme`, `newTheme`, `renderEditor`, `renderPreview`, `saveTheme`, `deleteTheme`, `showMsg`.

---

## 3. seo_clustering_page.php (1643 строк)

### Назначение
Pipeline: keywords → clustering → review (approve/reject) → article gen. Job'ы, intent-типы, таблица keywords с фильтрами.

### GET-параметры
Нет (localStorage `seo_profile_id`).

### AJAX
```
GET    r=keywords/intents
GET    r=keywords/jobs?per_page=50
POST   r=keywords/jobs
GET    r=keywords/jobs/{id}
DELETE r=keywords/jobs/{id}
POST   r=keywords/jobs/{id}/collect
GET    r=keywords/cluster/{jobId}/sse           (SSE)
POST   r=keywords/jobs/{id}/cluster
GET    r=keywords/clusters?job_id={id}&...
POST   r=keywords/clusters/{id}/approve
POST   r=keywords/clusters/{id}/reject
DELETE r=keywords/clusters/{id}
DELETE r=keywords/clusters/{id}/article
POST   r=keywords/clusters/{id}/generate
GET    r=keywords/raw/item/{id}
DELETE r=keywords/raw/item/{id}
PUT    r=keywords/raw/item/{id}
POST   r=profiles/{profileId}                   (header)
```

### UI
- Sidebar job-list (badges по статусу)
- Job header + actions
- Tabs: Intents | Clusters | Keywords | Log
- **Intents**: list intent-types, color picker, form
- **Clusters**: фильтры (status, group by, sort, view grid/list), card/row рендер, side panel
- **Keywords**: search + pagination, editable cells (volume/cluster tag)
- **Log**: progress bar, info box
- Collect panel (manual/api source, seed input)

### JS-функции (по группам)
**Core**: `api`, `$`, `esc`, `toast`, `fmtNum`
**Intents**: `loadIntentTypes`, `switchPageView`, `renderIntentList`, `openIntentForm`, `closeIntentForm`, `setIntentColor`, `syncColorFromHex`, `syncColorFromPicker`, `saveIntent`, `deleteIntent`, `toggleIntentActive`, `intentLabel`, `intentBadge`
**Jobs**: `loadJobs`, `createJob`, `selectJob`, `deleteCurrentJob`, `renderJobList`, `renderJobHeader`
**Collection**: `doCollect`, `doCollectDirect`, `onCollectSourceChange`, `updateManualCounter`
**Clustering**: `clusterKeywords` (SSE), `handleClusterEvent`
**Clusters**: `loadClusters`, `setStatusFilter`, `setGroupBy`, `setView`, `applyClusterView`, `sortClusters`, `renderGrouped`, `renderFlat`, `renderCardsHTML`, `renderClusterCard`, `renderClusterRow`, `openClusterDetail`, `approveCluster`, `rejectCluster`, `deleteCluster`, `deleteAllClusters`, `deleteClusterArticle`, `generateArticle`, `generateAllApproved`
**Keywords**: `loadKeywords`, `renderKeywords`, `kwPageNav`, `debounceSearch`, `deleteKeyword`, `updateKw`, `updateClusterFilter`, `showClusterKeywords`
**Util**: `priClass`, `logMsg`, `setProgress`, `loadProfileHeader`, `switchMainTab`

---

## 4. seo_profile_page.php (3497 строк)

### Назначение
Управление SEO-профилями (workspaces). Overview, Settings, Branding (icon, theme, publish targets), Templates, Intents, Telegram, Brief. Wizard для создания.

### GET-параметры
Нет.

### AJAX
```
GET    r=profiles
POST   r=profiles
GET    r=profiles/{id}
PUT    r=profiles/{id}
DELETE r=profiles/{id}
GET    r=profiles/{id}/tokens
GET    r=profiles/{id}/overview
GET    r=profiles/{id}/icon
POST   r=profiles/{id}/icon
DELETE r=profiles/{id}/icon
GET    r=profiles/{id}/generate-template-sse        (SSE)
GET    r=profiles/{id}/intents
POST   r=profiles/{id}/intents
PUT    r=profiles/{id}/intents/{code}
DELETE r=profiles/{id}/intents/{code}
GET    r=templates?profile_id={id}
POST   r=templates
GET    r=templates/{id}
PUT    r=templates/{id}
DELETE r=templates/{id}
GET    r=templates/{id}/ai-regenerate-sse           (SSE)
GET    r=block-types
POST   r=generate/profile/{id}                      (SSE)
PUT    r=telegram/channels/{id}
GET    r=themes
GET    r=profiles/{id}/brief/suggestions
```

### UI
- Card grid (Overview)
- Workspace view: header + nav (Settings/Branding/Templates/Intents/Telegram/Brief)
- **Settings**: name, slug, domain, description, color_scheme
- **Branding**: icon upload (drag), theme select
- **Token usage graph** + stat-cards
- **Templates**: grid + AI-regen + delete + editor modal с block-picker и JSON
- **AI review panel** side-by-side
- **Intents**: list + modal CRUD
- **Telegram**: form + test connection + image pool
- **Brief**: многошаговый wizard, JSON editor, option-picker, rule-builder
- **Wizard modal** (создание): name/slug/color/icon/purpose suggestions, dots progress

### JS-функции (по группам)
**Core**: `api`, `toast`, `esc`, `iconUrl`, `iconHtml`, `fmtTokNum`, `fmtTokCost`, `escTokHtml`
**Theme picker**: `renderThemePicker`, `selectThemeCard`, `getSelectedTheme`
**Profiles**: `loadProfiles`, `renderProfiles`, `openWorkspace`, `goToList`
**Workspace nav**: `renderWsHeader`, `switchWsTab`
**Tokens**: `loadTokenUsage` (chart inline)
**Overview**: `loadOverview`
**Settings**: `fillSettings`, `saveSettings`
**Branding**: `fillBranding`, `uploadBrandIcon`, `removeBrandIcon`, `saveBranding`, `populateDefaultThemeCodeSelect`
**Templates**: `loadTemplates`, `openTplEditor`, `closeTplEditor`, `reloadTplEditor`, `renderTplEditor`, `parseTplBlockConfig`, `runAiReview`, `applyReview`, `rollbackReview`, `resetReviewUI`, `dismissReview`, `openRegenForm`, `startRegeneration`, `handleRegenEvent`, `renderRegenPreview`, `deleteTemplate`, `deleteTemplateFromEditor`, `openRegenFormDirect`, `doAddTplBlock`, `updateTplBlock`, `deleteTplBlock`
**Intents**: `loadIntents`, `renderIntentsTab`, `renderIntentCard`, `openAddIntentModal`, `editIntent`, `saveIntent`, `deleteIntent`, `aiGenerateIntents`
**Telegram**: `fillTelegram`, `testTgConnection`, `showTgChannelInfo`, `saveTelegram`
**Brief**: `loadPurposeSuggestions`, `pickPurpose`, `clearPurposeSuggestions`, `briefApi`, `briefInit`, `briefReset`, `briefVisibleSteps`, `briefOptKey`, `briefRender`, `briefRenderBody`, `briefSetView`, `briefInitStepJson`, `briefStepJsonFormat`, `briefStepJsonApply`, `briefCollectFromCur`, `briefHydrateFromSaved`, `briefGoto`, `briefRegen`, `briefAutoSave`, `briefDelOption`, `briefDelRule`, `briefAttachHandlers`, `briefEntitiesToText`, `briefStepHtml`, `briefHintHtml`, `briefAddOption`
**Wizard**: `openWizard`, `closeWizard`, `updateWizardUI`, `wizardNext`, `wizardBack`, `renderWizPreview`, `createProfileFromWizard`, `aiGenerateProfile`, `previewWizIcon`, `clearWizIcon`, `renderGenBriefBox`, `openGenModal`, `closeGenModal`, `startTemplateGeneration`, `handleTemplateGenEvent`
**SearchSelect class**: `open`, `close`, `setValue`, `render`

---

## 5. seo_page.php (6092 строк) — главный

### Назначение
Контент-хаб: статьи, каталоги, шаблоны, ссылки, целевые хосты, изображения, публикация, Telegram-посты, audit log.

### GET-параметры
- `article_id` (опц., прямой переход)

### AJAX (40+)
```
GET    r=profiles/{id}                              (header)
GET    r=block-types
GET    r=block-types/{type}/schema
GET    r=catalogs?profile_id={id}
GET/POST/PUT/DELETE r=catalogs[/{id}]
GET    r=articles?profile_id=&search=&status=
GET    r=articles/{id}
POST/PUT/DELETE r=articles[/{id}]
POST   r=articles/render-block
GET/POST/PUT/DELETE r=articles/{id}/blocks[/{blockId}]
PUT    r=articles/{id}/blocks/order                  (drag&drop)
GET/POST/PUT/DELETE r=templates[/{id}]
GET    r=templates/{id}/ai-regenerate-sse           (SSE)
GET/POST/PUT/DELETE r=links[/{id}]
GET/POST/PUT/DELETE r=publish-targets[/{id}]
GET    r=audit-log?profile_id=&entity=&action=
GET    r=audit-log/{id}
POST   r=generate/{articleId}/sse                   (SSE)
POST   r=generate/block/{blockId}/sse               (SSE)
GET    r=images?article_id={id}
POST   r=images/upload
PUT/DELETE r=images/{id}
GET    r=illustrations?article_id={id}
POST   r=illustrations/upload/{kind}
DELETE r=illustrations/{kind}
POST   r=generate/image[/{blockId}]
POST   r=publish/{articleId}
POST   r=publish/{articleId}/preview
POST   r=unpublish/{articleId}
GET    r=telegram
GET    r=telegram/rendered-image/{postId}
POST   r=telegram/{articleId}/build-preview
POST   r=telegram/{articleId}/compose
GET    r=telegram/posts?article_id={id}
GET    r=telegram/post/{postId}
POST   r=telegram/post/{postId}/save
POST   r=telegram/post/{postId}/send
POST   r=telegram/post/send-now
POST   r=telegram/post/{postId}/schedule
DELETE r=telegram/post/{postId}
DELETE r=telegram/posts/{articleId}
POST   r=telegram/upload-image/{postId}
DELETE r=telegram/image/{imageId}
GET    r=keywords?profile_id={id}
```

### UI
**Layout**: `.topbar` + `.page` (flex two-pane: list-panel + editor-panel + toggle)

**List panel** (collapsible): tabs Articles | Catalogs | Templates | Links | Targets | Audit, search/filter под каждой, item list

**Article editor** (tabs):
- **Content**: catalog/template/article_plan/publication_date, blocks list (drag&drop), `+ Add block` dropdown, iframe preview, JSON/GUI editor (CodeMirror)
- **Research**: prep block (collapsible), Plan + Research + Outline, mode toggle (text/list, cards/json), CodeMirror
- **Meta**: meta_title, meta_description, seo_tags, generate
- **Publishing**: status, published_at, publish/preview/unpublish
- **Images**: gallery, drag&drop upload, detail form
- **Telegram**: render preview, message template picker, image pool, send/schedule

**Sidebar управление**:
- Catalogs: tree view (collapsible nodes)
- Templates/Links/Targets: список с edit/delete
- Audit: timeline с фильтрами

**Components**: badges, spinner, toast, SearchSelect, CodeMirror, Chart.js, SSE progress

### JS-функции (80+, по группам)
**Core**: `api`, `$`, `esc`, `toast`, `debounce`, `loadProfileHeader`
**Articles**: `loadArticlesList`, `selectArticle`, `saveArticle`, `deleteArticle`
**Article blocks**: `loadBlockTypeSchemas`, `loadArticleBlocks`, `renderBlockPreview`, `doAddArtBlock`, `updateArtBlock`, `deleteArtBlock`, `toggleBlockVis`, `saveBlockJson`, `saveArtBlocksOrder`
**Research**: `buildResearchAdv`, `saveResearchAdv`, `buildOutlineAdv`, `saveOutlineAdv`, `generateAllBlocks`, `regenBlock`, `pollResearchProgressOnce`
**Meta**: `generateMeta`
**Pipeline**: `generateFullPipeline`
**Prep**: `togglePrep`, `prepSetMode`, `renderPlanList`, `setArticlePlan`, `getArticlePlan`, `renderOutlineCards`, `generateSlugFromTitle`
**Catalogs**: `loadCatalogsList`, `loadAllCatalogArticles`, `renderCatalogTree`, `selectCatalog`, `selectArticleFromTree`, `saveCatalog`
**Templates**: `loadTemplatesList`, `selectTemplate`, `saveTemplate`, `doAddTplBlock`, `updateTplBlock`, `deleteTplBlock`
**Links**: `loadLinksList`, `selectLink`, `saveLink`
**Targets**: `loadTargetsList`, `selectTarget`, `saveTarget`
**Publishing**: `publishArticle`, `previewArticle`, `unpublishArticle`
**Images**: `loadArticleImages`, `uploadFiles`, `saveImgMeta`, `deleteImg`, `deleteImgFromPreview`, `setBlockImgLayout`, `unlinkImageFromBlock`
**Illustrations**: `loadIllustrations`, `genIllustHero`, `genIllustOg`, `uploadIllust`, `dropIllust`
**Image gen**: `generateManualImage`, `generateBlockImage`, `generateAllImages`
**Telegram**: `buildTgPreview`, `recomposeTgPost`, `loadTgPost`, `saveTgPost`, `sendTgPostById`, `sendTgNow`, `scheduleTgPost`, `loadTgPostHistory`, `deleteTgPost`, `deleteAllTgPosts`, `uploadTgImage`, `deleteTgImageFromPool`, `tgPickerPickBlock`, `tgPickerPickArticleImage`, `tgPickerPickUpload`, `callAddImage`
**Audit**: `loadAuditList`, `selectAudit`, `doDelete`
**Util**: `generateSlugFromTitle`, `setArticlePlan`, `getArticlePlan`, `fmtNum`, `escTokHtml`, `refreshGenLog`
**SearchSelect**: instances ssFilterCatalog, ssArtCatalog, ssArtTemplate, ssCatParent, ssLnkArticle, ssPubTarget
**Listeners**: DOMContentLoaded, Ctrl+S save, Esc closeImgPreview, debounce search, dirty tracking

### Внешние либы
- CodeMirror (lazy при openTplEditor)
- Chart.js (token usage)

---

## ИТОГИ

| Файл | Строк | AJAX | JS-функций | UI-компонентов |
|------|-------|------|------------|----------------|
| cost_report | 171 | 0 | 0 | 2 |
| themes | 210 | 5 | 11 | 5 |
| clustering | 1643 | 21 | ~35 | 15 |
| profile | 3497 | 30+ | ~100 | 20+ |
| seo_page | 6092 | 40+ | 80+ | 25+ |
| **Σ** | **11613** | **100+** | **250+** | **70+** |

## Кандидаты на shared-код

**JS общий (`_assets/js/app.js`)**:
- `api(resource, opts)` — fetch+JSON+errors+toast
- `$(id)`, `esc(s)`, `escAttr(s)`, `debounce(fn, ms)`
- `toast(msg, type)`
- `fmtNum(n)`, `fmtCost(c)`
- `loadProfileHeader()` (топбар-аватар + имя)
- `iconUrl(profile)`, `iconHtml(profile, size)`
- `SearchSelect` class (используется в profile + seo_page)
- SSE-обёртка (на 4 страницах есть SSE)
- Chart.js loader (lazy)
- CodeMirror loader (lazy)

**CSS (`_assets/css/admin.css`)**:
- Палитра best_admin (бежевые тона + жёлтые акценты + dark accent карточки)
- Tailwind config через JS (`tailwind.config = {theme:{extend:{...}}}`)
- Радиусы: rounded-2xl/3xl
- Тени: мягкие (`shadow-sm`, `shadow-lg`)
- Custom компоненты: `.card`, `.btn-primary`, `.btn-secondary`, `.badge`, `.toast`, `.sidebar-icon`

## Layout shared (`_layout/`)
- `header.php` — `<head>`, шрифты, Tailwind CDN, Alpine CDN, `admin.css`, tailwind.config
- `sidebar.php` — иконки навигации (best_admin стиль)
- `topbar.php` — приветствие + поиск + профиль/upgrade
- `footer.php` — `app.js` + закрытие
