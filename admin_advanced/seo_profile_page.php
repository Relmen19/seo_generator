<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
requireAuth();

require_once __DIR__ . '/../config.php';

$pageTitle      = 'Профили — SEO admin';
$activeNav      = 'profile';
$pageHeading    = 'Профили';
$pageSubheading = 'Изолированные рабочие пространства проектов';

ob_start();
?>
<button type="button" class="btn-primary" style="height:48px;padding:0 20px"
        onclick="window.dispatchEvent(new CustomEvent('seo:new-profile'))">
  <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M10 4v12M4 10h12" stroke-linecap="round"/></svg>
  Новый профиль
</button>
<?php
$topbarRight = ob_get_clean();

include __DIR__ . '/_layout/header.php';
?>

<div x-data="profilePage()" x-init="init()"
     @seo:new-profile.window="openWizard()"
     class="space-y-6 md:space-y-8">

  <!-- ========== LIST VIEW ========== -->
  <template x-if="view === 'list'">
    <div class="space-y-5 md:space-y-6">
      <template x-if="loading">
        <div class="card p-10 text-center text-ink-300"><span class="spinner"></span> Загрузка…</div>
      </template>

      <template x-if="!loading && profiles.length === 0">
        <div class="card p-10 md:p-16 text-center">
          <div class="text-6xl mb-4">📁</div>
          <h2 class="text-lg font-semibold mb-2">Пока нет профилей</h2>
          <p class="text-ink-500 mb-6">Создайте первый профиль, чтобы начать работу с проектом.</p>
          <button type="button" class="btn-primary" @click="openWizard()">+ Создать профиль</button>
        </div>
      </template>

      <template x-if="!loading && profiles.length > 0">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 md:gap-6">
          <template x-for="p in profiles" :key="p.id">
            <button type="button" class="card text-left hover:shadow-lg transition-shadow"
                    style="padding:22px"
                    @click="openWorkspace(p.id)">
              <div class="flex items-start gap-4 mb-3">
                <div x-html="SEO.profile.iconHtml(p, 48)"></div>
                <div class="flex-1 min-w-0">
                  <div class="font-semibold text-base truncate" x-text="p.name"></div>
                  <div class="text-xs text-ink-500 truncate mt-0.5">
                    <span x-text="p.slug"></span><template x-if="p.domain"><span> · <span x-text="p.domain"></span></span></template>
                  </div>
                </div>
                <span :class="p.is_active ? 'badge-ok' : 'badge-err'" x-text="p.is_active ? 'Активен' : 'Неактивен'"></span>
              </div>
              <template x-if="p.description">
                <div class="text-sm text-ink-700 line-clamp-2 mb-3" x-text="p.description"></div>
              </template>
              <div class="flex flex-wrap gap-3 text-xs text-ink-500">
                <template x-if="p.niche"><span>Ниша: <b class="text-ink-900" x-text="p.niche"></b></span></template>
                <span>Тон: <b class="text-ink-900" x-text="toneLabels[p.tone] || p.tone || '—'"></b></span>
                <span>Язык: <b class="text-ink-900" x-text="langLabels[p.language] || p.language || '—'"></b></span>
              </div>
            </button>
          </template>
        </div>
      </template>
    </div>
  </template>

  <!-- ========== WORKSPACE VIEW ========== -->
  <template x-if="view === 'workspace' && current">
    <div class="space-y-5 md:space-y-6">

      <!-- Workspace header -->
      <div class="card flex flex-wrap items-center gap-4" style="padding:18px 22px">
        <button type="button" class="btn-icon" @click="goToList()" title="К списку">
          <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5l-5 5 5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <div x-html="SEO.profile.iconHtml(current, 56)"></div>
        <div class="flex-1 min-w-0">
          <div class="text-lg font-semibold truncate" x-text="current.name"></div>
          <div class="text-xs text-ink-500 truncate mt-0.5">
            <span x-text="current.slug"></span><template x-if="current.domain"><span> · <span x-text="current.domain"></span></span></template><template x-if="current.niche"><span> · <span x-text="current.niche"></span></span></template>
          </div>
        </div>
        <span :class="current.is_active ? 'badge-ok' : 'badge-err'" x-text="current.is_active ? 'Активен' : 'Неактивен'"></span>
        <button type="button" class="btn-danger" @click="deleteCurrent()">Удалить</button>
      </div>

      <!-- Tabs -->
      <div class="overflow-x-auto -mx-1 px-1">
        <div class="tabs">
          <template x-for="t in tabs" :key="t.key">
            <button type="button" class="tab" :class="tab === t.key ? 'tab-active' : ''" @click="switchTab(t.key)" x-text="t.label"></button>
          </template>
        </div>
      </div>

      <!-- ===== Overview ===== -->
      <template x-if="tab === 'overview'">
        <div class="space-y-5 md:space-y-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <a href="/admin_advanced/seo_page.php" class="card flex items-center gap-4 hover:shadow-lg transition-shadow" style="padding:22px">
              <span class="grid place-items-center w-12 h-12 rounded-2xl bg-sun-300 text-ink-900 text-xl">✎</span>
              <div class="flex-1 min-w-0">
                <div class="font-semibold">SEO &amp; Контент</div>
                <div class="text-xs text-ink-500 mt-0.5">Статьи, каталоги, шаблоны, публикация</div>
              </div>
              <span class="text-ink-300">→</span>
            </a>
            <a href="/admin_advanced/seo_clustering_page.php" class="card flex items-center gap-4 hover:shadow-lg transition-shadow" style="padding:22px">
              <span class="grid place-items-center w-12 h-12 rounded-2xl bg-sand-200 text-ink-900 text-xl">🔍</span>
              <div class="flex-1 min-w-0">
                <div class="font-semibold">Семантика</div>
                <div class="text-xs text-ink-500 mt-0.5">Ключевые слова, кластеризация, интенты</div>
              </div>
              <span class="text-ink-300">→</span>
            </a>
          </div>

          <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <template x-for="s in statsList" :key="s[0]">
              <div class="card text-center" style="padding:16px">
                <div class="text-2xl font-bold" x-text="s[1]"></div>
                <div class="text-[11px] uppercase tracking-wide text-ink-500 mt-1" x-text="s[0]"></div>
              </div>
            </template>
          </div>

          <div class="card p-6 md:p-8">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-ink-500 mb-3">Описание проекта</h3>
            <div class="text-sm text-ink-700 leading-relaxed whitespace-pre-wrap" x-text="current.description || current.niche || 'Описание не задано'"></div>
          </div>
        </div>
      </template>

      <!-- ===== Settings ===== -->
      <template x-if="tab === 'settings'">
        <div class="space-y-5 md:space-y-6">
          <div class="card p-6 md:p-8">
            <h3 class="text-base font-semibold mb-4">Основные данные</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div><label class="label">Название</label><input type="text" class="input" x-model="settings.name"></div>
              <div><label class="label">Slug</label><input type="text" class="input" x-model="settings.slug"></div>
              <div><label class="label">Домен</label><input type="text" class="input" x-model="settings.domain" placeholder="example.com"></div>
              <div><label class="label">Ниша</label><input type="text" class="input" x-model="settings.niche" placeholder="Медицина, e-commerce…"></div>
              <div><label class="label">Бренд</label><input type="text" class="input" x-model="settings.brand_name"></div>
              <div><label class="label">Язык</label>
                <select class="select" x-model="settings.language">
                  <option value="ru">Русский</option><option value="en">English</option><option value="uk">Українська</option>
                </select>
              </div>
              <div><label class="label">Тон</label>
                <select class="select" x-model="settings.tone">
                  <option value="professional">Профессиональный</option>
                  <option value="friendly">Дружелюбный</option>
                  <option value="academic">Академический</option>
                  <option value="casual">Разговорный</option>
                  <option value="persuasive">Убеждающий</option>
                </select>
              </div>
              <div><label class="label">Статус</label>
                <select class="select" x-model.number="settings.is_active">
                  <option value="1">Активен</option><option value="0">Неактивен</option>
                </select>
              </div>
              <div class="md:col-span-2"><label class="label">Описание проекта</label><textarea class="textarea" rows="3" x-model="settings.description"></textarea></div>
            </div>
          </div>

          <div class="card p-6 md:p-8">
            <h3 class="text-base font-semibold mb-4">GPT-настройки</h3>
            <div class="space-y-4">
              <div><label class="label">GPT Персона (системный промпт)</label><textarea class="textarea" rows="4" x-model="settings.gpt_persona" placeholder="Ты — профессиональный SEO-копирайтер…"></textarea></div>
              <div><label class="label">Доп. правила генерации</label><textarea class="textarea" rows="3" x-model="settings.gpt_rules"></textarea></div>
            </div>
          </div>

          <div class="card p-6 md:p-8">
            <h3 class="text-base font-semibold">Стратегия research</h3>
            <p class="text-xs text-ink-500 mt-1 mb-4">Как собирается фактура для статьи. Split дешевле и меньше галлюцинаций. Split + Search требует BRAVE_SEARCH_API_KEY.</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <template x-for="opt in researchStrategies" :key="opt.value">
                <button type="button"
                        class="fmt-pick"
                        :class="settings.research_strategy === opt.value ? 'fmt-pick-on' : ''"
                        @click="settings.research_strategy = opt.value">
                  <span class="fmt-pick-icon" x-text="opt.icon"></span>
                  <span class="flex-1 min-w-0">
                    <span class="block font-semibold text-sm" x-text="opt.title"></span>
                    <span class="block text-xs text-ink-500 mt-1" x-text="opt.desc"></span>
                  </span>
                </button>
              </template>
            </div>
          </div>

          <div class="flex justify-end">
            <button type="button" class="btn-primary" @click="saveSettings()" :disabled="saving">
              <span x-show="!saving">Сохранить настройки</span>
              <span x-show="saving" class="flex items-center gap-2"><span class="spinner"></span>Сохраняю…</span>
            </button>
          </div>
        </div>
      </template>

      <!-- ===== Branding ===== -->
      <template x-if="tab === 'branding'">
        <div class="space-y-5 md:space-y-6">
          <div class="card p-6 md:p-8">
            <h3 class="text-base font-semibold mb-4">Иконка профиля</h3>
            <div class="flex flex-wrap gap-6 items-start">
              <label class="block cursor-pointer">
                <div class="w-32 h-32 rounded-3xl overflow-hidden bg-sand-100 border-2 border-dashed border-sand-300 grid place-items-center hover:border-ink-700 transition-colors relative">
                  <template x-if="current.has_icon">
                    <img :src="SEO.profile.iconUrl(current)" alt="" class="w-full h-full object-cover">
                  </template>
                  <template x-if="!current.has_icon">
                    <span class="text-xs text-ink-500 text-center px-2">Загрузить иконку</span>
                  </template>
                </div>
                <input type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml,image/gif" class="hidden" @change="uploadIcon($event.target)">
              </label>
              <div class="flex-1 min-w-0 space-y-2">
                <p class="text-sm text-ink-700">Загрузите иконку для профиля. Рекомендуемый размер 256×256 px.</p>
                <p class="text-xs text-ink-500">Форматы: PNG, JPEG, WebP, SVG, GIF. Макс. 2 МБ.</p>
                <template x-if="current.has_icon">
                  <button type="button" class="btn-soft mt-2" @click="removeIcon()">Удалить иконку</button>
                </template>
              </div>
            </div>
          </div>

          <div class="card p-6 md:p-8">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
              <div>
                <h3 class="text-base font-semibold">Тема оформления</h3>
                <p class="text-xs text-ink-500 mt-1">Token-based темы. Управление: <a href="/admin_advanced/seo_themes_page.php" class="underline">страница тем</a>.</p>
              </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
              <button type="button"
                      class="theme-pick"
                      :class="!branding.default_theme_code ? 'theme-pick-on' : ''"
                      @click="branding.default_theme_code = ''">
                <div class="tp-stage" style="background:#F4EEE2;color:#171511">
                  <div class="tp-tag" style="background:rgba(23,21,17,.1);color:#171511;border-radius:6px">CATEGORY</div>
                  <div class="tp-card" style="background:#FBF8F3;border:1px solid #DDD3BF;border-radius:14px">
                    <div class="tp-h1">Как вкус меняет восприятие</div>
                    <div class="tp-lead">Подзаголовок задаёт тон — здесь смотрятся <span style="color:#D97706">акцентные слова</span>.</div>
                    <div class="tp-body">Тело статьи использует обычный текст с цветом ink. Радиусы применяются к карточкам, кнопкам и плашкам.</div>
                    <div class="tp-actions">
                      <span class="tp-btn" style="background:#171511;color:#FBF8F3;border-radius:9999px">Главная</span>
                      <span class="tp-btn" style="background:transparent;color:#171511;box-shadow:inset 0 0 0 1px #DDD3BF;border-radius:9999px">Вторая</span>
                    </div>
                    <div class="tp-callout" style="background:rgba(245,200,66,.18);border-color:#D97706;color:#171511">
                      <div class="tp-callout-title" style="color:#D97706">Внимание</div>
                      Блок с цветом warn поверх surface.
                    </div>
                    <div class="tp-pills">
                      <span class="tp-pill" style="background:#d1fae5;color:#065f46">success</span>
                      <span class="tp-pill" style="background:rgba(245,200,66,.3);color:#171511">warn</span>
                      <span class="tp-pill" style="background:#fee2e2;color:#991b1b">danger</span>
                    </div>
                  </div>
                  <div class="tp-swatches">
                    <div class="tp-swatch" style="background:#F4EEE2"></div>
                    <div class="tp-swatch" style="background:#FBF8F3"></div>
                    <div class="tp-swatch" style="background:#171511"></div>
                    <div class="tp-swatch" style="background:#D97706"></div>
                    <div class="tp-swatch" style="background:#10b981"></div>
                    <div class="tp-swatch" style="background:#f59e0b"></div>
                  </div>
                </div>
                <div class="tp-meta">
                  <div>
                    <div class="tp-name">Legacy theme</div>
                    <div class="tp-code">встроенная</div>
                  </div>
                  <span class="tp-badge" x-show="!branding.default_theme_code">Выбрано</span>
                </div>
              </button>
              <template x-for="t in themes" :key="t.code">
                <button type="button"
                        class="theme-pick"
                        :class="branding.default_theme_code === t.code ? 'theme-pick-on' : ''"
                        @click="branding.default_theme_code = t.code">
                  <div class="tp-stage"
                       :style="`background:${themeColor(t,'bg','#fff')};color:${themeColor(t,'text','#0f172a')}`">
                    <div class="tp-tag"
                         :style="`background:${themeColor(t,'accent','#2563EB')}22;color:${themeColor(t,'accent','#2563EB')};border-radius:${themeRadius(t,'sm','6px')}`">CATEGORY</div>
                    <div class="tp-card"
                         :style="`background:${themeColor(t,'surface','#fff')};border:1px solid ${themeColor(t,'border','#e2e8f0')};border-radius:${themeRadius(t,'md','14px')};color:${themeColor(t,'text','#0f172a')}`">
                      <div class="tp-h1">Как вкус меняет восприятие</div>
                      <div class="tp-lead">Подзаголовок задаёт тон — здесь смотрятся <span :style="`color:${themeColor(t,'accent','#2563EB')}`">акцентные слова</span>.</div>
                      <div class="tp-body">Тело статьи использует обычный текст. Радиусы применяются к карточкам, кнопкам и плашкам.</div>
                      <div class="tp-actions">
                        <span class="tp-btn"
                              :style="`background:${themeColor(t,'accent','#2563EB')};color:#fff;border-radius:${themeRadius(t,'md','12px')}`">Главная</span>
                        <span class="tp-btn"
                              :style="`background:transparent;color:${themeColor(t,'accent','#2563EB')};box-shadow:inset 0 0 0 1px ${themeColor(t,'border','#e2e8f0')};border-radius:${themeRadius(t,'md','12px')}`">Вторая</span>
                      </div>
                      <div class="tp-callout"
                           :style="`background:${themeColor(t,'warn','#f59e0b')}1A;border-color:${themeColor(t,'warn','#f59e0b')};border-radius:${themeRadius(t,'sm','6px')}`">
                        <div class="tp-callout-title" :style="`color:${themeColor(t,'warn','#f59e0b')}`">Внимание</div>
                        Блок с цветом warn поверх surface.
                      </div>
                      <div class="tp-pills">
                        <span class="tp-pill" :style="`background:${themeColor(t,'success','#10b981')}22;color:${themeColor(t,'success','#10b981')}`">success</span>
                        <span class="tp-pill" :style="`background:${themeColor(t,'warn','#f59e0b')}22;color:${themeColor(t,'warn','#f59e0b')}`">warn</span>
                        <span class="tp-pill" :style="`background:${themeColor(t,'danger','#ef4444')}22;color:${themeColor(t,'danger','#ef4444')}`">danger</span>
                      </div>
                    </div>
                    <div class="tp-swatches">
                      <div class="tp-swatch" :style="`background:${themeColor(t,'bg','#fff')}`"></div>
                      <div class="tp-swatch" :style="`background:${themeColor(t,'surface','#fff')}`"></div>
                      <div class="tp-swatch" :style="`background:${themeColor(t,'text','#0f172a')}`"></div>
                      <div class="tp-swatch" :style="`background:${themeColor(t,'accent','#2563EB')}`"></div>
                      <div class="tp-swatch" :style="`background:${themeColor(t,'success','#10b981')}`"></div>
                      <div class="tp-swatch" :style="`background:${themeColor(t,'danger','#ef4444')}`"></div>
                    </div>
                  </div>
                  <div class="tp-meta">
                    <div class="min-w-0">
                      <div class="tp-name truncate" x-text="t.name"></div>
                      <div class="tp-code truncate" x-text="t.code"></div>
                    </div>
                    <span class="tp-badge" x-show="branding.default_theme_code === t.code">Выбрано</span>
                  </div>
                </button>
              </template>
            </div>
          </div>

          <div class="flex justify-end">
            <button type="button" class="btn-primary" @click="saveBranding()" :disabled="saving">
              <span x-show="!saving">Сохранить брендинг</span>
              <span x-show="saving" class="flex items-center gap-2"><span class="spinner"></span>Сохраняю…</span>
            </button>
          </div>
        </div>
      </template>

      <!-- ===== Brief (AI wizard) ===== -->
      <template x-if="tab === 'brief'">
        <div class="space-y-5 md:space-y-6">
          <div class="card p-6 md:p-8">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
              <div>
                <h3 class="text-base font-semibold">AI Бриф проекта</h3>
                <p class="text-xs text-ink-500 mt-1">Пошаговый wizard: ниша → аудитория → УТП → конкуренты → голос → правила → пробы.</p>
              </div>
              <div class="flex gap-2">
                <button type="button" class="btn-soft" @click="briefReset()">Сбросить</button>
              </div>
            </div>

            <!-- Progress -->
            <div class="mb-5">
              <div class="h-1.5 rounded-full bg-sand-200 overflow-hidden">
                <div class="h-full bg-ember-500 transition-all"
                     :style="`width:${((briefIdx + 1) / Math.max(1, briefVisibleSteps().length) * 100)}%`"></div>
              </div>
              <div class="flex flex-wrap items-center gap-2 mt-3">
                <template x-for="(s, i) in briefVisibleSteps()" :key="s.key">
                  <button type="button" @click="briefGoto(i)"
                          class="text-[11px] px-2.5 py-1 rounded-full border transition"
                          :class="i === briefIdx ? 'bg-ink-900 text-sand-50 border-ink-900' : (briefIsStepDone(s.key) ? 'bg-sun-300 border-sun-400 text-ink-900' : 'bg-sand-50 border-sand-300 text-ink-500')"
                          x-text="(i+1) + '. ' + s.title"></button>
                </template>
              </div>
            </div>

            <!-- Step header -->
            <div class="flex items-start gap-3 mb-4 pb-4 border-b border-sand-200">
              <div class="w-9 h-9 rounded-full bg-ink-900 text-sand-50 grid place-items-center font-bold text-sm flex-shrink-0"
                   x-text="briefIdx + 1"></div>
              <div class="flex-1 min-w-0">
                <div class="font-semibold" x-text="briefCurStep().title"></div>
                <div class="text-xs text-ink-500 mt-0.5" x-text="briefCurStep().sub"></div>
              </div>
              <div class="text-xs text-ink-300 font-semibold"
                   x-text="(briefIdx+1) + ' / ' + briefVisibleSteps().length"></div>
            </div>

            <!-- Hint + Regen -->
            <div class="mb-4 flex flex-wrap gap-2">
              <input type="text" class="input flex-1 min-w-[260px]"
                     :placeholder="'Уточнение для AI (опц.)'"
                     :value="briefHints[briefCurStep().key] || ''"
                     @input="briefHints[briefCurStep().key] = $event.target.value">
              <button type="button" class="btn-accent" @click="briefRegen()" :disabled="briefRunning">
                <span x-show="!briefRunning">✨ <span x-text="briefHasOptions() ? 'Другие варианты' : 'Сгенерировать варианты'"></span></span>
                <span x-show="briefRunning" class="flex items-center gap-2"><span class="spinner"></span>AI…</span>
              </button>
              <button type="button" class="btn-soft" @click="briefViewMode[briefCurStep().key] = (briefViewMode[briefCurStep().key]==='json'?'form':'json')"
                      x-text="(briefViewMode[briefCurStep().key]==='json' ? 'Форма' : 'JSON')"></button>
            </div>

            <!-- Step body -->
            <div x-show="briefViewMode[briefCurStep().key] === 'json'">
              <textarea class="textarea" rows="14" spellcheck="false"
                        style="font-family:ui-monospace,monospace;font-size:12.5px"
                        x-model="briefStepJsonRaw"></textarea>
              <div class="flex gap-2 mt-2">
                <button type="button" class="btn-soft" @click="briefStepJsonApply()">Применить JSON</button>
                <button type="button" class="btn-soft" @click="briefStepJsonReload()">Перечитать</button>
                <span class="text-xs text-ember-500 self-center" x-text="briefStepJsonError"></span>
              </div>
            </div>

            <div x-show="briefViewMode[briefCurStep().key] !== 'json'" x-html="briefStepHtml()"></div>

            <!-- Footer -->
            <div class="flex items-center justify-between gap-3 mt-6 pt-4 border-t border-sand-200">
              <button type="button" class="btn-soft" @click="briefPrev()" :disabled="briefIdx === 0">← Назад</button>
              <div class="text-xs" :class="briefAutoStatus.kind === 'err' ? 'text-ember-500' : 'text-ink-300'" x-text="briefAutoStatus.msg"></div>
              <button type="button" class="btn-primary" @click="briefNext()"
                      x-text="briefIdx === briefVisibleSteps().length - 1 ? 'Готово ✓' : 'Далее →'"></button>
            </div>
          </div>

          <!-- Full JSON -->
          <div class="card p-6 md:p-8">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
              <h3 class="text-base font-semibold">Итоговый бриф (JSON)</h3>
              <div class="flex gap-2">
                <button type="button" class="btn-soft" @click="briefFullJsonFormat()">Форматировать</button>
                <button type="button" class="btn-soft" @click="briefFullJsonApply()">Применить</button>
                <button type="button" class="btn-soft" @click="briefFullJsonReload()">Перечитать</button>
              </div>
            </div>
            <textarea class="textarea" rows="14" spellcheck="false"
                      style="font-family:ui-monospace,monospace;font-size:12.5px"
                      x-model="briefFullJsonRaw"></textarea>
            <p class="text-xs mt-2" :class="briefFullJsonError ? 'text-ember-500' : 'text-ink-300'"
               x-text="briefFullJsonError || 'Сохраняется в content_brief профиля.'"></p>
          </div>
        </div>
      </template>

      <!-- ===== Templates ===== -->
      <template x-if="tab === 'templates'">
        <div class="space-y-5 md:space-y-6">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-base font-semibold">Шаблоны профиля</h3>
            <div class="flex flex-wrap gap-2">
              <template x-if="hasBriefData()">
                <button type="button" class="btn-primary" @click="openGenModalFromBrief()" title="Использует ниша/ICP/УТП из брифа">
                  ✨ Сгенерировать из брифа
                </button>
              </template>
              <button type="button" class="btn-accent" @click="openGenModal()">+ AI-шаблон</button>
            </div>
          </div>

          <template x-if="hasBriefData() && !templatesLoading">
            <div class="card p-4 flex items-start gap-3" style="background:linear-gradient(180deg,#fffbeb,#fff);border-left:3px solid var(--ember-500)">
              <div class="text-2xl">✨</div>
              <div class="flex-1 text-sm">
                <div class="font-semibold mb-1">Сгенерировать шаблоны на основе брифа</div>
                <div class="text-ink-500 text-xs" x-html="buildBriefSummary() || 'Бриф заполнен — AI учтёт нишу, аудиторию и УТП.'"></div>
              </div>
              <button type="button" class="btn-primary text-sm" @click="openGenModalFromBrief()">Запустить</button>
            </div>
          </template>

          <template x-if="templatesLoading">
            <div class="card p-10 text-center text-ink-300"><span class="spinner"></span> Загрузка…</div>
          </template>

          <template x-if="!templatesLoading && templates.length === 0">
            <div class="card p-10 text-center">
              <div class="text-5xl mb-3">📄</div>
              <h2 class="text-base font-semibold mb-1">Нет шаблонов</h2>
              <p class="text-ink-500 text-sm mb-5">Создайте шаблон через AI — опишите тип статьи, и он подберёт блоки.</p>
              <div class="flex flex-wrap justify-center gap-2">
                <template x-if="hasBriefData()">
                  <button type="button" class="btn-primary" @click="openGenModalFromBrief()">✨ Сгенерировать из брифа</button>
                </template>
                <button type="button" class="btn-accent" @click="openGenModal()">+ AI-шаблон</button>
              </div>
            </div>
          </template>

          <template x-if="!templatesLoading && templates.length > 0">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <template x-for="t in templates" :key="t.id">
                <div class="card hover:shadow-lg transition-shadow" style="padding:18px">
                  <div class="flex items-start justify-between gap-3 mb-2">
                    <div class="min-w-0">
                      <div class="font-semibold truncate" x-text="t.name"></div>
                      <div class="text-xs text-ink-500 truncate mt-0.5">
                        <span x-text="t.slug || ''"></span> · <span x-text="(t.blocks || []).length"></span> блоков
                        <template x-if="!t.is_active"><span class="text-ember-500"> · неактивен</span></template>
                      </div>
                    </div>
                    <button type="button" class="btn-icon" @click="deleteTemplate(t)" title="Удалить">
                      <svg width="14" height="14" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l8 8M14 6l-8 8" stroke-linecap="round"/></svg>
                    </button>
                  </div>
                  <div class="flex flex-wrap gap-1.5 mt-3">
                    <template x-for="(b, i) in (t.blocks || [])" :key="i">
                      <span class="badge-soft text-[11px]" x-text="b.type"></span>
                    </template>
                  </div>
                </div>
              </template>
            </div>
          </template>
        </div>
      </template>

      <!-- ===== Intents ===== -->
      <template x-if="tab === 'intents'">
        <div class="space-y-5 md:space-y-6">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-base font-semibold">Интенты профиля</h3>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="btn-accent" @click="aiGenerateIntents()" :disabled="aiIntentsRunning">
                <span x-show="!aiIntentsRunning">+ AI-интенты</span>
                <span x-show="aiIntentsRunning" class="flex items-center gap-2"><span class="spinner"></span>AI…</span>
              </button>
              <button type="button" class="btn-primary" @click="openIntentEditor(null)">+ Новый интент</button>
            </div>
          </div>

          <template x-if="intentsLoading">
            <div class="card p-10 text-center text-ink-300"><span class="spinner"></span> Загрузка…</div>
          </template>

          <template x-if="!intentsLoading && customIntents.length === 0 && globalIntents.length === 0">
            <div class="card p-10 text-center">
              <div class="text-5xl mb-3">🎯</div>
              <h2 class="text-base font-semibold mb-1">Нет интентов</h2>
              <p class="text-ink-500 text-sm mb-5">Добавьте интенты вручную или сгенерируйте AI.</p>
            </div>
          </template>

          <template x-if="!intentsLoading && customIntents.length > 0">
            <div>
              <div class="text-xs font-semibold uppercase tracking-wide text-ink-500 mb-2">Кастомные интенты профиля (<span x-text="customIntents.length"></span>)</div>
              <div class="space-y-2">
                <template x-for="i in customIntents" :key="i.code">
                  <div class="card flex items-center gap-3" style="padding:14px 18px;border-left:3px solid var(--ink-900)">
                    <span class="w-3 h-3 rounded-full flex-shrink-0" :style="`background:${i.color || '#6366f1'}`"></span>
                    <div class="flex-1 min-w-0">
                      <div class="font-semibold text-sm" x-text="i.label_ru || i.code"></div>
                      <div class="text-xs text-ink-500" style="font-family:ui-monospace,monospace">
                        <span x-text="i.code"></span><template x-if="!i.is_active"><span class="text-ember-500"> · неактивен</span></template>
                      </div>
                      <template x-if="i.description"><div class="text-xs text-ink-700 mt-1 line-clamp-2" x-text="i.description"></div></template>
                    </div>
                    <button type="button" class="btn-icon" @click="openIntentEditor(i)" title="Редактировать">
                      <svg width="14" height="14" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 14l9-9 3 3-9 9H3v-3z" stroke-linejoin="round"/></svg>
                    </button>
                    <button type="button" class="btn-icon" @click="deleteIntent(i)" title="Удалить">
                      <svg width="14" height="14" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l8 8M14 6l-8 8" stroke-linecap="round"/></svg>
                    </button>
                  </div>
                </template>
              </div>
            </div>
          </template>

          <template x-if="!intentsLoading && globalIntents.length > 0">
            <div>
              <div class="text-xs font-semibold uppercase tracking-wide text-ink-500 mb-2 mt-4">Общие интенты (<span x-text="globalIntents.length"></span>)</div>
              <div class="space-y-2">
                <template x-for="i in globalIntents" :key="i.code">
                  <div class="card flex items-center gap-3 opacity-90" style="padding:14px 18px;border-left:3px solid var(--sand-300)">
                    <span class="w-3 h-3 rounded-full flex-shrink-0" :style="`background:${i.color || '#94a3b8'}`"></span>
                    <div class="flex-1 min-w-0">
                      <div class="font-semibold text-sm" x-text="i.label_ru || i.code"></div>
                      <div class="text-xs text-ink-500" style="font-family:ui-monospace,monospace" x-text="i.code"></div>
                    </div>
                    <span class="badge-soft text-[10px]">global</span>
                  </div>
                </template>
              </div>
            </div>
          </template>
        </div>
      </template>

      <!-- ===== Telegram ===== -->
      <template x-if="tab === 'telegram'">
        <div class="space-y-5 md:space-y-6">
          <div class="card p-6 md:p-8">
            <h3 class="text-base font-semibold mb-4">Подключение Telegram-канала</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="label">Bot Token <span class="font-normal text-ink-300">(от @BotFather)</span></label>
                <input type="password" class="input" x-model="telegram.tg_bot_token" placeholder="123456:ABC-DEF…">
              </div>
              <div>
                <label class="label">Channel ID <span class="font-normal text-ink-300">(@username или chat_id)</span></label>
                <input type="text" class="input" x-model="telegram.tg_channel_id" placeholder="@mychannel">
              </div>
            </div>
            <div class="flex flex-wrap gap-3 items-center mt-4">
              <button type="button" class="btn-soft" @click="testTgConnection()" :disabled="tgTesting">
                <span x-show="!tgTesting">Проверить подключение</span>
                <span x-show="tgTesting" class="flex items-center gap-2"><span class="spinner"></span>Проверка…</span>
              </button>
              <span class="text-xs" :class="tgTestStatusClass" x-text="tgTestStatus"></span>
            </div>
            <template x-if="tgChannel.name">
              <div class="flex items-center gap-3 mt-5 p-4 rounded-2xl bg-sand-100">
                <div class="w-12 h-12 rounded-full overflow-hidden bg-sand-200 grid place-items-center font-bold text-ink-900">
                  <template x-if="tgChannel.avatar"><img :src="'data:image/jpeg;base64,'+tgChannel.avatar" class="w-full h-full object-cover"></template>
                  <template x-if="!tgChannel.avatar"><span x-text="(tgChannel.name||'?')[0].toUpperCase()"></span></template>
                </div>
                <div>
                  <div class="font-semibold" x-text="tgChannel.name"></div>
                  <div class="text-xs text-ink-500" x-text="tgChannel.meta"></div>
                </div>
              </div>
            </template>
          </div>

          <div class="card p-6 md:p-8">
            <h3 class="text-base font-semibold mb-4">Формат постов</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <template x-for="opt in [
                { code:'auto',   icon:'⚡', title:'Авто', desc:'Бот сам решит — медиа-группой или серией, по количеству блоков.' },
                { code:'single', icon:'🖼', title:'Один пост', desc:'Все картинки одной медиа-группой, текст в подписи.' },
                { code:'series', icon:'📚', title:'Серия постов', desc:'Каждый блок отдельным сообщением в канале.' },
              ]" :key="opt.code">
                <button type="button"
                        class="fmt-pick"
                        :class="telegram.tg_post_format === opt.code ? 'fmt-pick-on' : ''"
                        @click="telegram.tg_post_format = opt.code">
                  <div class="fmt-pick-icon" x-text="opt.icon"></div>
                  <div class="flex-1 min-w-0">
                    <div class="font-semibold text-sm" x-text="opt.title"></div>
                    <div class="text-xs text-ink-500 mt-1 leading-snug" x-text="opt.desc"></div>
                  </div>
                </button>
              </template>
            </div>
          </div>

          <div class="card p-6 md:p-8">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
              <div>
                <h3 class="text-base font-semibold">Блоки для рендера в изображения</h3>
                <p class="text-xs text-ink-500 mt-1">Кликни блок — он будет конвертирован в PNG для Telegram-постов.</p>
              </div>
              <div class="flex gap-2">
                <button type="button" class="btn-soft" @click="renderAllBlocks(true)">Выбрать все</button>
                <button type="button" class="btn-soft" @click="renderAllBlocks(false)">Снять</button>
                <span class="badge-soft self-center"><span x-text="(telegram.tg_render_blocks || []).length"></span> / <span x-text="blockTypes.length"></span></span>
              </div>
            </div>
            <template x-if="blockTypes.length === 0">
              <div class="text-sm text-ink-500">Загрузка…</div>
            </template>
            <div class="flex flex-wrap gap-2">
              <template x-for="t in blockTypes" :key="t.code">
                <div class="blk-chip"
                     :class="(telegram.tg_render_blocks || []).includes(t.code) ? 'blk-chip-on' : ''"
                     @click="toggleRenderBlock(t.code, !(telegram.tg_render_blocks || []).includes(t.code))">
                  <div class="blk-chip-icon" x-text="(t.display_name || t.code).slice(0,1).toUpperCase()"></div>
                  <div>
                    <div class="text-sm font-semibold leading-tight" x-text="t.display_name || t.code"></div>
                    <div class="text-[10px] opacity-70 leading-tight font-mono" x-text="t.code"></div>
                  </div>
                </div>
              </template>
            </div>
          </div>

          <div class="flex justify-end">
            <button type="button" class="btn-primary" @click="saveTelegram()" :disabled="saving">
              <span x-show="!saving">Сохранить</span>
              <span x-show="saving" class="flex items-center gap-2"><span class="spinner"></span>Сохраняю…</span>
            </button>
          </div>
        </div>
      </template>

      <!-- ===== Tokens ===== -->
      <template x-if="tab === 'tokens'">
        <div class="space-y-5 md:space-y-6">
          <div class="card p-6 md:p-8">
            <div class="flex items-center justify-between gap-3 mb-4">
              <h3 class="text-base font-semibold">Итого по профилю</h3>
              <button type="button" class="btn-soft" @click="loadTokens(true)">↻ Обновить</button>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
              <template x-for="(box, i) in tokenTotalsBoxes" :key="i">
                <div class="card-tinted text-center" style="padding:14px">
                  <div class="text-xl font-bold" :class="box.cls || ''" x-text="box.value"></div>
                  <div class="text-[11px] uppercase tracking-wide text-ink-500 mt-1" x-text="box.label"></div>
                </div>
              </template>
            </div>
          </div>

          <div class="card p-6 md:p-8">
            <h3 class="text-base font-semibold mb-2">По категориям</h3>
            <p class="text-xs text-ink-500 mb-4">Расходы разделены: создание шаблонов и ревью учитываются раздельно.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <template x-for="cat in tokenCategoriesList" :key="cat.key">
                <div class="card-tinted" style="padding:14px;border-left:3px solid var(--ink-900)">
                  <div class="flex items-start gap-3">
                    <div class="text-2xl" x-text="cat.icon"></div>
                    <div class="flex-1 min-w-0">
                      <div class="text-sm font-semibold" x-text="cat.label"></div>
                      <div class="text-xs text-ink-700 mt-1">
                        <span x-text="SEO.fmtNum(cat.calls)"></span> вызовов · <span x-text="SEO.fmtNum(cat.total_tokens)"></span> ток. · <b x-text="formatCost(cat.cost_usd)"></b>
                      </div>
                      <div class="text-[11px] text-ink-500 mt-0.5">
                        prompt <span x-text="SEO.fmtNum(cat.prompt_tokens)"></span> / completion <span x-text="SEO.fmtNum(cat.completion_tokens)"></span>
                      </div>
                    </div>
                  </div>
                </div>
              </template>
            </div>
          </div>

          <div class="card p-6 md:p-8">
            <h3 class="text-base font-semibold mb-3">Последние вызовы</h3>
            <template x-if="tokenRecent.length === 0">
              <div class="text-sm text-ink-500">Пока нет вызовов.</div>
            </template>
            <template x-if="tokenRecent.length > 0">
              <div class="overflow-x-auto">
                <table class="tbl">
                  <thead><tr>
                    <th>Когда</th><th>Категория</th><th>Операция</th><th>Модель</th>
                    <th class="!text-right">Prompt</th><th class="!text-right">Completion</th><th class="!text-right">Всего</th><th class="!text-right">USD</th>
                  </tr></thead>
                  <tbody>
                    <template x-for="(r, i) in tokenRecent" :key="i">
                      <tr>
                        <td class="text-xs text-ink-500" x-text="String(r.created_at||'').replace('T',' ').slice(0,19)"></td>
                        <td class="font-semibold" x-text="TOK_CAT_LABELS[r.category] || r.category"></td>
                        <td x-text="r.operation || ''"></td>
                        <td class="text-ink-500" x-text="r.model || ''"></td>
                        <td class="text-right" x-text="SEO.fmtNum(r.prompt_tokens)"></td>
                        <td class="text-right" x-text="SEO.fmtNum(r.completion_tokens)"></td>
                        <td class="text-right font-semibold" x-text="SEO.fmtNum(r.total_tokens)"></td>
                        <td class="text-right" x-text="formatCost(r.cost_usd)"></td>
                      </tr>
                    </template>
                  </tbody>
                </table>
              </div>
            </template>
          </div>
        </div>
      </template>

    </div>
  </template>

  <!-- ========== WIZARD MODAL ========== -->
  <div x-show="wizard.open" x-cloak class="modal-backdrop" @click.self="closeWizard()">
    <div class="modal-card p-0" style="max-width:760px">
      <div class="p-6 md:p-8 border-b border-sand-200">
        <h2 class="text-lg font-semibold">Создание нового профиля</h2>
        <p class="text-xs text-ink-500 mt-1">Шаг <span x-text="wizard.step"></span> из 3</p>

        <!-- Progress dots -->
        <div class="flex items-center gap-2 mt-4">
          <template x-for="i in [1,2,3]" :key="i">
            <div class="flex items-center gap-2 flex-1">
              <span class="w-7 h-7 rounded-full grid place-items-center text-xs font-bold flex-shrink-0"
                    :class="i < wizard.step ? 'bg-sun-400 text-ink-900' : (i === wizard.step ? 'bg-ink-900 text-sand-50' : 'bg-sand-200 text-ink-500')"
                    x-text="i < wizard.step ? '✓' : i"></span>
              <span class="text-xs" :class="i === wizard.step ? 'text-ink-900 font-semibold' : 'text-ink-500'"
                    x-text="['Описание','Брендинг','Готово'][i-1]"></span>
              <template x-if="i < 3">
                <span class="flex-1 h-0.5 rounded" :class="i < wizard.step ? 'bg-sun-400' : 'bg-sand-200'"></span>
              </template>
            </div>
          </template>
        </div>
      </div>

      <!-- Step 1 -->
      <div x-show="wizard.step === 1" class="p-6 md:p-8 space-y-4">
        <div>
          <label class="label">Опишите ваш проект</label>
          <textarea class="textarea" rows="4" x-model="wizard.desc"
                    placeholder="Например: Медицинский портал для пациентов. Публикуем статьи о здоровье, симптомах, профилактике…"></textarea>
          <p class="text-xs text-ink-500 mt-1">Подробное описание поможет AI заполнить остальные поля автоматически.</p>
        </div>
        <div class="flex flex-wrap gap-3 items-center">
          <button type="button" class="btn-accent" @click="aiGenerateProfile()" :disabled="wizard.aiRunning">
            <span x-show="!wizard.aiRunning">✨ AI: заполнить по описанию</span>
            <span x-show="wizard.aiRunning" class="flex items-center gap-2"><span class="spinner"></span>Генерация…</span>
          </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
          <div><label class="label">Название</label><input type="text" class="input" x-model="wizard.name" placeholder="Мой проект"></div>
          <div><label class="label">Slug</label><input type="text" class="input" x-model="wizard.slug" placeholder="my-project"></div>
          <div><label class="label">Ниша</label><input type="text" class="input" x-model="wizard.niche"></div>
          <div><label class="label">Бренд</label><input type="text" class="input" x-model="wizard.brand_name"></div>
          <div><label class="label">Язык</label>
            <select class="select" x-model="wizard.language">
              <option value="ru">Русский</option><option value="en">English</option><option value="uk">Українська</option>
            </select>
          </div>
          <div><label class="label">Тон</label>
            <select class="select" x-model="wizard.tone">
              <option value="professional">Профессиональный</option>
              <option value="friendly">Дружелюбный</option>
              <option value="academic">Академический</option>
              <option value="casual">Разговорный</option>
              <option value="persuasive">Убеждающий</option>
            </select>
          </div>
          <div class="md:col-span-2"><label class="label">GPT Персона</label><textarea class="textarea" rows="3" x-model="wizard.gpt_persona"></textarea></div>
          <div class="md:col-span-2"><label class="label">Доп. правила</label><textarea class="textarea" rows="2" x-model="wizard.gpt_rules"></textarea></div>
        </div>
      </div>

      <!-- Step 2 -->
      <div x-show="wizard.step === 2" class="p-6 md:p-8 space-y-5">
        <div class="flex flex-wrap gap-6 items-start">
          <label class="block cursor-pointer">
            <div class="text-xs uppercase tracking-wide text-ink-500 mb-2">Иконка</div>
            <div class="w-32 h-32 rounded-3xl overflow-hidden bg-sand-100 border-2 border-dashed border-sand-300 grid place-items-center hover:border-ink-700 transition-colors">
              <template x-if="wizard.iconPreview">
                <img :src="wizard.iconPreview" class="w-full h-full object-cover">
              </template>
              <template x-if="!wizard.iconPreview">
                <span class="text-xs text-ink-500">Загрузить</span>
              </template>
            </div>
            <input type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml,image/gif" class="hidden" @change="previewWizardIcon($event.target)">
          </label>
          <div class="flex-1 min-w-0 space-y-4">
            <div>
              <label class="label">Цвет</label>
              <div class="flex gap-2 items-center">
                <input type="color" class="w-12 h-10 rounded-xl cursor-pointer border border-sand-300" x-model="wizard.color_scheme">
                <input type="text" class="input flex-1" x-model="wizard.color_scheme" style="font-family:ui-monospace,monospace">
              </div>
            </div>
            <div><label class="label">Домен</label><input type="text" class="input" x-model="wizard.domain"></div>
            <div><label class="label">Logo URL</label><input type="url" class="input" x-model="wizard.logo_url"></div>
            <div><label class="label">Base URL</label><input type="url" class="input" x-model="wizard.base_url"></div>
          </div>
        </div>
        <div>
          <label class="label">Тема оформления</label>
          <select class="select" x-model="wizard.default_theme_code">
            <option value="">— использовать legacy —</option>
            <template x-for="t in themes" :key="t.code">
              <option :value="t.code" x-text="t.name + ' (' + t.code + ')'"></option>
            </template>
          </select>
        </div>
      </div>

      <!-- Step 3 -->
      <div x-show="wizard.step === 3" class="p-6 md:p-8">
        <div class="card-tinted" style="padding:24px">
          <div class="flex items-center gap-4 mb-4">
            <template x-if="wizard.iconPreview">
              <img :src="wizard.iconPreview" class="w-14 h-14 rounded-2xl object-cover">
            </template>
            <template x-if="!wizard.iconPreview">
              <span class="w-14 h-14 rounded-2xl grid place-items-center text-xl font-bold text-sand-50"
                    :style="`background:${wizard.color_scheme || '#171511'}`"
                    x-text="(wizard.name || '?')[0].toUpperCase()"></span>
            </template>
            <div>
              <div class="text-lg font-semibold" x-text="wizard.name || '—'"></div>
              <div class="text-xs text-ink-500" x-text="(wizard.slug || '') + (wizard.domain ? ' · ' + wizard.domain : '')"></div>
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
            <div><span class="text-ink-500">Ниша:</span> <span x-text="wizard.niche || '—'"></span></div>
            <div><span class="text-ink-500">Бренд:</span> <span x-text="wizard.brand_name || '—'"></span></div>
            <div><span class="text-ink-500">Язык:</span> <span x-text="langLabels[wizard.language] || wizard.language"></span></div>
            <div><span class="text-ink-500">Тон:</span> <span x-text="toneLabels[wizard.tone] || wizard.tone"></span></div>
            <div class="md:col-span-2"><span class="text-ink-500">Цвет:</span>
              <span class="inline-block w-3 h-3 rounded align-middle" :style="`background:${wizard.color_scheme}`"></span>
              <span x-text="wizard.color_scheme"></span>
            </div>
          </div>
          <template x-if="wizard.gpt_persona">
            <div class="mt-4 pt-4 border-t border-sand-200">
              <div class="text-xs uppercase tracking-wide text-ink-500 mb-1">GPT Персона</div>
              <div class="text-sm text-ink-700 whitespace-pre-wrap" x-text="wizard.gpt_persona"></div>
            </div>
          </template>
        </div>
      </div>

      <div class="p-6 md:p-8 border-t border-sand-200 flex justify-between gap-2">
        <button type="button" class="btn-ghost" @click="closeWizard()">Отмена</button>
        <div class="flex gap-2">
          <button type="button" class="btn-soft" x-show="wizard.step > 1" @click="wizardBack()">← Назад</button>
          <button type="button" class="btn-primary" @click="wizardNext()" :disabled="wizard.creating">
            <template x-if="wizard.step < 3"><span>Далее →</span></template>
            <template x-if="wizard.step === 3 && !wizard.creating"><span>Создать профиль</span></template>
            <template x-if="wizard.creating"><span class="flex items-center gap-2"><span class="spinner"></span>Создаю…</span></template>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ========== AI TEMPLATE GENERATION MODAL (SSE) ========== -->
  <div x-show="gen.open" x-cloak class="modal-backdrop" @click.self="closeGenModal()">
    <div class="modal-card p-0" style="max-width:820px">
      <div class="p-6 md:p-8 border-b border-sand-200">
        <h2 class="text-lg font-semibold">AI-генерация шаблона</h2>
      </div>

      <div class="p-6 md:p-8 space-y-4 max-h-[60vh] overflow-y-auto">
        <template x-if="!gen.running && !gen.savedId">
          <div class="space-y-4">
            <template x-if="gen.briefSummary">
              <div class="card-tinted" style="padding:14px;font-size:.85rem">
                <div class="text-xs font-semibold uppercase tracking-wide text-ink-500 mb-2">📋 Бриф для AI</div>
                <div x-html="gen.briefSummary"></div>
              </div>
            </template>
            <template x-if="!gen.briefSummary">
              <div class="card-tinted" style="padding:14px;font-size:.85rem;color:var(--ember-500)">
                ⚠️ Бриф не заполнен — AI будет работать только по описанию и нише профиля.
              </div>
            </template>

            <div>
              <label class="label">Назначение шаблона — тип статьи</label>
              <textarea class="textarea" rows="3" x-model="gen.purpose"
                        placeholder="Например: Обзорная статья товара с таблицей характеристик и сравнением с аналогами."></textarea>
              <p class="text-xs text-ink-500 mt-1">Подробное описание поможет AI правильно подобрать блоки.</p>
            </div>
            <div>
              <label class="label">Дополнительные подсказки (необязательно)</label>
              <textarea class="textarea" rows="2" x-model="gen.hints"
                        placeholder="Особые требования: нужны таблицы сравнения, обязательно FAQ…"></textarea>
            </div>
          </div>
        </template>

        <template x-if="gen.running || gen.savedId">
          <div class="space-y-3">
            <template x-for="(s, i) in gen.steps" :key="i">
              <div class="flex items-center gap-3 py-2">
                <span class="w-8 h-8 rounded-full grid place-items-center text-xs font-bold flex-shrink-0"
                      :class="s.state === 'done' ? 'bg-sun-400 text-ink-900' : (s.state === 'active' ? 'bg-ink-900 text-sand-50' : (s.state === 'error' ? 'bg-ember-500 text-white' : 'bg-sand-200 text-ink-500'))"
                      x-text="s.state === 'done' ? '✓' : (s.state === 'error' ? '✗' : (i+1))"></span>
                <span class="flex-1 text-sm" :class="s.state === 'active' ? 'font-semibold' : ''" x-text="s.label"></span>
                <span class="text-xs text-ink-500 flex items-center gap-2" x-html="s.status"></span>
              </div>
            </template>

            <template x-if="gen.preview">
              <div class="card-tinted mt-3" style="padding:14px">
                <div class="font-semibold mb-1" x-text="gen.preview.name"></div>
                <div class="text-xs text-ink-500 mb-3" x-text="gen.preview.description || ''"></div>
                <div class="text-xs text-ink-500 mb-2">Блоки (<span x-text="(gen.preview.blocks || []).length"></span>):</div>
                <div class="space-y-2">
                  <template x-for="(b, i) in (gen.preview.blocks || [])" :key="i">
                    <div class="card" style="padding:10px 12px">
                      <div class="flex items-center gap-2 flex-wrap">
                        <span class="badge-soft text-[11px]" x-text="b.type"></span>
                        <span class="text-sm font-semibold" x-text="b.name"></span>
                        <template x-if="b.is_required"><span class="text-xs text-sun-500">★ обяз.</span></template>
                      </div>
                      <template x-if="b.hint"><div class="text-xs text-ink-500 mt-1" x-text="b.hint"></div></template>
                    </div>
                  </template>
                </div>
              </div>
            </template>

            <template x-if="gen.suggestions.length > 0">
              <div class="card-tinted" style="padding:14px">
                <div class="text-xs font-semibold uppercase tracking-wide text-ink-500 mb-2">Рекомендации AI</div>
                <ul class="space-y-1 text-sm">
                  <template x-for="(s, i) in gen.suggestions" :key="i">
                    <li>• <span x-text="s"></span></li>
                  </template>
                </ul>
              </div>
            </template>
          </div>
        </template>
      </div>

      <div class="p-6 md:p-8 border-t border-sand-200 flex justify-between gap-2">
        <button type="button" class="btn-ghost" @click="closeGenModal()" x-text="gen.running ? 'Отменить' : 'Закрыть'"></button>
        <button type="button" class="btn-primary" @click="startGen()"
                x-show="!gen.running && !gen.savedId" :disabled="gen.running">Сгенерировать шаблон</button>
      </div>
    </div>
  </div>

  <!-- ========== INTENT EDITOR MODAL ========== -->
  <div x-show="intent.open" x-cloak class="modal-backdrop" @click.self="closeIntentEditor()">
    <div class="modal-card p-0" style="max-width:680px">
      <div class="p-6 md:p-8 border-b border-sand-200">
        <h2 class="text-lg font-semibold" x-text="intent.editing ? 'Редактировать интент' : 'Новый интент'"></h2>
      </div>
      <div class="p-6 md:p-8 space-y-4 max-h-[60vh] overflow-y-auto">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="label">Код (a–z, 0–9, _)</label>
            <input type="text" class="input" x-model="intent.code" :disabled="intent.editing" placeholder="my_intent" pattern="[a-z0-9_]+">
          </div>
          <div>
            <label class="label">Цвет</label>
            <input type="color" class="w-full h-10 rounded-xl cursor-pointer border border-sand-300" x-model="intent.color">
          </div>
          <div><label class="label">Название (RU)</label><input type="text" class="input" x-model="intent.label_ru"></div>
          <div><label class="label">Название (EN)</label><input type="text" class="input" x-model="intent.label_en"></div>
          <div class="md:col-span-2"><label class="label">Описание</label><textarea class="textarea" rows="2" x-model="intent.description"></textarea></div>
          <div class="md:col-span-2"><label class="label">GPT Hint</label><textarea class="textarea" rows="2" x-model="intent.gpt_hint"></textarea></div>
          <div class="md:col-span-2"><label class="label">Тон статьи</label><textarea class="textarea" rows="2" x-model="intent.article_tone"></textarea></div>
          <div class="md:col-span-2"><label class="label">Открывающая фраза</label><input type="text" class="input" x-model="intent.article_open"></div>
          <div>
            <label class="label">Статус</label>
            <select class="select" x-model.number="intent.is_active">
              <option value="1">Активен</option><option value="0">Неактивен</option>
            </select>
          </div>
        </div>
      </div>
      <div class="p-6 md:p-8 border-t border-sand-200 flex justify-between gap-2">
        <button type="button" class="btn-ghost" @click="closeIntentEditor()">Отмена</button>
        <button type="button" class="btn-primary" @click="saveIntent()" :disabled="intent.saving">
          <span x-show="!intent.saving">Сохранить</span>
          <span x-show="intent.saving" class="flex items-center gap-2"><span class="spinner"></span>Сохраняю…</span>
        </button>
      </div>
    </div>
  </div>

