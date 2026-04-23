<?php
require_once __DIR__ . '/../auth.php';
requireAuth();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Статьи — SEO Studio</title>
<style>
:root {
    --bg: #f4f6f9;
    --surface: #ffffff;
    --border: #e8ecf1;
    --border-light: #f1f4f8;
    --text: #1a2332;
    --text-2: #5a6a7a;
    --text-3: #8a9aab;
    --accent: #5b5bd6;
    --accent-light: #ede9fe;
    --accent-hover: #4f4cc0;
    --success: #16a34a;
    --success-light: #dcfce7;
    --danger: #dc2626;
    --danger-light: #fee2e2;
    --warn: #d97706;
    --warn-light: #fef3c7;
    --shadow-sm: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
    --shadow: 0 4px 12px rgba(0,0,0,.06), 0 1px 3px rgba(0,0,0,.04);
    --radius: 10px;
    --radius-sm: 6px;
    --radius-lg: 14px;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; font-size: 14px; line-height: 1.5; }

/* ─── Topbar ─── */
.topbar { background: var(--surface); border-bottom: 1px solid var(--border); padding: 0 24px; height: 56px; display: flex; align-items: center; justify-content: space-between; gap: 16px; position: sticky; top: 0; z-index: 100; }
.topbar-brand { display: flex; align-items: center; gap: 10px; }
.topbar-brand-logo { width: 30px; height: 30px; background: var(--accent); border-radius: 8px; display: flex; align-items: center; justify-content: center; }
.topbar-brand-logo svg { width: 16px; height: 16px; color: #fff; }
.topbar-brand-name { font-weight: 700; font-size: 15px; color: var(--text); letter-spacing: -.2px; }
.topbar-brand-sep { color: var(--border); margin: 0 4px; }
.topbar-page { font-size: 13px; color: var(--text-2); }
.topbar-right { display: flex; align-items: center; gap: 8px; }
.topbar-nav-link { font-size: 13px; color: var(--text-2); text-decoration: none; padding: 6px 12px; border-radius: var(--radius-sm); transition: .15s; }
.topbar-nav-link:hover { background: var(--bg); color: var(--text); }
.btn-logout { color: var(--danger) !important; }

/* ─── Layout ─── */
.page { max-width: 1180px; margin: 0 auto; padding: 28px 24px; }

/* ─── Buttons ─── */
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border: 1px solid transparent; border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; cursor: pointer; transition: .15s; text-decoration: none; white-space: nowrap; font-family: inherit; }
.btn:disabled { opacity: .45; cursor: not-allowed; }
.btn-primary { background: var(--accent); color: #fff; border-color: var(--accent); }
.btn-primary:hover:not(:disabled) { background: var(--accent-hover); border-color: var(--accent-hover); }
.btn-secondary { background: var(--surface); color: var(--text-2); border-color: var(--border); }
.btn-secondary:hover:not(:disabled) { background: var(--bg); color: var(--text); }
.btn-ghost { background: transparent; color: var(--text-2); border-color: transparent; }
.btn-ghost:hover:not(:disabled) { background: var(--bg); color: var(--text); }
.btn-danger { background: var(--danger); color: #fff; border-color: var(--danger); }
.btn-sm { padding: 5px 12px; font-size: 12px; }
.btn-xs { padding: 3px 8px; font-size: 11px; }

/* ─── Page header ─── */
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; gap: 12px; flex-wrap: wrap; }
.page-header-title { font-size: 22px; font-weight: 700; color: var(--text); letter-spacing: -.3px; }
.page-header-sub { font-size: 13px; color: var(--text-2); margin-top: 2px; }

/* ─── Filters ─── */
.filters { display: flex; gap: 10px; align-items: center; margin-bottom: 16px; flex-wrap: wrap; }
.filters input, .filters select { padding: 8px 12px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-sm); color: var(--text); font-size: 13px; font-family: inherit; outline: none; transition: border .15s; }
.filters input:focus, .filters select:focus { border-color: var(--accent); }
.filters input { flex: 1; min-width: 200px; }

/* ─── Articles table ─── */
.articles-table-wrap { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-sm); overflow: hidden; }
.articles-table { width: 100%; border-collapse: collapse; }
.articles-table th { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; color: var(--text-3); padding: 10px 14px; text-align: left; border-bottom: 1px solid var(--border); background: var(--bg); }
.articles-table td { padding: 12px 14px; border-bottom: 1px solid var(--border-light); vertical-align: middle; font-size: 13px; color: var(--text-2); }
.articles-table tr:last-child td { border-bottom: none; }
.articles-table tr:hover td { background: #fafbfc; cursor: pointer; }
.articles-table .a-title { font-weight: 600; color: var(--text); }
.articles-table .a-slug { font-size: 11px; color: var(--text-3); font-family: 'SF Mono', monospace; margin-top: 2px; }
.badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 100px; font-size: 11px; font-weight: 600; }
.badge-success { background: var(--success-light); color: var(--success); }
.badge-info { background: #dbeafe; color: #1d4ed8; }
.badge-muted { background: var(--bg); color: var(--text-3); border: 1px solid var(--border); }

/* ─── Editor header ─── */
.ed-header { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); padding: 18px 24px; margin-bottom: 20px; display: flex; align-items: center; gap: 14px; }
.ed-back { background: none; border: 1px solid var(--border); border-radius: var(--radius-sm); width: 34px; height: 34px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--text-2); transition: .15s; flex-shrink: 0; }
.ed-back:hover { background: var(--bg); color: var(--text); }
.ed-info { flex: 1; min-width: 0; }
.ed-title { font-size: 17px; font-weight: 700; color: var(--text); }
.ed-meta { font-size: 12px; color: var(--text-3); margin-top: 2px; }
.ed-actions { display: flex; gap: 8px; flex-shrink: 0; flex-wrap: wrap; }

