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
<button type="button" class="btn-primary h-12 px-5" @click="newTheme()">
  <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M10 4v12M4 10h12" stroke-linecap="round"/></svg>
  Новая тема
</button>
<?php
$topbarRight = ob_get_clean();

include __DIR__ . '/_layout/header.php';
?>

<div x-data="themesPage()" x-init="init()" class="grid grid-cols-1 lg:grid-cols-[320px_1fr] gap-6 lg:gap-8">

  <aside class="card p-5 md:p-6 lg:sticky lg:top-6 self-start max-h-[calc(100vh-160px)] overflow-auto">
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
                  class="w-full text-left rounded-2xl px-3 py-3 transition flex items-start gap-2"
                  :class="t.code === currentCode ? 'bg-ink-900 text-sand-50' : 'hover:bg-sand-100 text-ink-900'">
            <div class="flex-1 min-w-0">
              <div class="text-xs font-mono opacity-70" x-text="t.code"></div>
              <div class="font-semibold text-sm truncate mt-0.5" x-text="t.name"></div>
            </div>
            <span class="text-[10px] font-bold uppercase rounded-full px-2 h-5 grid place-items-center"
                  :class="t.is_active
                    ? (t.code === currentCode ? 'bg-sun-400 text-ink-900' : 'bg-emerald-100 text-emerald-800')
                    : (t.code === currentCode ? 'bg-white/15 text-sand-300' : 'bg-sand-200 text-ink-500')"
                  x-text="t.is_active ? 'on' : 'off'"></span>
          </button>
        </li>
      </template>
    </ul>
  </aside>

  <section class="card p-6 md:p-8 min-h-[420px]">
    <template x-if="!editor">
      <div class="h-full grid place-items-center text-center text-ink-300 py-20">
        <div>
          <div class="text-6xl mb-4">🎨</div>
          <p class="text-ink-500">Выбери тему слева или создай новую.</p>
        </div>
      </div>
    </template>

    <template x-if="editor">
      <div>
        <div class="grid grid-cols-1 md:grid-cols-[200px_1fr_140px] gap-4 mb-6">
          <div>
            <label class="label">Код</label>
            <input type="text" class="input font-mono" x-model="editor.code" :disabled="!editor.isNew" placeholder="my_theme">
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

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
          <div>
            <div class="flex items-center justify-between mb-2">
              <label class="label !mb-0">Токены (JSON)</label>
              <button type="button" class="btn-ghost h-8 px-3 text-xs" @click="formatJson()">Форматировать</button>
            </div>
            <textarea class="textarea font-mono text-xs" rows="20"
                      x-model="editor.tokensRaw" @input.debounce.300ms="renderPreview()" spellcheck="false"></textarea>
            <p class="text-xs mt-1" :class="editor.jsonError ? 'text-ember-500' : 'text-ink-300'"
               x-text="editor.jsonError || 'Структура: { color:{…}, type:{…}, radius:{…} }'"></p>
          </div>

          <div>
            <label class="label">Превью палитры</label>
            <div class="swatch-grid">
              <template x-for="(val, key) in previewColors" :key="key">
                <div class="swatch" :style="`background:${val}`">
                  <span class="swatch-label" x-text="key"></span>
                </div>
              </template>
              <template x-if="Object.keys(previewColors).length === 0">
                <div style="grid-column: 1 / -1" class="py-8 text-center text-ink-300 text-sm">Нет цветов в JSON</div>
              </template>
            </div>

            <template x-if="previewType.length > 0 || previewRadius.length > 0">
              <div class="mt-5 space-y-3 text-sm">
                <template x-if="previewType.length > 0">
                  <div>
                    <div class="label">Шрифты</div>
                    <ul class="space-y-1">
                      <template x-for="row in previewType" :key="row.k">
                        <li class="flex items-baseline gap-3">
                          <span class="text-xs text-ink-500 font-mono w-32 shrink-0" x-text="row.k"></span>
                          <span class="text-ink-900 truncate" x-text="row.v"></span>
                        </li>
                      </template>
                    </ul>
                  </div>
                </template>
                <template x-if="previewRadius.length > 0">
                  <div>
                    <div class="label">Радиусы</div>
                    <div class="flex flex-wrap gap-3">
                      <template x-for="row in previewRadius" :key="row.k">
                        <div class="flex flex-col items-center gap-1">
                          <div class="bg-ink-900" :style="`width:48px;height:48px;border-radius:${row.v}`"></div>
                          <span class="text-[10px] font-mono text-ink-500" x-text="row.k+': '+row.v"></span>
                        </div>
                      </template>
                    </div>
                  </div>
                </template>
              </div>
            </template>
          </div>
        </div>

        <div class="divider"></div>

        <div class="flex flex-wrap items-center gap-2">
          <button type="button" class="btn-primary" @click="saveTheme()" :disabled="saving">
            <span x-show="!saving">Сохранить</span>
            <span x-show="saving" class="flex items-center gap-2"><span class="spinner"></span>Сохраняю…</span>
          </button>
          <template x-if="!editor.isNew">
            <button type="button" class="btn-danger" @click="deleteTheme()">Удалить</button>
          </template>
          <button type="button" class="btn-soft" @click="renderPreview()">Обновить превью</button>
          <a class="btn-soft" :href="`/public/article-demo.php?theme=${encodeURIComponent(editor.code)}`" target="_blank" rel="noopener">
            Открыть demo
          </a>
        </div>
      </div>
    </template>
  </section>
