<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
requireAuth();

require_once __DIR__ . '/../config.php';

$pageTitle      = 'Семантика — SEO admin';
$activeNav      = 'clustering';
$pageHeading    = 'Семантика';
$pageSubheading = 'Сбор запросов, кластеризация и интенты';

ob_start();
?>
<div x-data x-cloak class="flex items-center gap-2 bg-sand-100 rounded-full p-1 shadow-rail">
  <button type="button"
          class="px-5 h-10 rounded-full text-sm font-semibold transition-colors"
          :class="$store.clst.view === 'semantic' ? 'bg-ink-900 text-sand-50' : 'text-ink-700 hover:bg-sand-200'"
          @click="$store.clst.view = 'semantic'">
    Семантика
  </button>
  <button type="button"
          class="px-5 h-10 rounded-full text-sm font-semibold transition-colors"
          :class="$store.clst.view === 'intents' ? 'bg-ink-900 text-sand-50' : 'text-ink-700 hover:bg-sand-200'"
          @click="$store.clst.view = 'intents'">
    Интенты
  </button>
</div>
<?php
$topbarRight = ob_get_clean();

include __DIR__ . '/_layout/header.php';
?>

<div x-data x-cloak>

  <!-- ─────────────── SEMANTIC VIEW ─────────────── -->
  <section x-show="$store.clst.view === 'semantic'"
           x-data="semanticPage()" x-init="init()"
           class="grid grid-cols-1 lg:grid-cols-[340px_1fr] gap-5 md:gap-8">

    <!-- Sidebar -->
    <aside class="space-y-5 lg:sticky lg:top-6 self-start max-h-[calc(100vh-140px)] overflow-auto">
      <div class="card" style="padding:20px">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-ink-500 mb-3">Новая задача</h2>
        <div class="space-y-3">
          <div>
            <label class="label">Базовый запрос</label>
            <textarea class="textarea" rows="3" x-model="newJob.seed" placeholder="ключевое слово 1, ключевое слово 2"></textarea>
          </div>
          <div>
            <label class="label">Метод сбора</label>
            <select class="select" x-model="newJob.source">
              <option value="gpt">GPT-генерация запросов</option>
              <option value="manual">Ручной ввод</option>
              <option value="yandex">Yandex Wordstat API</option>
            </select>
          </div>
          <button type="button" class="btn-primary w-full" @click="createJob()" :disabled="creating">
            <span x-show="!creating">Создать задачу</span>
            <span x-show="creating" class="flex items-center gap-2 justify-center"><span class="spinner"></span>Создаю…</span>
          </button>
        </div>
      </div>

      <div class="card" style="padding:0;overflow:hidden">
        <div class="flex items-center justify-between px-5 pt-4 pb-3">
          <h2 class="text-xs font-semibold uppercase tracking-wide text-ink-500">Задачи</h2>
          <span class="badge-soft" x-text="jobs.length"></span>
        </div>
        <template x-if="jobs.length === 0">
          <div class="text-ink-300 text-sm py-6 text-center">Пусто</div>
        </template>
        <ul class="divide-y divide-sand-200">
          <template x-for="j in jobs" :key="j.id">
            <li>
              <button type="button" @click="selectJob(j.id)"
                      class="w-full text-left px-5 py-3 transition-colors"
                      :class="j.id == currentJobId ? 'bg-ink-900 text-sand-50' : 'hover:bg-sand-100 text-ink-900'">
                <div class="font-semibold text-sm truncate" x-text="j.seed_keyword"></div>
                <div class="flex items-center gap-2 mt-1.5 text-xs"
                     :class="j.id == currentJobId ? 'text-sand-300' : 'text-ink-500'">
                  <span class="badge-soft" x-text="statusLabel(j.status)"></span>
                  <span><span x-text="j.keyword_count || j.total_found || 0"></span> зап.</span>
                  <span><span x-text="j.cluster_count || j.total_clusters || 0"></span> кл.</span>
                </div>
              </button>
            </li>
          </template>
        </ul>
      </div>
    </aside>

    <!-- Main panel -->
    <div class="min-w-0 space-y-5 md:space-y-6">

      <template x-if="!currentJob">
        <div class="card grid place-items-center text-center text-ink-300" style="padding:80px 20px">
          <div>
            <div class="text-6xl mb-4">🧭</div>
            <p class="text-ink-500">Выбери задачу слева или создай новую.</p>
          </div>
        </div>
      </template>

      <template x-if="currentJob">
        <div class="space-y-5 md:space-y-6">

          <!-- Job header -->
          <div class="card-dark" style="padding:24px">
            <div class="flex flex-wrap items-start justify-between gap-4">
              <div class="min-w-0 flex-1">
                <div class="text-xs uppercase tracking-wide text-sand-300 mb-1">Задача #<span x-text="currentJob.id"></span></div>
                <h2 class="text-xl md:text-2xl font-bold text-sand-50 break-words" x-text="currentJob.seed_keyword"></h2>
                <div class="flex flex-wrap items-center gap-3 mt-3 text-sm text-sand-300">
                  <span class="badge-soft" x-text="statusLabel(currentJob.status)"></span>
                  <span>Запросов: <b class="text-sand-50" x-text="currentJob.total_found || 0"></b></span>
                  <span>Кластеров: <b class="text-sand-50" x-text="currentJob.total_clusters || 0"></b></span>
                </div>
              </div>
              <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn-soft" @click="collectOpen = !collectOpen">+ Запросы</button>
                <button type="button" class="btn-accent" @click="clusterKeywords()" :disabled="clustering">
                  <span x-show="!clustering">Кластеризовать</span>
                  <span x-show="clustering" class="flex items-center gap-2"><span class="spinner"></span>Идёт…</span>
                </button>
                <button type="button" class="btn-danger" @click="deleteJob()">Удалить</button>
              </div>
            </div>
            <div class="mt-4" x-show="clustering">
              <div class="progress-bar"><div class="h-full bg-sun-400 rounded-full transition-all" :style="`width:${progress}%`"></div></div>
            </div>
          </div>

          <!-- Collect panel -->
          <div class="card" x-show="collectOpen" x-collapse style="padding:20px">
            <div class="flex items-center justify-between mb-3">
              <h3 class="text-base font-semibold">Добавление запросов</h3>
              <button type="button" class="btn-icon" @click="collectOpen = false">✕</button>
            </div>
            <div class="grid md:grid-cols-[200px_1fr] gap-4">
              <div>
                <label class="label">Метод</label>
                <select class="select" x-model="collect.source">
                  <option value="gpt">GPT-генерация</option>
                  <option value="manual">Ручной ввод</option>
                </select>
              </div>
              <div>
                <template x-if="collect.source === 'gpt'">
                  <div>
                    <label class="label">Количество запросов</label>
                    <input type="number" class="input" x-model.number="collect.max" min="10" max="2000">
                  </div>
                </template>
                <template x-if="collect.source === 'manual'">
                  <div>
                    <label class="label">Поисковые запросы (по одному в строке)</label>
                    <textarea class="textarea" rows="8" x-model="collect.text" placeholder="запрос 1&#10;запрос 2"></textarea>
                    <div class="text-xs text-ink-500 mt-1">
                      Введено: <b x-text="manualCount()"></b> ·
                      В базе: <b x-text="currentJob ? (currentJob.total_found || 0) : 0"></b>
                    </div>
                  </div>
                </template>
              </div>
            </div>
            <div class="flex justify-end mt-4">
              <button type="button" class="btn-primary" @click="doCollect()" :disabled="collecting">
                <span x-show="!collecting" x-text="collect.source === 'manual' ? 'Сохранить' : 'Сгенерировать'"></span>
                <span x-show="collecting" class="flex items-center gap-2"><span class="spinner"></span>Идёт…</span>
              </button>
            </div>
          </div>

          <!-- Tabs -->
          <div class="tabs">
            <button type="button" class="tab" :class="tab === 'clusters' ? 'tab-active' : ''" @click="tab = 'clusters'">Кластеры</button>
            <button type="button" class="tab" :class="tab === 'keywords' ? 'tab-active' : ''" @click="tab = 'keywords'; loadKeywords()">Запросы</button>
            <button type="button" class="tab" :class="tab === 'log' ? 'tab-active' : ''" @click="tab = 'log'">Журнал</button>
          </div>

          <!-- Clusters tab -->
          <div x-show="tab === 'clusters'" class="space-y-5">
            <!-- Toolbar -->
            <div class="card flex flex-wrap items-center gap-3" style="padding:14px 18px" x-show="allClusters.length > 0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-xs uppercase tracking-wide text-ink-500">Статус</span>
                <template x-for="opt in statusFilters" :key="opt.val">
                  <button type="button"
                          class="px-3 h-8 rounded-full text-xs font-semibold transition-colors border"
                          :class="view.status === opt.val ? 'bg-ink-900 text-sand-50 border-ink-900' : 'bg-transparent text-ink-700 border-sand-300 hover:bg-sand-100'"
                          @click="view.status = opt.val" x-text="opt.label"></button>
                </template>
              </div>
              <div class="hidden md:block w-px h-6 bg-sand-200"></div>
              <div class="flex items-center gap-2">
                <span class="text-xs uppercase tracking-wide text-ink-500">Группа</span>
                <select class="select" style="width:auto;min-width:130px" x-model="view.group">
                  <option value="">Нет</option>
                  <option value="intent">По интенту</option>
                  <option value="status">По статусу</option>
                </select>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-xs uppercase tracking-wide text-ink-500">Сортировка</span>
                <select class="select" style="width:auto;min-width:160px" x-model="view.sort">
                  <option value="priority_desc">Приоритет ↓</option>
                  <option value="volume_desc">Объём ↓</option>
                  <option value="count_desc">Запросов ↓</option>
                  <option value="name_asc">По алфавиту</option>
                </select>
              </div>
              <div class="flex items-center gap-1 ml-auto">
                <button type="button" class="btn-icon" :class="view.layout === 'grid' ? 'bg-sand-200' : ''" @click="view.layout = 'grid'" title="Сетка">⊞</button>
                <button type="button" class="btn-icon" :class="view.layout === 'list' ? 'bg-sand-200' : ''" @click="view.layout = 'list'" title="Список">☰</button>
              </div>
              <div class="w-full md:w-auto flex flex-wrap items-center gap-3 text-xs text-ink-500" x-html="summaryHtml()"></div>
              <div class="flex flex-wrap gap-2 w-full md:w-auto">
                <button type="button" class="btn-soft" @click="generateAllApproved()">Создать статьи</button>
                <button type="button" class="btn-danger" @click="deleteAllClusters()">Удалить все</button>
              </div>
            </div>

            <div x-html="clustersHtml()"></div>

            <template x-if="allClusters.length === 0 && !clustering">
              <div class="card grid place-items-center text-center text-ink-300" style="padding:60px 20px">
                <div>
                  <p class="text-ink-500">Кластеров пока нет. Сначала собери запросы и нажми «Кластеризовать».</p>
                </div>
              </div>
            </template>
          </div>

          <!-- Keywords tab -->
          <div x-show="tab === 'keywords'" class="space-y-4">
            <div class="card" style="padding:14px 18px">
              <div class="flex flex-wrap items-center gap-2">
                <input type="text" class="input" style="max-width:240px" x-model="kw.search" @input.debounce.300ms="kw.page = 1; loadKeywords()" placeholder="Поиск...">
                <select class="select" style="width:auto;min-width:160px" x-model="kw.cluster_id" @change="kw.page = 1; loadKeywords()">
                  <option value="">Все кластеры</option>
                  <option value="0">Без кластера</option>
                  <template x-for="c in allClusters" :key="c.id">
                    <option :value="c.id" x-text="c.name"></option>
                  </template>
                </select>
                <select class="select" style="width:auto" x-model="kw.sort" @change="kw.page = 1; loadKeywords()">
                  <option value="volume_desc">Частотность ↓</option>
                  <option value="volume_asc">Частотность ↑</option>
                  <option value="alpha">По алфавиту</option>
                </select>
                <select class="select" style="width:auto" x-model.number="kw.per_page" @change="kw.page = 1; loadKeywords()">
                  <option :value="50">50</option>
                  <option :value="100">100</option>
                  <option :value="250">250</option>
                  <option :value="500">500</option>
                </select>
                <span class="text-xs text-ink-500 ml-auto" x-text="kw.info"></span>
              </div>
            </div>

            <div class="card" style="padding:0;overflow:hidden">
              <table class="tbl w-full">
                <thead>
                  <tr>
                    <th>Запрос</th>
                    <th style="width:110px">Частотность</th>
                    <th style="width:110px">Конкуренция</th>
                    <th style="width:90px">CPC, ₽</th>
                    <th style="width:160px">Кластер</th>
                    <th style="width:50px"></th>
                  </tr>
                </thead>
                <tbody>
                  <template x-for="row in kw.rows" :key="row.id">
                    <tr>
                      <td class="font-medium" x-text="row.keyword"></td>
                      <td><input type="number" class="input" style="height:32px;text-align:right" :value="row.volume ?? ''" @change="updateKw(row.id, 'volume', $event.target.value)"></td>
                      <td><input type="number" step="0.01" class="input" style="height:32px;text-align:right" :value="row.competition ?? ''" @change="updateKw(row.id, 'competition', $event.target.value)"></td>
                      <td><input type="number" step="0.01" class="input" style="height:32px;text-align:right" :value="row.cpc ?? ''" @change="updateKw(row.id, 'cpc', $event.target.value)"></td>
                      <td>
                        <template x-if="row.cluster_id">
                          <span class="badge-soft" x-text="clusterName(row.cluster_id)"></span>
                        </template>
                        <template x-if="!row.cluster_id"><span class="text-ink-300">—</span></template>
                      </td>
                      <td><button type="button" class="btn-icon" @click="deleteKeyword(row.id)" title="Удалить">✕</button></td>
                    </tr>
                  </template>
                  <template x-if="kw.rows.length === 0">
                    <tr><td colspan="6" class="text-center text-ink-300 py-8">Нет данных</td></tr>
                  </template>
                </tbody>
              </table>
              <div class="flex items-center justify-between px-4 py-3 border-t border-sand-200">
                <span class="text-xs text-ink-500" x-text="kw.info"></span>
                <div class="flex gap-2">
                  <button type="button" class="btn-soft" @click="kwPageNav(-1)" :disabled="kw.page <= 1">Назад</button>
                  <button type="button" class="btn-soft" @click="kwPageNav(1)" :disabled="kw.page >= kw.pages">Далее</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Log tab -->
          <div x-show="tab === 'log'" class="card" style="padding:18px">
            <h3 class="text-base font-semibold mb-3">Журнал операций</h3>
            <div class="bg-ink-900 text-sand-100 rounded-2xl p-4 font-mono text-xs leading-relaxed max-h-[500px] overflow-y-auto" x-ref="logBox">
              <template x-if="logLines.length === 0">
                <div class="text-ink-300">Журнал пуст</div>
              </template>
              <template x-for="(line, idx) in logLines" :key="idx">
                <div :class="line.cls" x-text="line.text"></div>
              </template>
            </div>
          </div>

        </div>
      </template>
    </div>
  </section>

  <!-- ─────────────── INTENTS VIEW ─────────────── -->
  <section x-show="$store.clst.view === 'intents'"
           x-data="intentsPage()" x-init="init()"
           class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_340px] gap-5 md:gap-8">

    <!-- Form panel (left, wide) -->
    <div>
      <template x-if="!form">
        <div class="card grid place-items-center text-center text-ink-300" style="padding:60px 20px">
          <div>
            <p class="text-ink-500">Выбери интент или создай новый.</p>
            <button type="button" class="btn-primary mt-4" @click="openForm(null)">+ Добавить</button>
          </div>
        </div>
      </template>
      <template x-if="form">
        <div class="card" style="padding:24px">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold" x-text="form.isNew ? 'Новый интент' : 'Редактировать: ' + form.code"></h3>
            <button type="button" class="btn-icon" @click="closeForm()">✕</button>
          </div>
          <div class="space-y-3">
            <div>
              <label class="label">Код</label>
              <input type="text" class="input" style="font-family:ui-monospace,monospace"
                     x-model="form.code" :disabled="!form.isNew" maxlength="30" placeholder="action_plan">
              <p class="text-xs text-ink-500 mt-1" x-show="form.isNew">Только a-z, 0-9, _. Нельзя изменить после создания.</p>
            </div>
            <div>
              <label class="label">Название RU</label>
              <input type="text" class="input" x-model="form.label_ru" placeholder="План действий">
            </div>
            <div>
              <label class="label">Название EN</label>
              <input type="text" class="input" x-model="form.label_en" placeholder="Action Plan">
            </div>
            <div>
              <label class="label">Цвет бейджа</label>
              <div class="flex items-center gap-2">
                <input type="color" x-model="form.color" style="width:40px;height:40px;border:1px solid var(--sand-300);border-radius:10px;padding:0;background:transparent;cursor:pointer">
                <input type="text" class="input flex-1" x-model="form.color" maxlength="7" placeholder="#6366f1" style="font-family:ui-monospace,monospace">
              </div>
            </div>
            <div>
              <label class="label">Порядок сортировки</label>
              <input type="number" class="input" x-model.number="form.sort_order" min="0" max="255" style="width:120px">
            </div>
            <div>
              <label class="label">Описание (для людей)</label>
              <textarea class="textarea" rows="3" x-model="form.description" placeholder="Что означает этот интент..."></textarea>
            </div>
            <div>
              <label class="label">GPT-инструкция (gpt_hint)</label>
              <textarea class="textarea" rows="4" x-model="form.gpt_hint" placeholder="Назначай intent=X когда запрос содержит..."></textarea>
              <p class="text-xs text-ink-500 mt-1">Когда GPT должен назначать этот интент.</p>
            </div>
            <div>
              <label class="label">Тон статьи (опционально)</label>
              <textarea class="textarea" rows="2" x-model="form.article_tone" placeholder="Тон — энциклопедический..."></textarea>
            </div>
            <div>
              <label class="label">Пример открытия (опционально)</label>
              <textarea class="textarea" rows="2" x-model="form.article_open" placeholder="Первый абзац статьи..."></textarea>
            </div>
            <div class="flex items-center gap-3 pt-1">
              <label class="label" style="margin:0">Активен</label>
              <label class="toggle">
                <input type="checkbox" x-model="form.is_active">
                <span class="toggle-track"></span>
                <span class="toggle-thumb"></span>
              </label>
            </div>
            <div class="flex flex-wrap gap-2 pt-2">
              <button type="button" class="btn-primary" @click="save()" :disabled="saving">
                <span x-show="!saving">Сохранить</span>
                <span x-show="saving" class="flex items-center gap-2"><span class="spinner"></span>…</span>
              </button>
              <button type="button" class="btn-danger" @click="del()" x-show="!form.isNew">Удалить</button>
              <button type="button" class="btn-soft" @click="closeForm()">Отмена</button>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- List (right, narrow, sticky) -->
    <aside class="card lg:sticky lg:top-6 self-start max-h-[calc(100vh-140px)] overflow-auto" style="padding:0">
      <div class="flex items-center justify-between px-4 py-3 border-b border-sand-200">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-ink-500">Интенты</h2>
        <button type="button" class="btn-primary" style="padding:6px 12px;font-size:12px" @click="openForm(null)">+ Добавить</button>
      </div>
      <template x-if="list.length === 0">
        <div class="text-ink-300 text-sm py-8 text-center">Нет интентов</div>
      </template>
      <ul class="divide-y divide-sand-200">
        <template x-for="t in list" :key="t.code">
          <li>
            <button type="button" @click="openForm(t.code)"
                    class="w-full flex items-center gap-2 px-4 py-3 transition-colors text-left"
                    :class="editingCode === t.code ? 'bg-sand-100' : 'hover:bg-sand-100'">
              <span class="w-3 h-3 rounded-full flex-shrink-0" :style="`background:${t.color || '#6366f1'}`"></span>
              <span class="flex-1 min-w-0">
                <span class="block font-semibold text-sm truncate" x-text="t.label_ru"></span>
                <span class="block font-mono text-[11px] text-ink-300 truncate" x-text="t.code"></span>
              </span>
              <span @click.stop>
                <label class="toggle">
                  <input type="checkbox" :checked="!!t.is_active" @change="toggleActive(t.code, $event.target.checked)">
                  <span class="toggle-track"></span>
                  <span class="toggle-thumb"></span>
                </label>
              </span>
            </button>
          </li>
        </template>
      </ul>
    </aside>
  </section>