/* ─── Section ─── */
.section { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-sm); margin-bottom: 16px; overflow: hidden; }
.section-head { padding: 14px 20px; border-bottom: 1px solid var(--border-light); display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.section-head-title { font-size: 13px; font-weight: 700; color: var(--text); }
.section-body { padding: 20px; }
.section-foot { padding: 12px 20px; border-top: 1px solid var(--border-light); display: flex; justify-content: flex-end; gap: 8px; background: var(--bg); }

/* ─── Forms ─── */
.field { margin-bottom: 16px; }
.field:last-child { margin-bottom: 0; }
.field label { display: block; font-size: 12px; font-weight: 600; color: var(--text-2); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 6px; }
.field input[type="text"], .field input[type="url"], .field input[type="number"], .field textarea, .field select {
    width: 100%; padding: 9px 12px; background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius-sm);
    color: var(--text); font-size: 13px; font-family: inherit; outline: none; transition: border .15s;
}
.field input:focus, .field textarea:focus, .field select:focus { border-color: var(--accent); background: var(--surface); }
.field textarea { resize: vertical; min-height: 70px; line-height: 1.5; }
.field-hint { font-size: 11.5px; color: var(--text-3); margin-top: 4px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 640px) { .form-row { grid-template-columns: 1fr; } }