</div>

<script>
const TOK_CAT_LABELS = {
  profile_create: 'Создание профиля',
  profile_brief: 'AI Бриф',
  template_create: 'Создание шаблонов',
  template_review: 'Ревью шаблонов',
  article_create: 'Создание статей',
  telegram_aggregate: 'Агрегация Telegram',
};
const TOK_CAT_ICONS = {
  profile_create: '🚀', profile_brief: '📋', template_create: '🧩',
  template_review: '🔍', article_create: '📝', telegram_aggregate: '✈️',
};

const BRIEF_STEPS = [
  { key: 'classify',    title: 'Нишевые параметры',    sub: 'Ниша, язык, регулирование' },
  { key: 'audience',    title: 'Аудитория (ICP)',      sub: 'Один-два ICP с болями и целями' },
  { key: 'usp',         title: 'УТП',                  sub: 'Сильнейшие УТП' },
  { key: 'competitors', title: 'Конкуренты',           sub: 'Кого обыгрывать в контенте' },
  { key: 'voice',       title: 'Голос бренда',         sub: 'Один архетип' },
  { key: 'rules',       title: 'Правила do/dont',      sub: 'Редакционные do и don\'t' },
  { key: 'compliance',  title: 'Compliance',           sub: 'Запреты регулятора и оговорки', regulatedOnly: true },
  { key: 'phrases',     title: 'Пробы голоса',         sub: 'Образцы стиля' },
];