</div>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.store('clst', { view: 'semantic' });
});

window.__clst = {};

function statusLabel(s) {
  const map = { pending: 'Ожидает', collecting: 'Сбор', clustering: 'Кластеризация', done: 'Готов', error: 'Ошибка',
                new: 'Новый', approved: 'Утверждён', rejected: 'Отклонён', article_created: 'Статья создана' };
  return map[s] || s || '—';
}

function semanticPage() {
  return {
    profileId: null,
    jobs: [],
    intentTypes: {},
    currentJobId: null,
    currentJob: null,
    creating: false,
    collecting: false,
    clustering: false,
    progress: 0,
    tab: 'clusters',
    collectOpen: false,
    newJob: { seed: '', source: 'gpt' },
    collect: { source: 'gpt', max: 200, text: '' },
    allClusters: [],
    view: { status: '', group: '', sort: 'priority_desc', layout: 'grid' },
    kw: { rows: [], page: 1, pages: 1, per_page: 100, sort: 'volume_desc', cluster_id: '', search: '', info: '' },
    logLines: [],
    statusFilters: [
      { val: '', label: 'Все' },
      { val: 'new', label: 'Новые' },
      { val: 'approved', label: 'Утверждённые' },
      { val: 'rejected', label: 'Отклонённые' },
      { val: 'article_created', label: 'Со статьёй' },
    ],

    statusLabel,

    async init() {
      this.profileId = window.SEO && SEO.profile.id;
      if (!this.profileId) {
        SEO.toast('Профиль не выбран', 'err');
        return;
      }
      window.__clst = this;
      await this.loadIntents();
      await this.loadJobs();
    },

    async loadIntents() {
      try {
        const data = await SEO.api('keywords/intents', { silent: true });
        const list = data || [];
        this.intentTypes = {};
        list.forEach(t => { this.intentTypes[t.code] = t; });
      } catch (_) { this.intentTypes = {}; }
    },

    async loadJobs() {
      try {
        const data = await SEO.api('keywords/jobs?profile_id=' + this.profileId + '&per_page=50', { silent: true });
        this.jobs = data || [];
      } catch (_) { this.jobs = []; }
    },

    async createJob() {
      const seed = this.newJob.seed.trim();
      if (!seed) { SEO.toast('Введите базовый запрос', 'err'); return; }
      this.creating = true;
      try {
        const body = { seed_keyword: seed, source: this.newJob.source, profile_id: this.profileId };
        const data = await SEO.api('keywords/jobs', { method: 'POST', body });
        this.newJob.seed = '';
        SEO.toast('Задача создана', 'ok');
        await this.loadJobs();
        await this.selectJob(data.id);
        if (this.newJob.source === 'gpt') {
          this.collect.source = 'gpt';
          await this.doCollectDirect('gpt', { max_keywords: 200 });
        }
      } catch (e) { SEO.toast('Ошибка: ' + e.message, 'err'); }
      this.creating = false;
    },

    async selectJob(id) {
      this.currentJobId = id;
      try {
        this.currentJob = await SEO.api('keywords/jobs/' + id, { silent: true });
        this.tab = 'clusters';
        this.collectOpen = false;
        await this.loadClusters();
      } catch (e) { SEO.toast('Ошибка загрузки', 'err'); }
    },

    async deleteJob() {
      if (!this.currentJobId || !confirm('Удалить задачу со всеми данными?')) return;
      try {
        await SEO.api('keywords/jobs/' + this.currentJobId, { method: 'DELETE' });
        this.currentJobId = null;
        this.currentJob = null;
        this.allClusters = [];
        SEO.toast('Удалено', 'ok');
        await this.loadJobs();
      } catch (e) { SEO.toast('Ошибка: ' + e.message, 'err'); }
    },

    manualCount() {
      return this.collect.text.split('\n').filter(l => l.trim()).length;
    },

    async doCollect() {
      if (this.collect.source === 'manual') {
        const text = this.collect.text.trim();
        if (!text) { SEO.toast('Введите запросы', 'err'); return; }
        await this.doCollectDirect('manual', { keywords: text });
      } else {
        await this.doCollectDirect(this.collect.source, { max_keywords: this.collect.max || 200 });
      }
    },

    async doCollectDirect(source, extra) {
      if (!this.currentJobId) return;
      this.collecting = true;
      this.logMsg('Сбор запросов (' + source + ')...', 'log-info');
      try {
        const body = { source, config: { max_keywords: extra.max_keywords || 200 } };
        if (source === 'manual') body.keywords = extra.keywords;
        const data = await SEO.api('keywords/collect/' + this.currentJobId, { method: 'POST', body });
        const count = (data && data.imported) || 0;
        SEO.toast('Добавлено ' + count + ' запросов', 'ok');
        this.logMsg('Добавлено ' + count + ' запросов (' + source + ')', 'log-ok');
        this.collectOpen = false;
        this.collect.text = '';
        await this.selectJob(this.currentJobId);
      } catch (e) {
        SEO.toast('Ошибка: ' + e.message, 'err');
        this.logMsg('Ошибка: ' + e.message, 'log-err');
      }
      this.collecting = false;
    },

    async clusterKeywords() {
      if (!this.currentJobId || !confirm('Запустить GPT-кластеризацию? Существующие кластеры будут пересозданы.')) return;
      this.clustering = true;
      this.progress = 0;
      this.logMsg('--- Запуск кластеризации ---', 'log-info');
      try {
        const resp = await fetch('/controllers/router.php?r=keywords/cluster/' + this.currentJobId + '/sse', {
          method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ model: 'gpt-4o' }),
        });
        const reader = resp.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        while (true) {
          const { value, done } = await reader.read();
          if (done) break;
          buffer += decoder.decode(value, { stream: true });
          const lines = buffer.split('\n'); buffer = lines.pop();
          let eventName = '';
          for (const line of lines) {
            if (line.startsWith('event: ')) eventName = line.substring(7).trim();
            else if (line.startsWith('data: ') && eventName) {
              try { this.handleClusterEvent(eventName, JSON.parse(line.substring(6))); } catch(_) {}
              eventName = '';
            }
          }
        }
      } catch (e) {
        this.logMsg('Ошибка: ' + e.message, 'log-err');
        SEO.toast('Ошибка кластеризации', 'err');
      }
      this.clustering = false;
      this.progress = 0;
      await this.selectJob(this.currentJobId);
    },

    handleClusterEvent(event, data) {
      switch (event) {
        case 'start': this.logMsg('Кластеризация: "' + data.seed + '"', 'log-info'); break;
        case 'progress':
          if (data.phase === 'clustering') this.logMsg(data.total_keywords + ' запросов, ' + data.total_batches + ' батч(ей)', 'log-info');
          else if (data.phase === 'merging') { this.logMsg('Объединение кластеров...', 'log-info'); this.progress = 80; }
          else if (data.phase === 'saving') { this.logMsg('Сохранение ' + data.clusters + ' кластеров...', 'log-info'); this.progress = 90; }
          break;
        case 'batch_start':
          this.logMsg('Батч ' + data.batch + '/' + data.total + ' (' + data.keywords_in_batch + ' запросов)...', 'log-info');
          this.progress = Math.round((data.batch / data.total) * 70);
          break;
        case 'batch_done': this.logMsg('Батч ' + data.batch + ': ' + data.clusters_found + ' кластеров', 'log-ok'); break;
        case 'batch_error': this.logMsg('Батч ' + data.batch + ': ' + data.error, 'log-err'); break;
        case 'done':
          this.logMsg('--- Готово: ' + data.total_clusters + ' кластеров из ' + data.total_keywords + ' запросов ---', 'log-ok');
          this.progress = 100;
          SEO.toast('Кластеризация: ' + data.total_clusters + ' кластеров', 'ok');
          break;
        case 'error':
          this.logMsg('ОШИБКА: ' + data.message, 'log-err');
          SEO.toast('Ошибка: ' + data.message, 'err');
          break;
      }
    },

    async loadClusters() {
      if (!this.currentJobId) { this.allClusters = []; return; }
      try {
        this.allClusters = (await SEO.api('keywords/clusters/' + this.currentJobId, { silent: true })) || [];
      } catch (_) { this.allClusters = []; }
    },

    sortedFiltered() {
      let list = this.allClusters.filter(c => !this.view.status || c.status === this.view.status);
      const sort = this.view.sort;
      list = [...list].sort((a, b) => {
        switch (sort) {
          case 'priority_desc': return (b.priority || 0) - (a.priority || 0);
          case 'volume_desc':   return (Number(b.total_volume) || 0) - (Number(a.total_volume) || 0);
          case 'count_desc':    return (b.keyword_count || 0) - (a.keyword_count || 0);
          case 'name_asc':      return (a.name || '').localeCompare(b.name || '', 'ru');
          default: return 0;
        }
      });
      return list;
    },

    summaryHtml() {
      const list = this.sortedFiltered();
      const totalVol = list.reduce((s, c) => s + (Number(c.total_volume) || 0), 0);
      const approved = list.filter(c => c.status === 'approved').length;
      const withArticle = list.filter(c => c.status === 'article_created').length;
      const ready = list.filter(c => c.status === 'approved' && c.intent && !c.article_id).length;
      let h = '<span>Показано: <b>' + list.length + '</b></span><span>Объём: <b>' + SEO.fmtNum(totalVol) + '</b></span>';
      if (approved) h += '<span>Утв.: <b>' + approved + '</b></span>';
      if (withArticle) h += '<span>Со статьёй: <b>' + withArticle + '</b></span>';
      if (ready) h += '<span class="text-ember-500">К генерации: <b>' + ready + '</b></span>';
      return h;
    },

    clustersHtml() {
      const list = this.sortedFiltered();
      if (!list.length) return '';
      if (this.view.group) return this.renderGrouped(list);
      return this.renderFlat(list);
    },

    renderGrouped(clusters) {
      const key = this.view.group;
      const statusLabels = { new: 'Новые', approved: 'Утверждённые', rejected: 'Отклонённые', article_created: 'Со статьёй', '': 'Без статуса' };
      const groups = {};
      clusters.forEach(c => {
        const gKey = c[key] || '';
        if (!groups[gKey]) groups[gKey] = [];
        groups[gKey].push(c);
      });
      let order;
      if (key === 'intent') { order = Object.keys(this.intentTypes); order.push(''); }
      else order = ['new', 'approved', 'article_created', 'rejected', ''];
      const sorted = Object.keys(groups).sort((a, b) => {
        const ia = order.indexOf(a), ib = order.indexOf(b);
        return (ia === -1 ? 99 : ia) - (ib === -1 ? 99 : ib);
      });
      return sorted.map(g => {
        const label = key === 'status' ? (statusLabels[g] || g || '—') : ((this.intentTypes[g] && this.intentTypes[g].label_ru) || g || 'Без интента');
        return '<div class="mb-6">'
          + '<div class="flex items-center gap-2 mb-3 pb-2 border-b border-sand-200">'
          + '<span class="text-xs font-bold uppercase tracking-wide text-ink-500">' + SEO.esc(label) + '</span>'
          + '<span class="badge-soft">' + groups[g].length + '</span></div>'
          + this.renderCardsHTML(groups[g]) + '</div>';
      }).join('');
    },

    renderFlat(list) { return this.renderCardsHTML(list); },

    renderCardsHTML(list) {
      if (this.view.layout === 'list') {
        return '<div class="card" style="padding:0;overflow:hidden"><div class="flex gap-3 px-4 py-2 border-b border-sand-200 text-xs uppercase tracking-wide text-ink-500">'
          + '<span class="flex-1">Название</span><span style="width:140px">Интент</span><span style="width:50px;text-align:right">Кл.</span><span style="width:80px;text-align:right">Vol</span>'
          + '</div>' + list.map(c => this.renderRow(c)).join('') + '</div>';
      }
      return '<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">' + list.map(c => this.renderCard(c)).join('') + '</div>';
    },

    statusBadge(s) {
      if (s === 'approved') return 'badge-ok';
      if (s === 'rejected') return 'badge-err';
      if (s === 'article_created') return 'badge-sun';
      return 'badge-soft';
    },

    renderCard(c) {
      const hasArticle = !!(c.article_id && c.status === 'article_created');
      const canApprove = c.status !== 'approved' && c.status !== 'article_created';
      const canReject = !hasArticle && c.status !== 'rejected';
      const canGenerate = !hasArticle && c.status === 'approved' && c.intent;
      const canDelete = !hasArticle;
      const intentLabel = c.intent ? ((this.intentTypes[c.intent] && this.intentTypes[c.intent].label_ru) || c.intent) : '';
      const intentColor = c.intent ? ((this.intentTypes[c.intent] && this.intentTypes[c.intent].color) || '') : '';

      let actions = '';
      if (hasArticle) {
        actions += '<a class="btn-icon" href="seo_page.php?article_id=' + c.article_id + '" title="Открыть статью">📄</a>';
        actions += '<button class="btn-icon" onclick="window.__clst.deleteClusterArticle(' + c.id + ')" title="Открепить">✕</button>';
      } else if (canGenerate) {
        actions += '<button class="btn-icon" onclick="window.__clst.generateArticle(' + c.id + ')" title="Создать статью">📝</button>';
      }
      if (canApprove) actions += '<button class="btn-icon" onclick="window.__clst.approveCluster(' + c.id + ')" title="Утвердить">✓</button>';
      if (canReject)  actions += '<button class="btn-icon" onclick="window.__clst.rejectCluster(' + c.id + ')" title="Отклонить">✗</button>';
      if (canDelete)  actions += '<button class="btn-icon" onclick="window.__clst.deleteCluster(' + c.id + ')" title="Удалить">🗑</button>';
      actions += '<button class="btn-icon" onclick="window.__clst.openClusterDetail(' + c.id + ')" title="Подробнее">…</button>';

      const opacity = c.status === 'rejected' ? 'opacity:.55;' : '';
      const intentBadge = intentLabel
        ? '<span class="badge-soft" style="' + (intentColor ? 'background:' + intentColor + '22;color:' + intentColor : '') + '">' + SEO.esc(intentLabel) + '</span>'
        : '';

      return '<div class="card" style="padding:18px;' + opacity + '">'
        + '<div class="flex items-start justify-between gap-2 mb-2">'
        + '<div class="font-semibold text-base line-clamp-2 flex-1">' + SEO.esc(c.name || '') + '</div>'
        + '<span class="badge-soft ' + this.statusBadge(c.status) + '">' + SEO.esc(statusLabel(c.status)) + '</span>'
        + '</div>'
        + '<div class="flex flex-wrap items-center gap-2 text-xs text-ink-500 mb-3">'
        + intentBadge
        + '<span><b class="text-ink-900">' + (c.keyword_count || 0) + '</b> кл.</span>'
        + '<span><b class="text-ink-900">' + SEO.fmtNum(c.total_volume) + '</b> vol</span>'
        + (c.priority > 0 ? '<span>P<b class="text-ink-900">' + c.priority + '</b></span>' : '')
        + '</div>'
        + '<div class="flex gap-1 flex-wrap">' + actions + '</div>'
        + '</div>';
    },

    renderRow(c) {
      const intentLabel = c.intent ? ((this.intentTypes[c.intent] && this.intentTypes[c.intent].label_ru) || c.intent) : '—';
      return '<div class="flex items-center gap-3 px-4 py-3 border-b border-sand-200 hover:bg-sand-100 cursor-pointer" onclick="window.__clst.openClusterDetail(' + c.id + ')">'
        + '<span class="flex-1 min-w-0 truncate font-medium">' + SEO.esc(c.name || '') + '</span>'
        + '<span class="text-xs text-ink-500" style="width:140px">' + SEO.esc(intentLabel) + '</span>'
        + '<span class="text-xs text-ink-700" style="width:50px;text-align:right">' + (c.keyword_count || 0) + '</span>'
        + '<span class="text-xs text-ink-700" style="width:80px;text-align:right">' + SEO.fmtNum(c.total_volume) + '</span>'
        + '</div>';
    },

    openClusterDetail(id) {
      const c = this.allClusters.find(x => x.id == id);
      if (!c) return;
      const hasArticle = !!(c.article_id && c.status === 'article_created');
      const canApprove = c.status !== 'approved' && c.status !== 'article_created';
      const canReject = !hasArticle && c.status !== 'rejected';
      const canGenerate = !hasArticle && c.status === 'approved' && c.intent;
      const canDelete = !hasArticle;
      const intentLabel = c.intent ? ((this.intentTypes[c.intent] && this.intentTypes[c.intent].label_ru) || c.intent) : '—';

      let actions = '';
      if (hasArticle) {
        actions += '<a class="btn-soft" href="seo_page.php?article_id=' + c.article_id + '">📄 Статья #' + c.article_id + '</a>';
      } else if (canGenerate) {
        actions += '<button class="btn-primary" onclick="window.__clst.generateArticle(' + c.id + ')">📝 Создать статью</button>';
      }
      if (canApprove) actions += '<button class="btn-soft" onclick="window.__clst.approveCluster(' + c.id + ')">✓ Утвердить</button>';
      if (canReject)  actions += '<button class="btn-soft" onclick="window.__clst.rejectCluster(' + c.id + ')">✗ Отклонить</button>';
      if (canDelete)  actions += '<button class="btn-danger" onclick="window.__clst.deleteCluster(' + c.id + ')">🗑 Удалить кластер</button>';

      const html = '<div style="padding:24px;max-width:520px">'
        + '<h3 class="text-lg font-bold mb-3">' + SEO.esc(c.name || '') + '</h3>'
        + '<div class="space-y-2 text-sm mb-4">'
        + '<div class="flex justify-between"><span class="text-ink-500">Статус</span><span class="font-medium">' + SEO.esc(statusLabel(c.status)) + '</span></div>'
        + '<div class="flex justify-between"><span class="text-ink-500">Интент</span><span class="font-medium">' + SEO.esc(intentLabel) + '</span></div>'
        + '<div class="flex justify-between"><span class="text-ink-500">Запросов</span><span class="font-medium">' + (c.keyword_count || 0) + '</span></div>'
        + '<div class="flex justify-between"><span class="text-ink-500">Объём</span><span class="font-medium">' + SEO.fmtNum(c.total_volume) + '</span></div>'
        + (c.priority > 0 ? '<div class="flex justify-between"><span class="text-ink-500">Приоритет</span><span class="font-medium">' + c.priority + '</span></div>' : '')
        + '</div>'
        + (c.article_angle ? '<div class="bg-sand-100 rounded-2xl p-4 text-sm mb-4 whitespace-pre-wrap">' + SEO.esc(c.article_angle) + '</div>' : '')
        + '<div class="flex flex-wrap gap-2">' + actions + '</div>'
        + '</div>';
      SEO.modal.open(html);
    },

    async approveCluster(id) {
      try { await SEO.api('keywords/clusters/approve/' + id, { method: 'POST', body: {} }); SEO.toast('Утверждён', 'ok'); await this.loadClusters(); }
      catch (e) { SEO.toast(e.message, 'err'); }
    },
    async rejectCluster(id) {
      try { await SEO.api('keywords/clusters/reject/' + id, { method: 'POST', body: {} }); SEO.toast('Отклонён', 'ok'); await this.loadClusters(); }
      catch (e) { SEO.toast(e.message, 'err'); }
    },
    async deleteCluster(id) {
      if (!confirm('Удалить кластер? Запросы станут нераспределёнными.')) return;
      try {
        await SEO.api('keywords/clusters/detail/' + id, { method: 'DELETE' });
        SEO.toast('Удалён', 'ok');
        await this.loadClusters();
        if (this.tab === 'keywords') await this.loadKeywords();
        try { this.currentJob = await SEO.api('keywords/jobs/' + this.currentJobId, { silent: true }); } catch(_){}
      } catch (e) { SEO.toast(e.message, 'err'); }
    },
    async deleteAllClusters() {
      if (!this.currentJobId) return;
      const count = this.allClusters.length;
      if (!count) { SEO.toast('Нет кластеров', 'err'); return; }
      if (!confirm('Удалить все ' + count + ' кластеров?')) return;
      try {
        const res = await SEO.api('keywords/clusters/all/' + this.currentJobId, { method: 'DELETE' });
        SEO.toast('Удалено ' + ((res && res.deleted) || count), 'ok');
        await this.loadClusters();
        if (this.tab === 'keywords') await this.loadKeywords();
        try { this.currentJob = await SEO.api('keywords/jobs/' + this.currentJobId, { silent: true }); } catch(_){}
      } catch (e) { SEO.toast('Ошибка: ' + e.message, 'err'); }
    },
    async deleteClusterArticle(id) {
      if (!confirm('Открепить статью от кластера?')) return;
      try {
        await SEO.api('keywords/clusters/article/' + id, { method: 'DELETE' });
        SEO.toast('Откреплено', 'ok');
        await this.loadClusters();
      } catch (e) { SEO.toast('Ошибка: ' + e.message, 'err'); }
    },
    async generateArticle(id) {
      const cluster = this.allClusters.find(c => c.id == id);
      if (!cluster) return;
      if (!cluster.intent) { SEO.toast('Не задан интент', 'err'); return; }
      if (!confirm('Создать статью из кластера «' + cluster.name + '»?')) return;
      try {
        const res = await SEO.api('keywords/clusters/generate-article/' + id, { method: 'POST', body: { strategy: 'rotate' } });
        const articleId = (res && (res.article_id || (res.data && res.data.article_id))) || '?';
        SEO.toast('Статья #' + articleId + ' создана', 'ok');
        this.logMsg('Статья #' + articleId + ' создана для «' + cluster.name + '»', 'log-ok');
        await this.loadClusters();
      } catch (e) { SEO.toast('Ошибка: ' + e.message, 'err'); }
    },
    async generateAllApproved() {
      const approved = this.allClusters.filter(c => c.status === 'approved' && c.intent && !c.article_id);
      if (!approved.length) { SEO.toast('Нет утверждённых без статьи', 'err'); return; }
      if (!confirm('Создать статьи для ' + approved.length + ' кластеров?')) return;
      this.tab = 'log';
      this.logMsg('Пакетная генерация: ' + approved.length + ' кластеров', 'log-info');
      let ok = 0, fail = 0;
      for (const c of approved) {
        try {
          this.logMsg('Создаю статью для «' + c.name + '»...', 'log-info');
          await SEO.api('keywords/clusters/generate-article/' + c.id, { method: 'POST', body: { strategy: 'rotate' } });
          ok++; this.logMsg('✓ «' + c.name + '» — готово', 'log-ok');
        } catch (e) { fail++; this.logMsg('✗ «' + c.name + '»: ' + e.message, 'log-err'); }
      }
      this.logMsg('--- Готово: ' + ok + ' создано, ' + fail + ' ошибок ---', ok > 0 ? 'log-ok' : 'log-err');
      SEO.toast('Создано ' + ok + ' из ' + approved.length, 'ok');
      await this.loadClusters();
    },

    clusterName(id) {
      const c = this.allClusters.find(x => x.id == id);
      return c ? c.name : '';
    },

    async loadKeywords() {
      if (!this.currentJobId) { this.kw.rows = []; return; }
      const params = new URLSearchParams({
        page: String(this.kw.page),
        per_page: String(this.kw.per_page),
        sort: this.kw.sort,
      });
      if (this.kw.cluster_id !== '') params.set('cluster_id', this.kw.cluster_id);
      if (this.kw.search.trim()) params.set('search', this.kw.search.trim());
      try {
        const resp = await fetch('/controllers/router.php?r=keywords/raw/' + this.currentJobId + '&' + params.toString());
        const json = await resp.json();
        if (!json.success) throw new Error(json.error || 'API error');
        this.kw.rows = json.data || [];
        const meta = json.meta || {};
        const total = meta.total || this.kw.rows.length;
        this.kw.pages = meta.pages || 1;
        this.kw.info = total + ' запросов · стр. ' + this.kw.page + ' из ' + this.kw.pages;
      } catch (e) {
        this.kw.rows = []; this.kw.info = '';
        SEO.toast('Ошибка: ' + e.message, 'err');
      }
    },

    kwPageNav(d) {
      this.kw.page = Math.max(1, Math.min(this.kw.pages, this.kw.page + d));
      this.loadKeywords();
    },

    async deleteKeyword(id) {
      try {
        await SEO.api('keywords/raw/item/' + id, { method: 'DELETE' });
        await this.loadKeywords();
      } catch (e) { SEO.toast('Ошибка', 'err'); }
    },

    async updateKw(id, field, value) {
      const body = {};
      body[field] = value === '' ? null : (field === 'competition' || field === 'cpc' ? parseFloat(value) : parseInt(value));
      try { await SEO.api('keywords/raw/update/' + id, { method: 'PUT', body, silent: true }); }
      catch (e) { SEO.toast('Ошибка сохранения', 'err'); }
    },

    logMsg(msg, cls) {
      const ts = new Date().toLocaleTimeString('ru-RU');
      const colorClass = cls === 'log-ok' ? 'text-emerald-400' : cls === 'log-err' ? 'text-ember-400' : 'text-sand-300';
      this.logLines.push({ text: '[' + ts + '] ' + msg, cls: colorClass });
      this.$nextTick(() => { if (this.$refs.logBox) this.$refs.logBox.scrollTop = this.$refs.logBox.scrollHeight; });
    },
  };
}