/* ─── Generate card ─── */
.gen-card { background: linear-gradient(135deg, #faf5ff 0%, #ede9fe 100%); border: 1px solid #d8cff8; border-radius: var(--radius); padding: 18px 22px; display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
.gen-card-icon { width: 46px; height: 46px; background: var(--accent); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 22px; flex-shrink: 0; box-shadow: 0 4px 10px rgba(91,91,214,.3); }
.gen-card-body { flex: 1; min-width: 0; }
.gen-card-title { font-size: 14px; font-weight: 700; color: var(--text); }
.gen-card-desc { font-size: 12.5px; color: var(--text-2); margin-top: 2px; }

/* ─── Blocks list ─── */
.block-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); margin-bottom: 12px; overflow: hidden; }
.block-card-head { padding: 12px 18px; background: var(--bg); border-bottom: 1px solid var(--border-light); display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; }
.block-sort { font-size: 11px; font-weight: 700; color: var(--text-3); width: 22px; text-align: center; flex-shrink: 0; }
.block-type { font-size: 11px; font-weight: 600; padding: 2px 9px; border-radius: 100px; background: var(--accent-light); color: var(--accent); flex-shrink: 0; }
.block-name { font-size: 13px; font-weight: 600; color: var(--text); flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.block-arrow { font-size: 11px; color: var(--text-3); transition: transform .2s; flex-shrink: 0; }
.block-card.open .block-arrow { transform: rotate(180deg); }
.block-body { padding: 16px 18px; display: none; }
.block-card.open .block-body { display: block; }

/* ─── Block tabs ─── */
.block-tabs { display: flex; gap: 4px; margin-bottom: 14px; border-bottom: 1px solid var(--border-light); }
.block-tab { padding: 7px 14px; font-size: 12px; font-weight: 600; color: var(--text-3); cursor: pointer; border-bottom: 2px solid transparent; transition: .15s; margin-bottom: -1px; }
.block-tab:hover { color: var(--text-2); }
.block-tab.active { color: var(--accent); border-bottom-color: var(--accent); }

/* ─── Chat-style regenerate ─── */
.regen-chat { margin-top: 14px; padding: 12px 14px; background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius); }
.regen-chat-label { font-size: 11px; font-weight: 600; color: var(--text-3); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 8px; }
.regen-chat-row { display: flex; gap: 8px; align-items: flex-end; }
.regen-chat-row textarea {
    flex: 1; min-height: 42px; max-height: 180px; padding: 10px 14px; border: 1px solid var(--border); border-radius: 22px;
    background: var(--surface); color: var(--text); font-size: 13px; font-family: inherit; outline: none; resize: none; line-height: 1.5;
}
.regen-chat-row textarea:focus { border-color: var(--accent); }
.regen-btn { background: var(--accent); color: #fff; border: none; width: 42px; height: 42px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: .15s; flex-shrink: 0; }
.regen-btn:hover:not(:disabled) { background: var(--accent-hover); transform: scale(1.05); }
.regen-btn:disabled { opacity: .5; cursor: not-allowed; }
.regen-btn svg { width: 18px; height: 18px; }

/* ─── Preview ─── */
.preview-frame { border: 1px solid var(--border); border-radius: var(--radius-sm); background: #fff; min-height: 120px; padding: 14px; overflow: auto; }
.preview-empty { text-align: center; color: var(--text-3); padding: 30px 10px; font-size: 13px; }

/* ─── Image box ─── */
.img-box { display: flex; gap: 14px; align-items: flex-start; padding: 14px; background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius-sm); margin-top: 14px; }
.img-preview { width: 120px; height: 120px; background: var(--surface); border: 1px dashed var(--border); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; color: var(--text-3); font-size: 11px; text-align: center; }
.img-preview img { width: 100%; height: 100%; object-fit: cover; }
.img-ctrl { flex: 1; min-width: 0; }
.img-ctrl-title { font-size: 12px; font-weight: 700; color: var(--text); margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
.img-ctrl-title .badge { font-size: 10px; background: #fef3c7; color: #92400e; }

/* ─── Toast ─── */
.toast-wrap { position: fixed; bottom: 24px; right: 24px; z-index: 500; display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
.toast { background: var(--text); color: #fff; padding: 10px 18px; border-radius: var(--radius-sm); font-size: 13px; box-shadow: var(--shadow); opacity: 0; transform: translateY(8px); transition: .25s; max-width: 360px; }
.toast.show { opacity: 1; transform: translateY(0); }
.toast.ok { background: var(--success); }
.toast.err { background: var(--danger); }
.toast.info { background: var(--accent); }

/* ─── Spinner ─── */
.spin { display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(0,0,0,.15); border-top-color: currentColor; border-radius: 50%; animation: spin .6s linear infinite; vertical-align: middle; }
.spin-white { border-color: rgba(255,255,255,.35); border-top-color: #fff; }
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes pulse { 0%,100%{opacity:1}50%{opacity:.4} }

/* ─── SSE progress ─── */
.ai-progress { background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 14px 16px; margin-top: 14px; max-height: 240px; overflow-y: auto; }
.ai-step { display: flex; align-items: center; gap: 10px; padding: 5px 0; font-size: 12.5px; color: var(--text-2); }
.ai-step-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--border); flex-shrink: 0; }
.ai-step.active .ai-step-dot { background: var(--accent); animation: pulse .8s ease infinite; }
.ai-step.done .ai-step-dot { background: var(--success); }
.ai-step.error .ai-step-dot { background: var(--danger); }
.ai-step.active { color: var(--text); font-weight: 600; }

/* ─── Empty ─── */
.empty { text-align: center; padding: 48px 20px; color: var(--text-3); }
.empty-icon { font-size: 36px; opacity: .5; margin-bottom: 12px; }
.empty-title { font-size: 15px; font-weight: 600; color: var(--text-2); margin-bottom: 6px; }
.empty-sub { font-size: 13px; }

/* ─── Settings row ─── */
.meta-pair { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--border-light); font-size: 13px; }
.meta-pair:last-child { border-bottom: none; }
.meta-pair .k { color: var(--text-3); font-size: 12px; text-transform: uppercase; letter-spacing: .4px; font-weight: 600; }
.meta-pair .v { color: var(--text); font-weight: 600; font-family: 'SF Mono', monospace; font-size: 12.5px; }

/* ─── Image modal controls ─── */
.img-model-pill { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; background: var(--accent-light); color: var(--accent); border-radius: 100px; font-size: 11px; font-weight: 700; margin-bottom: 6px; }
</style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
    <div class="topbar-brand">
        <div class="topbar-brand-logo">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M2 12L6 4l4 6 2-3 3 4"/>
            </svg>
        </div>
        <span class="topbar-brand-name">SEO Studio</span>
        <span class="topbar-brand-sep">›</span>
        <span class="topbar-page" id="topbarPage">Статьи</span>
    </div>
    <div class="topbar-right">
        <a href="profiles.php" class="topbar-nav-link">Профили</a>
        <a href="../admin_advanced/seo_page.php" class="topbar-nav-link" style="color:var(--accent);font-weight:600">⚡ Расширенная версия</a>
        <a href="../logout.php" class="topbar-nav-link btn-logout">Выйти</a>
    </div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<!-- ─── List view ─── -->
<div class="page" id="listView">
    <div class="page-header">
        <div>
            <div class="page-header-title">Статьи</div>
            <div class="page-header-sub" id="listSubtitle">Загрузка...</div>
        </div>
    </div>
    <div class="filters">
        <input type="text" id="filterSearch" placeholder="Поиск по заголовку...">
        <select id="filterProfile"><option value="">Все профили</option></select>
        <select id="filterSort">
            <option value="updated">Недавно обновлённые</option>
            <option value="created">Недавно созданные</option>
            <option value="title">По названию</option>
            <option value="published">По публикации</option>
        </select>
    </div>
    <div id="listContainer">
        <div class="empty"><div class="spin"></div></div>
    </div>
</div>

<!-- ─── Editor view ─── -->
<div class="page" id="editorView" style="display:none">
    <div class="ed-header">
        <button class="ed-back" onclick="showList()" title="Назад">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M10 3L5 8l5 5"/>
            </svg>
        </button>
        <div class="ed-info">
            <div class="ed-title" id="edTitle">—</div>
            <div class="ed-meta" id="edMeta">—</div>
        </div>
        <div class="ed-actions">
            <button class="btn btn-secondary btn-sm" onclick="openPublicPage()" id="btnViewPage">👁 Открыть</button>
        </div>
    </div>

    <!-- Main -->
    <div class="section">
        <div class="section-head"><span class="section-head-title">Основные данные</span></div>
        <div class="section-body">
            <div class="form-row">
                <div class="field">
                    <label>Заголовок</label>
                    <input type="text" id="fTitle" placeholder="Заголовок статьи">
                </div>
                <div class="field">
                    <label>Slug (URL)</label>
                    <input type="text" id="fSlug" placeholder="url-slug">
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <label>Профиль</label>
                    <input type="text" id="fProfile" disabled>
                </div>
                <div class="field">
                    <label>Шаблон</label>
                    <input type="text" id="fTemplate" disabled>
                </div>
            </div>
        </div>
        <div class="section-foot">
            <button class="btn btn-primary btn-sm" onclick="saveMain()">
                <span id="saveMainSpin"></span> Сохранить
            </button>
        </div>
    </div>

    <!-- SEO Meta -->
    <div class="section">
        <div class="section-head"><span class="section-head-title">SEO Meta</span></div>
        <div class="section-body">
            <div class="field">
                <label>Meta Title</label>
                <input type="text" id="fMetaTitle" placeholder="SEO title для &lt;title&gt; и OG">
            </div>
            <div class="field">
                <label>Meta Description</label>
                <textarea id="fMetaDesc" rows="3" placeholder="Описание для поисковиков (до ~160 символов)"></textarea>
            </div>
        </div>
        <div class="section-foot">
            <button class="btn btn-primary btn-sm" onclick="saveMeta()">
                <span id="saveMetaSpin"></span> Сохранить Meta
            </button>
        </div>
    </div>

    <!-- Generate -->
    <div class="gen-card">
        <div class="gen-card-icon">✨</div>
        <div class="gen-card-body">
            <div class="gen-card-title">AI-генерация контента</div>
            <div class="gen-card-desc">Одна кнопка сгенерирует все блоки и SEO meta-теги статьи.</div>
        </div>
        <button class="btn btn-primary" id="btnGenerate" onclick="generateAll()">
            <span id="genSpin"></span> Сгенерировать всё
        </button>
    </div>
    <div id="genProgress" style="display:none" class="section">
        <div class="section-head"><span class="section-head-title">Генерация...</span></div>
        <div class="section-body"><div class="ai-progress" id="genSteps"></div></div>
    </div>

    <!-- Blocks -->
    <div class="section">
        <div class="section-head">
            <span class="section-head-title">Блоки контента</span>
            <span style="font-size:12px;color:var(--text-3)" id="blocksCount"></span>
        </div>
        <div class="section-body">
            <div id="blocksList"></div>
        </div>
    </div>

    <!-- Article settings -->
    <div class="section">
        <div class="section-head"><span class="section-head-title">Настройки статьи</span></div>
        <div class="section-body">
            <div class="field">
                <label>Опубликованный URL</label>
                <input type="url" id="fPublishedUrl" placeholder="https://...">
            </div>
            <div style="margin-top:6px">
                <div class="meta-pair"><span class="k">Версия</span><span class="v" id="metaVersion">—</span></div>
                <div class="meta-pair"><span class="k">Токенов потрачено</span><span class="v" id="metaTokens">—</span></div>
                <div class="meta-pair"><span class="k">GPT модель</span><span class="v" id="metaModel">—</span></div>
            </div>
        </div>
        <div class="section-foot">
            <button class="btn btn-primary btn-sm" onclick="saveUrl()">
                <span id="saveUrlSpin"></span> Сохранить URL
            </button>
        </div>
    </div>
</div>

<script>
const API = '../controllers/router.php?r=';

const S = {
    articles: [],
    article: null,
    profiles: [],
    blockTypes: [],
    openBlockId: null,
    blockTabs: {},
};

const IMAGE_MODEL = 'gemini-2.5-flash-image';
const IMAGE_MODEL_LABEL = 'Nano Banana';

function el(id) { return document.getElementById(id); }

function esc(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function toast(msg, type='') {
    const wrap = el('toastWrap');
    const t = document.createElement('div');
    t.className = 'toast' + (type ? ' ' + type : '');
    t.textContent = msg;
    wrap.appendChild(t);
    setTimeout(() => t.classList.add('show'), 10);
    setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 3000);
}

async function api(path, method='GET', body=null) {
    const opts = { method, headers: {} };
    if (body !== null && body !== undefined) {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(body);
    }
    const res = await fetch(API + path, opts);
    const data = await res.json();
    if (!data.success) throw new Error(data.error || 'Ошибка API');
    return data;
}

// ─── View switching ───
function showList() {
    el('listView').style.display = '';
    el('editorView').style.display = 'none';
    el('topbarPage').textContent = 'Статьи';
    S.article = null;
    loadArticles();
}

function showEditor() {
    el('listView').style.display = 'none';
    el('editorView').style.display = '';
}

// ─── List ───
async function loadProfiles() {
    try {
        const res = await api('profiles');
        S.profiles = res.data || [];
        const sel = el('filterProfile');
        sel.innerHTML = '<option value="">Все профили</option>' +
            S.profiles.map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join('');
    } catch(e) { /* ignore */ }
}

async function loadBlockTypes() {
    try {
        const res = await api('block-types');
        S.blockTypes = res.data || [];
    } catch(e) { /* ignore */ }
}

function blockTypeMeta(code) {
    return S.blockTypes.find(b => b.code === code) || null;
}

async function loadArticles() {
    el('listContainer').innerHTML = '<div class="empty"><div class="spin"></div></div>';
    const params = new URLSearchParams();
    const q = el('filterSearch').value.trim();
    const pid = el('filterProfile').value;
    const sort = el('filterSort').value;
    if (q) params.set('q', q);
    if (pid) params.set('profile_id', pid);
    if (sort) params.set('sort', sort);
    params.set('per_page', '100');
    try {
        const res = await api('articles?' + params.toString());
        S.articles = res.data || [];
        renderList(res.meta && res.meta.total);
    } catch(e) {
        el('listContainer').innerHTML = '<div class="empty"><div class="empty-icon">⚠️</div><div class="empty-title">Ошибка</div><div class="empty-sub">' + esc(e.message) + '</div></div>';
    }
}

function renderList(total) {
    el('listSubtitle').textContent = (total != null ? total : S.articles.length) + ' статей';
    if (!S.articles.length) {
        el('listContainer').innerHTML = '<div class="empty"><div class="empty-icon">📝</div><div class="empty-title">Нет статей</div><div class="empty-sub">Попробуйте изменить фильтры</div></div>';
        return;
    }
    const rows = S.articles.map(a => {
        const statusBadge = a.status === 'published'
            ? '<span class="badge badge-success">Опубликовано</span>'
            : (a.status === 'draft' ? '<span class="badge badge-muted">Черновик</span>'
               : '<span class="badge badge-info">' + esc(a.status || '—') + '</span>');
        const profile = (S.profiles.find(p => p.id === a.profile_id) || {}).name || '—';
        const updated = a.updated_at ? a.updated_at.substring(0, 10) : '—';
        return `
        <tr onclick="openArticle(${a.id})">
            <td><div class="a-title">${esc(a.title || '—')}</div><div class="a-slug">${esc(a.slug || '')}</div></td>
            <td>${esc(profile)}</td>
            <td>${esc(a.template_name || '—')}</td>
            <td>${statusBadge}</td>
            <td>${updated}</td>
        </tr>`;
    }).join('');
    el('listContainer').innerHTML = `
    <div class="articles-table-wrap">
        <table class="articles-table">
            <thead><tr>
                <th>Заголовок</th><th>Профиль</th><th>Шаблон</th><th>Статус</th><th>Обновлено</th>
            </tr></thead>
            <tbody>${rows}</tbody>
        </table>
    </div>`;
}

el('filterSearch').addEventListener('input', debounce(loadArticles, 300));
el('filterProfile').addEventListener('change', loadArticles);
el('filterSort').addEventListener('change', loadArticles);

function debounce(fn, ms) {
    let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
}

// ─── Open article ───
async function openArticle(id) {
    try {
        const res = await api('articles/' + id);
        S.article = res.data;
        renderEditor();
        showEditor();
    } catch(e) {
        toast(e.message, 'err');
    }
}

function renderEditor() {
    const a = S.article;
    if (!a) return;

    el('topbarPage').textContent = a.title || 'Статья #' + a.id;
    el('edTitle').textContent = a.title || '—';
    el('edMeta').textContent = (a.template_name || 'без шаблона') + ' · v' + (a.version || 1);

    el('fTitle').value = a.title || '';
    el('fSlug').value = a.slug || '';
    const profile = (S.profiles.find(p => p.id === a.profile_id) || {}).name || '—';
    el('fProfile').value = profile;
    el('fTemplate').value = a.template_name || '—';

    el('fMetaTitle').value = a.meta_title || '';
    el('fMetaDesc').value = a.meta_description || '';

    el('fPublishedUrl').value = a.published_url || '';
    el('metaVersion').textContent = a.version || 1;
    el('metaModel').textContent = a.gpt_model || '—';
    el('metaTokens').textContent = sumTokens(a.generation_log);

    const btn = el('btnViewPage');
    if (a.published_url) {
        btn.style.display = '';
        btn.onclick = () => window.open(a.published_url, '_blank');
    } else {
        btn.style.display = 'none';
    }

    renderBlocks(a.blocks || []);
}

function sumTokens(log) {
    if (!log) return '—';
    let usage = log;
    try {
        if (typeof log === 'string') usage = JSON.parse(log);
    } catch(e) { return '—'; }
    if (!usage || typeof usage !== 'object') return '—';

    let total = 0;
    const collect = (obj) => {
        if (!obj || typeof obj !== 'object') return;
        if (typeof obj.total_tokens === 'number') { total += obj.total_tokens; return; }
        if (typeof obj.prompt_tokens === 'number' || typeof obj.completion_tokens === 'number') {
            total += (obj.prompt_tokens || 0) + (obj.completion_tokens || 0);
            return;
        }
        if (Array.isArray(obj)) { obj.forEach(collect); return; }
        Object.values(obj).forEach(collect);
    };
    collect(usage);
    return total ? total.toLocaleString('ru-RU') + ' токенов' : '—';
}

// ─── Main save ───
async function saveMain() {
    if (!S.article) return;
    const sp = el('saveMainSpin');
    sp.innerHTML = '<span class="spin spin-white"></span>';
    try {
        const res = await api('articles/' + S.article.id, 'PUT', {
            title: el('fTitle').value.trim(),
            slug: el('fSlug').value.trim(),
        });
        Object.assign(S.article, res.data);
        el('edTitle').textContent = S.article.title;
        toast('Сохранено', 'ok');
    } catch(e) { toast(e.message, 'err'); }
    sp.innerHTML = '';
}

async function saveMeta() {
    if (!S.article) return;
    const sp = el('saveMetaSpin');
    sp.innerHTML = '<span class="spin spin-white"></span>';
    try {
        const res = await api('articles/' + S.article.id, 'PUT', {
            meta_title: el('fMetaTitle').value.trim() || null,
            meta_description: el('fMetaDesc').value.trim() || null,
        });
        Object.assign(S.article, res.data);
        toast('Meta сохранены', 'ok');
    } catch(e) { toast(e.message, 'err'); }
    sp.innerHTML = '';
}

async function saveUrl() {
    if (!S.article) return;
    const sp = el('saveUrlSpin');
    sp.innerHTML = '<span class="spin spin-white"></span>';
    try {
        const res = await api('articles/' + S.article.id, 'PUT', {
            published_url: el('fPublishedUrl').value.trim() || null,
        });
        Object.assign(S.article, res.data);
        toast('URL сохранён', 'ok');
        renderEditor();
    } catch(e) { toast(e.message, 'err'); }
    sp.innerHTML = '';
}

function openPublicPage() {
    if (S.article && S.article.published_url) {
        window.open(S.article.published_url, '_blank');
    }
}

// ─── Generate all (SSE) ───
async function generateAll() {
    if (!S.article) return;
    if (!confirm('Сгенерировать все блоки и SEO meta? Это перезапишет текущий контент.')) return;

    const btn = el('btnGenerate');
    btn.disabled = true;
    el('genSpin').innerHTML = '<span class="spin spin-white"></span>';
    el('genProgress').style.display = '';
    const steps = el('genSteps');
    steps.innerHTML = '<div class="ai-step active"><div class="ai-step-dot"></div>Запуск генерации...</div>';

    const addStep = (msg, state='active') => {
        const prev = steps.querySelector('.ai-step.active');
        if (prev) prev.className = 'ai-step done';
        const d = document.createElement('div');
        d.className = 'ai-step ' + state;
        d.innerHTML = '<div class="ai-step-dot"></div>' + esc(msg);
        steps.appendChild(d);
        steps.scrollTop = steps.scrollHeight;
    };

    try {
        // 1) Блоки через SSE
        const res = await fetch(API + 'generate/' + S.article.id + '/sse', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}'
        });
        if (!res.body) throw new Error('Streaming не поддерживается');
        const reader = res.body.getReader();
        const dec = new TextDecoder();
        let buf = '';
        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            buf += dec.decode(value, { stream: true });
            const lines = buf.split('\n');
            buf = lines.pop();
            for (const line of lines) {
                if (line.startsWith('data:')) {
                    try {
                        const d = JSON.parse(line.slice(5).trim());
                        if (d.step || d.message) addStep(d.step || d.message);
                    } catch(_) {}
                }
            }
        }

        // 2) Meta
        addStep('Генерация SEO meta...');
        try {
            await api('generate/' + S.article.id + '/meta', 'POST', {});
        } catch(e) { /* non-fatal */ }

        const last = steps.querySelector('.ai-step.active');
        if (last) last.className = 'ai-step done';
        addStep('Готово', 'done');

        // Reload
        const art = await api('articles/' + S.article.id);
        S.article = art.data;
        renderEditor();
        toast('Генерация завершена', 'ok');
    } catch(e) {
        addStep(e.message, 'error');
        toast(e.message, 'err');
    }

    btn.disabled = false;
    el('genSpin').innerHTML = '';
    setTimeout(() => { el('genProgress').style.display = 'none'; }, 3000);
}