</div>

<script>
function themesPage() {
  return {
    themes: [],
    currentCode: null,
    editor: null,
    saving: false,
    previewColors: {},
    previewType: [],
    previewRadius: [],

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
      } catch (_) { /* api handles toast */ }
    },

    newTheme() {
      this.currentCode = null;
      this.openEditor({
        code: '',
        name: '',
        is_active: 1,
        tokens: {
          color:  { accent:'#2563EB', text:'#0f172a', surface:'#ffffff', border:'#e2e8f0', bg:'#ffffff',
                    danger:'#ef4444', success:'#16a34a', warn:'#f59e0b',
                    'chart-1':'#2563EB','chart-2':'#0D9488','chart-3':'#8B5CF6','chart-4':'#F59E0B',
                    'chart-5':'#EF4444','chart-6':'#16A34A','chart-7':'#EC4899','chart-8':'#06B6D4' },
          type:   { 'font-text':'"Onest", sans-serif', 'font-heading':'"Geologica", sans-serif' },
          radius: { sm:'6px', md:'12px', lg:'16px' },
        },
      }, true);
    },

    openEditor(t, isNew) {
      this.editor = {
        isNew,
        code: t.code || '',
        name: t.name || '',
        is_active: t.is_active ? 1 : 0,
        tokensRaw: JSON.stringify(t.tokens || {}, null, 2),
        jsonError: '',
      };
      this.renderPreview();
    },

    formatJson() {
      try {
        const obj = JSON.parse(this.editor.tokensRaw);
        this.editor.tokensRaw = JSON.stringify(obj, null, 2);
        this.editor.jsonError = '';
        this.renderPreview();
      } catch (e) { this.editor.jsonError = 'JSON некорректен: ' + e.message; }
    },

    renderPreview() {
      if (!this.editor) return;
      let parsed;
      try { parsed = JSON.parse(this.editor.tokensRaw); this.editor.jsonError = ''; }
      catch (e) { this.editor.jsonError = 'JSON некорректен: ' + e.message; return; }

      this.previewColors = parsed.color || {};
      this.previewType   = Object.entries(parsed.type   || {}).map(([k, v]) => ({ k, v }));
      this.previewRadius = Object.entries(parsed.radius || {}).map(([k, v]) => ({ k, v }));
    },

    async saveTheme() {
      const e = this.editor; if (!e) return;
      const code = e.code.trim(), name = e.name.trim();
      if (!code) { SEO.toast('code обязателен', 'err'); return; }
      if (!name) { SEO.toast('Название обязательно', 'err'); return; }

      let tokens;
      try { tokens = JSON.parse(e.tokensRaw); }
      catch (err) { SEO.toast('JSON некорректен: ' + err.message, 'err'); return; }

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
      } catch (_) { /* api handles toast */ }
    },
  };
}
</script>

<?php include __DIR__ . '/_layout/footer.php'; ?>
