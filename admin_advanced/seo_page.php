<?php
/**
 * admin_advanced/seo_page.php — Articles workspace (rewritten).
 *
 * Two-pane SPA built on Tailwind Play CDN + Alpine.js + window.SEO helper.
 * All data flows through /controllers/router.php?r=… (verified endpoints).
 *
 * PHP 8.0 compatible. Russian UI labels.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$pageTitle      = 'Статьи — SEO admin';
$activeNav      = 'articles';
$pageHeading    = 'Статьи';
$pageSubheading = 'Генерация, редактура, публикация и Telegram-постинг';

$topbarRight = '
  <button class="btn-primary" @click="openCreateArticle()">
    <svg width="14" height="14" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 4v12M4 10h12" stroke-linecap="round"/></svg>
    Новая статья
  </button>
';

include __DIR__ . '/_layout/header.php';
?>

<div x-data="seoApp()" x-init="init()" class="space-y-6">

  <!-- ============================================================ TOP BAR (filters + tabs) ========================== -->
  <section class="card">
    <div class="flex flex-wrap items-center gap-2 mb-4">
      <div class="tabs">
        <template x-for="t in listTabs" :key="t.id">
          <button class="tab" :class="{ 'tab-active': listTab === t.id }" @click="setListTab(t.id)" x-text="t.label"></button>
        </template>
      </div>
      <div class="flex-1"></div>
    </div>

    <!-- ARTICLES filters -->
    <div x-show="listTab === 'articles'" class="grid md:grid-cols-[1fr_180px_180px_140px_120px] gap-2">
      <input class="input" placeholder="Поиск по заголовку / slug…" x-model.debounce.300ms="filters.q" @input="loadArticles()">
      <select class="select" x-model="filters.status" @change="loadArticles()">
        <option value="">Любой статус</option>
        <option value="draft">Черновик</option>
        <option value="review">На ревью</option>
        <option value="published">Опубликована</option>
        <option value="unpublished">Снята</option>
      </select>
      <select class="select" x-model="filters.catalogId" @change="loadArticles()">
        <option value="">Все рубрики</option>
        <template x-for="c in flatCatalogs" :key="c.id">
          <option :value="c.id" x-text="c._indent + c.name"></option>
        </template>
      </select>
      <select class="select" x-model="filters.sort" @change="loadArticles()">
        <option value="updated_desc">Обновлены ↓</option>
        <option value="updated_asc">Обновлены ↑</option>
        <option value="created_desc">Созданы ↓</option>
        <option value="title_asc">Заголовок A→Я</option>
      </select>
      <button class="btn-soft" @click="resetFilters()">Сброс</button>
    </div>

    <!-- CATALOGS toolbar -->
    <div x-show="listTab === 'catalogs'" class="flex flex-wrap gap-2">
      <input class="input flex-1 min-w-[240px]" placeholder="Поиск рубрики…" x-model.debounce.300ms="filters.qCat">
      <button class="btn-primary" @click="openCreateCatalog()">+ Рубрика</button>
    </div>

    <!-- TEMPLATES toolbar -->
    <div x-show="listTab === 'templates'" class="flex flex-wrap gap-2">
      <input class="input flex-1 min-w-[240px]" placeholder="Поиск шаблона…" x-model.debounce.300ms="filters.qTpl">
      <button class="btn-primary" @click="openCreateTemplate()">+ Шаблон</button>
    </div>

    <!-- LINKS toolbar -->
    <div x-show="listTab === 'links'" class="flex flex-wrap gap-2">
      <input class="input flex-1 min-w-[240px]" placeholder="Поиск ссылок…" x-model.debounce.300ms="filters.qLnk">
      <button class="btn-primary" @click="openCreateLink()">+ Ссылка</button>
    </div>

    <!-- TARGETS toolbar -->
    <div x-show="listTab === 'targets'" class="flex flex-wrap gap-2">
      <input class="input flex-1 min-w-[240px]" placeholder="Поиск площадки…" x-model.debounce.300ms="filters.qTgt">
      <button class="btn-primary" @click="openCreateTarget()">+ Площадка</button>
    </div>
  </section>

  <!-- ============================================================ MAIN GRID (articles / catalogs / templates) === -->
  <div x-show="['articles','catalogs','templates'].includes(listTab)" class="grid xl:grid-cols-[440px_1fr] gap-6">

    <!-- ------------------------------------------------------- LEFT: list -->
    <section class="card p-0 overflow-hidden">
      <div class="max-h-[78vh] overflow-auto">

        <!-- ARTICLES list -->
        <div x-show="listTab === 'articles'">
          <template x-if="!articles.length && !loadingList">
            <div class="p-6 text-ink-500 text-sm">Статей нет. Нажмите «Новая статья», чтобы создать первую.</div>
          </template>
          <template x-if="loadingList">
            <div class="p-6 text-ink-500 text-sm">Загрузка…</div>
          </template>
          <template x-for="a in articles" :key="a.id">
            <button @click="openArticle(a.id)"
                    class="w-full text-left px-4 py-3 border-b border-sand-200 hover:bg-sand-100 transition flex flex-col gap-1"
                    :class="{ 'bg-sand-100': current.kind === 'article' && current.id === a.id }">
              <div class="flex items-start gap-2">
                <span class="font-semibold flex-1 line-clamp-2" x-text="a.title || '(без заголовка)'"></span>
                <span class="badge" :class="statusBadge(a.status)" x-text="STATUS_LABELS[a.status] || a.status"></span>
              </div>
              <div class="flex items-center gap-3 text-xs text-ink-500">
                <span x-text="a.slug || '—'"></span>
                <span x-show="a.catalog_name" x-text="a.catalog_name"></span>
                <span class="ml-auto" x-text="fmtDate(a.updated_at)"></span>
              </div>
            </button>
          </template>
        </div>

        <!-- CATALOGS list -->
        <div x-show="listTab === 'catalogs'">
          <template x-for="c in filteredCatalogs()" :key="c.id">
            <button @click="openCatalog(c.id)"
                    class="w-full text-left px-4 py-3 border-b border-sand-200 hover:bg-sand-100 flex items-center gap-2"
                    :class="{ 'bg-sand-100': current.kind === 'catalog' && current.id === c.id }">
              <span x-html="c._indent.replace(/ /g, '&nbsp;')"></span>
              <span class="font-medium" x-text="c.name"></span>
              <span class="text-xs text-ink-500 ml-auto" x-text="c.slug"></span>
            </button>
          </template>
          <div x-show="!flatCatalogs.length" class="p-6 text-ink-500 text-sm">Рубрик нет.</div>
        </div>

        <!-- TEMPLATES list -->
        <div x-show="listTab === 'templates'">
          <template x-for="t in filteredTemplates()" :key="t.id">
            <button @click="openTemplate(t.id)"
                    class="w-full text-left px-4 py-3 border-b border-sand-200 hover:bg-sand-100 flex flex-col gap-1"
                    :class="{ 'bg-sand-100': current.kind === 'template' && current.id === t.id }">
              <span class="font-semibold" x-text="t.name"></span>
              <span class="text-xs text-ink-500" x-text="(t.code || '') + ' · блоков: ' + (t.block_count || 0)"></span>
            </button>
          </template>
          <div x-show="!templates.length" class="p-6 text-ink-500 text-sm">Шаблонов нет.</div>
        </div>


      </div>
    </section>

    <!-- ------------------------------------------------------- RIGHT: detail -->
    <section>

      <!-- empty state -->
      <div x-show="!current.kind" class="card text-center text-ink-500 py-20">
        Выберите элемент в списке слева или создайте новый.
      </div>

      <!-- ============================================================ ARTICLE DETAIL ============================ -->
      <div x-show="current.kind === 'article' && art" class="space-y-4">

        <!-- header strip -->
        <div class="card flex flex-wrap items-center gap-3">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <h2 class="text-xl font-bold truncate" x-text="art?.title || '(без заголовка)'"></h2>
              <span class="badge" :class="statusBadge(art?.status)" x-text="STATUS_LABELS[art?.status] || art?.status"></span>
              <span x-show="dirty" class="text-xs text-ember-500">● несохранено</span>
            </div>
            <div class="text-xs text-ink-500 mt-0.5">
              ID #<span x-text="art?.id"></span> · <span x-text="art?.slug || '—'"></span> ·
              обновлено <span x-text="fmtDate(art?.updated_at)"></span>
            </div>
          </div>
          <button class="btn-soft" @click="reloadArticle()">↻ Обновить</button>
          <button class="btn-primary" @click="saveArticle()" :disabled="saving || !dirty">
            <span x-show="!saving">Сохранить</span>
            <span x-show="saving" class="spinner"></span>
          </button>
          <button class="btn-danger" @click="confirmDeleteArticle()">Удалить</button>
        </div>

        <!-- tabs -->
        <div class="card">
          <div class="tabs flex-wrap">
            <template x-for="t in articleTabs" :key="t.id">
              <button class="tab" :class="{ 'tab-active': artTab === t.id }" @click="artTab = t.id" x-text="t.label"></button>
            </template>
          </div>
        </div>

        <!-- TAB: Основное -->
        <div x-show="artTab === 'main'" class="card space-y-4">
          <div class="grid md:grid-cols-2 gap-4">
            <div>
              <label class="label">Заголовок (H1)</label>
              <input class="input" x-model="art.title" @input="dirty = true" @change="autoSlug()">
            </div>
            <div>
              <label class="label">Slug</label>
              <div class="flex gap-2">
                <input class="input" x-model="art.slug" @input="dirty = true">
                <button class="btn-soft" @click="art.slug = generateSlug(art.title); dirty=true">Из заголовка</button>
              </div>
            </div>
            <div>
              <label class="label">Рубрика</label>
              <select class="select" x-model="art.catalog_id" @change="dirty = true">
                <option :value="null">— без рубрики —</option>
                <template x-for="c in flatCatalogs" :key="c.id">
                  <option :value="c.id" x-text="c._indent + c.name"></option>
                </template>
              </select>
            </div>
            <div>
              <label class="label">Шаблон</label>
              <select class="select" x-model="art.template_id" @change="dirty = true">
                <option :value="null">— без шаблона —</option>
                <template x-for="t in templates" :key="t.id">
                  <option :value="t.id" x-text="t.name"></option>
                </template>
              </select>
            </div>
            <div>
              <label class="label">Статус</label>
              <select class="select" x-model="art.status" @change="changeStatus($event.target.value)">
                <option value="draft">Черновик</option>
                <option value="review">На ревью</option>
                <option value="published">Опубликована</option>
                <option value="unpublished">Снята</option>
              </select>
            </div>
            <div>
              <label class="label">Канонический URL</label>
              <input class="input" x-model="art.canonical_url" @input="dirty = true">
            </div>
          </div>

          <div class="grid md:grid-cols-2 gap-4">
            <div>
              <label class="label">Meta title</label>
              <input class="input" x-model="art.meta_title" @input="dirty = true">
            </div>
            <div>
              <label class="label">Meta description</label>
              <textarea class="textarea" rows="2" x-model="art.meta_description" @input="dirty = true"></textarea>
            </div>
          </div>

          <div>
            <label class="label">Lead / интро</label>
            <textarea class="textarea" rows="3" x-model="art.intro" @input="dirty = true"></textarea>
          </div>

          <div class="grid md:grid-cols-2 gap-4">
            <div>
              <label class="label">Ключевая фраза</label>
              <input class="input" x-model="art.keyword" @input="dirty = true">
            </div>
            <div>
              <label class="label">Поисковый интент</label>
              <select class="select" x-model="art.intent" @change="dirty = true">
                <option value="">—</option>
                <option value="informational">Информационный</option>
                <option value="commercial">Коммерческий</option>
                <option value="navigational">Навигационный</option>
                <option value="transactional">Транзакционный</option>
              </select>
            </div>
          </div>

          <!-- cost / token snapshot -->
          <div x-show="art.tokens_total || art.cost_total" class="grid grid-cols-2 md:grid-cols-4 gap-2">
            <div class="card-tinted">
              <div class="label">Токены вход</div>
              <div class="font-semibold" x-text="fmtNum(art.tokens_input)"></div>
            </div>
            <div class="card-tinted">
              <div class="label">Токены выход</div>
              <div class="font-semibold" x-text="fmtNum(art.tokens_output)"></div>
            </div>
            <div class="card-tinted">
              <div class="label">Всего токенов</div>
              <div class="font-semibold" x-text="fmtNum(art.tokens_total)"></div>
            </div>
            <div class="card-tinted">
              <div class="label">Стоимость</div>
              <div class="font-semibold" x-text="fmtCost(art.cost_total)"></div>
            </div>
          </div>
        </div>

        <!-- TAB: Подготовка (research / outline / plan) -->
        <div x-show="artTab === 'prep'" class="space-y-4">
          <div class="card space-y-3">
            <div class="flex items-center gap-2 flex-wrap">
              <h3 class="font-semibold flex-1">Ресёрч</h3>
              <button class="btn-soft" @click="runResearch()" :disabled="prep.researchBusy">
                <span x-show="!prep.researchBusy">Сгенерировать ресёрч</span>
                <span x-show="prep.researchBusy" class="spinner"></span>
              </button>
              <button class="btn-ghost" @click="prep.researchOpen = !prep.researchOpen" x-text="prep.researchOpen ? 'Скрыть' : 'Показать'"></button>
            </div>
            <textarea x-show="prep.researchOpen" class="textarea font-mono text-xs" rows="14"
                      x-model="prep.research" @input="dirty = true" placeholder="Ресёрч-досье (текст / JSON)"></textarea>
          </div>

          <div class="card space-y-3">
            <div class="flex items-center gap-2 flex-wrap">
              <h3 class="font-semibold flex-1">Outline</h3>
              <div class="tabs">
                <button class="tab" :class="{ 'tab-active': prep.outlineMode === 'cards' }" @click="prep.outlineMode='cards'">Карточки</button>
                <button class="tab" :class="{ 'tab-active': prep.outlineMode === 'json' }" @click="prep.outlineMode='json'">JSON</button>
              </div>
              <button class="btn-soft" @click="runOutline()" :disabled="prep.outlineBusy">
                <span x-show="!prep.outlineBusy">Сгенерировать outline</span>
                <span x-show="prep.outlineBusy" class="spinner"></span>
              </button>
            </div>

            <div x-show="prep.outlineMode === 'cards'">
              <template x-for="(s, i) in prep.outlineSections" :key="i">
                <div class="card-tinted mb-2 space-y-2">
                  <div class="flex items-center gap-2">
                    <span class="badge badge-soft" x-text="'§ ' + (i+1)"></span>
                    <input class="input" x-model="s.title" @input="syncOutline()" placeholder="Заголовок секции">
                    <select class="select max-w-[140px]" x-model="s.level" @change="syncOutline()">
                      <option value="h2">H2</option>
                      <option value="h3">H3</option>
                    </select>
                    <button class="btn-icon" @click="prep.outlineSections.splice(i,1); syncOutline()" title="Удалить">×</button>
                  </div>
                  <textarea class="textarea" rows="2" x-model="s.summary" @input="syncOutline()" placeholder="Краткое описание / тезисы"></textarea>
                </div>
              </template>
              <button class="btn-soft" @click="prep.outlineSections.push({ title:'', summary:'', level:'h2' }); syncOutline()">+ Секция</button>
            </div>

            <textarea x-show="prep.outlineMode === 'json'" class="textarea font-mono text-xs" rows="14"
                      x-model="prep.outline" @input="dirty = true"></textarea>
          </div>

          <div class="card space-y-3">
            <h3 class="font-semibold">План блоков</h3>
            <textarea class="textarea font-mono text-xs" rows="10" x-model="prep.plan" @input="dirty = true"
                      placeholder="Список типов блоков, по одному в строке (h2_section, list_block, faq, …)"></textarea>
            <div class="text-xs text-ink-500">План используется генератором при пакетной генерации блоков.</div>
          </div>
        </div>

        <!-- TAB: Блоки -->
        <div x-show="artTab === 'blocks'" class="space-y-4">
          <div class="card flex flex-wrap items-center gap-2">
            <div class="flex-1">
              <h3 class="font-semibold">Блоки статьи</h3>
              <p class="text-xs text-ink-500">Перетаскивайте, чтобы менять порядок. Двойной клик по блоку для редактирования.</p>
            </div>
            <button class="btn-soft" @click="openBlockTypePicker()">+ Блок</button>
            <button class="btn-soft" @click="openGenPanel()">⚡ Сгенерировать</button>
            <button class="btn-danger" @click="confirmClearBlocks()">Очистить все</button>
          </div>

          <!-- generation panel -->
          <div x-show="gen.open" class="card space-y-3">
            <div class="flex items-center gap-2">
              <h3 class="font-semibold flex-1">Генерация</h3>
              <button class="btn-ghost" @click="gen.open = false">Свернуть</button>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <button class="btn-primary" @click="startFullGen()" :disabled="gen.running">
                <span x-show="!gen.running">▶ Запустить полную генерацию</span>
                <span x-show="gen.running" class="spinner"></span>
              </button>
              <button class="btn-soft" @click="generateMeta()" :disabled="gen.running">Только meta</button>
              <button class="btn-danger" x-show="gen.running" @click="cancelGen()">⏹ Стоп</button>
            </div>
            <div x-show="gen.progress" class="space-y-2">
              <div class="progress-bar"><span :style="{ width: gen.progress + '%' }"></span></div>
              <div class="text-xs text-ink-500" x-text="gen.message"></div>
            </div>
            <div x-show="gen.log.length" class="card-tinted max-h-60 overflow-auto text-xs font-mono">
              <template x-for="(line, i) in gen.log" :key="i">
                <div :class="{ 'text-ember-500': line.kind==='error' }" x-text="'[' + line.t + '] ' + line.msg"></div>
              </template>
            </div>
          </div>

          <!-- block list -->
          <div class="card space-y-2">
            <template x-if="!blocks.length">
              <div class="text-ink-500 text-sm py-6 text-center">Блоков нет.</div>
            </template>
            <template x-for="(b, i) in blocks" :key="b.id">
              <div class="card-tinted flex items-start gap-3"
                   draggable="true"
                   @dragstart="dragStart(i, $event)"
                   @dragover.prevent
                   @drop="dragDrop(i)">
                <span class="drag-handle pt-1">⋮⋮</span>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-1">
                    <span class="badge badge-soft" x-text="b.block_type"></span>
                    <span class="text-xs text-ink-500" x-text="'#' + (i+1)"></span>
                    <span x-show="b.tokens_total" class="text-xs text-ink-500" x-text="fmtNum(b.tokens_total) + ' tok · ' + fmtCost(b.cost_total)"></span>
                  </div>
                  <div class="text-sm line-clamp-3 text-ink-700" x-text="blockPreviewText(b)"></div>
                </div>
                <div class="flex flex-col gap-1">
                  <button class="btn-icon" @click="editBlock(b)" title="Редактировать">✎</button>
                  <button class="btn-icon" @click="regenerateBlock(b)" title="Перегенерировать">↻</button>
                  <button class="btn-icon" @click="confirmDeleteBlock(b)" title="Удалить">×</button>
                </div>
              </div>
            </template>
          </div>
        </div>

        <!-- TAB: Изображения -->
        <div x-show="artTab === 'images'" class="space-y-4">
          <!-- illustrations -->
          <div class="card space-y-3">
            <h3 class="font-semibold">Иллюстрации</h3>
            <div class="grid md:grid-cols-2 gap-4">
              <!-- HERO -->
              <div class="card-tinted space-y-2">
                <div class="label flex justify-between">
                  <span>Hero</span>
                  <button x-show="illust.hero" class="text-ember-500 text-xs" @click="deleteIllustration('hero')">Удалить</button>
                </div>
                <div class="aspect-[16/9] bg-sand-200 rounded-2xl overflow-hidden flex items-center justify-center">
                  <img x-show="illust.hero" :src="illust.hero" class="w-full h-full object-cover">
                  <span x-show="!illust.hero" class="text-ink-300 text-sm">Нет</span>
                </div>
                <div class="flex gap-2 flex-wrap">
                  <button class="btn-soft" @click="generateIllustration('hero')" :disabled="illust.busyHero">Сгенерировать</button>
                  <label class="btn-soft cursor-pointer">
                    Загрузить
                    <input type="file" accept="image/*" class="hidden" @change="uploadIllustration('hero', $event)">
                  </label>
                </div>
              </div>
              <!-- OG -->
              <div class="card-tinted space-y-2">
                <div class="label flex justify-between">
                  <span>OpenGraph</span>
                  <button x-show="illust.og" class="text-ember-500 text-xs" @click="deleteIllustration('og')">Удалить</button>
                </div>
                <div class="aspect-[1200/630] bg-sand-200 rounded-2xl overflow-hidden flex items-center justify-center">
                  <img x-show="illust.og" :src="illust.og" class="w-full h-full object-cover">
                  <span x-show="!illust.og" class="text-ink-300 text-sm">Нет</span>
                </div>
                <div class="flex gap-2 flex-wrap">
                  <button class="btn-soft" @click="generateIllustration('og')" :disabled="illust.busyOg">Сгенерировать</button>
                  <label class="btn-soft cursor-pointer">
                    Загрузить
                    <input type="file" accept="image/*" class="hidden" @change="uploadIllustration('og', $event)">
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- inline images -->
          <div class="card space-y-3">
            <div class="flex items-center gap-2">
              <h3 class="font-semibold flex-1">Изображения в тексте</h3>
              <button class="btn-soft" @click="generateAllImages()" :disabled="imgGen.busy">
                <span x-show="!imgGen.busy">⚡ Сгенерировать все</span>
                <span x-show="imgGen.busy" class="spinner"></span>
              </button>
            </div>
            <div x-show="!images.length" class="text-ink-500 text-sm">Изображений нет.</div>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
              <template x-for="img in images" :key="img.id">
                <div class="relative group">
                  <img :src="img.url" class="w-full aspect-video object-cover rounded-2xl bg-sand-200 cursor-pointer"
                       @click="openImagePreview(img)">
                  <button class="absolute top-2 right-2 btn-icon opacity-0 group-hover:opacity-100" @click="deleteImage(img)">×</button>
                  <div class="text-xs text-ink-500 mt-1 truncate" x-text="img.alt || ''"></div>
                </div>
              </template>
            </div>
          </div>
        </div>

        <!-- TAB: Telegram -->
        <div x-show="artTab === 'telegram'" class="space-y-4">
          <div class="card flex flex-wrap items-center gap-2">
            <h3 class="font-semibold flex-1">Telegram-посты</h3>
            <button class="btn-primary" @click="buildTgPreview()" :disabled="tg.busy">
              <span x-show="!tg.busy">⚡ Собрать превью</span>
              <span x-show="tg.busy" class="spinner"></span>
            </button>
            <button class="btn-danger" @click="confirmClearTgPosts()" x-show="tg.posts.length">Очистить все</button>
          </div>

          <div x-show="!tg.posts.length" class="card text-ink-500 text-sm text-center py-10">
            Постов нет. Соберите превью.
          </div>

          <template x-for="(p, idx) in tg.posts" :key="p.id">
            <div class="card space-y-3">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="badge badge-soft" x-text="'#' + (idx+1)"></span>
                <span class="badge" :class="p.status === 'sent' ? 'badge-ok' : (p.status === 'scheduled' ? 'badge-sun' : 'badge-soft')"
                      x-text="p.status"></span>
                <span class="text-xs text-ink-500" x-show="p.sent_at" x-text="'отправлено ' + fmtDate(p.sent_at)"></span>
                <div class="flex-1"></div>
                <button class="btn-soft" @click="recomposeTgPost(p)">↻ Пересобрать</button>
                <button class="btn-soft" @click="saveTgPost(p)">Сохранить</button>
                <button class="btn-primary" @click="sendTgPost(p)" :disabled="p.status === 'sent'">▶ Отправить</button>
                <button class="btn-soft" @click="scheduleTgPost(p)">🕒 Запланировать</button>
                <button class="btn-danger" @click="deleteTgPost(p)">×</button>
              </div>
              <textarea class="textarea font-mono text-xs" rows="6" x-model="p.text" @input="p._dirty = true"></textarea>
              <div x-show="p.image_url" class="flex items-center gap-3">
                <img :src="p.image_url" class="w-32 h-32 object-cover rounded-2xl bg-sand-200">
                <div class="text-xs text-ink-500">Прикреплено изображение</div>
              </div>
            </div>
          </template>
        </div>

        <!-- TAB: Публикация -->
        <div x-show="artTab === 'publish'" class="space-y-4">
          <div class="card space-y-3">
            <h3 class="font-semibold">Публикация</h3>
            <div class="grid md:grid-cols-[1fr_auto_auto_auto] gap-2">
              <select class="select" x-model="pub.targetId">
                <option :value="null">Площадка не выбрана</option>
                <template x-for="t in targets" :key="t.id">
                  <option :value="t.id" x-text="t.name + ' (' + (t.type === 'ftp' ? 'FTP' : 'Self-hosted') + ')'"></option>
                </template>
              </select>
              <button class="btn-soft" @click="previewPublish()" :disabled="!pub.targetId || pub.busy">Предпросмотр</button>
              <button class="btn-primary" @click="doPublish()" :disabled="!pub.targetId || pub.busy">Опубликовать</button>
              <button class="btn-danger" @click="doUnpublish()" :disabled="!pub.targetId || pub.busy">Снять</button>
            </div>
            <div x-show="pub.message" class="text-sm" :class="{ 'text-ember-500': pub.error }" x-text="pub.message"></div>
          </div>
          <div x-show="pub.previewHtml" class="card p-0 overflow-hidden">
            <iframe class="w-full" style="min-height: 60vh; border:0; background:#fff;" :srcdoc="pub.previewHtml"></iframe>
          </div>
        </div>

        <!-- TAB: QA -->
        <div x-show="artTab === 'qa'" class="space-y-4">
          <div class="card flex flex-wrap items-center gap-2">
            <h3 class="font-semibold flex-1">Editorial QA</h3>
            <button class="btn-soft" @click="loadQa()">↻</button>
            <button class="btn-primary" @click="runQa()" :disabled="qa.busy">
              <span x-show="!qa.busy">⚡ Запустить проверку</span>
              <span x-show="qa.busy" class="spinner"></span>
            </button>
          </div>
          <div x-show="!qa.issues.length" class="card text-ink-500 text-sm text-center py-10">Замечаний нет.</div>
          <template x-for="iss in qa.issues" :key="iss.id">
            <div class="card flex items-start gap-3" :class="iss.resolved_at ? 'opacity-60' : ''">
              <span class="badge" :class="iss.severity === 'error' ? 'badge-err' : (iss.severity === 'warn' ? 'badge-warn' : 'badge-soft')"
                    x-text="iss.severity"></span>
              <div class="flex-1 min-w-0">
                <div class="font-medium" x-text="iss.rule"></div>
                <div class="text-sm text-ink-500" x-text="iss.message"></div>
                <div x-show="iss.snippet" class="card-tinted text-xs font-mono mt-2 line-clamp-3" x-text="iss.snippet"></div>
              </div>
              <div class="flex flex-col gap-1">
                <button class="btn-icon" @click="fixQa(iss)" :disabled="qa.busy" title="Авто-фикс">⚡</button>
                <button class="btn-icon" @click="resolveQa(iss)" :disabled="qa.busy" title="Решено">✓</button>
              </div>
            </div>
          </template>
        </div>

      </div>

      <!-- ============================================================ CATALOG / TEMPLATE / LINK / TARGET / AUDIT EDITORS -->
      <div x-show="current.kind === 'catalog' && cat" class="card space-y-4">
        <h2 class="text-xl font-bold" x-text="cat?.id ? 'Рубрика #' + cat.id : 'Новая рубрика'"></h2>
        <div class="grid md:grid-cols-2 gap-3">
          <div><label class="label">Название</label><input class="input" x-model="cat.name"></div>
          <div><label class="label">Slug</label><input class="input" x-model="cat.slug"></div>
          <div>
            <label class="label">Родитель</label>
            <select class="select" x-model="cat.parent_id">
              <option :value="null">— верхний уровень —</option>
              <template x-for="c in flatCatalogs" :key="c.id">
                <option :value="c.id" x-text="c._indent + c.name" x-show="c.id !== cat.id"></option>
              </template>
            </select>
          </div>
          <div><label class="label">Сортировка</label><input class="input" type="number" x-model.number="cat.sort_order"></div>
        </div>
        <div><label class="label">Описание</label><textarea class="textarea" rows="3" x-model="cat.description"></textarea></div>
        <div class="flex gap-2">
          <button class="btn-primary" @click="saveCatalog()">Сохранить</button>
          <button class="btn-danger" x-show="cat.id" @click="confirmDeleteCatalog()">Удалить</button>
        </div>
      </div>

      <div x-show="current.kind === 'template' && tpl" class="card space-y-4">
        <h2 class="text-xl font-bold" x-text="tpl?.id ? 'Шаблон #' + tpl.id : 'Новый шаблон'"></h2>
        <div class="grid md:grid-cols-2 gap-3">
          <div><label class="label">Название</label><input class="input" x-model="tpl.name"></div>
          <div><label class="label">Код</label><input class="input" x-model="tpl.code"></div>
        </div>
        <div><label class="label">Описание</label><textarea class="textarea" rows="2" x-model="tpl.description"></textarea></div>
        <div>
          <label class="label">Структура (JSON массив блоков)</label>
          <textarea class="textarea font-mono text-xs" rows="14" x-model="tpl.structure_json"></textarea>
        </div>
        <div class="flex gap-2">
          <button class="btn-primary" @click="saveTemplate()">Сохранить</button>
          <button class="btn-danger" x-show="tpl.id" @click="confirmDeleteTemplate()">Удалить</button>
        </div>
      </div>

    </section>
  </div>

  <!-- ============================================================ LINKS drawer ============================== -->
  <div x-show="listTab === 'links'"
       x-transition:enter="anim-fade-in"
       class="drawer-split" :data-drawer="lnk ? 'open' : 'closed'">
    <div class="drawer-list anim-stagger overflow-auto" style="max-height: 78vh">
      <template x-for="l in filteredLinks()" :key="l.id">
        <button class="drawer-list-item press-shrink" :class="{ 'is-active': lnk && lnk.id === l.id }" @click="openLink(l.id)">
          <span class="drawer-list-title" x-text="l.label || l.key"></span>
          <span class="drawer-list-sub" x-text="l.description || l.url || '—'"></span>
        </button>
      </template>
      <div x-show="!links.length" class="p-6 text-ink-500 text-sm text-center">Ссылок нет. Нажмите «+ Ссылка».</div>
    </div>

    <div class="drawer-editor" x-show="lnk" x-cloak
         x-transition:enter="anim-slide-up" x-transition:leave="anim-fade-in"
         :key="lnk && lnk.id">
      <div class="flex items-center gap-2 mb-4">
        <button class="drawer-back-btn" @click="closeLinkEditor()">
          <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 4l-4 4 4 4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          К списку
        </button>
        <h2 class="text-xl font-bold flex-1 truncate" x-text="lnk?.id ? (lnk.label || lnk.key || ('Ссылка #' + lnk.id)) : 'Новая ссылка'"></h2>
      </div>

      <div class="space-y-3 anim-stagger">
        <div class="grid md:grid-cols-2 gap-3">
          <div>
            <label class="label">Ключ</label>
            <input class="input" x-model="lnk.key" placeholder="home, contacts, …">
          </div>
          <div>
            <label class="label">Подпись</label>
            <input class="input" x-model="lnk.label" placeholder="Главная">
          </div>
        </div>
        <div>
          <label class="label">URL</label>
          <input class="input" x-model="lnk.url" placeholder="https://…">
        </div>
        <div class="grid md:grid-cols-3 gap-3">
          <div>
            <label class="label">target</label>
            <select class="select" x-model="lnk.target">
              <option value="_self">_self</option>
              <option value="_blank">_blank</option>
            </select>
          </div>
          <div>
            <label class="label">nofollow</label>
            <select class="select" x-model.number="lnk.nofollow">
              <option :value="0">нет</option>
              <option :value="1">да</option>
            </select>
          </div>
          <div>
            <label class="label">Активна</label>
            <select class="select" x-model.number="lnk.is_active">
              <option :value="1">Да</option>
              <option :value="0">Нет</option>
            </select>
          </div>
        </div>
        <div>
          <label class="label">Описание</label>
          <textarea class="textarea" rows="3" x-model="lnk.description" placeholder="Где используется, особенности…"></textarea>
        </div>
        <div class="flex gap-2 pt-2">
          <button class="btn-primary" @click="saveLink()">Сохранить</button>
          <button class="btn-danger" x-show="lnk.id" @click="confirmDeleteLink()">Удалить</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ============================================================ PUBLISH targets drawer =================== -->
  <div x-show="listTab === 'targets'"
       x-transition:enter="anim-fade-in"
       class="drawer-split" :data-drawer="tgt ? 'open' : 'closed'">
    <div class="drawer-list anim-stagger overflow-auto" style="max-height: 78vh">
      <template x-for="t in filteredTargets()" :key="t.id">
        <button class="drawer-list-item press-shrink" :class="{ 'is-active': tgt && tgt.id === t.id }" @click="openTarget(t.id)">
          <span class="drawer-list-title" x-text="t.name"></span>
          <span class="drawer-list-sub" x-text="(t.type === 'ftp' ? 'FTP' : 'Self-hosted') + ' · ' + (t.base_url || '—')"></span>
        </button>
      </template>
      <div x-show="!targets.length" class="p-6 text-ink-500 text-sm text-center">Площадок нет. Нажмите «+ Площадка».</div>
    </div>

    <div class="drawer-editor" x-show="tgt" x-cloak
         x-transition:enter="anim-slide-up" x-transition:leave="anim-fade-in"
         :key="tgt && tgt.id">
      <div class="flex items-center gap-2 mb-4">
        <button class="drawer-back-btn" @click="closeTargetEditor()">
          <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 4l-4 4 4 4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          К списку
        </button>
        <h2 class="text-xl font-bold flex-1 truncate" x-text="tgt?.id ? (tgt.name || ('Площадка #' + tgt.id)) : 'Новая площадка'"></h2>
      </div>

      <div class="space-y-3 anim-stagger">
        <div class="grid md:grid-cols-2 gap-3">
          <div>
            <label class="label">Название</label>
            <input class="input" x-model="tgt.name" placeholder="Прод">
          </div>
          <div>
            <label class="label">Тип</label>
            <select class="select" x-model="tgt.type">
              <option value="selfhosted">Self-hosted (deploy/publish.php)</option>
              <option value="ftp">FTP</option>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="label">Base URL <span class="text-ink-300 text-xs">— используется для построения публичных ссылок</span></label>
            <input class="input" x-model="tgt.base_url" placeholder="https://example.com">
          </div>
          <div>
            <label class="label">Активна</label>
            <select class="select" x-model.number="tgt.is_active">
              <option :value="1">Да</option>
              <option :value="0">Нет</option>
            </select>
          </div>
        </div>

        <!-- SELF-HOSTED config -->
        <template x-if="tgt && tgt.type === 'selfhosted'">
          <div class="card-tinted space-y-3 anim-pop">
            <div class="text-xs text-ink-500 font-medium uppercase tracking-wide">Параметры self-hosted</div>
            <div>
              <label class="label">Хост</label>
              <input class="input" x-model="tgt.config.host" placeholder="194.87.138.88">
            </div>
            <div>
              <label class="label">Document root</label>
              <input class="input" x-model="tgt.config.document_root" placeholder="/var/www/html/aurum">
            </div>
            <div>
              <label class="label">Publish endpoint <span class="text-ink-300 text-xs">— куда POST'ить (если пусто, base_url + /admin/seo_generator/deploy/publish.php)</span></label>
              <input class="input" x-model="tgt.config.publish_endpoint" placeholder="http://194.87.138.88:8080/deploy/publish.php">
            </div>
            <div>
              <label class="label">Заметка</label>
              <input class="input" x-model="tgt.config.note">
            </div>
          </div>
        </template>

        <!-- FTP config -->
        <template x-if="tgt && tgt.type === 'ftp'">
          <div class="card-tinted space-y-3 anim-pop">
            <div class="text-xs text-ink-500 font-medium uppercase tracking-wide">Параметры FTP</div>
            <div class="grid md:grid-cols-3 gap-3">
              <div class="md:col-span-2">
                <label class="label">Хост</label>
                <input class="input" x-model="tgt.config.host" placeholder="ftp.example.com">
              </div>
              <div>
                <label class="label">Порт</label>
                <input class="input" type="number" x-model.number="tgt.config.port" placeholder="21">
              </div>
            </div>
            <div class="grid md:grid-cols-2 gap-3">
              <div>
                <label class="label">Username</label>
                <input class="input" x-model="tgt.config.username">
              </div>
              <div>
                <label class="label">Password</label>
                <input class="input" type="password" x-model="tgt.config.password">
              </div>
            </div>
            <div>
              <label class="label">Document root</label>
              <input class="input" x-model="tgt.config.document_root" placeholder="/public_html">
            </div>
            <div>
              <label class="label">SSL</label>
              <select class="select" x-model.number="tgt.config.ssl">
                <option :value="0">Нет</option>
                <option :value="1">Да (FTPS)</option>
              </select>
            </div>
          </div>
        </template>

        <div class="flex gap-2 pt-2">
          <button class="btn-primary" @click="saveTarget()">Сохранить</button>
          <button class="btn-danger" x-show="tgt.id" @click="confirmDeleteTarget()">Удалить</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ============================================================ BLOCK EDITOR (modal-style) ====================== -->
  <div x-show="modal.block" x-cloak class="modal-backdrop" @click.self="closeBlockEditor()">
    <div class="modal-card" style="max-width: 920px">
      <div class="p-5 border-b border-sand-200 flex items-center gap-2">
        <h3 class="font-semibold flex-1">Блок: <span x-text="modal.block?.block_type"></span> <span class="text-ink-500 text-xs" x-text="'#' + modal.block?.id"></span></h3>
        <div class="tabs">
          <button class="tab" :class="{ 'tab-active': modal.blockTab === 'form' }" @click="modal.blockTab='form'">Форма</button>
          <button class="tab" :class="{ 'tab-active': modal.blockTab === 'json' }" @click="switchBlockTab('json')">JSON</button>
          <button class="tab" :class="{ 'tab-active': modal.blockTab === 'preview' }" @click="switchBlockTab('preview')">Preview</button>
        </div>
      </div>
      <div class="p-5">
        <div x-show="modal.blockTab === 'form'" class="space-y-3">
          <template x-for="(field, key) in modal.blockFields" :key="key">
            <div>
              <label class="label" x-text="field.label || key"></label>
              <input x-show="field.type === 'string'" class="input" x-model="modal.blockData[key]">
              <textarea x-show="field.type === 'text'" class="textarea" rows="4" x-model="modal.blockData[key]"></textarea>
              <input x-show="field.type === 'number'" type="number" class="input" x-model.number="modal.blockData[key]">
              <select x-show="field.type === 'enum'" class="select" x-model="modal.blockData[key]">
                <template x-for="opt in field.options" :key="opt"><option :value="opt" x-text="opt"></option></template>
              </select>
              <textarea x-show="field.type === 'json'" class="textarea font-mono text-xs" rows="6" x-model="modal.blockData[key]"></textarea>
            </div>
          </template>
          <div x-show="!Object.keys(modal.blockFields || {}).length" class="text-ink-500 text-sm">
            Для этого типа нет схемы формы — используйте JSON.
          </div>
        </div>
        <div x-show="modal.blockTab === 'json'">
          <textarea id="block-json-cm" class="textarea font-mono text-xs" rows="20" x-model="modal.blockJson"></textarea>
        </div>
        <div x-show="modal.blockTab === 'preview'" class="card-tinted">
          <div x-html="modal.blockPreviewHtml || '<div class=\'text-ink-500 text-sm\'>Загрузка превью…</div>'"></div>
        </div>
      </div>
      <div class="p-5 border-t border-sand-200 flex gap-2">
        <button class="btn-primary" @click="saveBlockEditor()">Сохранить</button>
        <button class="btn-soft" @click="regenerateBlock(modal.block); closeBlockEditor()">Перегенерировать</button>
        <button class="btn-ghost" @click="closeBlockEditor()">Отмена</button>
        <button class="btn-danger ml-auto" @click="confirmDeleteBlock(modal.block); closeBlockEditor()">Удалить блок</button>
      </div>
    </div>
  </div>

  <!-- ============================================================ BLOCK TYPE PICKER ============================== -->
  <div x-show="modal.blockTypePicker" x-cloak class="modal-backdrop" @click.self="modal.blockTypePicker = false">
    <div class="modal-card" style="max-width: 560px">
      <div class="p-5 border-b border-sand-200">
        <h3 class="font-semibold">Тип блока</h3>
        <input class="input mt-3" placeholder="Поиск типа…" x-model="modal.blockTypeQuery">
      </div>
      <div class="p-3 max-h-[60vh] overflow-auto">
        <template x-for="bt in filteredBlockTypes()" :key="bt.code">
          <button class="w-full text-left px-3 py-2 rounded-xl hover:bg-sand-100 flex items-center gap-2"
                  @click="addBlock(bt.code); modal.blockTypePicker = false">
            <span class="badge badge-soft" x-text="bt.code"></span>
            <span class="text-sm" x-text="bt.label || bt.code"></span>
          </button>
        </template>
      </div>
    </div>
  </div>

  <!-- ============================================================ IMAGE PREVIEW ================================== -->
  <div x-show="modal.imagePreview" x-cloak class="modal-backdrop" @click.self="modal.imagePreview = null">
    <div class="modal-card p-3" style="max-width: 90vw">
      <img :src="modal.imagePreview?.url" class="max-h-[80vh] mx-auto rounded-2xl">
      <div class="p-3 flex items-center gap-2">
        <span class="text-sm text-ink-500 flex-1" x-text="modal.imagePreview?.alt || ''"></span>
        <button class="btn-ghost" @click="modal.imagePreview = null">Закрыть</button>
      </div>
    </div>
  </div>

  <!-- ============================================================ CONFIRM ====================================== -->
  <div x-show="modal.confirm" x-cloak class="modal-backdrop" @click.self="modal.confirm = null">
    <div class="modal-card" style="max-width: 480px">
      <div class="p-5">
        <h3 class="font-semibold mb-2" x-text="modal.confirm?.title || 'Подтверждение'"></h3>
        <p class="text-sm text-ink-500" x-text="modal.confirm?.message"></p>
      </div>
      <div class="p-5 border-t border-sand-200 flex gap-2 justify-end">
        <button class="btn-ghost" @click="modal.confirm = null">Отмена</button>
        <button class="btn-danger" @click="modal.confirm?.onConfirm && modal.confirm.onConfirm(); modal.confirm = null"
                x-text="modal.confirm?.confirmLabel || 'Удалить'"></button>
      </div>
    </div>
  </div>

</div>

<script>
const STATUS_LABELS = {
  draft: 'Черновик',
  review: 'На ревью',
  published: 'Опубликована',
  unpublished: 'Снята',
};

function seoApp() {
  return {
    STATUS_LABELS,
    // -------- meta tabs --------
    listTabs: [
      { id: 'articles', label: 'Статьи' },
      { id: 'catalogs', label: 'Рубрики' },
      { id: 'templates', label: 'Шаблоны' },
      { id: 'links', label: 'Ссылки' },
      { id: 'targets', label: 'Публикация' },
    ],
    articleTabs: [
      { id: 'main',     label: 'Основное' },
      { id: 'prep',     label: 'Подготовка' },
      { id: 'blocks',   label: 'Блоки' },
      { id: 'images',   label: 'Изображения' },
      { id: 'telegram', label: 'Telegram' },
      { id: 'publish',  label: 'Размещение' },
      { id: 'qa',       label: 'QA' },
    ],
    listTab: 'articles',
    artTab: 'main',
    profileId: null,
    profile: null,
    loadingList: false,
    saving: false,
    dirty: false,
    current: { kind: null, id: null },
    filters: { q: '', status: '', catalogId: '', sort: 'updated_desc', qCat: '', qTpl: '', qLnk: '', qTgt: '' },

    // collections
    articles: [], catalogs: [], flatCatalogs: [], templates: [], links: [], targets: [],
    blockTypeSchemas: {},

    // detail state
    art: null, blocks: [], images: [], illust: { hero: null, og: null, busyHero: false, busyOg: false },
    cat: null, tpl: null, lnk: null, tgt: null,

    prep: { researchOpen: false, research: '', researchBusy: false, outline: '', outlineMode: 'cards', outlineSections: [], outlineBusy: false, plan: '' },
    gen:  { open: false, running: false, abort: null, log: [], progress: 0, message: '' },
    imgGen: { busy: false },
    tg:   { busy: false, posts: [] },
    pub:  { targetId: null, busy: false, message: '', error: false, previewHtml: '' },
    qa:   { busy: false, issues: [] },
    modal:{ block: null, blockTab: 'form', blockData: {}, blockFields: {}, blockJson: '', blockPreviewHtml: '', blockCm: null,
            blockTypePicker: false, blockTypeQuery: '', imagePreview: null, confirm: null },

    // drag
    _dragIndex: null,

    // ============================================================ INIT ==
    async init() {
      this.profileId = SEO.profile.id;
      if (!this.profileId) {
        SEO.toast('Сначала выберите профиль в разделе «Профили»', 'err');
        return;
      }
      await this.loadProfile();
      await Promise.all([
        this.loadCatalogs(),
        this.loadTemplates(),
        this.loadTargets(),
        this.loadLinks(),
        this.loadBlockTypes(),
        this.loadArticles(),
      ]);
    },

    async loadProfile() {
      try {
        const list = await SEO.api('profiles', { silent: true });
        this.profile = (list || []).find(p => Number(p.id) === Number(this.profileId)) || null;
      } catch (_) {}
    },

    // ============================================================ TABS ==
    setListTab(t) {
      this.listTab = t;
    },

    resetFilters() {
      this.filters = { q: '', status: '', catalogId: '', sort: 'updated_desc', qCat: '', qTpl: '', qLnk: '', qTgt: '' };
      this.loadArticles();
    },

    // ============================================================ ARTICLES list ==
    async loadArticles() {
      if (!this.profileId) return;
      this.loadingList = true;
      try {
        const params = new URLSearchParams({ profile_id: String(this.profileId) });
        if (this.filters.q) params.set('q', this.filters.q);
        if (this.filters.status) params.set('status', this.filters.status);
        if (this.filters.catalogId) params.set('catalog_id', String(this.filters.catalogId));
        if (this.filters.sort) params.set('sort', this.filters.sort);
        const data = await SEO.api('articles?' + params.toString());
        this.articles = data || [];
      } finally { this.loadingList = false; }
    },

    // ============================================================ CATALOGS ==
    async loadCatalogs() {
      const data = await SEO.api('catalogs?profile_id=' + this.profileId);
      this.catalogs = data || [];
      this.flatCatalogs = this.flattenCatalogs(this.catalogs, null, 0);
    },
    flattenCatalogs(list, parentId, depth) {
      const out = [];
      const items = (list || []).filter(c => (c.parent_id || null) == parentId);
      for (const c of items) {
        out.push({ ...c, _indent: '— '.repeat(depth) });
        out.push(...this.flattenCatalogs(list, c.id, depth + 1));
      }
      // first call: list contains all rows; flatten by building tree from flat
      if (parentId === null && depth === 0 && !items.length && (list || []).length) {
        // fallback: list is flat and parent_id may be null/undefined for all → return as-is
        return (list || []).map(c => ({ ...c, _indent: '' }));
      }
      return out;
    },
    filteredCatalogs() {
      const q = (this.filters.qCat || '').toLowerCase();
      if (!q) return this.flatCatalogs;
      return this.flatCatalogs.filter(c => (c.name || '').toLowerCase().includes(q) || (c.slug || '').toLowerCase().includes(q));
    },

    // ============================================================ TEMPLATES ==
    async loadTemplates() {
      const data = await SEO.api('templates?profile_id=' + this.profileId);
      this.templates = data || [];
    },
    filteredTemplates() {
      const q = (this.filters.qTpl || '').toLowerCase();
      if (!q) return this.templates;
      return this.templates.filter(t => (t.name || '').toLowerCase().includes(q) || (t.code || '').toLowerCase().includes(q));
    },

    // ============================================================ LINKS ==
    async loadLinks() {
      const data = await SEO.api('links?profile_id=' + this.profileId);
      this.links = data || [];
    },
    filteredLinks() {
      const q = (this.filters.qLnk || '').toLowerCase();
      if (!q) return this.links;
      return this.links.filter(l =>
        (l.key || '').toLowerCase().includes(q) ||
        (l.label || '').toLowerCase().includes(q) ||
        (l.url || '').toLowerCase().includes(q) ||
        (l.description || '').toLowerCase().includes(q)
      );
    },

    // ============================================================ TARGETS ==
    async loadTargets() {
      const data = await SEO.api('publish-targets?profile_id=' + this.profileId);
      this.targets = data || [];
    },
    filteredTargets() {
      const q = (this.filters.qTgt || '').toLowerCase();
      if (!q) return this.targets;
      return this.targets.filter(t => (t.name || '').toLowerCase().includes(q));
    },

    // ============================================================ BLOCK TYPES ==
    async loadBlockTypes() {
      try {
        const data = await SEO.api('block-types', { silent: true });
        this.blockTypeSchemas = data || {};
      } catch (_) { this.blockTypeSchemas = {}; }
    },
    filteredBlockTypes() {
      const q = (this.modal.blockTypeQuery || '').toLowerCase();
      const all = Object.entries(this.blockTypeSchemas).map(([code, s]) => ({ code, label: s.label || code, ...s }));
      if (!q) return all;
      return all.filter(b => b.code.toLowerCase().includes(q) || (b.label || '').toLowerCase().includes(q));
    },

    // ============================================================ ARTICLE detail ==
    async openArticle(id) {
      if (this.dirty && !confirm('Несохранённые изменения будут потеряны. Продолжить?')) return;
      this.current = { kind: 'article', id };
      this.artTab = 'main';
      this.dirty = false;
      this.art = null;
      try {
        const a = await SEO.api('articles/' + id);
        this.art = a;
        this.parsePrepFromArticle(a);
        await Promise.all([this.loadBlocks(), this.loadImages(), this.loadIllustrations(), this.loadTgPosts()]);
      } catch (e) { this.current = { kind: null, id: null }; }
    },

    async reloadArticle() {
      if (this.current.kind === 'article' && this.current.id) await this.openArticle(this.current.id);
    },

    parsePrepFromArticle(a) {
      this.prep.research = a.research_dossier || '';
      this.prep.outline  = a.outline_json || '';
      this.prep.plan     = (a.plan_json || a.plan || '');
      // try parse outline JSON to cards
      try {
        const arr = JSON.parse(this.prep.outline || '[]');
        this.prep.outlineSections = Array.isArray(arr) ? arr.map(s => ({
          title: s.title || s.heading || '',
          summary: s.summary || s.brief || '',
          level: s.level || 'h2',
        })) : [];
      } catch (_) { this.prep.outlineSections = []; }
    },

    syncOutline() {
      try {
        this.prep.outline = JSON.stringify(this.prep.outlineSections, null, 2);
        this.dirty = true;
      } catch (_) {}
    },

    parsedOutline() { try { return JSON.parse(this.prep.outline || '[]'); } catch { return []; } },

    autoSlug() {
      if (!this.art.slug || this.art._autoSlug) {
        this.art.slug = this.generateSlug(this.art.title);
        this.art._autoSlug = true;
      }
    },

    generateSlug(s) {
      const map = {'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'yo','ж':'zh','з':'z','и':'i','й':'y','к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r','с':'s','т':'t','у':'u','ф':'f','х':'h','ц':'ts','ч':'ch','ш':'sh','щ':'sch','ъ':'','ы':'y','ь':'','э':'e','ю':'yu','я':'ya'};
      return (s || '').toLowerCase().split('').map(c => map[c] !== undefined ? map[c] : c).join('')
        .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80);
    },

    statusBadge(s) {
      return ({ draft:'badge-soft', review:'badge-warn', published:'badge-ok', unpublished:'badge-err' })[s] || 'badge-soft';
    },

    fmtDate(s) {
      if (!s) return '';
      const d = new Date(s.replace(' ', 'T'));
      if (isNaN(+d)) return s;
      return d.toLocaleString('ru-RU', { day:'2-digit', month:'2-digit', year:'2-digit', hour:'2-digit', minute:'2-digit' });
    },
    fmtNum(n) { return SEO.fmtNum(n); },
    fmtCost(n) { return SEO.fmtCost(n); },

    async openCreateArticle() {
      try {
        const a = await SEO.api('articles', { method: 'POST', body: { profile_id: this.profileId, title: 'Новая статья' } });
        await this.loadArticles();
        await this.openArticle(a.id);
        SEO.toast('Создано', 'ok');
      } catch (_) {}
    },

    async saveArticle() {
      this.saving = true;
      try {
        const payload = {
          title: this.art.title, slug: this.art.slug, status: this.art.status,
          catalog_id: this.art.catalog_id, template_id: this.art.template_id,
          meta_title: this.art.meta_title, meta_description: this.art.meta_description,
          intro: this.art.intro, keyword: this.art.keyword, intent: this.art.intent,
          canonical_url: this.art.canonical_url,
          research_dossier: this.prep.research, outline_json: this.prep.outline, plan_json: this.prep.plan,
        };
        const updated = await SEO.api('articles/' + this.art.id, { method: 'PUT', body: payload });
        Object.assign(this.art, updated || {});
        this.dirty = false;
        await this.loadArticles();
        SEO.toast('Сохранено', 'ok');
      } finally { this.saving = false; }
    },

    async changeStatus(status) {
      this.art.status = status;
      try { await SEO.api('articles/' + this.art.id + '/status', { method: 'POST', body: { status } }); await this.loadArticles(); }
      catch (_) {}
    },

    confirmDeleteArticle() {
      this.modal.confirm = {
        title: 'Удалить статью',
        message: 'Действие необратимо. Удалить «' + (this.art.title || '') + '»?',
        onConfirm: async () => {
          try {
            await SEO.api('articles/' + this.art.id, { method: 'DELETE' });
            this.current = { kind: null, id: null };
            this.art = null;
            await this.loadArticles();
            SEO.toast('Удалено', 'ok');
          } catch (_) {}
        }
      };
    },

    // ============================================================ BLOCKS ==
    async loadBlocks() {
      const data = await SEO.api('articles/' + this.art.id + '/blocks');
      this.blocks = data || [];
    },

    blockPreviewText(b) {
      const d = b.data_json || b.data || {};
      const obj = (typeof d === 'string') ? (() => { try { return JSON.parse(d); } catch { return {}; } })() : d;
      const candidates = ['text', 'body', 'content', 'html', 'intro', 'caption', 'question'];
      for (const k of candidates) if (typeof obj[k] === 'string' && obj[k].trim()) return obj[k].replace(/<[^>]+>/g, '').slice(0, 240);
      return JSON.stringify(obj).slice(0, 240);
    },

    openBlockTypePicker() { this.modal.blockTypePicker = true; this.modal.blockTypeQuery = ''; },

    async addBlock(blockType) {
      try {
        const b = await SEO.api('articles/' + this.art.id + '/blocks', {
          method: 'POST', body: { block_type: blockType, data: {} }
        });
        this.blocks.push(b);
        this.editBlock(b);
      } catch (_) {}
    },

    editBlock(b) {
      this.modal.block = b;
      this.modal.blockTab = 'form';
      const data = (typeof b.data_json === 'string') ? (() => { try { return JSON.parse(b.data_json); } catch { return {}; } })() : (b.data_json || b.data || {});
      this.modal.blockData = JSON.parse(JSON.stringify(data));
      this.modal.blockJson = JSON.stringify(data, null, 2);
      this.modal.blockFields = (this.blockTypeSchemas[b.block_type] && this.blockTypeSchemas[b.block_type].fields) || {};
      this.modal.blockPreviewHtml = '';
    },

    async switchBlockTab(tab) {
      // sync form ↔ json
      if (this.modal.blockTab === 'form' && tab !== 'form') {
        this.modal.blockJson = JSON.stringify(this.modal.blockData, null, 2);
      } else if (this.modal.blockTab === 'json' && tab !== 'json') {
        try { this.modal.blockData = JSON.parse(this.modal.blockJson || '{}'); }
        catch (e) { SEO.toast('JSON некорректен', 'err'); return; }
      }
      this.modal.blockTab = tab;
      if (tab === 'preview') await this.renderBlockPreview();
      if (tab === 'json' && !this.modal.blockCm) {
        try {
          await SEO.loadCodeMirror();
          const ta = document.getElementById('block-json-cm');
          if (ta && window.CodeMirror) {
            this.modal.blockCm = window.CodeMirror.fromTextArea(ta, { mode: 'application/json', lineNumbers: true });
            this.modal.blockCm.on('change', cm => { this.modal.blockJson = cm.getValue(); });
          }
        } catch (_) {}
      }
    },

    async renderBlockPreview() {
      try {
        const data = this.modal.blockTab === 'json'
          ? (() => { try { return JSON.parse(this.modal.blockJson); } catch { return {}; } })()
          : this.modal.blockData;
        const html = await SEO.api('articles/render-block', {
          method: 'POST', body: { block_type: this.modal.block.block_type, data }
        });
        this.modal.blockPreviewHtml = (typeof html === 'string') ? html : (html.html || '');
      } catch (_) { this.modal.blockPreviewHtml = '<div class="text-ember-500 text-sm">Не удалось отрендерить</div>'; }
    },

    closeBlockEditor() {
      if (this.modal.blockCm) {
        try { this.modal.blockCm.toTextArea(); } catch (_) {}
        this.modal.blockCm = null;
      }
      this.modal.block = null;
    },

    async saveBlockEditor() {
      if (this.modal.blockTab === 'json') {
        try { this.modal.blockData = JSON.parse(this.modal.blockJson || '{}'); }
        catch (e) { SEO.toast('JSON некорректен', 'err'); return; }
      }
      try {
        const updated = await SEO.api('articles/' + this.art.id + '/blocks', {
          method: 'PUT',
          body: { id: this.modal.block.id, data: this.modal.blockData }
        });
        const idx = this.blocks.findIndex(b => b.id === this.modal.block.id);
        if (idx !== -1) this.blocks[idx] = updated || { ...this.modal.block, data_json: JSON.stringify(this.modal.blockData) };
        this.closeBlockEditor();
        SEO.toast('Блок сохранён', 'ok');
      } catch (_) {}
    },

    confirmDeleteBlock(b) {
      this.modal.confirm = {
        title: 'Удалить блок',
        message: 'Удалить блок ' + b.block_type + '?',
        onConfirm: async () => {
          try {
            await SEO.api('articles/' + this.art.id + '/blocks/' + b.id, { method: 'DELETE' });
            this.blocks = this.blocks.filter(x => x.id !== b.id);
            SEO.toast('Удалено', 'ok');
          } catch (_) {}
        }
      };
    },

    confirmClearBlocks() {
      this.modal.confirm = {
        title: 'Очистить все блоки',
        message: 'Удалить все блоки статьи? Действие необратимо.',
        onConfirm: async () => {
          try {
            await SEO.api('articles/' + this.art.id + '/clear-blocks', { method: 'POST' });
            this.blocks = [];
            SEO.toast('Очищено', 'ok');
          } catch (_) {}
        }
      };
    },

    async regenerateBlock(b) {
      try {
        await SEO.api('generate/block/' + b.id, { method: 'POST' });
        await this.loadBlocks();
        SEO.toast('Блок перегенерирован', 'ok');
      } catch (_) {}
    },

    // drag-reorder
    dragStart(i, ev) { this._dragIndex = i; ev.dataTransfer.effectAllowed = 'move'; },
    async dragDrop(i) {
      if (this._dragIndex === null || this._dragIndex === i) return;
      const moved = this.blocks.splice(this._dragIndex, 1)[0];
      this.blocks.splice(i, 0, moved);
      this._dragIndex = null;
      try {
        await SEO.api('articles/' + this.art.id + '/reorder', {
          method: 'POST',
          body: { order: this.blocks.map(b => b.id) }
        });
      } catch (_) { await this.loadBlocks(); }
    },

    // ============================================================ PREP / GENERATE ==
    async runResearch() {
      this.prep.researchBusy = true;
      try {
        const r = await SEO.api('generate/research/' + this.art.id, { method: 'POST' });
        if (r && r.research_dossier) this.prep.research = r.research_dossier;
        SEO.toast('Ресёрч готов', 'ok');
      } finally { this.prep.researchBusy = false; }
    },

    async runOutline() {
      this.prep.outlineBusy = true;
      try {
        const r = await SEO.api('generate/outline/' + this.art.id, { method: 'POST' });
        if (r && r.outline_json) {
          this.prep.outline = r.outline_json;
          this.parsePrepFromArticle({ ...this.art, outline_json: r.outline_json });
        }
        SEO.toast('Outline готов', 'ok');
      } finally { this.prep.outlineBusy = false; }
    },

    openGenPanel() { this.gen.open = true; },

    async startFullGen() {
      this.gen.running = true; this.gen.log = []; this.gen.progress = 0; this.gen.message = 'Запуск…';
      try {
        const ctrl = new AbortController();
        this.gen.abort = ctrl;
        const url = '/controllers/router.php?r=generate/' + this.art.id + '/sse';
        const res = await fetch(url, { method: 'POST', signal: ctrl.signal });
        await this.consumeSse(res, (event, data) => {
          if (event === 'progress' && data && data.percent != null) {
            this.gen.progress = data.percent;
            this.gen.message = data.message || '';
          } else if (event === 'log') {
            this.gen.log.push({ t: new Date().toLocaleTimeString('ru-RU'), msg: data.message || JSON.stringify(data), kind: data.level || 'info' });
          } else if (event === 'step') {
            this.gen.log.push({ t: new Date().toLocaleTimeString('ru-RU'), msg: '▶ ' + (data.name || data.step || ''), kind: 'info' });
          } else if (event === 'done') {
            this.gen.progress = 100;
            this.gen.message = 'Готово';
          } else if (event === 'error') {
            this.gen.log.push({ t: new Date().toLocaleTimeString('ru-RU'), msg: data.message || 'Ошибка', kind: 'error' });
          }
        });
        await Promise.all([this.loadBlocks(), this.reloadArticle()]);
        SEO.toast('Генерация завершена', 'ok');
      } catch (e) {
        if (e.name !== 'AbortError') SEO.toast('Ошибка SSE: ' + e.message, 'err');
      } finally { this.gen.running = false; this.gen.abort = null; }
    },

    async cancelGen() {
      try { await SEO.api('generate/cancel/' + this.art.id, { method: 'POST', silent: true }); } catch (_) {}
      if (this.gen.abort) this.gen.abort.abort();
      this.gen.running = false;
    },

    async generateMeta() {
      try {
        await SEO.api('generate/' + this.art.id + '/meta', { method: 'POST' });
        await this.reloadArticle();
        SEO.toast('Meta сгенерирована', 'ok');
      } catch (_) {}
    },

    async consumeSse(res, handler) {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const reader = res.body.getReader();
      const decoder = new TextDecoder();
      let buf = '';
      while (true) {
        const { value, done } = await reader.read();
        if (done) break;
        buf += decoder.decode(value, { stream: true });
        let sep;
        while ((sep = buf.indexOf('\n\n')) !== -1 || (sep = buf.indexOf('\r\n\r\n')) !== -1) {
          const chunk = buf.slice(0, sep);
          buf = buf.slice(sep + (buf.indexOf('\r\n\r\n') === sep ? 4 : 2));
          let event = 'message', dataStr = '';
          for (const raw of chunk.split(/\r?\n/)) {
            if (raw.startsWith('event:')) event = raw.slice(6).trim();
            else if (raw.startsWith('data:')) dataStr += (dataStr ? '\n' : '') + raw.slice(5).trim();
          }
          let data = dataStr; try { data = JSON.parse(dataStr); } catch (_) {}
          handler(event, data);
        }
      }
    },

    // ============================================================ IMAGES ==
    async loadImages() {
      try { const data = await SEO.api('images?article_id=' + this.art.id, { silent: true }); this.images = data || []; }
      catch (_) { this.images = []; }
    },

    openImagePreview(img) { this.modal.imagePreview = img; },

    async deleteImage(img) {
      try {
        await SEO.api('images/' + img.id, { method: 'DELETE' });
        this.images = this.images.filter(i => i.id !== img.id);
        SEO.toast('Удалено', 'ok');
      } catch (_) {}
    },

    async generateAllImages() {
      this.imgGen.busy = true;
      try {
        await SEO.api('images/generate', { method: 'POST', body: { article_id: this.art.id } });
        await this.loadImages();
        SEO.toast('Готово', 'ok');
      } finally { this.imgGen.busy = false; }
    },

    // ============================================================ ILLUSTRATIONS ==
    async loadIllustrations() {
      try {
        const d = await SEO.api('illustrations/' + this.art.id, { silent: true });
        this.illust.hero = d?.hero_url || null;
        this.illust.og   = d?.og_url || null;
      } catch (_) { this.illust.hero = null; this.illust.og = null; }
    },

    async generateIllustration(kind) {
      const busyKey = kind === 'hero' ? 'busyHero' : 'busyOg';
      this.illust[busyKey] = true;
      try {
        const r = await SEO.api('illustrations/' + this.art.id + '/' + kind, { method: 'POST' });
        if (kind === 'hero') this.illust.hero = r?.hero_url || this.illust.hero;
        else this.illust.og = r?.og_url || this.illust.og;
        SEO.toast('Готово', 'ok');
      } finally { this.illust[busyKey] = false; }
    },

    async uploadIllustration(kind, ev) {
      const file = ev.target.files[0]; if (!file) return;
      const fd = new FormData();
      fd.append('file', file);
      try {
        const r = await SEO.api('illustrations/' + this.art.id + '/upload-' + kind, { method: 'POST', body: fd });
        if (kind === 'hero') this.illust.hero = r?.hero_url || this.illust.hero;
        else this.illust.og = r?.og_url || this.illust.og;
        SEO.toast('Загружено', 'ok');
      } catch (_) {}
      ev.target.value = '';
    },

    async deleteIllustration(kind) {
      try {
        await SEO.api('illustrations/' + this.art.id + '/' + kind, { method: 'DELETE' });
        if (kind === 'hero') this.illust.hero = null; else this.illust.og = null;
        SEO.toast('Удалено', 'ok');
      } catch (_) {}
    },

    // ============================================================ TELEGRAM ==
    async loadTgPosts() {
      try { const d = await SEO.api('telegram/' + this.art.id + '/posts', { silent: true }); this.tg.posts = d || []; }
      catch (_) { this.tg.posts = []; }
    },

    async buildTgPreview() {
      this.tg.busy = true;
      try {
        await SEO.api('telegram/' + this.art.id + '/build-preview', { method: 'POST' });
        await this.loadTgPosts();
        SEO.toast('Превью собрано', 'ok');
      } finally { this.tg.busy = false; }
    },

    async recomposeTgPost(p) {
      try { await SEO.api('telegram/recompose/' + p.id, { method: 'POST' }); await this.loadTgPosts(); SEO.toast('Пересобрано', 'ok'); } catch (_) {}
    },

    async saveTgPost(p) {
      try {
        await SEO.api('telegram/post/' + p.id, { method: 'PUT', body: { text: p.text } });
        p._dirty = false;
        SEO.toast('Сохранено', 'ok');
      } catch (_) {}
    },

    async sendTgPost(p) {
      try { await SEO.api('telegram/' + this.art.id + '/send', { method: 'POST', body: { post_id: p.id } }); await this.loadTgPosts(); SEO.toast('Отправлено', 'ok'); } catch (_) {}
    },

    async scheduleTgPost(p) {
      const at = prompt('Запланировать на (YYYY-MM-DD HH:MM):');
      if (!at) return;
      try { await SEO.api('telegram/' + this.art.id + '/schedule', { method: 'POST', body: { post_id: p.id, send_at: at } }); await this.loadTgPosts(); SEO.toast('Запланировано', 'ok'); } catch (_) {}
    },

    async deleteTgPost(p) {
      try { await SEO.api('telegram/post/' + p.id, { method: 'DELETE' }); this.tg.posts = this.tg.posts.filter(x => x.id !== p.id); } catch (_) {}
    },

    confirmClearTgPosts() {
      this.modal.confirm = {
        title: 'Очистить посты',
        message: 'Удалить все Telegram-посты этой статьи?',
        onConfirm: async () => {
          try { await SEO.api('telegram/' + this.art.id + '/posts', { method: 'DELETE' }); this.tg.posts = []; SEO.toast('Очищено', 'ok'); } catch (_) {}
        }
      };
    },

    // ============================================================ PUBLISH ==
    async previewPublish() {
      this.pub.busy = true; this.pub.message = ''; this.pub.error = false;
      try {
        const r = await SEO.api('publish/' + this.art.id + '/preview', { method: 'POST', body: { target_id: this.pub.targetId } });
        this.pub.previewHtml = r?.html || '';
      } catch (e) { this.pub.error = true; this.pub.message = e.message; }
      finally { this.pub.busy = false; }
    },

    async doPublish() {
      this.pub.busy = true; this.pub.message = ''; this.pub.error = false;
      try {
        const r = await SEO.api('publish/' + this.art.id, { method: 'POST', body: { target_id: this.pub.targetId } });
        this.pub.message = 'Опубликовано: ' + (r?.url || 'OK');
        await this.reloadArticle();
      } catch (e) { this.pub.error = true; this.pub.message = e.message; }
      finally { this.pub.busy = false; }
    },

    async doUnpublish() {
      this.pub.busy = true; this.pub.message = ''; this.pub.error = false;
      try {
        await SEO.api('publish/' + this.art.id + '/unpublish', { method: 'POST', body: { target_id: this.pub.targetId } });
        this.pub.message = 'Снято с публикации';
        await this.reloadArticle();
      } catch (e) { this.pub.error = true; this.pub.message = e.message; }
      finally { this.pub.busy = false; }
    },

    // ============================================================ QA ==
    async loadQa() {
      try { const d = await SEO.api('qa/' + this.art.id + '/all', { silent: true }); this.qa.issues = d || []; }
      catch (_) { this.qa.issues = []; }
    },

    async runQa() {
      this.qa.busy = true;
      try { await SEO.api('qa/' + this.art.id + '/run', { method: 'POST' }); await this.loadQa(); SEO.toast('Проверено', 'ok'); }
      finally { this.qa.busy = false; }
    },

    async fixQa(iss) {
      this.qa.busy = true;
      try { await SEO.api('qa/' + this.art.id + '/fix', { method: 'POST', body: { issue_id: iss.id } }); await this.loadQa(); await this.loadBlocks(); }
      finally { this.qa.busy = false; }
    },

    async resolveQa(iss) {
      try { await SEO.api('qa/' + this.art.id + '/resolve', { method: 'POST', body: { issue_id: iss.id } }); iss.resolved_at = new Date().toISOString(); } catch (_) {}
    },

    // ============================================================ CATALOG editor ==
    openCreateCatalog() {
      this.current = { kind: 'catalog', id: null };
      this.cat = { id: null, profile_id: this.profileId, name: '', slug: '', parent_id: null, sort_order: 0, description: '' };
    },
    async openCatalog(id) {
      this.current = { kind: 'catalog', id };
      const c = await SEO.api('catalogs/' + id);
      this.cat = c;
    },
    async saveCatalog() {
      try {
        const isNew = !this.cat.id;
        const r = isNew
          ? await SEO.api('catalogs', { method: 'POST', body: this.cat })
          : await SEO.api('catalogs/' + this.cat.id, { method: 'PUT', body: this.cat });
        Object.assign(this.cat, r || {});
        await this.loadCatalogs();
        SEO.toast('Сохранено', 'ok');
      } catch (_) {}
    },
    confirmDeleteCatalog() {
      this.modal.confirm = {
        title: 'Удалить рубрику', message: 'Удалить «' + this.cat.name + '»?',
        onConfirm: async () => {
          try {
            await SEO.api('catalogs/' + this.cat.id, { method: 'DELETE' });
            this.current = { kind: null, id: null }; this.cat = null;
            await this.loadCatalogs(); SEO.toast('Удалено', 'ok');
          } catch (_) {}
        }
      };
    },

    // ============================================================ TEMPLATE editor ==
    openCreateTemplate() {
      this.current = { kind: 'template', id: null };
      this.tpl = { id: null, profile_id: this.profileId, name: '', code: '', description: '', structure_json: '[]' };
    },
    async openTemplate(id) {
      this.current = { kind: 'template', id };
      const t = await SEO.api('templates/' + id);
      this.tpl = { ...t, structure_json: typeof t.structure_json === 'string' ? t.structure_json : JSON.stringify(t.structure_json || [], null, 2) };
    },
    async saveTemplate() {
      try {
        const body = { ...this.tpl };
        try { body.structure_json = JSON.parse(this.tpl.structure_json || '[]'); } catch { SEO.toast('JSON структуры некорректен', 'err'); return; }
        const isNew = !this.tpl.id;
        const r = isNew
          ? await SEO.api('templates', { method: 'POST', body })
          : await SEO.api('templates/' + this.tpl.id, { method: 'PUT', body });
        if (r) this.tpl = { ...r, structure_json: typeof r.structure_json === 'string' ? r.structure_json : JSON.stringify(r.structure_json || [], null, 2) };
        await this.loadTemplates(); SEO.toast('Сохранено', 'ok');
      } catch (_) {}
    },
    confirmDeleteTemplate() {
      this.modal.confirm = {
        title: 'Удалить шаблон', message: 'Удалить «' + this.tpl.name + '»?',
        onConfirm: async () => {
          try {
            await SEO.api('templates/' + this.tpl.id, { method: 'DELETE' });
            this.current = { kind: null, id: null }; this.tpl = null;
            await this.loadTemplates(); SEO.toast('Удалено', 'ok');
          } catch (_) {}
        }
      };
    },

    // ============================================================ LINK editor ==
    openCreateLink() {
      this.lnk = { id: null, profile_id: this.profileId, key: '', url: '', label: '', target: '_blank', nofollow: 0, is_active: 1, description: '' };
    },
    async openLink(id) {
      const r = await SEO.api('links/' + id);
      this.lnk = { ...r, nofollow: r.nofollow ? 1 : 0, is_active: r.is_active ? 1 : 0 };
    },
    closeLinkEditor() { this.lnk = null; },
    async saveLink() {
      if (!this.lnk.key || !this.lnk.url) { SEO.toast('Ключ и URL обязательны', 'err'); return; }
      try {
        const body = { ...this.lnk, profile_id: this.profileId };
        const isNew = !this.lnk.id;
        const r = isNew
          ? await SEO.api('links', { method: 'POST', body })
          : await SEO.api('links/' + this.lnk.id, { method: 'PUT', body });
        if (r) this.lnk = { ...r, nofollow: r.nofollow ? 1 : 0, is_active: r.is_active ? 1 : 0 };
        await this.loadLinks(); SEO.toast('Сохранено', 'ok');
      } catch (_) {}
    },
    confirmDeleteLink() {
      this.modal.confirm = {
        title: 'Удалить ссылку', message: 'Удалить ссылку «' + (this.lnk.key || this.lnk.label || '') + '»?',
        onConfirm: async () => {
          try {
            await SEO.api('links/' + this.lnk.id, { method: 'DELETE' });
            this.lnk = null;
            await this.loadLinks(); SEO.toast('Удалено', 'ok');
          } catch (_) {}
        }
      };
    },

    // ============================================================ TARGET editor ==
    openCreateTarget() {
      this.tgt = {
        id: null, profile_id: this.profileId,
        name: '', type: 'selfhosted', base_url: '', is_active: 1,
        config: { host: '', document_root: '', publish_endpoint: '', note: '' },
      };
    },
    async openTarget(id) {
      const r = await SEO.api('publish-targets/' + id);
      this.tgt = { ...r, config: r.config || {}, is_active: r.is_active ? 1 : 0 };
      // ensure config has all keys for the active type so x-model bindings stay reactive
      if (this.tgt.type === 'selfhosted') {
        this.tgt.config = Object.assign({ host: '', document_root: '', publish_endpoint: '', note: '' }, this.tgt.config);
      } else if (this.tgt.type === 'ftp') {
        this.tgt.config = Object.assign({ host: '', port: 21, username: '', password: '', document_root: '', ssl: 0 }, this.tgt.config);
      }
    },
    closeTargetEditor() { this.tgt = null; },
    async saveTarget() {
      if (!this.tgt.name || !this.tgt.base_url) { SEO.toast('Название и base_url обязательны', 'err'); return; }
      try {
        const body = { ...this.tgt, profile_id: this.profileId };
        const isNew = !this.tgt.id;
        const r = isNew
          ? await SEO.api('publish-targets', { method: 'POST', body })
          : await SEO.api('publish-targets/' + this.tgt.id, { method: 'PUT', body });
        if (r) this.tgt = { ...r, config: r.config || {}, is_active: r.is_active ? 1 : 0 };
        await this.loadTargets(); SEO.toast('Сохранено', 'ok');
      } catch (_) {}
    },
    confirmDeleteTarget() {
      this.modal.confirm = {
        title: 'Удалить площадку', message: 'Удалить «' + this.tgt.name + '»?',
        onConfirm: async () => {
          try {
            await SEO.api('publish-targets/' + this.tgt.id, { method: 'DELETE' });
            this.tgt = null;
            await this.loadTargets(); SEO.toast('Удалено', 'ok');
          } catch (_) {}
        }
      };
    },

  };
}
</script>

<?php
$extraFoot = '';
include __DIR__ . '/_layout/footer.php';