// ─── Blocks ───
function renderBlocks(blocks) {
    el('blocksCount').textContent = blocks.length + ' блоков';
    if (!blocks.length) {
        el('blocksList').innerHTML = '<div class="empty"><div class="empty-icon">🧩</div><div class="empty-title">Блоков пока нет</div><div class="empty-sub">Запустите генерацию</div></div>';
        return;
    }
    el('blocksList').innerHTML = blocks.map((b, i) => renderBlockCard(b, i)).join('');
}

function renderBlockCard(b, idx) {
    const tab = S.blockTabs[b.id] || 'form';
    const meta = blockTypeMeta(b.type);
    const displayName = (meta && meta.display_name) || b.type;
    const name = b.name || displayName;
    const isImageBlock = b.type === 'image' || (b.content && (b.content.image_id || b.content.image_layout));

    return `
    <div class="block-card ${S.openBlockId === b.id ? 'open' : ''}" id="block-${b.id}">
        <div class="block-card-head" onclick="toggleBlock(${b.id})">
            <div class="block-sort">${idx + 1}</div>
            <span class="block-type">${esc(displayName)}</span>
            <span class="block-name">${esc(name)}</span>
            <span class="block-arrow">▼</span>
        </div>
        <div class="block-body">
            <div class="block-tabs">
                <div class="block-tab ${tab === 'form' ? 'active' : ''}" onclick="setBlockTab(${b.id}, 'form')">Форма</div>
                <div class="block-tab ${tab === 'preview' ? 'active' : ''}" onclick="setBlockTab(${b.id}, 'preview')">Превью</div>
            </div>
            <div id="blockPane-${b.id}">${renderBlockPane(b, tab)}</div>
            ${renderChatRegen(b)}
            ${isImageBlock ? renderImageBox(b) : ''}
        </div>
    </div>`;
}