function briefOptKey(o) {
  if (!o || typeof o !== 'object') return String(o || '');
  return o.label || o.headline || o.name || o.archetype || o.text || o.rule || JSON.stringify(o);
}

function briefEntitiesToText(e) {
  if (!e || typeof e !== 'object') return '';
  const lines = [];
  for (const k of Object.keys(e)) {
    const v = e[k];
    if (Array.isArray(v)) v.forEach(x => lines.push(String(x)));
    else if (v) lines.push(String(v));
  }
  return lines.join('\n');
}

function briefExtras(o, known) {
  const e = SEO.esc;
  if (!o || typeof o !== 'object') return '';
  const skip = new Set(known);
  const rows = [];
  for (const k of Object.keys(o)) {
    if (skip.has(k)) continue;
    const v = o[k];
    if (v == null || v === '' || (Array.isArray(v) && v.length === 0)) continue;
    let val;
    if (Array.isArray(v)) {
      val = v.map(x => '<li>' + e(typeof x === 'string' ? x : JSON.stringify(x)) + '</li>').join('');
      val = '<ul class="list-disc ml-4 mt-0.5">' + val + '</ul>';
    } else if (typeof v === 'object') {
      val = '<span class="font-mono">' + e(JSON.stringify(v)) + '</span>';
    } else if (typeof v === 'boolean') {
      val = v ? '✓' : '—';
    } else {
      val = e(String(v));
    }
    rows.push('<div class="text-xs mt-1"><b class="text-ink-500">' + e(k) + ':</b> ' + val + '</div>');
  }
  return rows.join('');
}

