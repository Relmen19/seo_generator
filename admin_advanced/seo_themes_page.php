<?php
require_once __DIR__ . '/../auth.php';
requireAuth();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Темы оформления</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; }
        .topbar { background: #1e293b; border-bottom: 1px solid #334155; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; }
        .topbar h1 { font-size: 1.1rem; color: #f1f5f9; }
        .topbar nav { display: flex; gap: 8px; }
        .topbar nav a { color: #94a3b8; text-decoration: none; padding: 6px 14px; border-radius: 6px; font-size: .85rem; }
        .topbar nav a:hover { background: #334155; color: #e2e8f0; }
        .topbar nav a.active { background: #6366f1; color: #fff; }
        .btn-logout { color: #f87171 !important; }

        .container { max-width: 1240px; margin: 24px auto; padding: 0 24px; display: grid; grid-template-columns: 280px 1fr; gap: 16px; }
        .side { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 12px; height: fit-content; position: sticky; top: 16px; }
        .side h2 { font-size: .85rem; color: #cbd5e1; margin-bottom: 10px; text-transform: uppercase; letter-spacing: .05em; }
        .theme-item { padding: 10px 12px; border-radius: 8px; cursor: pointer; margin-bottom: 4px; border: 1px solid transparent; }
        .theme-item:hover { background: #334155; }
        .theme-item.active { background: #312e81; border-color: #6366f1; }
        .theme-code { font-family: ui-monospace, monospace; font-size: .8rem; color: #a5b4fc; }
        .theme-name { font-size: .9rem; color: #f1f5f9; margin-top: 2px; }
        .badge { display: inline-block; font-size: .65rem; padding: 1px 6px; border-radius: 4px; background: #064e3b; color: #6ee7b7; margin-left: 6px; }
        .badge.off { background: #4c1d24; color: #fca5a5; }

        .editor { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 20px; }
        .row { display: flex; gap: 12px; margin-bottom: 14px; }
        .row > * { flex: 1; }
        label { display: block; font-size: .75rem; color: #94a3b8; margin-bottom: 4px; text-transform: uppercase; }
        input[type=text], textarea, select { width: 100%; background: #0f172a; border: 1px solid #334155; color: #e2e8f0; padding: 8px 12px; border-radius: 6px; font: inherit; font-family: ui-monospace, monospace; font-size: .85rem; }
        textarea { min-height: 360px; resize: vertical; }
        .btn { padding: 8px 16px; background: #6366f1; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: .9rem; }
        .btn:hover { background: #818cf8; }
        .btn.danger { background: #b91c1c; }
        .btn.danger:hover { background: #dc2626; }
        .btn.ghost { background: transparent; border: 1px solid #475569; color: #cbd5e1; }
        .btn.ghost:hover { background: #334155; }
        .actions { display: flex; gap: 8px; margin-top: 16px; flex-wrap: wrap; }
        .preview { display: grid; grid-template-columns: repeat(auto-fit, minmax(60px, 1fr)); gap: 6px; margin-top: 14px; }
        .swatch { aspect-ratio: 1; border-radius: 6px; border: 1px solid #334155; position: relative; }
        .swatch span { position: absolute; bottom: 2px; left: 4px; font-size: .55rem; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,.6); font-family: ui-monospace, monospace; }
        .msg { padding: 10px; background: #064e3b; color: #6ee7b7; border-radius: 6px; margin-bottom: 12px; }
        .msg.err { background: #4c1d24; color: #fca5a5; }
        .empty { color: #64748b; padding: 40px; text-align: center; }
    </style>
</head>
<body>
<div class="topbar">
    <h1>Темы оформления</h1>
    <nav>
        <a href="/admin_advanced/seo_page.php">SEO</a>
        <a href="/admin_advanced/seo_profile_page.php">Профили</a>
        <a href="/admin_advanced/seo_themes_page.php" class="active">Темы</a>
        <a href="/logout.php" class="btn-logout">Выйти</a>
    </nav>
</div>

<div class="container">
    <aside class="side">
        <h2>Темы</h2>
        <div id="themesList"></div>
        <button class="btn ghost" style="width:100%;margin-top:8px" onclick="newTheme()">+ Создать</button>
    </aside>
    <main class="editor" id="editor">
        <div class="empty">Выбери тему из списка слева</div>
    </main>
</div>

<script>
const API = '/controllers/router.php?r=themes';
let themes = [];
let currentCode = null;

async function api(path, opts = {}) {
    const r = await fetch(API + path, { ...opts, headers: { 'Content-Type': 'application/json', ...(opts.headers || {}) } });
    const j = await r.json();
    if (!j.success) throw new Error(j.error || 'API error');
    return j.data;
}

async function loadThemes() {
    themes = await api('');
    renderList();
}

function renderList() {
    const el = document.getElementById('themesList');
    el.innerHTML = themes.map(t =>
        `<div class="theme-item ${t.code === currentCode ? 'active' : ''}" onclick="selectTheme('${t.code}')">
            <div class="theme-code">${t.code}</div>
            <div class="theme-name">${escapeHtml(t.name)}<span class="badge ${t.is_active ? '' : 'off'}">${t.is_active ? 'on' : 'off'}</span></div>
        </div>`
    ).join('');
}

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

async function selectTheme(code) {
    currentCode = code;
    renderList();
    const data = await api('/' + encodeURIComponent(code));
    renderEditor(data, false);
}

function newTheme() {
    currentCode = null;
    renderList();
    renderEditor({
        code: '',
        name: '',
        is_active: 1,
        tokens: {
            color: { accent: '#2563EB', text: '#0f172a', surface: '#ffffff', border: '#e2e8f0', bg: '#ffffff', danger: '#ef4444', success: '#16a34a', warn: '#f59e0b', 'chart-1': '#2563EB', 'chart-2': '#0D9488', 'chart-3': '#8B5CF6', 'chart-4': '#F59E0B', 'chart-5': '#EF4444', 'chart-6': '#16A34A', 'chart-7': '#EC4899', 'chart-8': '#06B6D4' },
            type: { 'font-text': '"Onest", sans-serif', 'font-heading': '"Geologica", sans-serif' },
            radius: { sm: '6px', md: '12px', lg: '16px' },
        },
    }, true);
}

function renderEditor(t, isNew) {
    const tokensJson = JSON.stringify(t.tokens, null, 2);
    document.getElementById('editor').innerHTML = `
        <div id="msg"></div>
        <div class="row">
            <div>
                <label>Код</label>
                <input type="text" id="fldCode" value="${escapeHtml(t.code)}" ${isNew ? '' : 'disabled'} placeholder="my_theme">
            </div>
            <div>
                <label>Название</label>
                <input type="text" id="fldName" value="${escapeHtml(t.name)}">
            </div>
            <div style="flex:0 0 120px">
                <label>Активна</label>
                <select id="fldActive"><option value="1" ${t.is_active ? 'selected' : ''}>Да</option><option value="0" ${!t.is_active ? 'selected' : ''}>Нет</option></select>
            </div>
        </div>
        <div>
            <label>Токены (JSON)</label>
            <textarea id="fldTokens">${escapeHtml(tokensJson)}</textarea>
        </div>
        <div class="preview" id="preview"></div>
        <div class="actions">
            <button class="btn" onclick="saveTheme(${isNew ? 'true' : 'false'})">Сохранить</button>
            ${isNew ? '' : '<button class="btn danger" onclick="deleteTheme()">Удалить</button>'}
            <button class="btn ghost" onclick="renderPreview()">Обновить превью</button>
            <a class="btn ghost" href="/public/article-demo.php?theme=${encodeURIComponent(t.code)}" target="_blank" style="text-decoration:none">Открыть demo</a>
        </div>`;
    renderPreview();
}

function renderPreview() {
    const el = document.getElementById('preview');
    if (!el) return;
    let tokens;
    try { tokens = JSON.parse(document.getElementById('fldTokens').value); } catch (e) { el.innerHTML = '<div class="msg err">JSON некорректен</div>'; return; }
    const colors = tokens.color || {};
    el.innerHTML = Object.entries(colors).map(([k, v]) => `<div class="swatch" style="background:${escapeHtml(v)}"><span>${escapeHtml(k)}</span></div>`).join('');
}

async function saveTheme(isNew) {
    const code = document.getElementById('fldCode').value.trim();
    const name = document.getElementById('fldName').value.trim();
    const isActive = document.getElementById('fldActive').value === '1';
    let tokens;
    try { tokens = JSON.parse(document.getElementById('fldTokens').value); } catch (e) {
        showMsg('JSON некорректен: ' + e.message, true); return;
    }
    if (!code) { showMsg('code обязателен', true); return; }
    if (!name) { showMsg('Название обязательно', true); return; }
    try {
        const method = isNew ? 'POST' : 'PUT';
        const path = isNew ? '' : '/' + encodeURIComponent(code);
        const data = await api(path, { method, body: JSON.stringify({ code, name, tokens, is_active: isActive }) });
        currentCode = data.code;
        await loadThemes();
        await selectTheme(currentCode);
        showMsg('Сохранено');
    } catch (e) { showMsg(e.message, true); }
}

async function deleteTheme() {
    if (!currentCode) return;
    if (!confirm('Удалить тему ' + currentCode + '?')) return;
    try {
        await api('/' + encodeURIComponent(currentCode), { method: 'DELETE' });
        currentCode = null;
        await loadThemes();
        document.getElementById('editor').innerHTML = '<div class="empty">Тема удалена</div>';
    } catch (e) { showMsg(e.message, true); }
}

function showMsg(text, isErr) {
    const m = document.getElementById('msg');
    if (m) m.innerHTML = `<div class="msg ${isErr ? 'err' : ''}">${escapeHtml(text)}</div>`;
}

loadThemes();
</script>
</body>
</html>