function toggleBlock(blockId) {
    const card = el('block-' + blockId);
    if (!card) return;
    if (S.openBlockId === blockId) {
        S.openBlockId = null;
        card.classList.remove('open');
    } else {
        if (S.openBlockId) {
            const prev = el('block-' + S.openBlockId);
            if (prev) prev.classList.remove('open');
        }
        S.openBlockId = blockId;
        card.classList.add('open');
        // Load preview lazily if it's the default tab
        const tab = S.blockTabs[blockId] || 'form';
        if (tab === 'preview') loadPreview(blockId);
    }
}

function setBlockTab(blockId, tab) {
    S.blockTabs[blockId] = tab;
    const b = (S.article.blocks || []).find(x => x.id === blockId);
    if (!b) return;
    el('blockPane-' + blockId).innerHTML = renderBlockPane(b, tab);
    if (tab === 'preview') loadPreview(blockId);
    // Refresh tab highlights
    const card = el('block-' + blockId);
    card.querySelectorAll('.block-tab').forEach((t, i) => {
        t.classList.toggle('active', (i === 0 && tab === 'form') || (i === 1 && tab === 'preview'));
    });
}

function renderBlockPane(b, tab) {
    if (tab === 'preview') {
        return `<div class="preview-frame" id="preview-${b.id}"><div class="preview-empty"><span class="spin"></span></div></div>`;
    }
    return renderFormFields(b);
}

