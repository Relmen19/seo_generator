<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
requireAuth();

require_once __DIR__ . '/../config.php';

$pageTitle      = 'Темы оформления — SEO admin';
$activeNav      = 'themes';
$pageHeading    = 'Темы оформления';
$pageSubheading = 'Палитры, шрифты и радиусы для статей';

ob_start();
?>
<button type="button" class="btn-primary" style="height:48px;padding:0 20px"
        onclick="window.dispatchEvent(new CustomEvent('seo:new-theme'))">
  <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M10 4v12M4 10h12" stroke-linecap="round"/></svg>
  Новая тема
</button>
<?php
$topbarRight = ob_get_clean();

include __DIR__ . '/_layout/header.php';
?>

<div x-data="themesPage()" x-init="init()"
     @seo:new-theme.window="newTheme()"
     class="grid grid-cols-1 lg:grid-cols-[300px_1fr] gap-6 lg:gap-8">

  <aside class="card lg:sticky lg:top-6 self-start max-h-[calc(100vh-160px)] overflow-auto" style="padding:20px">
    <div class="flex items-center justify-between mb-3">
      <h2 class="text-xs font-semibold uppercase tracking-wide text-ink-500">Все темы</h2>
      <span class="badge-soft" x-text="themes.length"></span>
    </div>

    <template x-if="themes.length === 0">
      <div class="text-ink-300 text-sm py-6 text-center">Пока пусто</div>
    </template>

    <ul class="space-y-1">
      <template x-for="t in themes" :key="t.code">
        <li>
          <button type="button" @click="selectTheme(t.code)"
                  class="theme-pick"
                  :class="t.code === currentCode ? 'theme-pick-active' : ''">
            <div class="min-w-0 flex-1">
              <div class="theme-pick-code" x-text="t.code"></div>
              <div class="theme-pick-name" x-text="t.name"></div>
            </div>
            <span :class="t.is_active ? 'theme-pick-on' : 'theme-pick-off'" x-text="t.is_active ? 'on' : 'off'"></span>
          </button>
        </li>
      </template>
    </ul>
  </aside>

  <section class="min-h-[420px]">
    <template x-if="!editor">
      <div class="card grid place-items-center text-center text-ink-300" style="padding:80px 20px">
        <div>
          <div class="text-6xl mb-4">🎨</div>
          <p class="text-ink-500">Выбери тему слева или создай новую.</p>
          <button type="button" class="btn-primary mt-5" @click="newTheme()">+ Новая тема</button>
        </div>
      </div>
    </template>

    <template x-if="editor">
      <div class="space-y-6">

        <!-- meta + view toggle -->
        <div class="card" style="padding:24px">
          <div class="grid grid-cols-1 md:grid-cols-[200px_1fr_140px] gap-4 mb-5">
            <div>
              <label class="label">Код</label>
              <input type="text" class="input" style="font-family:ui-monospace,monospace" x-model="editor.code" :disabled="!editor.isNew" placeholder="my_theme">
            </div>
            <div>
              <label class="label">Название</label>
              <input type="text" class="input" x-model="editor.name" placeholder="My Theme">
            </div>
            <div>
              <label class="label">Статус</label>
              <select class="select" x-model.number="editor.is_active">
                <option value="1">Активна</option>
                <option value="0">Отключена</option>
              </select>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <div class="tabs">
              <button type="button" class="tab" :class="editor.view === 'form' ? 'tab-active' : ''" @click="setView('form')">Форма</button>
              <button type="button" class="tab" :class="editor.view === 'json' ? 'tab-active' : ''" @click="setView('json')">JSON</button>
            </div>
            <span class="text-xs text-ink-500">
              <span x-text="editor.colors.length"></span> цветов ·
              <span x-text="editor.fonts.length"></span> шрифтов ·
              <span x-text="editor.radii.length"></span> радиусов
            </span>
            <span class="ml-auto flex flex-wrap items-center gap-2">
              <button type="button" class="btn-primary" @click="saveTheme()" :disabled="saving">
                <span x-show="!saving">Сохранить</span>
                <span x-show="saving" class="flex items-center gap-2"><span class="spinner"></span>Сохраняю…</span>
              </button>
              <template x-if="!editor.isNew">
                <button type="button" class="btn-danger" @click="deleteTheme()">Удалить</button>
              </template>
              <a class="btn-soft" :href="`/public/article-demo.php?theme=${encodeURIComponent(editor.code)}`" target="_blank" rel="noopener">Demo →</a>
            </span>
          </div>
        </div>

        <!-- editor + preview side by side -->
        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_460px] gap-6">

          <!-- left: form OR json -->
          <div class="space-y-6">

            <!-- FORM view -->
            <template x-if="editor.view === 'form'">
              <div class="space-y-6">

                <!-- Colors -->
                <div class="card" style="padding:24px">
                  <button type="button" class="section-head" @click="editor.expanded.colors = !editor.expanded.colors">
                    <span class="section-chev" :class="editor.expanded.colors ? 'open' : ''">▸</span>
                    <span class="flex-1 text-left">
                      <span class="text-base font-semibold">Цвета</span>
                      <span class="block text-xs text-ink-500 mt-0.5">accent, text, surface, border, bg, danger, success, warn, chart-N…</span>
                    </span>
                    <span class="badge-soft" x-text="editor.colors.length"></span>
                  </button>
                  <div x-show="editor.expanded.colors" x-collapse>
                    <div class="flex justify-end mb-3 mt-3">
                      <button type="button" class="btn-soft" @click="addRow('colors')">+ Цвет</button>
                    </div>
                    <ul class="space-y-2">
                      <template x-for="(row, i) in editor.colors" :key="row._id">
                        <li class="token-row">
                          <input type="color" class="token-color" :value="normalizeColor(row.v)" @input="row.v = $event.target.value">
                          <input type="text" class="input token-key" x-model="row.k" placeholder="accent">
                          <input type="text" class="input token-val" x-model="row.v" placeholder="#2563EB" style="font-family:ui-monospace,monospace">
                          <button type="button" class="btn-icon" @click="removeRow('colors', i)" title="Удалить">
                            <svg width="14" height="14" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l8 8M14 6l-8 8" stroke-linecap="round"/></svg>
                          </button>
                        </li>
                      </template>
                      <template x-if="editor.colors.length === 0">
                        <li class="text-ink-300 text-sm py-2">Нет цветов. Добавь первый.</li>
                      </template>
                    </ul>
                  </div>
                </div>

                <!-- Typography -->
                <div class="card" style="padding:24px">
                  <button type="button" class="section-head" @click="editor.expanded.fonts = !editor.expanded.fonts">
                    <span class="section-chev" :class="editor.expanded.fonts ? 'open' : ''">▸</span>
                    <span class="flex-1 text-left">
                      <span class="text-base font-semibold">Типографика</span>
                      <span class="block text-xs text-ink-500 mt-0.5">font-heading, font-text, font-mono, размеры (size-h1, size-text…)</span>
                    </span>
                    <span class="badge-soft" x-text="editor.fonts.length"></span>
                  </button>
                  <div x-show="editor.expanded.fonts" x-collapse>
                    <div class="flex justify-end mb-3 mt-3">
                      <button type="button" class="btn-soft" @click="addRow('fonts')">+ Параметр</button>
                    </div>
                    <ul class="space-y-2">
                      <template x-for="(row, i) in editor.fonts" :key="row._id">
                        <li class="token-row" x-data="combo()">
                          <span class="token-fontprev" :style="rowFontPreviewStyle(row)">Aa</span>
                          <input type="text" class="input token-key" x-model="row.k" placeholder="font-text">
                          <div class="combo-wrap">
                            <input type="text" class="input token-val" x-model="row.v"
                                   @focus="open=true; q=row.v||''"
                                   @input="q=$event.target.value"
                                   @keydown.escape="open=false"
                                   placeholder='"Onest", sans-serif'
                                   style="font-family:ui-monospace,monospace">
                            <div class="combo-pop" x-show="open" @click.outside="open=false">
                              <template x-for="(p, idx) in filterPresets(fontPresetsFor(row.k), q)" :key="idx">
                                <button type="button" class="combo-opt"
                                        @click="row.v=p; open=false"
                                        :style="`font-family:${p}`"
                                        x-text="p"></button>
                              </template>
                              <template x-if="filterPresets(fontPresetsFor(row.k), q).length === 0">
                                <div class="combo-empty">Нет совпадений</div>
                              </template>
                            </div>
                          </div>
                          <button type="button" class="btn-icon" @click="removeRow('fonts', i)" title="Удалить">
                            <svg width="14" height="14" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l8 8M14 6l-8 8" stroke-linecap="round"/></svg>
                          </button>
                        </li>
                      </template>
                      <template x-if="editor.fonts.length === 0">
                        <li class="text-ink-300 text-sm py-2">Пусто. Добавь шрифт или размер.</li>
                      </template>
                    </ul>
                  </div>
                </div>

                <!-- Radii -->
                <div class="card" style="padding:24px">
                  <button type="button" class="section-head" @click="editor.expanded.radii = !editor.expanded.radii">
                    <span class="section-chev" :class="editor.expanded.radii ? 'open' : ''">▸</span>
                    <span class="flex-1 text-left">
                      <span class="text-base font-semibold">Радиусы</span>
                      <span class="block text-xs text-ink-500 mt-0.5">sm, md, lg, xl — любые CSS-значения (px, rem, %).</span>
                    </span>
                    <span class="badge-soft" x-text="editor.radii.length"></span>
                  </button>
                  <div x-show="editor.expanded.radii" x-collapse>
                    <div class="flex justify-end mb-3 mt-3">
                      <button type="button" class="btn-soft" @click="addRow('radii')">+ Радиус</button>
                    </div>
                    <ul class="space-y-2">
                      <template x-for="(row, i) in editor.radii" :key="row._id">
                        <li class="token-row" x-data="combo()">
                          <span class="token-radprev" :style="`border-radius:${row.v}`"></span>
                          <input type="text" class="input token-key" x-model="row.k" placeholder="md">
                          <div class="combo-wrap">
                            <input type="text" class="input token-val" x-model="row.v"
                                   @focus="open=true; q=row.v||''"
                                   @input="q=$event.target.value"
                                   @keydown.escape="open=false"
                                   placeholder="12px"
                                   style="font-family:ui-monospace,monospace">
                            <div class="combo-pop" x-show="open" @click.outside="open=false">
                              <template x-for="(p, idx) in filterPresets(radiusPresetsFor(row.k), q)" :key="idx">
                                <button type="button" class="combo-opt combo-opt-rad"
                                        @click="row.v=p; open=false">
                                  <span class="combo-rad-box" :style="`border-radius:${p}`"></span>
                                  <span x-text="p"></span>
                                </button>
                              </template>
                              <template x-if="filterPresets(radiusPresetsFor(row.k), q).length === 0">
                                <div class="combo-empty">Нет совпадений</div>
                              </template>
                            </div>
                          </div>
                          <button type="button" class="btn-icon" @click="removeRow('radii', i)" title="Удалить">
                            <svg width="14" height="14" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l8 8M14 6l-8 8" stroke-linecap="round"/></svg>
                          </button>
                        </li>
                      </template>
                      <template x-if="editor.radii.length === 0">
                        <li class="text-ink-300 text-sm py-2">Пусто.</li>
                      </template>
                    </ul>
                  </div>
                </div>
              </div>
            </template>

            <!-- JSON view -->
            <template x-if="editor.view === 'json'">
              <div class="card" style="padding:24px">
                <div class="flex items-center justify-between mb-3">
                  <h3 class="text-base font-semibold">JSON-токены</h3>
                  <div class="flex gap-2">
                    <button type="button" class="btn-soft" @click="formatJson()">Форматировать</button>
                    <button type="button" class="btn-soft" @click="applyJson()">Применить → форма</button>
                  </div>
                </div>
                <textarea class="textarea" rows="22" spellcheck="false"
                          style="font-family:ui-monospace,monospace;font-size:12.5px"
                          x-model="editor.jsonRaw"></textarea>
                <p class="text-xs mt-2" :class="editor.jsonError ? 'text-ember-500' : 'text-ink-300'"
                   x-text="editor.jsonError || 'Структура: { color:{…}, type:{…}, radius:{…} }. «Применить» переносит в форму.'"></p>
              </div>
            </template>
          </div>

          <!-- right: live preview -->
          <aside class="xl:sticky xl:top-6 self-start space-y-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-ink-500">Превью статьи</div>
            <div class="theme-preview" :style="previewRootStyle()">
              <div class="theme-preview-card" :style="previewCardStyle()">
                <div class="theme-preview-tag" :style="previewTagStyle()">CATEGORY</div>
                <h1 class="theme-preview-h1" :style="previewH1Style()">Как вкус меняет восприятие</h1>
                <p class="theme-preview-lead" :style="previewLeadStyle()">Подзаголовок задаёт тон материалу — здесь смотрятся <span :style="`color:${getColor('accent')}`">акцентные слова</span>.</p>
                <p class="theme-preview-body" :style="previewBodyStyle()">Тело статьи использует <code :style="previewCodeStyle()">font-mono</code> для кода и обычный <strong :style="`color:${getColor('text')}`">текст</strong> с цветом <code :style="previewCodeStyle()">color.text</code>. Радиусы применяются к карточкам, кнопкам и плашкам.</p>

                <div class="flex flex-wrap gap-2 mt-3">
                  <button type="button" class="theme-preview-btn" :style="previewBtnPrimaryStyle()">Главная кнопка</button>
                  <button type="button" class="theme-preview-btn" :style="previewBtnGhostStyle()">Вторая</button>
                </div>

                <div class="theme-preview-callout" :style="previewCalloutStyle()">
                  <div class="theme-preview-callout-title" :style="`color:${getColor('warn')}`">Внимание</div>
                  <div :style="previewBodyStyle()">Это блок с цветом <code :style="previewCodeStyle()">warn</code> и фоном поверх <code :style="previewCodeStyle()">surface</code>.</div>
                </div>

                <div class="theme-preview-statuses">
                  <span class="theme-preview-pill" :style="pillStyle('success')">success</span>
                  <span class="theme-preview-pill" :style="pillStyle('warn')">warn</span>
                  <span class="theme-preview-pill" :style="pillStyle('danger')">danger</span>
                </div>
              </div>

              <div class="theme-preview-grid">
                <template x-for="row in editor.colors" :key="'pv-c-'+row._id">
                  <div class="theme-preview-swatch" :style="`background:${row.v}`">
                    <span class="theme-preview-swatch-key" x-text="row.k"></span>
                    <span class="theme-preview-swatch-val" x-text="row.v"></span>
                  </div>
                </template>
              </div>

              <template x-if="editor.radii.length > 0">
                <div class="theme-preview-radii">
                  <template x-for="row in editor.radii" :key="'pv-r-'+row._id">
                    <div class="theme-preview-radius">
                      <div class="theme-preview-radius-box" :style="`border-radius:${row.v};background:${getColor('accent')}`"></div>
                      <div class="theme-preview-radius-label">
                        <span x-text="row.k"></span> · <span class="theme-preview-mono" x-text="row.v"></span>
                      </div>
                    </div>
                  </template>
                </div>
              </template>
            </div>
          </aside>
        </div>
      </div>
    </template>
  </section>