function intentsPage() {
  return {
    list: [],
    editingCode: null,
    form: null,
    saving: false,

    async init() { await this.load(); },

    async load() {
      try {
        const data = await SEO.api('intents', { silent: true });
        this.list = (data || []).slice().sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));
      } catch (_) { this.list = []; }
    },

    openForm(code) {
      this.editingCode = code;
      const isNew = code === null;
      const t = isNew ? {} : (this.list.find(x => x.code === code) || {});
      this.form = {
        isNew,
        code: isNew ? '' : code,
        label_ru: t.label_ru || '',
        label_en: t.label_en || '',
        color: t.color || '#6366f1',
        sort_order: t.sort_order !== undefined ? t.sort_order : 0,
        description: t.description || '',
        gpt_hint: t.gpt_hint || '',
        article_tone: t.article_tone || '',
        article_open: t.article_open || '',
        is_active: isNew ? true : !!t.is_active,
      };
    },

    closeForm() {
      this.editingCode = null;
      this.form = null;
    },

    async toggleActive(code, isActive) {
      try {
        await SEO.api('intents/' + code, { method: 'PATCH', body: { is_active: isActive ? 1 : 0 }, silent: true });
        const t = this.list.find(x => x.code === code);
        if (t) t.is_active = isActive ? 1 : 0;
      } catch (e) { SEO.toast('Ошибка', 'err'); await this.load(); }
    },

    async save() {
      if (!this.form) return;
      const f = this.form;
      const code = f.isNew ? f.code.trim() : f.code;
      if (!code) { SEO.toast('Введите код', 'err'); return; }
      if (f.isNew && !/^[a-z0-9_]{1,30}$/.test(code)) {
        SEO.toast('Код: a-z, 0-9, _', 'err'); return;
      }
      const color = (f.color || '').trim();
      if (!/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(color)) { SEO.toast('Некорректный HEX', 'err'); return; }
      const body = {
        label_ru: f.label_ru.trim(),
        label_en: f.label_en.trim(),
        color,
        sort_order: parseInt(f.sort_order) || 0,
        description: f.description.trim(),
        gpt_hint: f.gpt_hint.trim(),
        article_tone: f.article_tone.trim() || null,
        article_open: f.article_open.trim() || null,
        is_active: f.is_active ? 1 : 0,
      };
      if (!body.label_ru || !body.description || !body.gpt_hint) {
        SEO.toast('Заполни RU, описание и GPT-инструкцию', 'err'); return;
      }
      this.saving = true;
      try {
        if (f.isNew) {
          body.code = code;
          await SEO.api('intents', { method: 'POST', body });
          SEO.toast('Создано', 'ok');
        } else {
          await SEO.api('intents/' + code, { method: 'PATCH', body });
          SEO.toast('Сохранено', 'ok');
        }
        await this.load();
        if (f.isNew) this.closeForm();
        else this.openForm(code);
      } catch (e) { SEO.toast('Ошибка: ' + e.message, 'err'); }
      this.saving = false;
    },

    async del() {
      if (!this.form || this.form.isNew) return;
      const code = this.form.code;
      if (!confirm('Удалить интент «' + code + '»? Кластеры с этим интентом потеряют связь.')) return;
      try {
        await SEO.api('intents/' + code, { method: 'DELETE' });
        SEO.toast('Удалено', 'ok');
        this.closeForm();
        await this.load();
      } catch (e) { SEO.toast('Ошибка: ' + e.message, 'err'); }
    },
  };
}
</script>

<?php include __DIR__ . '/_layout/footer.php'; ?>