function renderFormFields(b) {
    const content = b.content || {};
    const keys = Object.keys(content).filter(k => k !== 'image_id' && k !== 'image_layout' && k !== 'gpt_prompt');
    if (!keys.length) {
        return '<div class="field-hint">Блок не содержит редактируемых полей.</div>' +
               renderNameField(b);
    }
    let html = renderNameField(b);
    keys.forEach(k => {
        const v = content[k];
        html += renderFieldForValue(b.id, k, v);
    });
    html += '<div style="display:flex;justify-content:flex-end;gap:8px;margin-top:8px">' +
            '<button class="btn btn-primary btn-sm" onclick="saveBlockFields(' + b.id + ')"><span id="saveBlkSpin-' + b.id + '"></span> Сохранить блок</button>' +
            '</div>';
    return html;
}

function renderNameField(b) {
    return `
    <div class="field">
        <label>Название блока</label>
        <input type="text" id="blkName-${b.id}" value="${esc(b.name || '')}" placeholder="Внутреннее название">
    </div>`;
}

function renderFieldForValue(blockId, key, value) {
    const safeKey = key.replace(/[^a-zA-Z0-9_-]/g, '');
    const inputId = 'blkField-' + blockId + '-' + safeKey;
    if (value === null || value === undefined) {
        return `<div class="field"><label>${esc(key)}</label><input type="text" id="${inputId}" data-key="${esc(key)}" value=""></div>`;
    }
    if (typeof value === 'string') {
        const isLong = value.length > 80 || value.includes('\n');
        if (isLong) {
            return `<div class="field"><label>${esc(key)}</label><textarea id="${inputId}" data-key="${esc(key)}" rows="4">${esc(value)}</textarea></div>`;
        }
        return `<div class="field"><label>${esc(key)}</label><input type="text" id="${inputId}" data-key="${esc(key)}" value="${esc(value)}"></div>`;
    }
    if (typeof value === 'number' || typeof value === 'boolean') {
        return `<div class="field"><label>${esc(key)}</label><input type="text" id="${inputId}" data-key="${esc(key)}" data-type="${typeof value}" value="${esc(String(value))}"></div>`;
    }
    // object/array → json textarea
    const json = JSON.stringify(value, null, 2);
    return `<div class="field"><label>${esc(key)} <span style="font-weight:400;text-transform:none;color:var(--text-3)">(JSON)</span></label><textarea id="${inputId}" data-key="${esc(key)}" data-type="json" rows="6" style="font-family:'SF Mono',monospace;font-size:12px">${esc(json)}</textarea></div>`;
}