const BRIEF_ICONS = { audience: '👥', usp: '⭐', competitors: '⚔', voice: '🎙', phrases: '💬' };

function briefOptHtml(stepKey, o) {
  const e = SEO.esc;
  if (!o) return '';
  const icon = BRIEF_ICONS[stepKey] || '◆';

  if (stepKey === 'phrases') {
    return '<div class="bf-icon">' + icon + '</div>' +
      '<div class="flex-1 min-w-0">' +
        '<div class="bf-title font-mono">«' + e(typeof o === 'string' ? o : (o.text || '')) + '»</div>' +
        briefExtras(o, ['text']) +
      '</div>';
  }
  if (stepKey === 'audience') {
    const pains = (o.pains || []).map(p => '<li>' + e(p) + '</li>').join('');
    const goals = (o.goals || []).map(g => '<li>' + e(g) + '</li>').join('');
    return '<div class="bf-icon">' + icon + '</div>' +
      '<div class="flex-1 min-w-0">' +
        '<div class="bf-title">' + e(o.label || o.name || '') + '</div>' +
        (o.description ? '<div class="bf-sub">' + e(o.description) + '</div>' : '') +
        (pains ? '<div class="bf-section-label">Боли</div><ul class="bf-list">' + pains + '</ul>' : '') +
        (goals ? '<div class="bf-section-label">Цели</div><ul class="bf-list">' + goals + '</ul>' : '') +
        briefExtras(o, ['label','name','description','pains','goals']) +
      '</div>';
  }
  if (stepKey === 'usp') {
    return '<div class="bf-icon">' + icon + '</div>' +
      '<div class="flex-1 min-w-0">' +
        '<div class="bf-title">' + e(o.headline || o.label || '') + '</div>' +
        (o.why ? '<div class="bf-sub">' + e(o.why) + '</div>' : '') +
        briefExtras(o, ['headline','label','why']) +
      '</div>';
  }
  if (stepKey === 'competitors') {
    return '<div class="bf-icon">' + icon + '</div>' +
      '<div class="flex-1 min-w-0">' +
        '<div class="bf-title flex items-center gap-2">' + e(o.name || '') +
          (o.url ? ' <a href="' + e(o.url) + '" target="_blank" class="text-ember-500 text-xs no-underline">↗</a>' : '') +
        '</div>' +
        (o.why_strong ? '<div class="bf-sub">' + e(o.why_strong) + '</div>' : '') +
        briefExtras(o, ['name','url','why_strong']) +
      '</div>';
  }
  if (stepKey === 'voice') {
    const vibes = Array.isArray(o.vibes) ? '<div class="bf-tags">' + o.vibes.map(v => '<span class="bf-tag">' + e(v) + '</span>').join('') + '</div>' : '';
    const examples = Array.isArray(o.examples)
      ? '<div class="bf-section-label">Примеры</div>' + o.examples.map(x => '<div class="bf-sub font-mono mt-1">«' + e(x) + '»</div>').join('')
      : '';
    return '<div class="bf-icon">' + icon + '</div>' +
      '<div class="flex-1 min-w-0">' +
        '<div class="bf-title">' + e(o.archetype || o.label || '') + '</div>' +
        vibes + examples +
        briefExtras(o, ['archetype','label','vibes','examples']) +
      '</div>';
  }
  return '<div class="bf-icon">◆</div><div class="flex-1 min-w-0 font-mono text-xs">' + e(JSON.stringify(o)) + '</div>';
}