</div>

<style>
/* theme list */
.theme-pick { width:100%; display:flex; align-items:flex-start; gap:.5rem; padding:.625rem .75rem; border-radius:18px; background:transparent; color:var(--ink-900); border:0; font-family:inherit; cursor:pointer; text-align:left; transition:background-color .15s, color .15s; }
.theme-pick:hover { background:var(--sand-100); }
.theme-pick-active { background:var(--ink-900); color:var(--sand-50); }
.theme-pick-code { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:.7rem; opacity:.7; }
.theme-pick-name { font-weight:600; font-size:.875rem; margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.theme-pick-on  { font-size:10px; font-weight:700; padding:0 .5rem; height:1.25rem; border-radius:9999px; display:inline-grid; place-items:center; background:#d1fae5; color:#065f46; text-transform:uppercase; }
.theme-pick-off { font-size:10px; font-weight:700; padding:0 .5rem; height:1.25rem; border-radius:9999px; display:inline-grid; place-items:center; background:var(--sand-200); color:var(--ink-500); text-transform:uppercase; }
.theme-pick-active .theme-pick-on  { background:var(--sun-400); color:var(--ink-900); }
.theme-pick-active .theme-pick-off { background:rgba(255,255,255,.15); color:var(--sand-300); }

/* collapsible section */
.section-head { width:100%; display:flex; align-items:center; gap:.75rem; padding:0; background:transparent; border:0; cursor:pointer; font-family:inherit; color:var(--ink-900); }
.section-chev { display:inline-grid; place-items:center; width:24px; height:24px; border-radius:8px; background:var(--sand-100); color:var(--ink-700); font-size:11px; transition:transform .15s; flex-shrink:0; }
.section-chev.open { transform:rotate(90deg); }

/* token rows */
.token-row { display:grid; grid-template-columns: 36px 160px 1fr 40px; gap:.5rem; align-items:center; }
@media (max-width: 720px) { .token-row { grid-template-columns: 36px 1fr 40px; } .token-row .combo-wrap, .token-row .token-val { grid-column: 1 / -1; } }
.token-color { width:36px; height:36px; padding:0; border:1px solid var(--sand-300); border-radius:12px; background:transparent; cursor:pointer; }
.token-color::-webkit-color-swatch-wrapper { padding:0; }
.token-color::-webkit-color-swatch { border:0; border-radius:10px; }
.token-fontprev { width:36px; height:36px; display:inline-grid; place-items:center; border-radius:12px; background:var(--sand-100); color:var(--ink-900); font-size:18px; font-weight:600; }
.token-radprev { width:36px; height:36px; background:var(--ink-900); display:inline-block; }

/* combobox */
.combo-wrap { position:relative; }
.combo-pop { position:absolute; top:calc(100% + 4px); left:0; right:0; z-index:50; background:var(--sand-50); border:1px solid var(--sand-200); border-radius:14px; box-shadow:var(--shadow-card); padding:4px; max-height:280px; overflow:auto; }
.combo-opt { display:block; width:100%; text-align:left; padding:.5rem .75rem; border:0; border-radius:10px; background:transparent; color:var(--ink-900); font-family:ui-monospace,monospace; font-size:.8125rem; cursor:pointer; }
.combo-opt:hover { background:var(--sand-100); }
.combo-opt-rad { display:flex; align-items:center; gap:.625rem; }
.combo-rad-box { width:22px; height:22px; background:var(--ink-900); flex-shrink:0; }
.combo-empty { padding:.625rem .75rem; font-size:.8125rem; color:var(--ink-300); }

/* live preview */
.theme-preview { border-radius:24px; padding:18px; background:var(--sand-100); display:flex; flex-direction:column; gap:14px; }
.theme-preview-card { padding:22px; box-shadow: 0 1px 2px rgba(0,0,0,.04), 0 8px 24px rgba(0,0,0,.06); }
.theme-preview-tag { display:inline-block; padding:2px 10px; font-size:11px; font-weight:700; letter-spacing:.06em; }
.theme-preview-h1 { font-size:24px; line-height:1.2; margin:8px 0 6px; font-weight:700; }
.theme-preview-lead { font-size:14px; line-height:1.55; margin-bottom:10px; }
.theme-preview-body { font-size:13.5px; line-height:1.6; }
.theme-preview-btn { padding:9px 16px; border:0; font-weight:600; font-size:13px; cursor:pointer; }
.theme-preview-callout { margin-top:14px; padding:12px 14px; }
.theme-preview-callout-title { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; margin-bottom:4px; }
.theme-preview-statuses { display:flex; flex-wrap:wrap; gap:6px; margin-top:14px; }
.theme-preview-pill { font-size:11px; font-weight:700; padding:3px 10px; border-radius:9999px; text-transform:uppercase; letter-spacing:.04em; }
.theme-preview-grid { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:6px; }
.theme-preview-swatch { aspect-ratio:1/1; border-radius:12px; position:relative; box-shadow: inset 0 0 0 1px rgba(0,0,0,.06); padding:6px; display:flex; flex-direction:column; justify-content:flex-end; }
.theme-preview-swatch-key { font-size:10px; font-weight:700; color:#fff; text-shadow:0 1px 2px rgba(0,0,0,.5); font-family:ui-monospace,monospace; }
.theme-preview-swatch-val { font-size:9px; color:rgba(255,255,255,.85); text-shadow:0 1px 2px rgba(0,0,0,.5); font-family:ui-monospace,monospace; }
.theme-preview-radii { display:flex; flex-wrap:wrap; gap:14px; }
.theme-preview-radius { display:flex; flex-direction:column; align-items:center; gap:4px; }
.theme-preview-radius-box { width:46px; height:46px; }
.theme-preview-radius-label { font-size:11px; color:var(--ink-500); }
.theme-preview-mono { font-family:ui-monospace,monospace; }
</style>

<script>
let __tokenRowSeq = 0;
const tokenRow = (k, v) => ({ _id: ++__tokenRowSeq, k, v });

const FONT_PRESETS = {
  'font-heading': ['"Geologica", sans-serif', '"Playfair Display", Georgia, serif', '"Space Grotesk", sans-serif'],
  'font-text':    ['"Onest", sans-serif', '"Inter", sans-serif', '"Manrope", sans-serif'],
  'font-mono':    ['ui-monospace, SFMono-Regular, Menlo, monospace', '"JetBrains Mono", monospace', '"Fira Code", monospace'],
  '_size':        ['14px', '16px', '18px', '20px', '24px', '32px'],
  '_default':     ['"Onest", sans-serif', '"Inter", sans-serif', '"Geologica", sans-serif', '"Playfair Display", serif', 'ui-monospace, monospace'],
};
const RADIUS_PRESETS = {
  'sm':       ['4px', '6px', '8px'],
  'md':       ['10px', '12px', '16px'],
  'lg':       ['16px', '20px', '24px'],
  'xl':       ['24px', '28px', '32px'],
  '_default': ['4px', '8px', '12px', '16px', '20px', '24px'],
};

function combo() {
  return { open: false, q: '',
    filterPresets(list, q) {
      if (!q) return list;
      const ql = q.toLowerCase();
      return list.filter(p => p.toLowerCase().includes(ql));
    },
    fontPresetsFor(key) {
      const k = (key || '').toLowerCase().trim();
      if (FONT_PRESETS[k]) return FONT_PRESETS[k];
      if (k.startsWith('size')) return FONT_PRESETS._size;
      return FONT_PRESETS._default;
    },
    radiusPresetsFor(key) {
      const k = (key || '').toLowerCase().trim();
      return RADIUS_PRESETS[k] || RADIUS_PRESETS._default;
    },
  };
}

function themesPage() {
  return {
    themes: [],
    currentCode: null,
    editor: null,
    saving: false,

    async init() { await this.loadThemes(); },

    async loadThemes() {
      try { this.themes = (await SEO.api('themes')) || []; }
      catch (_) { this.themes = []; }
    },

    async selectTheme(code) {
      this.currentCode = code;
      try {
        const t = await SEO.api('themes/' + encodeURIComponent(code));
        this.openEditor(t, false);
      } catch (_) {}
    },

    newTheme() {
      this.currentCode = null;
      this.openEditor({
        code: '',
        name: '',
        is_active: 1,
        tokens: {
          color:  { accent:'#2563EB', text:'#0f172a', surface:'#ffffff', border:'#e2e8f0', bg:'#f8fafc',
                    danger:'#ef4444', success:'#16a34a', warn:'#f59e0b' },
          type:   { 'font-heading':'"Geologica", sans-serif', 'font-text':'"Onest", sans-serif', 'font-mono':'ui-monospace, monospace' },
          radius: { sm:'6px', md:'12px', lg:'20px' },
        },
      }, true);
    },

    openEditor(t, isNew) {
      const tokens = t.tokens || {};
      this.editor = {
        isNew,
        code: t.code || '',
        name: t.name || '',
        is_active: t.is_active ? 1 : 0,
        view: 'form',
        expanded: { colors: true, fonts: true, radii: true },
        colors: Object.entries(tokens.color  || {}).map(([k, v]) => tokenRow(k, v)),
        fonts:  Object.entries(tokens.type   || {}).map(([k, v]) => tokenRow(k, v)),
        radii:  Object.entries(tokens.radius || {}).map(([k, v]) => tokenRow(k, v)),
        jsonRaw: '',
        jsonError: '',
      };
      this.editor.jsonRaw = JSON.stringify(this.collectTokens(), null, 2);
    },

    addRow(group) {
      const defaults = { colors: ['', '#000000'], fonts: ['', '"Inter", sans-serif'], radii: ['', '8px'] };
      const [k, v] = defaults[group];
      this.editor[group].push(tokenRow(k, v));
    },
    removeRow(group, i) { this.editor[group].splice(i, 1); },

    setView(v) {
      if (v === this.editor.view) return;
      if (v === 'json') {
        this.editor.jsonRaw = JSON.stringify(this.collectTokens(), null, 2);
        this.editor.jsonError = '';
      } else {
        if (!this.applyJson(false)) return;
      }
      this.editor.view = v;
    },

    formatJson() {
      try {
        const obj = JSON.parse(this.editor.jsonRaw);
        this.editor.jsonRaw = JSON.stringify(obj, null, 2);
        this.editor.jsonError = '';
      } catch (e) { this.editor.jsonError = 'JSON некорректен: ' + e.message; }
    },

    applyJson(toast = true) {
      try {
        const obj = JSON.parse(this.editor.jsonRaw);
        this.editor.colors = Object.entries(obj.color  || {}).map(([k, v]) => tokenRow(k, v));
        this.editor.fonts  = Object.entries(obj.type   || {}).map(([k, v]) => tokenRow(k, v));
        this.editor.radii  = Object.entries(obj.radius || {}).map(([k, v]) => tokenRow(k, v));
        this.editor.jsonError = '';
        if (toast) SEO.toast('Применено', 'ok');
        return true;
      } catch (e) {
        this.editor.jsonError = 'JSON некорректен: ' + e.message;
        if (toast) SEO.toast(this.editor.jsonError, 'err');
        return false;
      }
    },

    collectTokens() {
      const toObj = (arr) => Object.fromEntries(arr.filter(r => r.k && String(r.k).trim() !== '').map(r => [r.k.trim(), r.v]));
      return { color: toObj(this.editor.colors), type: toObj(this.editor.fonts), radius: toObj(this.editor.radii) };
    },

    getColor(k, fb)  { const r = this.editor.colors.find(x => x.k === k); return (r && r.v) || fb || '#000000'; },
    getFont(k, fb)   { const r = this.editor.fonts.find(x => x.k === k); return (r && r.v) || fb || 'system-ui, sans-serif'; },
    getRadius(k, fb) { const r = this.editor.radii.find(x => x.k === k); return (r && r.v) || fb || '8px'; },

    normalizeColor(v) { const m = String(v || '').match(/^#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/); return m ? v : '#000000'; },

    rowFontPreviewStyle(row) {
      const v = row.v || '';
      const isFont = /font|serif|sans|mono/i.test(row.k) || /["']/.test(v);
      return isFont ? `font-family:${v}` : '';
    },

    previewRootStyle() { return `background:${this.getColor('bg', '#f8fafc')}; color:${this.getColor('text', '#0f172a')}; font-family:${this.getFont('font-text', '"Onest",sans-serif')}`; },
    previewCardStyle() { return `background:${this.getColor('surface', '#fff')}; color:${this.getColor('text','#0f172a')}; border:1px solid ${this.getColor('border','#e2e8f0')}; border-radius:${this.getRadius('lg','20px')}`; },
    previewTagStyle()  { return `background:${this.getColor('accent','#2563EB')}33; color:${this.getColor('accent','#2563EB')}; border-radius:${this.getRadius('sm','6px')}`; },
    previewH1Style()   { return `font-family:${this.getFont('font-heading','"Geologica",sans-serif')}; color:${this.getColor('text','#0f172a')}`; },
    previewLeadStyle() { return `color:${this.getColor('text','#0f172a')}; opacity:.8`; },
    previewBodyStyle() { return `color:${this.getColor('text','#0f172a')}; opacity:.85`; },
    previewCodeStyle() { return `font-family:${this.getFont('font-mono','ui-monospace,monospace')}; background:${this.getColor('bg','#f1f5f9')}; padding:1px 6px; border-radius:${this.getRadius('sm','6px')}; font-size:.85em`; },
    previewBtnPrimaryStyle() { return `background:${this.getColor('accent','#2563EB')}; color:#fff; border-radius:${this.getRadius('md','12px')}; font-family:${this.getFont('font-text','inherit')}`; },
    previewBtnGhostStyle()   { return `background:transparent; color:${this.getColor('accent','#2563EB')}; box-shadow: inset 0 0 0 1px ${this.getColor('border','#e2e8f0')}; border-radius:${this.getRadius('md','12px')}; font-family:${this.getFont('font-text','inherit')}`; },
    previewCalloutStyle() { return `background:${this.getColor('bg','#f8fafc')}; border-left:3px solid ${this.getColor('warn','#f59e0b')}; border-radius:${this.getRadius('md','12px')}`; },
    pillStyle(key) { const c = this.getColor(key, '#888'); return `background:${c}22; color:${c}`; },

    async saveTheme() {
      const e = this.editor; if (!e) return;
      if (e.view === 'json' && !this.applyJson(false)) { SEO.toast('Сначала исправь JSON', 'err'); return; }
      const code = e.code.trim(), name = e.name.trim();
      if (!code) { SEO.toast('code обязателен', 'err'); return; }
      if (!name) { SEO.toast('Название обязательно', 'err'); return; }

      const tokens = this.collectTokens();
      this.saving = true;
      try {
        const body = { code, name, tokens, is_active: !!e.is_active };
        const data = e.isNew
          ? await SEO.api('themes', { method:'POST', body })
          : await SEO.api('themes/' + encodeURIComponent(code), { method:'PUT', body });
        SEO.toast('Сохранено', 'ok');
        this.currentCode = data.code;
        await this.loadThemes();
        await this.selectTheme(this.currentCode);
      } finally { this.saving = false; }
    },

    async deleteTheme() {
      if (!this.editor || this.editor.isNew) return;
      const code = this.editor.code;
      if (!confirm('Удалить тему ' + code + '?')) return;
      try {
        await SEO.api('themes/' + encodeURIComponent(code), { method:'DELETE' });
        SEO.toast('Удалено', 'ok');
        this.currentCode = null;
        this.editor = null;
        await this.loadThemes();
      } catch (_) {}
    },
  };
}
</script>

<?php include __DIR__ . '/_layout/footer.php'; ?>