async function saveBlockFields(blockId) {
    const b = (S.article.blocks || []).find(x => x.id === blockId);
    if (!b) return;
    const sp = el('saveBlkSpin-' + blockId);
    if (sp) sp.innerHTML = '<span class="spin spin-white"></span>';

    const content = Object.assign({}, b.content || {});
    const pane = el('blockPane-' + blockId);
    pane.querySelectorAll('[data-key]').forEach(input => {
        const k = input.getAttribute('data-key');
        const t = input.getAttribute('data-type');
        let v = input.value;
        if (t === 'number') v = parseFloat(v);
        else if (t === 'boolean') v = (v === 'true' || v === '1');
        else if (t === 'json') {
            try { v = JSON.parse(v); } catch(e) { toast('Некорректный JSON в поле ' + k, 'err'); return; }
        }
        content[k] = v;
    });
    const name = el('blkName-' + blockId).value;

    try {
        await api('articles/' + S.article.id + '/blocks', 'PUT', {
            block_id: blockId, content, name
        });
        // Refresh article
        const res = await api('articles/' + S.article.id);
        S.article = res.data;
        const nb = (S.article.blocks || []).find(x => x.id === blockId);
        if (nb) {
            const card = el('block-' + blockId);
            const nameEl = card.querySelector('.block-name');
            if (nameEl) nameEl.textContent = nb.name || blockTypeMeta(nb.type)?.display_name || nb.type;
        }
        toast('Блок сохранён', 'ok');
    } catch(e) { toast(e.message, 'err'); }
    if (sp) sp.innerHTML = '';
}

// ─── Chat regenerate ───
function renderChatRegen(b) {
    const prompt = b.gpt_prompt || '';
    return `
    <div class="regen-chat">
        <div class="regen-chat-label">GPT-промпт · перегенерация</div>
        <div class="regen-chat-row">
            <textarea id="blkPrompt-${b.id}" placeholder="Опишите, как нужно изменить блок...">${esc(prompt)}</textarea>
            <button class="regen-btn" onclick="regenBlock(${b.id})" id="blkRegenBtn-${b.id}" title="Перегенерировать">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13"/>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
            </button>
        </div>
    </div>`;
}