function profilePage() {
  return {
    SEO: window.SEO,
    TOK_CAT_LABELS,
    toneLabels: { professional: 'Профессиональный', friendly: 'Дружелюбный', academic: 'Академический', casual: 'Разговорный', persuasive: 'Убеждающий' },
    langLabels: { ru: 'Русский', en: 'English', uk: 'Українська' },
    researchStrategies: [
      { value: 'single',       icon: '◯', title: 'Single', desc: 'Один большой GPT-вызов (legacy).' },
      { value: 'split',        icon: '◐', title: 'Split',  desc: 'Outline + fill по секциям. Дешевле.' },
      { value: 'split_search', icon: '⊕', title: 'Split + Search', desc: 'Fill бенчмарков через Brave Search.' },
    ],

    view: 'list',
    loading: true,
    profiles: [],
    current: null,
    saving: false,

    tabs: [
      { key: 'overview',  label: 'Обзор' },
      { key: 'settings',  label: 'Настройки' },
      { key: 'branding',  label: 'Брендинг' },
      { key: 'brief',     label: 'AI Бриф' },
      { key: 'templates', label: 'Шаблоны' },
      { key: 'intents',   label: 'Интенты' },
      { key: 'telegram',  label: 'Telegram' },
      { key: 'tokens',    label: 'Расход токенов' },
    ],
    tab: 'overview',

    settings: {},
    branding: {},
    telegram: { tg_render_blocks: [] },
    tgTesting: false,
    tgTestStatus: '',
    tgTestStatusClass: 'text-ink-500',
    tgChannel: { name: '', avatar: null, meta: '' },

    blockTypes: [],
    themes: [],

    statsList: [],

    // AI brief wizard state
    briefIdx: 0,
    briefState: {},
    briefCur: {},
    briefHints: {},
    briefViewMode: {},
    briefStepJsonRaw: '',
    briefStepJsonError: '',
    briefFullJsonRaw: '',
    briefFullJsonError: '',
    briefRunning: false,
    briefAutoStatus: { kind: '', msg: '' },
    _briefAutoTimer: null,

    templates: [],
    templatesLoading: false,

    customIntents: [],
    globalIntents: [],
    intentsLoading: false,
    aiIntentsRunning: false,

    tokenTotalsBoxes: [],
    tokenCategoriesList: [],
    tokenRecent: [],

    wizard: {
      open: false, step: 1,
      desc: '', name: '', slug: '', niche: '', brand_name: '',
      language: 'ru', tone: 'professional',
      gpt_persona: '', gpt_rules: '',
      domain: '', logo_url: '', base_url: '',
      color_scheme: '#171511',
      default_theme_code: '',
      iconFile: null, iconPreview: '',
      aiRunning: false, creating: false,
    },

    gen: {
      open: false, running: false,
      purpose: '', hints: '',
      briefSummary: '',
      steps: [
        { label: 'Генерация шаблона', state: 'idle', status: '' },
        { label: 'Ревью качества',    state: 'idle', status: '' },
        { label: 'Сохранение',        state: 'idle', status: '' },
      ],
      preview: null,
      suggestions: [],
      savedId: null,
    },

    intent: {
      open: false, editing: null, saving: false,
      code: '', color: '#6366f1',
      label_ru: '', label_en: '',
      description: '', gpt_hint: '',
      article_tone: '', article_open: '',
      is_active: 1,
    },

    // ===================================================================

    async init() {
      await Promise.all([this.loadProfiles(), this.loadThemes()]);
      const stored = localStorage.getItem('seo_profile_id');
      if (stored) {
        const id = Number(stored);
        if (this.profiles.some(p => p.id === id)) {
          await this.openWorkspace(id);
        }
      }
    },

    async loadProfiles() {
      this.loading = true;
      try {
        const data = await SEO.api('profiles');
        this.profiles = Array.isArray(data) ? data : (data || []);
      } catch (_) { this.profiles = []; }
      finally { this.loading = false; }
    },

    async loadThemes() {
      try {
        const data = await SEO.api('themes');
        const list = Array.isArray(data) ? data : (data || []);
        this.themes = list.filter(t => t.is_active);
      } catch (_) { this.themes = []; }
    },

    async openWorkspace(id) {
      try {
        const p = await SEO.api('profiles/' + id);
        this.current = p;
        SEO.profile.id = id;
        this.view = 'workspace';
        this.tab = 'overview';
        const _tb = document.getElementById('seo-topbar'); if (_tb) _tb.style.display = 'none';
        await this.switchTab('overview');
      } catch (_) {}
    },

    goToList() {
      this.current = null;
      this.view = 'list';
      const _tb = document.getElementById('seo-topbar'); if (_tb) _tb.style.display = '';
      this.loadProfiles();
    },

    async switchTab(t) {
      this.tab = t;
      if (t === 'overview')       await this.loadOverview();
      else if (t === 'settings')  this.fillSettings();
      else if (t === 'branding')  this.fillBranding();
      else if (t === 'brief')     this.briefReload();
      else if (t === 'templates') await this.loadTemplates();
      else if (t === 'intents')   await this.loadIntents();
      else if (t === 'telegram')  await this.fillTelegram();
      else if (t === 'tokens')    await this.loadTokens();
    },

    async loadOverview() {
      try {
        const s = await SEO.api('profiles/' + this.current.id + '/stats');
        this.statsList = [
          ['Каталогов',  s.catalogs ?? 0],
          ['Шаблонов',   s.templates ?? 0],
          ['Статей',     s.articles ?? 0],
          ['Опубликовано', s.published ?? 0],
          ['Интентов',   s.intents ?? 0],
          ['Задач сбора', s.keyword_jobs ?? 0],
          ['Кластеров',  s.clusters ?? 0],
          ['Хостов',     s.publish_targets ?? 0],
        ];
      } catch (_) { this.statsList = []; }
    },

    fillSettings() {
      const p = this.current;
      this.settings = {
        name: p.name || '', slug: p.slug || '',
        domain: p.domain || '', niche: p.niche || '',
        brand_name: p.brand_name || '',
        language: p.language || 'ru',
        tone: p.tone || 'professional',
        is_active: p.is_active ? 1 : 0,
        description: p.description || '',
        gpt_persona: p.gpt_persona || '',
        gpt_rules: p.gpt_rules || '',
        research_strategy: p.research_strategy || 'single',
      };
    },

    themeColor(t, key, fallback) {
      return (t && t.tokens && t.tokens.color && t.tokens.color[key]) || fallback;
    },
    themeRadius(t, key, fallback) {
      return (t && t.tokens && t.tokens.radius && t.tokens.radius[key]) || fallback;
    },

    async saveSettings() {
      this.saving = true;
      try {
        const body = Object.assign({}, this.settings, {
          domain: this.settings.domain || null,
          niche: this.settings.niche || null,
          brand_name: this.settings.brand_name || null,
          description: this.settings.description || null,
          gpt_persona: this.settings.gpt_persona || null,
          gpt_rules: this.settings.gpt_rules || null,
        });
        const updated = await SEO.api('profiles/' + this.current.id, { method: 'PUT', body });
        Object.assign(this.current, updated);
        SEO.toast('Настройки сохранены', 'ok');
      } finally { this.saving = false; }
    },

    fillBranding() {
      const p = this.current;
      this.branding = {
        color_scheme: p.color_scheme || '#171511',
        logo_url: p.logo_url || '',
        base_url: p.base_url || '',
        default_theme_code: p.default_theme_code || '',
        research_strategy: p.research_strategy || 'single',
      };
    },

    async uploadIcon(input) {
      if (!input.files || !input.files[0]) return;
      const fd = new FormData();
      fd.append('icon', input.files[0]);
      try {
        await SEO.api('profiles/' + this.current.id + '/icon', { method: 'POST', body: fd });
        const fresh = await SEO.api('profiles/' + this.current.id);
        this.current = fresh;
        SEO.toast('Иконка загружена', 'ok');
      } catch (_) {}
      input.value = '';
    },

    async removeIcon() {
      try {
        await SEO.api('profiles/' + this.current.id + '/icon', { method: 'DELETE' });
        const fresh = await SEO.api('profiles/' + this.current.id);
        this.current = fresh;
        SEO.toast('Иконка удалена', 'ok');
      } catch (_) {}
    },

    async saveBranding() {
      this.saving = true;
      try {
        const body = {
          color_scheme: this.branding.color_scheme || '#171511',
          logo_url: this.branding.logo_url || null,
          base_url: this.branding.base_url || null,
          default_theme_code: this.branding.default_theme_code || null,
        };
        const updated = await SEO.api('profiles/' + this.current.id, { method: 'PUT', body });
        Object.assign(this.current, updated);
        SEO.toast('Брендинг сохранён', 'ok');
      } finally { this.saving = false; }
    },

    // ─── AI Brief wizard ──────────────────────────────────────────────
    briefReload() {
      const src = this.current.content_brief || {};
      this.briefState = JSON.parse(JSON.stringify(src));
      this.briefCur = {};
      this.briefIdx = 0;
      this.briefViewMode = {};
      this.briefHints = {};
      this.briefStepJsonError = '';
      this.briefFullJsonError = '';
      this.briefAutoStatus = { kind: '', msg: '' };
      this.briefRefreshFullJson();
      this.briefRefreshStepJson();
      this.$nextTick(() => this.briefAttachClicks());
    },

    briefReset() {
      if (!confirm('Сбросить весь бриф?')) return;
      this.briefState = {};
      this.briefCur = {};
      this.briefIdx = 0;
      this.briefViewMode = {};
      this.briefRefreshFullJson();
      this.briefRefreshStepJson();
      this.briefAutoSave();
    },

    briefVisibleSteps() {
      const regulated = !!(this.briefState && this.briefState.classify && this.briefState.classify.regulated);
      return BRIEF_STEPS.filter(s => !s.regulatedOnly || regulated);
    },

    briefCurStep() {
      const list = this.briefVisibleSteps();
      const i = Math.min(this.briefIdx, list.length - 1);
      return list[Math.max(0, i)] || BRIEF_STEPS[0];
    },

    briefIsStepDone(key) {
      if (key === 'usp') return Array.isArray(this.briefState.usps) && this.briefState.usps.length > 0;
      const v = this.briefState[key];
      if (v == null) return false;
      if (Array.isArray(v)) return v.length > 0;
      if (typeof v === 'object') return Object.keys(v).length > 0;
      return !!v;
    },

    briefHasOptions() {
      const k = this.briefCurStep().key;
      const c = this.briefCur[k];
      return !!(c && Array.isArray(c.options) && c.options.length > 0);
    },

    briefGoto(idx) {
      this.briefCommitLive();
      this.briefIdx = idx;
      this.briefRefreshStepJson();
    },

    briefPrev() {
      if (this.briefIdx === 0) return;
      this.briefCommitLive();
      this.briefIdx -= 1;
      this.briefRefreshStepJson();
    },

    briefNext() {
      this.briefCommitLive();
      const last = this.briefVisibleSteps().length - 1;
      if (this.briefIdx >= last) {
        SEO.toast('Бриф готов', 'ok');
        return;
      }
      this.briefIdx += 1;
      this.briefRefreshStepJson();
    },

    async briefRegen() {
      const step = this.briefCurStep();
      this.briefCommitLive();
      const hint = (this.briefHints[step.key] || '').trim();
      const prev = this.briefCur[step.key] || null;
      const existing = prev && Array.isArray(prev.options) ? prev.options : [];
      this.briefRunning = true;
      try {
        const res = await SEO.api('profiles/brief', {
          method: 'POST',
          body: {
            step: step.key,
            profile_id: this.current.id,
            description: this.current.description || '',
            brief: this.briefState || {},
            hint,
            existing_options: existing,
          },
        });
        const fresh = (res && res.data) || res || {};
        if (Array.isArray(fresh.options) && existing.length) {
          const seen = new Set(existing.map(briefOptKey));
          const merged = [...existing];
          for (const o of fresh.options) {
            const k = briefOptKey(o);
            if (!seen.has(k)) { merged.push(o); seen.add(k); }
          }
          fresh.options = merged;
        }
        if (step.key === 'rules' && prev) {
          const mergeArr = (a, b) => {
            const out = Array.isArray(a) ? [...a] : [];
            const seen = new Set(out.map(r => r.rule || ''));
            for (const r of (b || [])) if (!seen.has(r.rule || '')) { out.push(r); seen.add(r.rule || ''); }
            return out;
          };
          fresh.do = mergeArr(prev.do, fresh.do);
          fresh.dont = mergeArr(prev.dont, fresh.dont);
        }
        this.briefCur[step.key] = fresh;
        this.briefRefreshStepJson();
        this.briefAutoSave();
      } finally { this.briefRunning = false; }
    },

    briefHydrateFromSaved(key) {
      if (key === 'classify')   return this.briefState.classify   || {};
      if (key === 'compliance') return this.briefState.compliance || {};
      if (key === 'rules')      return this.briefState.rules      || { do: [], dont: [] };
      const s = key === 'usp' ? this.briefState.usps : this.briefState[key];
      if (s == null) return null;
      if (key === 'audience' || key === 'voice') return { options: [s] };
      if (key === 'competitors' || key === 'phrases') return { options: Array.isArray(s) ? s : [] };
      if (key === 'usp') return { options: Array.isArray(s) ? s : [] };
      return s;
    },

    briefStepData() {
      const k = this.briefCurStep().key;
      if (!this.briefCur[k]) {
        const h = this.briefHydrateFromSaved(k);
        if (h) this.briefCur[k] = h;
      }
      return this.briefCur[k] || null;
    },

    briefStepHtml() {
      const step = this.briefCurStep();
      const data = this.briefStepData();
      if (!data) {
        return '<div class="text-center text-ink-300 py-10"><div class="text-3xl mb-2">✨</div>' +
               '<div class="text-ink-500">Нажми «Сгенерировать варианты», чтобы AI подобрал содержимое для этого шага.</div></div>';
      }
      const sel = this.briefSelectionSet(step.key);
      const e = SEO.esc;
      if (step.key === 'classify') {
        return `<div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div><label class="label">Ниша</label><input type="text" class="input bf-fld" data-bk="niche" value="${e(data.niche || '')}"></div>
          <div><label class="label">Язык</label><input type="text" class="input bf-fld" data-bk="language" value="${e(data.language || 'ru')}" placeholder="ru | en | uk"></div>
          <div><label class="label">Регулирование (домен)</label><input type="text" class="input bf-fld" data-bk="regulatory_domain" value="${e(data.regulatory_domain || 'none')}" placeholder="finance | medical | legal | crypto | none"></div>
          <div class="flex items-center gap-3"><label class="label" style="margin:0">Регулируемая ниша</label>
            <label class="toggle">
              <input type="checkbox" class="bf-chk" data-bk="regulated" ${data.regulated ? 'checked' : ''}>
              <span class="toggle-track"></span>
              <span class="toggle-thumb"></span>
            </label></div>
          <div class="md:col-span-2"><label class="label">Ключевые сущности (по строке)</label><textarea class="textarea bf-txt" data-bk="entities" rows="3">${e(briefEntitiesToText(data.detected_entities || {}))}</textarea></div>
          <div class="md:col-span-2"><label class="label">Уточняющие вопросы (по строке)</label><textarea class="textarea bf-txt" data-bk="questions" rows="3">${e((data.clarifying_questions || []).join('\n'))}</textarea></div>
        </div>`;
      }
      if (step.key === 'rules') {
        const renderRules = (list, group) => list.map((r, i) => {
          const on = sel.has(r.rule || '');
          const ic = group === 'do' ? '✓' : '✕';
          const cls = group === 'do' ? 'bf-rule-do' : 'bf-rule-dont';
          return `<div class="bf-card bf-rule ${cls} ${on ? 'bf-on' : ''}" data-bf-rule="${e(group)}|${i}">
            <div class="flex items-start gap-3">
              <div class="bf-icon" style="${group==='do'?'background:#d1fae5;color:#065f46':'background:#fee2e2;color:#991b1b'}">${ic}</div>
              <div class="flex-1 min-w-0">
                <div class="bf-title">${e(r.rule || '')}</div>
                ${r.why ? '<div class="bf-sub">' + e(r.why) + '</div>' : ''}
              </div>
              <input type="checkbox" class="mt-1 flex-shrink-0" ${on ? 'checked' : ''}>
            </div>
            <button type="button" class="btn-icon bf-del" data-bf-rule-del="${e(group)}|${i}" title="Удалить">✕</button>
          </div>`;
        }).join('');
        return `<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div><div class="label" style="color:#16a34a">DO — что делать</div><div class="space-y-2">${renderRules(data.do || [], 'do') || '<div class="text-xs text-ink-300">Пусто. Нажми «Сгенерировать».</div>'}</div></div>
          <div><div class="label" style="color:#dc2626">DON'T — что не делать</div><div class="space-y-2">${renderRules(data.dont || [], 'dont') || '<div class="text-xs text-ink-300">Пусто.</div>'}</div></div>
        </div>`;
      }
      if (step.key === 'compliance') {
        return `<div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div><label class="label">Регулятор</label><input type="text" class="input bf-fld" data-bk="regulator" value="${e(data.regulator || '')}"></div>
          <div><label class="label">Юрисдикция</label><input type="text" class="input bf-fld" data-bk="jurisdiction" value="${e(data.jurisdiction || '')}"></div>
          <div class="md:col-span-2"><label class="label">Обязательные дисклеймеры (по строке)</label><textarea class="textarea bf-txt" data-bk="mandatory_disclaimers" rows="3">${e((data.mandatory_disclaimers || []).join('\n'))}</textarea></div>
          <div class="md:col-span-2"><label class="label">Запрещённые формулировки (по строке)</label><textarea class="textarea bf-txt" data-bk="banned_claims" rows="3">${e((data.banned_claims || []).join('\n'))}</textarea></div>
        </div>`;
      }
      // Generic options-list (audience/usp/competitors/voice/phrases)
      const opts = Array.isArray(data.options) ? data.options : [];
      if (opts.length === 0) {
        return '<div class="text-center text-ink-300 py-10">Вариантов нет. Нажми «Сгенерировать варианты».</div>';
      }
      const single = (step.key === 'audience' || step.key === 'voice');
      return '<div class="grid grid-cols-1 md:grid-cols-2 gap-3">' + opts.map((o, i) => {
        const k = briefOptKey(o);
        const on = sel.has(k);
        return `<div class="bf-card ${on ? 'bf-on' : ''}" data-bf-opt="${i}" data-bf-single="${single ? '1' : '0'}">
          <div class="flex items-start gap-3">
            ${briefOptHtml(step.key, o)}
            <input type="${single ? 'radio' : 'checkbox'}" name="bfopt-${e(step.key)}" class="mt-1 flex-shrink-0" ${on ? 'checked' : ''}>
          </div>
          <button type="button" class="btn-icon bf-del" data-bf-del="${i}" title="Удалить">✕</button>
        </div>`;
      }).join('') + '</div>';
    },

    briefSelectionSet(key) {
      const s = new Set();
      const saved = key === 'usp' ? this.briefState.usps : this.briefState[key];
      if (key === 'audience' || key === 'voice') {
        if (saved) s.add(briefOptKey(saved));
      } else if (key === 'usp' || key === 'competitors' || key === 'phrases') {
        (Array.isArray(saved) ? saved : []).forEach(o => s.add(briefOptKey(o)));
      } else if (key === 'rules') {
        const cur = this.briefCur.rules || {};
        const savedDo = (saved && saved.do) || [];
        const savedDont = (saved && saved.dont) || [];
        savedDo.forEach(r => s.add(r.rule || ''));
        savedDont.forEach(r => s.add(r.rule || ''));
      }
      return s;
    },

    briefCommitLive() {
      // Read current DOM into briefCur + briefState
      const step = this.briefCurStep();
      const k = step.key;
      const root = document.querySelector('[x-html="briefStepHtml()"]');
      if (!root) return;
      const cur = this.briefCur[k];
      if (k === 'classify') {
        const obj = cur || {};
        root.querySelectorAll('.bf-fld').forEach(inp => { obj[inp.dataset.bk] = inp.value; });
        const reg = root.querySelector('.bf-chk[data-bk="regulated"]');
        if (reg) obj.regulated = reg.checked;
        const ent = root.querySelector('.bf-txt[data-bk="entities"]');
        if (ent) {
          const lines = ent.value.split('\n').map(s => s.trim()).filter(Boolean);
          obj.detected_entities = { items: lines };
        }
        const q = root.querySelector('.bf-txt[data-bk="questions"]');
        if (q) obj.clarifying_questions = q.value.split('\n').map(s => s.trim()).filter(Boolean);
        this.briefCur[k] = obj;
        this.briefState.classify = obj;
      } else if (k === 'compliance') {
        const obj = cur || {};
        root.querySelectorAll('.bf-fld').forEach(inp => { obj[inp.dataset.bk] = inp.value; });
        root.querySelectorAll('.bf-txt').forEach(inp => {
          obj[inp.dataset.bk] = inp.value.split('\n').map(s => s.trim()).filter(Boolean);
        });
        this.briefCur[k] = obj;
        this.briefState.compliance = obj;
      } else if (k === 'rules') {
        const obj = cur || { do: [], dont: [] };
        const out = { do: [], dont: [] };
        root.querySelectorAll('[data-bf-rule]').forEach(row => {
          const [g, idx] = row.dataset.bfRule.split('|');
          const checked = row.querySelector('input[type=checkbox]').checked;
          if (checked) {
            const item = (obj[g] || [])[Number(idx)];
            if (item) out[g].push(item);
          }
        });
        this.briefState.rules = out;
      } else if (k === 'audience' || k === 'voice') {
        const opts = (cur && cur.options) || [];
        let picked = null;
        root.querySelectorAll('[data-bf-opt]').forEach(card => {
          if (card.querySelector('input').checked) {
            picked = opts[Number(card.dataset.bfOpt)] || null;
          }
        });
        this.briefState[k] = picked;
      } else if (k === 'usp' || k === 'competitors' || k === 'phrases') {
        const opts = (cur && cur.options) || [];
        const picked = [];
        root.querySelectorAll('[data-bf-opt]').forEach(card => {
          if (card.querySelector('input').checked) {
            picked.push(opts[Number(card.dataset.bfOpt)]);
          }
        });
        if (k === 'usp') this.briefState.usps = picked;
        else this.briefState[k] = picked;
      }
      this.briefRefreshFullJson();
      this.briefAutoSave();
    },

    briefAttachClicks() {
      const root = document.querySelector('[x-html="briefStepHtml()"]');
      if (!root || root._bfAttached) return;
      root._bfAttached = true;
      root.addEventListener('click', (e) => {
        const card = e.target.closest('[data-bf-opt]');
        if (card) {
          if (e.target.closest('[data-bf-del]')) {
            const idx = Number(e.target.closest('[data-bf-del]').dataset.bfDel);
            this.briefDelOption(idx);
            return;
          }
          if (e.target.tagName === 'BUTTON') return;
          const single = card.dataset.bfSingle === '1';
          const inp = card.querySelector('input');
          if (single) {
            root.querySelectorAll('[data-bf-opt] input').forEach(x => { x.checked = false; x.closest('[data-bf-opt]').classList.remove('border-ink-900','bg-sand-100'); x.closest('[data-bf-opt]').classList.add('border-sand-300'); });
            inp.checked = true;
            card.classList.add('border-ink-900','bg-sand-100');
            card.classList.remove('border-sand-300');
          } else {
            inp.checked = !inp.checked;
            card.classList.toggle('border-ink-900', inp.checked);
            card.classList.toggle('bg-sand-100', inp.checked);
            card.classList.toggle('border-sand-300', !inp.checked);
          }
          this.briefCommitLive();
          return;
        }
        const rule = e.target.closest('[data-bf-rule]');
        if (rule) {
          if (e.target.closest('[data-bf-rule-del]')) {
            const [g, idx] = e.target.closest('[data-bf-rule-del]').dataset.bfRuleDel.split('|');
            this.briefDelRule(g, Number(idx));
            return;
          }
          if (e.target.tagName === 'BUTTON') return;
          const inp = rule.querySelector('input');
          inp.checked = !inp.checked;
          rule.classList.toggle('border-ink-900', inp.checked);
          rule.classList.toggle('bg-sand-100', inp.checked);
          rule.classList.toggle('border-sand-300', !inp.checked);
          this.briefCommitLive();
        }
      });
      root.addEventListener('change', (e) => {
        if (e.target.matches('.bf-fld, .bf-txt, .bf-chk')) this.briefCommitLive();
      });
    },

    briefDelOption(idx) {
      const k = this.briefCurStep().key;
      const data = this.briefCur[k];
      if (!data || !Array.isArray(data.options)) return;
      const removed = data.options[idx];
      const rkey = removed ? briefOptKey(removed) : null;
      data.options.splice(idx, 1);
      if (rkey) {
        if (k === 'audience' && this.briefState.audience && briefOptKey(this.briefState.audience) === rkey) this.briefState.audience = null;
        else if (k === 'voice' && this.briefState.voice && briefOptKey(this.briefState.voice) === rkey) this.briefState.voice = null;
        else if (k === 'usp' && Array.isArray(this.briefState.usps)) this.briefState.usps = this.briefState.usps.filter(o => briefOptKey(o) !== rkey);
        else if ((k === 'competitors' || k === 'phrases') && Array.isArray(this.briefState[k])) this.briefState[k] = this.briefState[k].filter(o => briefOptKey(o) !== rkey);
      }
      this.briefRefreshFullJson();
      this.briefAutoSave();
    },

    briefDelRule(group, idx) {
      const data = this.briefCur.rules;
      if (!data || !Array.isArray(data[group])) return;
      const removed = data[group][idx];
      const rkey = removed && removed.rule ? removed.rule : null;
      data[group].splice(idx, 1);
      if (rkey && this.briefState.rules && Array.isArray(this.briefState.rules[group])) {
        this.briefState.rules[group] = this.briefState.rules[group].filter(r => (r.rule || '') !== rkey);
      }
      this.briefRefreshFullJson();
      this.briefAutoSave();
    },

    briefAutoSave() {
      if (!this.current || !this.current.id) return;
      clearTimeout(this._briefAutoTimer);
      this.briefAutoStatus = { kind: '', msg: 'Сохранение…' };
      this._briefAutoTimer = setTimeout(async () => {
        try {
          const updated = await SEO.api('profiles/' + this.current.id, { method: 'PUT', body: { content_brief: this.briefState } });
          Object.assign(this.current, updated);
          this.briefAutoStatus = { kind: 'ok', msg: 'Сохранено' };
        } catch (e) {
          this.briefAutoStatus = { kind: 'err', msg: 'Ошибка сохранения' };
        }
      }, 500);
    },

    briefRefreshStepJson() {
      const k = this.briefCurStep().key;
      this.briefStepJsonRaw = JSON.stringify(this.briefCur[k] || {}, null, 2);
      this.briefStepJsonError = '';
    },

    briefStepJsonApply() {
      try {
        const obj = JSON.parse(this.briefStepJsonRaw || '{}');
        const k = this.briefCurStep().key;
        this.briefCur[k] = obj;
        this.briefStepJsonError = '';
        this.briefCommitLive();
      } catch (e) { this.briefStepJsonError = 'JSON: ' + e.message; }
    },

    briefStepJsonReload() { this.briefRefreshStepJson(); },

    briefRefreshFullJson() {
      this.briefFullJsonRaw = JSON.stringify(this.briefState || {}, null, 2);
      this.briefFullJsonError = '';
    },

    briefFullJsonFormat() {
      try {
        const obj = JSON.parse(this.briefFullJsonRaw || '{}');
        this.briefFullJsonRaw = JSON.stringify(obj, null, 2);
        this.briefFullJsonError = '';
      } catch (e) { this.briefFullJsonError = 'JSON: ' + e.message; }
    },

    briefFullJsonApply() {
      try {
        const obj = JSON.parse(this.briefFullJsonRaw || '{}');
        this.briefState = obj;
        this.briefCur = {};
        this.briefFullJsonError = '';
        this.briefAutoSave();
      } catch (e) { this.briefFullJsonError = 'JSON: ' + e.message; }
    },

    briefFullJsonReload() {
      this.briefState = JSON.parse(JSON.stringify(this.current.content_brief || {}));
      this.briefCur = {};
      this.briefRefreshFullJson();
    },

    async loadTemplates() {
      this.templatesLoading = true;
      try {
        const data = await SEO.api('templates?profile_id=' + this.current.id);
        this.templates = Array.isArray(data) ? data : (data || []);
      } catch (_) { this.templates = []; }
      finally { this.templatesLoading = false; }
    },

    async deleteTemplate(t) {
      if (!confirm('Удалить шаблон "' + t.name + '"?')) return;
      try {
        await SEO.api('templates/' + t.id, { method: 'DELETE' });
        SEO.toast('Удалено', 'ok');
        await this.loadTemplates();
      } catch (_) {}
    },

    async loadIntents() {
      this.intentsLoading = true;
      try {
        const data = await SEO.api('intents?profile_id=' + this.current.id);
        const list = Array.isArray(data) ? data : (data || []);
        this.customIntents = list.filter(i => i.profile_id !== null);
        this.globalIntents = list.filter(i => i.profile_id === null);
      } catch (_) { this.customIntents = []; this.globalIntents = []; }
      finally { this.intentsLoading = false; }
    },

    openIntentEditor(i) {
      if (i) {
        this.intent = {
          open: true, editing: i.code, saving: false,
          code: i.code, color: i.color || '#6366f1',
          label_ru: i.label_ru || '', label_en: i.label_en || '',
          description: i.description || '', gpt_hint: i.gpt_hint || '',
          article_tone: i.article_tone || '', article_open: i.article_open || '',
          is_active: i.is_active ? 1 : 0,
        };
      } else {
        this.intent = {
          open: true, editing: null, saving: false,
          code: '', color: '#6366f1',
          label_ru: '', label_en: '',
          description: '', gpt_hint: '',
          article_tone: '', article_open: '',
          is_active: 1,
        };
      }
    },

    closeIntentEditor() { this.intent.open = false; },

    async saveIntent() {
      if (!this.intent.code.trim()) { SEO.toast('Код обязателен', 'err'); return; }
      this.intent.saving = true;
      try {
        const body = {
          label_ru: this.intent.label_ru.trim(),
          label_en: this.intent.label_en.trim(),
          color: this.intent.color,
          description: this.intent.description.trim(),
          gpt_hint: this.intent.gpt_hint.trim(),
          article_tone: this.intent.article_tone.trim() || null,
          article_open: this.intent.article_open.trim() || null,
          is_active: this.intent.is_active ? 1 : 0,
          profile_id: this.current.id,
        };
        if (this.intent.editing) {
          await SEO.api('intents/' + this.intent.editing, { method: 'PUT', body });
        } else {
          body.code = this.intent.code.trim();
          await SEO.api('intents', { method: 'POST', body });
        }
        SEO.toast('Сохранено', 'ok');
        this.intent.open = false;
        await this.loadIntents();
      } finally { this.intent.saving = false; }
    },

    async deleteIntent(i) {
      if (!confirm('Удалить интент "' + i.code + '"?')) return;
      try {
        await SEO.api('intents/' + i.code, { method: 'DELETE' });
        SEO.toast('Удалено', 'ok');
        await this.loadIntents();
      } catch (_) {}
    },

    async aiGenerateIntents() {
      this.aiIntentsRunning = true;
      try {
        const niche = this.current.niche || this.current.name || '';
        const res = await SEO.api('profiles/' + this.current.id + '/generate-intents', {
          method: 'POST', body: { niche },
        });
        SEO.toast('Сгенерировано: ' + (res && res.count != null ? res.count : '?'), 'ok');
        await this.loadIntents();
      } finally { this.aiIntentsRunning = false; }
    },

    async fillTelegram() {
      const p = this.current;
      let rb = p.tg_render_blocks;
      if (typeof rb === 'string') { try { rb = JSON.parse(rb); } catch (_) { rb = []; } }
      if (!Array.isArray(rb)) rb = [];
      this.telegram = {
        tg_bot_token: p.tg_bot_token || '',
        tg_channel_id: p.tg_channel_id || '',
        tg_post_format: p.tg_post_format || 'auto',
        tg_render_blocks: rb,
      };
      if (p.tg_channel_name) {
        this.tgChannel = { name: p.tg_channel_name, avatar: p.tg_channel_avatar || null, meta: '' };
      } else {
        this.tgChannel = { name: '', avatar: null, meta: '' };
      }
      if (this.blockTypes.length === 0) {
        try {
          const data = await SEO.api('block-types');
          this.blockTypes = Array.isArray(data) ? data : (data || []);
        } catch (_) {}
      }
    },

    toggleRenderBlock(code, on) {
      const src = this.telegram.tg_render_blocks;
      const arr = Array.isArray(src) ? src.slice() : [];
      const idx = arr.indexOf(code);
      if (on && idx === -1) arr.push(code);
      if (!on && idx !== -1) arr.splice(idx, 1);
      this.telegram.tg_render_blocks = arr;
    },

    renderAllBlocks(on) {
      this.telegram.tg_render_blocks = on ? this.blockTypes.map(t => t.code) : [];
    },

    async testTgConnection() {
      const token = (this.telegram.tg_bot_token || '').trim();
      const ch    = (this.telegram.tg_channel_id || '').trim();
      if (!token || !ch) { SEO.toast('Заполните Bot Token и Channel ID', 'err'); return; }
      this.tgTesting = true;
      this.tgTestStatus = 'Проверка…';
      this.tgTestStatusClass = 'text-ink-500';
      try {
        const d = await SEO.api('telegram/test-connection', {
          method: 'POST', body: { bot_token: token, channel_id: ch }, silent: true,
        });
        const meta = [];
        if (d.channel_type) meta.push(d.channel_type === 'channel' ? 'Канал' : d.channel_type);
        if (d.member_count != null) meta.push(d.member_count + ' подписчиков');
        this.tgChannel = { name: d.channel_name || 'Канал', avatar: d.channel_avatar || null, meta: meta.join(' · ') };
        this.tgTestStatus = 'Подключено';
        this.tgTestStatusClass = 'text-emerald-700';
      } catch (e) {
        this.tgTestStatus = e.message || 'Ошибка';
        this.tgTestStatusClass = 'text-ember-500';
        this.tgChannel = { name: '', avatar: null, meta: '' };
      } finally { this.tgTesting = false; }
    },

    async saveTelegram() {
      this.saving = true;
      try {
        const body = {
          tg_bot_token: (this.telegram.tg_bot_token || '').trim() || null,
          tg_channel_id: (this.telegram.tg_channel_id || '').trim() || null,
          tg_post_format: this.telegram.tg_post_format || 'auto',
          tg_render_blocks: (this.telegram.tg_render_blocks || []).length > 0 ? this.telegram.tg_render_blocks : null,
        };
        const updated = await SEO.api('profiles/' + this.current.id, { method: 'PUT', body });
        Object.assign(this.current, updated);
        SEO.toast('Telegram-настройки сохранены', 'ok');
        if (body.tg_bot_token && body.tg_channel_id) {
          try { await SEO.api('telegram/refresh-channel/' + this.current.id, { method: 'POST', silent: true }); } catch (_) {}
        }
      } finally { this.saving = false; }
    },

    async loadTokens(force) {
      try {
        const data = await SEO.api('profiles/' + this.current.id + '/token-usage');
        const t = data.totals || {};
        this.tokenTotalsBoxes = [
          { label: 'Вызовов',         value: SEO.fmtNum(t.calls) },
          { label: 'Prompt токенов',  value: SEO.fmtNum(t.prompt_tokens) },
          { label: 'Completion токенов', value: SEO.fmtNum(t.completion_tokens) },
          { label: 'Всего токенов',   value: SEO.fmtNum(t.total_tokens) },
          { label: 'Стоимость USD',   value: this.formatCost(t.cost_usd), cls: 'text-emerald-700' },
        ];
        const cats = data.categories || {};
        this.tokenCategoriesList = Object.keys(TOK_CAT_LABELS).map(k => {
          const c = cats[k] || { calls:0, prompt_tokens:0, completion_tokens:0, total_tokens:0, cost_usd:0 };
          return Object.assign({ key: k, label: TOK_CAT_LABELS[k], icon: TOK_CAT_ICONS[k] || '•' }, c);
        });
        this.tokenRecent = data.recent || [];
      } catch (_) {
        this.tokenTotalsBoxes = []; this.tokenCategoriesList = []; this.tokenRecent = [];
      }
    },

    formatCost(c) {
      const n = Number(c) || 0;
      return '$' + n.toFixed(n < 1 ? 4 : 2);
    },

    async deleteCurrent() {
      if (!confirm('Удалить профиль "' + this.current.name + '"? Все связанные данные могут быть потеряны.')) return;
      try {
        await SEO.api('profiles/' + this.current.id + '?force=1', { method: 'DELETE' });
        SEO.toast('Профиль удалён', 'ok');
        this.goToList();
      } catch (_) {}
    },

    // ============= WIZARD =============

    openWizard() {
      this.wizard = {
        open: true, step: 1,
        desc: '', name: '', slug: '', niche: '', brand_name: '',
        language: 'ru', tone: 'professional',
        gpt_persona: '', gpt_rules: '',
        domain: '', logo_url: '', base_url: '',
        color_scheme: '#171511',
        default_theme_code: '',
        iconFile: null, iconPreview: '',
        aiRunning: false, creating: false,
      };
    },

    closeWizard() { this.wizard.open = false; },

    wizardBack() { if (this.wizard.step > 1) this.wizard.step--; },

    wizardNext() {
      if (this.wizard.step === 1) {
        if (!this.wizard.name.trim()) { SEO.toast('Укажите название', 'err'); return; }
        if (!this.wizard.slug.trim()) this.wizard.slug = this.slugify(this.wizard.name);
        this.wizard.step = 2;
      } else if (this.wizard.step === 2) {
        this.wizard.step = 3;
      } else {
        this.createProfileFromWizard();
      }
    },

    previewWizardIcon(input) {
      if (!input.files || !input.files[0]) return;
      this.wizard.iconFile = input.files[0];
      this.wizard.iconPreview = URL.createObjectURL(input.files[0]);
    },

    async aiGenerateProfile() {
      const desc = this.wizard.desc.trim();
      if (!desc) { SEO.toast('Опишите проект', 'err'); return; }
      this.wizard.aiRunning = true;
      try {
        const res = await SEO.api('profiles/generate-from-description', { method: 'POST', body: { description: desc } });
        const p = (res && res.profile) || res || {};
        if (p.name)         this.wizard.name = p.name;
        if (p.slug)         this.wizard.slug = p.slug;
        if (p.niche)        this.wizard.niche = p.niche;
        if (p.brand_name)   this.wizard.brand_name = p.brand_name;
        if (p.language)     this.wizard.language = p.language;
        if (p.tone)         this.wizard.tone = p.tone;
        if (p.gpt_persona)  this.wizard.gpt_persona = p.gpt_persona;
        if (p.gpt_rules)    this.wizard.gpt_rules = p.gpt_rules;
        if (p.color_scheme) this.wizard.color_scheme = p.color_scheme;
        SEO.toast('AI заполнил поля!', 'ok');
      } finally { this.wizard.aiRunning = false; }
    },

    async createProfileFromWizard() {
      this.wizard.creating = true;
      try {
        const body = {
          name: this.wizard.name.trim(),
          slug: this.wizard.slug.trim() || this.slugify(this.wizard.name),
          domain: this.wizard.domain || null,
          niche: this.wizard.niche || null,
          brand_name: this.wizard.brand_name || null,
          language: this.wizard.language,
          tone: this.wizard.tone,
          color_scheme: this.wizard.color_scheme || '#171511',
          default_theme_code: this.wizard.default_theme_code || null,
          logo_url: this.wizard.logo_url || null,
          base_url: this.wizard.base_url || null,
          gpt_persona: this.wizard.gpt_persona || null,
          gpt_rules: this.wizard.gpt_rules || null,
        };
        const created = await SEO.api('profiles', { method: 'POST', body });
        const newId = created.id;
        if (this.wizard.iconFile) {
          const fd = new FormData(); fd.append('icon', this.wizard.iconFile);
          try { await SEO.api('profiles/' + newId + '/icon', { method: 'POST', body: fd, silent: true }); } catch (_) {}
        }
        SEO.toast('Профиль создан!', 'ok');
        this.wizard.open = false;
        await this.loadProfiles();
        setTimeout(() => this.openWorkspace(newId), 200);
      } finally { this.wizard.creating = false; }
    },

    slugify(s) {
      const map = { 'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'yo','ж':'zh','з':'z','и':'i','й':'i','к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r','с':'s','т':'t','у':'u','ф':'f','х':'h','ц':'c','ч':'ch','ш':'sh','щ':'sch','ы':'y','э':'e','ю':'yu','я':'ya','ъ':'','ь':'' };
      return String(s || '').toLowerCase().split('').map(c => map[c] !== undefined ? map[c] : c).join('')
        .replace(/[^a-z0-9-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60);
    },

    // ============= AI TEMPLATE GEN (SSE) =============

    hasBriefData() {
      const b = this.current && this.current.content_brief;
      return !!(b && typeof b === 'object' && Object.keys(b).length > 0);
    },

    openGenModalFromBrief() {
      const brief = (this.current && this.current.content_brief) || {};
      const niche = (brief.classify && brief.classify.niche) || (this.current && this.current.niche) || '';
      const icp = (brief.audience && (brief.audience.label || brief.audience.archetype)) || '';
      const parts = [];
      if (niche) parts.push('Шаблон статьи для ниши «' + niche + '»');
      if (icp) parts.push('целевая аудитория: ' + icp);
      const purpose = parts.join(', ') || 'Универсальный шаблон по брифу профиля';
      this.openGenModal();
      this.gen.purpose = purpose;
      this.gen.hints = 'Используй данные из брифа: УТП, голос бренда, конкурентов, compliance-ограничения.';
    },

    openGenModal() {
      this.gen = {
        open: true, running: false,
        purpose: '', hints: '',
        briefSummary: this.buildBriefSummary(),
        steps: [
          { label: 'Генерация шаблона', state: 'idle', status: '' },
          { label: 'Ревью качества',    state: 'idle', status: '' },
          { label: 'Сохранение',        state: 'idle', status: '' },
        ],
        preview: null,
        suggestions: [],
        savedId: null,
      };
    },

    closeGenModal() {
      if (this.gen.running && !confirm('Генерация в процессе. Закрыть?')) return;
      this.gen.open = false;
      if (this.gen.savedId) this.loadTemplates();
    },

    buildBriefSummary() {
      const brief = this.current && this.current.content_brief;
      if (!brief || typeof brief !== 'object' || Object.keys(brief).length === 0) return '';
      const rows = [];
      if (brief.classify && brief.classify.niche) rows.push('<div><b>Ниша:</b> ' + SEO.esc(brief.classify.niche) + (brief.classify.regulated ? ' · регулируемая' : '') + '</div>');
      if (brief.audience && brief.audience.label) rows.push('<div><b>ICP:</b> ' + SEO.esc(brief.audience.label) + '</div>');
      if (Array.isArray(brief.usps) && brief.usps.length) {
        const h = brief.usps.map(u => SEO.esc(u.headline || u.label || '')).filter(Boolean).slice(0, 3).join(' · ');
        if (h) rows.push('<div><b>УТП:</b> ' + h + '</div>');
      }
      if (brief.voice && (brief.voice.label || brief.voice.archetype)) rows.push('<div><b>Голос:</b> ' + SEO.esc(brief.voice.label || brief.voice.archetype) + '</div>');
      if (Array.isArray(brief.competitors) && brief.competitors.length) {
        const n = brief.competitors.map(c => SEO.esc(c.name || c.label || '')).filter(Boolean).slice(0, 3).join(', ');
        if (n) rows.push('<div><b>Конкуренты:</b> ' + n + '</div>');
      }
      return rows.join('');
    },

    async startGen() {
      const purpose = (this.gen.purpose || '').trim();
      if (!purpose) { SEO.toast('Опишите назначение шаблона', 'err'); return; }
      this.gen.running = true;
      this.gen.preview = null;
      this.gen.suggestions = [];
      this.gen.savedId = null;
      this.gen.steps.forEach(s => { s.state = 'idle'; s.status = ''; });

      try {
        const response = await fetch('/controllers/router.php?r=profiles/' + this.current.id + '/generate-template-sse', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ purpose, hints: (this.gen.hints || '').trim() || null }),
        });
        if (!response.ok || !response.body) {
          throw new Error('HTTP ' + response.status);
        }
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
          const { value, done } = await reader.read();
          if (done) break;
          buffer += decoder.decode(value, { stream: true });
          const lines = buffer.split('\n');
          buffer = lines.pop();
          let eventName = '';
          for (const line of lines) {
            if (line.startsWith('event: ')) {
              eventName = line.substring(7).trim();
            } else if (line.startsWith('data: ') && eventName) {
              try {
                const data = JSON.parse(line.substring(6));
                this.handleGenEvent(eventName, data);
              } catch (_) {}
              eventName = '';
            }
          }
        }
      } catch (e) {
        SEO.toast('Ошибка: ' + e.message, 'err');
      } finally {
        this.gen.running = false;
      }
    },

    handleGenEvent(event, data) {
      const s = this.gen.steps;
      switch (event) {
        case 'start':
        case 'generation_start':
          s[0].state = 'active'; s[0].status = '<span class="spinner"></span> AI подбирает блоки…';
          break;
        case 'generation_done':
          s[0].state = 'done'; s[0].status = 'Готово';
          if (data.template) this.gen.preview = data.template;
          break;
        case 'review_start':
          s[1].state = 'active'; s[1].status = '<span class="spinner"></span> AI проверяет качество…';
          break;
        case 'review_done': {
          s[1].state = 'done';
          const review = data.review || {};
          const score = review.score || 0;
          const color = score >= 8 ? '#047857' : score >= 5 ? '#92400e' : '#991b1b';
          s[1].status = '<span style="color:' + color + ';font-weight:700">' + score + '/10</span>';
          if (review.suggestions && review.suggestions.length) this.gen.suggestions = review.suggestions;
          if (data.template && data.template.blocks) this.gen.preview = data.template;
          break;
        }
        case 'save_start':
          s[2].state = 'active'; s[2].status = '<span class="spinner"></span> Сохранение…';
          break;
        case 'save_done':
          s[2].state = 'done'; s[2].status = 'Шаблон #' + data.template_id;
          this.gen.savedId = data.template_id;
          SEO.toast('Шаблон создан!', 'ok');
          break;
        case 'done':
          if (data.usage && data.usage.total_tokens) {
            s[2].status += ' · ' + data.usage.total_tokens + ' токенов';
          }
          break;
        case 'error':
          s.forEach(st => { if (st.state === 'active') { st.state = 'error'; st.status = data.message || 'Ошибка'; } });
          SEO.toast('Ошибка: ' + (data.message || 'Неизвестная ошибка'), 'err');
          this.gen.running = false;
          break;
      }
    },
  };
}
</script>

<?php include __DIR__ . '/_layout/footer.php'; ?>