async function regenBlock(blockId) {
    const b = (S.article.blocks || []).find(x => x.id === blockId);
    if (!b) return;
    const prompt = el('blkPrompt-' + blockId).value.trim();
    const btn = el('blkRegenBtn-' + blockId);
    btn.disabled = true;
    btn.innerHTML = '<span class="spin spin-white"></span>';

    try {
        // Save prompt first
        if (prompt !== (b.gpt_prompt || '')) {
            await api('articles/' + S.article.id + '/blocks', 'PUT', {
                block_id: blockId, gpt_prompt: prompt
            });
        }
        // Regenerate
        const res = await api('generate/' + S.article.id + '/block', 'POST', {
            block_id: blockId
        });
        // Refresh article
        const art = await api('articles/' + S.article.id);
        S.article = art.data;
        renderEditor();
        // Keep this block open
        S.openBlockId = blockId;
        const card = el('block-' + blockId);
        if (card) card.classList.add('open');
        toast('Блок перегенерирован', 'ok');
    } catch(e) { toast(e.message, 'err'); }

    btn.disabled = false;
    btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>`;
}

// ─── Preview ───
async function loadPreview(blockId) {
    const b = (S.article.blocks || []).find(x => x.id === blockId);
    if (!b) return;
    const pane = el('preview-' + blockId);
    if (!pane) return;
    pane.innerHTML = '<div class="preview-empty"><span class="spin"></span></div>';
    try {
        const res = await fetch(API + 'articles/render-block', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: b.type, content: b.content || {} })
        });
        const html = await res.text();
        pane.innerHTML = html || '<div class="preview-empty">Нет контента</div>';
    } catch(e) {
        pane.innerHTML = '<div class="preview-empty" style="color:var(--danger)">Ошибка: ' + esc(e.message) + '</div>';
    }
}

// ─── Image box (Nano Banana only) ───
function renderImageBox(b) {
    const imgId = b.content && b.content.image_id;
    const imgSrc = imgId ? (API + 'images/' + imgId + '/raw&_=' + Date.now()) : '';
    const hasImage = !!imgId;
    return `
    <div class="img-box">
        <div class="img-preview">
            ${hasImage ? '<img src="' + imgSrc + '" alt="">' : 'Нет изображения'}
        </div>
        <div class="img-ctrl">
            <div class="img-model-pill">🍌 ${IMAGE_MODEL_LABEL}</div>
            <div class="img-ctrl-title">Изображение блока</div>
            <div class="field" style="margin-bottom:10px">
                <textarea id="imgPrompt-${b.id}" rows="2" placeholder="Опишите изображение...">${esc((b.content && b.content.image_prompt) || '')}</textarea>
            </div>
            <button class="btn btn-primary btn-sm" onclick="genImage(${b.id})" id="imgGenBtn-${b.id}">
                <span id="imgGenSpin-${b.id}"></span> ${hasImage ? 'Перегенерировать' : 'Сгенерировать'}
            </button>
        </div>
    </div>`;
}

async function genImage(blockId) {
    const b = (S.article.blocks || []).find(x => x.id === blockId);
    if (!b) return;
    const prompt = el('imgPrompt-' + blockId).value.trim();
    const btn = el('imgGenBtn-' + blockId);
    const sp = el('imgGenSpin-' + blockId);
    btn.disabled = true;
    sp.innerHTML = '<span class="spin spin-white"></span>';

    try {
        const existing = b.content && b.content.image_id;
        let res;
        if (existing) {
            res = await api('images/' + existing + '/regenerate', 'POST', {
                custom_prompt: prompt || null,
                model: IMAGE_MODEL,
            });
        } else {
            res = await api('images/generate', 'POST', {
                article_id: S.article.id,
                block_id: blockId,
                model: IMAGE_MODEL,
                custom_prompt: prompt || null,
            });
        }
        // Refresh article
        const art = await api('articles/' + S.article.id);
        S.article = art.data;
        // re-render block
        const card = el('block-' + blockId);
        if (card) {
            const newB = (S.article.blocks || []).find(x => x.id === blockId);
            if (newB) {
                const body = card.querySelector('.block-body');
                const tab = S.blockTabs[blockId] || 'form';
                body.innerHTML = `
                <div class="block-tabs">
                    <div class="block-tab ${tab === 'form' ? 'active' : ''}" onclick="setBlockTab(${blockId}, 'form')">Форма</div>
                    <div class="block-tab ${tab === 'preview' ? 'active' : ''}" onclick="setBlockTab(${blockId}, 'preview')">Превью</div>
                </div>
                <div id="blockPane-${blockId}">${renderBlockPane(newB, tab)}</div>
                ${renderChatRegen(newB)}
                ${renderImageBox(newB)}`;
                if (tab === 'preview') loadPreview(blockId);
            }
        }
        toast('Изображение сгенерировано', 'ok');
    } catch(e) { toast(e.message, 'err'); }

    btn.disabled = false;
    sp.innerHTML = '';
}

// ─── Init ───
(async function init() {
    await Promise.all([loadProfiles(), loadBlockTypes()]);
    loadArticles();
})();
</script>
</body>
</html>
