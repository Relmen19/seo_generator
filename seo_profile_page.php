<?php
require_once __DIR__ . '/auth.php';
requireAuth();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO — Профили</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; }

        /* ── Topbar ── */
        .topbar { background: #1e293b; border-bottom: 1px solid #334155; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .topbar h1 { font-size: 1.1rem; color: #f1f5f9; white-space: nowrap; }
        .topbar nav { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .topbar nav a { color: #94a3b8; text-decoration: none; padding: 6px 14px; border-radius: 6px; font-size: .85rem; transition: .2s; white-space: nowrap; }
        .topbar nav a:hover { background: #334155; color: #e2e8f0; }
        .topbar nav a.active { background: #6366f1; color: #fff; }
        .btn-logout { color: #f87171 !important; }

        /* ── Content area ── */
        .content { padding: 24px; max-width: 1200px; margin: 0 auto; overflow-y: auto; overflow-x: hidden; height: calc(100vh - 53px); }

        /* ── Cards grid ── */
        .card-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(340px, 100%), 1fr)); gap: 16px; margin-bottom: 24px; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 20px; transition: border-color .2s, box-shadow .2s; min-width: 0; overflow: hidden; cursor: pointer; }
        .card:hover { border-color: #6366f1; box-shadow: 0 0 0 1px rgba(99,102,241,.3); }
        .card-header { display: flex; gap: 14px; align-items: flex-start; margin-bottom: 12px; }
        .card-icon { width: 48px; height: 48px; border-radius: 10px; background: #334155; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; font-size: 1.4rem; color: #94a3b8; }
        .card-icon img { width: 100%; height: 100%; object-fit: cover; border-radius: 10px; }
        .card-info { flex: 1; min-width: 0; }
        .card-title { font-size: 1rem; font-weight: 700; color: #f1f5f9; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .card-subtitle { font-size: .78rem; color: #64748b; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .card-stats { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 10px; }
        .card-stat { font-size: .72rem; color: #94a3b8; }
        .card-stat b { color: #e2e8f0; }
        .card-desc { font-size: .78rem; color: #94a3b8; margin-top: 8px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

        /* ── Buttons ── */
        .btn { padding: 7px 16px; border: none; border-radius: 6px; font-size: .8rem; font-weight: 600; cursor: pointer; transition: .15s; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; flex-shrink: 0; }
        .btn-primary { background: #6366f1; color: #fff; } .btn-primary:hover { background: #818cf8; }
        .btn-success { background: #059669; color: #fff; } .btn-success:hover { background: #10b981; }
        .btn-warn { background: #d97706; color: #fff; } .btn-warn:hover { background: #f59e0b; }
        .btn-danger { background: #dc2626; color: #fff; } .btn-danger:hover { background: #ef4444; }
        .btn-ghost { background: transparent; color: #94a3b8; border: 1px solid #334155; } .btn-ghost:hover { background: #334155; color: #e2e8f0; }
        .btn-sm { padding: 4px 10px; font-size: .72rem; }
        .btn-lg { padding: 10px 24px; font-size: .9rem; }
        .btn:disabled { opacity: .4; cursor: not-allowed; }

        /* ── Forms ── */
        input[type="text"], input[type="url"], input[type="color"], textarea, select { width: 100%; min-width: 0; padding: 8px 10px; background: #0f172a; border: 1px solid #334155; border-radius: 6px; color: #e2e8f0; font-size: .85rem; outline: none; transition: border .2s; }
        input:focus, textarea:focus, select:focus { border-color: #6366f1; }
        textarea { resize: vertical; min-height: 60px; font-family: inherit; }
        .form-row { margin-bottom: 14px; }
        .form-row label { display: block; font-size: .7rem; text-transform: uppercase; letter-spacing: .4px; color: #64748b; margin-bottom: 4px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .form-grid .full { grid-column: 1 / -1; }
        .form-hint { font-size: .7rem; color: #475569; margin-top: 3px; }

        /* ── Section title ── */
        .section-title { font-size: .9rem; font-weight: 700; color: #f1f5f9; margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }

        /* ── Badges ── */
        .badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 100px; font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; white-space: nowrap; flex-shrink: 0; }
        .badge-active { background: #052e16; color: #4ade80; }
        .badge-inactive { background: #450a0a; color: #fca5a5; }

        /* ── Workspace header ── */
        .ws-header { display: flex; align-items: center; gap: 16px; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid #334155; }
        .ws-back { background: none; border: none; color: #64748b; cursor: pointer; font-size: 1.2rem; padding: 6px; border-radius: 6px; transition: .2s; }
        .ws-back:hover { background: #334155; color: #e2e8f0; }
        .ws-icon { width: 56px; height: 56px; border-radius: 12px; background: #334155; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; font-size: 1.8rem; color: #94a3b8; }
        .ws-icon img { width: 100%; height: 100%; object-fit: cover; border-radius: 12px; }
        .ws-info { flex: 1; min-width: 0; }
        .ws-title { font-size: 1.3rem; font-weight: 700; color: #f1f5f9; }
        .ws-meta { font-size: .82rem; color: #64748b; margin-top: 2px; }
        .ws-actions { display: flex; gap: 8px; flex-wrap: wrap; }

        /* ── Workspace tabs ── */
        .ws-tabs { display: flex; gap: 0; border-bottom: 1px solid #334155; margin-bottom: 24px; overflow-x: auto; }
        .ws-tab { padding: 10px 18px; font-size: .82rem; font-weight: 600; color: #64748b; cursor: pointer; border-bottom: 2px solid transparent; transition: .2s; white-space: nowrap; flex-shrink: 0; }
        .ws-tab:hover { color: #e2e8f0; }
        .ws-tab.active { color: #6366f1; border-bottom-color: #6366f1; }

        /* ── Overview dashboard ── */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; margin-bottom: 24px; }
        .stat-card { background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 16px; text-align: center; }
        .stat-value { font-size: 1.6rem; font-weight: 700; color: #f1f5f9; }
        .stat-label { font-size: .72rem; color: #64748b; margin-top: 4px; text-transform: uppercase; letter-spacing: .3px; }

        /* ── Wizard ── */
        .wizard-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.6); z-index: 200; align-items: center; justify-content: center; padding: 16px; }
        .wizard-overlay.show { display: flex; }
        .wizard { background: #1e293b; border: 1px solid #334155; border-radius: 12px; width: 100%; max-width: 720px; max-height: 90vh; overflow-y: auto; overflow-x: hidden; }
        .wizard-header { padding: 20px 24px 0; }
        .wizard-title { font-size: 1.1rem; font-weight: 700; color: #f1f5f9; margin-bottom: 4px; }
        .wizard-steps { display: flex; gap: 0; padding: 16px 24px; }
        .wizard-step { flex: 1; text-align: center; position: relative; }
        .wizard-step-dot { width: 32px; height: 32px; border-radius: 50%; background: #334155; color: #64748b; display: inline-flex; align-items: center; justify-content: center; font-size: .8rem; font-weight: 700; transition: .3s; margin: 0 auto 4px; }
        .wizard-step.active .wizard-step-dot { background: #6366f1; color: #fff; }
        .wizard-step.done .wizard-step-dot { background: #059669; color: #fff; }
        .wizard-step-label { font-size: .68rem; color: #64748b; }
        .wizard-step.active .wizard-step-label { color: #e2e8f0; }
        .wizard-step-line { position: absolute; top: 16px; left: calc(50% + 20px); right: calc(-50% + 20px); height: 2px; background: #334155; }
        .wizard-step:last-child .wizard-step-line { display: none; }
        .wizard-step.done .wizard-step-line { background: #059669; }
        .wizard-body { padding: 0 24px 24px; }
        .wizard-footer { padding: 0 24px 24px; display: flex; gap: 8px; justify-content: space-between; }

        /* ── Icon upload zone ── */
        .icon-upload { width: 120px; height: 120px; border: 2px dashed #334155; border-radius: 14px; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; transition: .2s; background: #0f172a; overflow: hidden; position: relative; }
        .icon-upload:hover { border-color: #6366f1; background: rgba(99,102,241,.05); }
        .icon-upload.has-image { border-style: solid; border-color: #334155; }
        .icon-upload img { width: 100%; height: 100%; object-fit: cover; }
        .icon-upload-text { font-size: .72rem; color: #64748b; text-align: center; padding: 8px; }
        .icon-upload input[type="file"] { display: none; }
        .icon-upload .remove-icon { position: absolute; top: 4px; right: 4px; background: rgba(220,38,38,.9); color: #fff; border: none; border-radius: 50%; width: 22px; height: 22px; font-size: .7rem; cursor: pointer; display: none; align-items: center; justify-content: center; }
        .icon-upload.has-image .remove-icon { display: flex; }

        /* ── Profile settings form ── */
        .settings-section { background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 20px; margin-bottom: 16px; }
        .settings-section h3 { font-size: .85rem; font-weight: 700; color: #f1f5f9; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid #334155; }

        /* ── AI generation indicator ── */
        .ai-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 100px; font-size: .68rem; font-weight: 600; background: #312e81; color: #a78bfa; cursor: pointer; transition: .2s; border: none; }
        .ai-badge:hover { background: #3730a3; color: #c4b5fd; }

        /* ── Preview card ── */
        .preview-card { background: #0f172a; border: 1px solid #334155; border-radius: 10px; padding: 24px; }

        /* ── Toast ── */
        .toast { position: fixed; bottom: 24px; right: 24px; background: #059669; color: #fff; padding: 10px 20px; border-radius: 8px; font-size: .85rem; opacity: 0; transform: translateY(10px); transition: .3s; z-index: 300; pointer-events: none; max-width: calc(100vw - 48px); }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast.error { background: #dc2626; }

        /* ── Spinner ── */
        .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: spin .6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Gen proposal ── */
        .gen-proposal { background: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 16px; margin-top: 12px; }
        .gen-tpl-item { background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 14px; margin-bottom: 10px; }
        .gen-tpl-item:last-child { margin-bottom: 0; }
        .gen-tpl-item h4 { color: #f1f5f9; font-size: .9rem; margin-bottom: 6px; }
        .gen-tpl-item .blocks-preview { font-size: .72rem; color: #94a3b8; word-break: break-word; }

        /* ── SSE generation steps ── */
        .gen-step { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid #1e293b; }
        .gen-step:last-child { border-bottom: none; }
        .gen-step-icon { width: 28px; height: 28px; border-radius: 50%; background: #334155; color: #64748b; display: flex; align-items: center; justify-content: center; font-size: .78rem; font-weight: 700; flex-shrink: 0; transition: .3s; }
        .gen-step.active .gen-step-icon { background: #6366f1; color: #fff; }
        .gen-step.done .gen-step-icon { background: #059669; color: #fff; }
        .gen-step.error .gen-step-icon { background: #dc2626; color: #fff; }
        .gen-step-label { font-size: .85rem; color: #94a3b8; flex: 1; }
        .gen-step.active .gen-step-label { color: #e2e8f0; font-weight: 600; }
        .gen-step-status { font-size: .72rem; color: #64748b; }
        .gen-review-suggestion { font-size: .78rem; color: #94a3b8; padding: 3px 0; }
        .gen-block-chip { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: .7rem; background: #1e293b; border: 1px solid #334155; color: #94a3b8; margin: 2px; }

        /* ── Empty state ── */
        .empty-state { text-align: center; padding: 60px 20px; color: #64748b; }
        .empty-state-icon { font-size: 3rem; margin-bottom: 12px; opacity: .4; }
        .empty-state-title { font-size: 1.1rem; font-weight: 600; color: #94a3b8; margin-bottom: 6px; }
        .empty-state-text { font-size: .85rem; margin-bottom: 20px; }

        /* ── Template editor ── */
        .tpl-card { cursor: pointer; transition: border-color .2s; }
        .tpl-card:hover { border-color: #6366f1; }
        .tpl-ai-bar { display: flex; gap: 6px; flex-wrap: wrap; }
        .tpl-review-box { border: 1px solid #334155; border-radius: 8px; padding: 16px; background: #0f172a; }
        .tpl-score { font-size: 1.1rem; font-weight: 700; }
        .tpl-diff-block { border-color: #059669 !important; }
        .tpl-diff-block .gen-block-chip { border-color: #059669; color: #4ade80; }
    </style>
</head>
<body>
<div class="topbar" id="topbarList">
    <h1>SEO Generator</h1>
    <nav>
        <a href="/seo_profile_page.php" class="active">Профили</a>
        <a href="/logout.php" class="btn-logout">Выйти</a>
    </nav>
</div>
<div class="topbar" id="topbarWs" style="display:none">
    <div style="display:flex;align-items:center;gap:12px">
        <button class="ws-back" onclick="goToList()" title="К списку профилей">&larr;</button>
        <div id="topbarWsIcon" style="width:32px;height:32px;border-radius:8px;background:#334155;display:flex;align-items:center;justify-content:center;font-size:.9rem;font-weight:700;color:#6366f1;flex-shrink:0;overflow:hidden"></div>
        <div>
            <div id="topbarWsName" style="font-size:.95rem;font-weight:700;color:#f1f5f9;line-height:1.2"></div>
            <div id="topbarWsMeta" style="font-size:.7rem;color:#64748b"></div>
        </div>
    </div>
    <nav>
        <a href="/seo_page.php" id="navSeoLink">SEO</a>
        <a href="/seo_clustering_page.php" id="navSemLink">Семантика</a>
        <a href="/seo_profile_page.php" class="active">Профили</a>
        <a href="/logout.php" class="btn-logout">Выйти</a>
    </nav>
</div>

<!-- ═══════════════════ VIEW: Profile list ═══════════════════ -->
<div class="content" id="viewList">
    <div class="section-title">
        <span>Профили проектов</span>
        <button class="btn btn-primary btn-sm" onclick="openWizard()">+ Новый профиль</button>
    </div>
    <div class="card-grid" id="profileGrid"></div>
</div>

<!-- ═══════════════════ VIEW: Workspace ═══════════════════ -->
<div class="content" id="viewWorkspace" style="display:none">
    <div class="ws-header">
        <button class="ws-back" onclick="goToList()" title="Назад к списку">&larr;</button>
        <div class="ws-icon" id="wsIcon"></div>
        <div class="ws-info">
            <div class="ws-title" id="wsTitle"></div>
            <div class="ws-meta" id="wsMeta"></div>
        </div>
        <div class="ws-actions">
            <span class="badge" id="wsBadge"></span>
            <button class="btn btn-danger btn-sm" onclick="deleteCurrentProfile()">Удалить</button>
        </div>
    </div>

    <div class="ws-tabs" id="wsTabs">
        <div class="ws-tab active" data-tab="overview" onclick="switchWsTab('overview')">Обзор</div>
        <div class="ws-tab" data-tab="settings" onclick="switchWsTab('settings')">Настройки</div>
        <div class="ws-tab" data-tab="branding" onclick="switchWsTab('branding')">Брендинг</div>
        <div class="ws-tab" data-tab="templates" onclick="switchWsTab('templates')">Шаблоны</div>
        <div class="ws-tab" data-tab="intents" onclick="switchWsTab('intents')">Интенты</div>
    </div>

    <!-- Tab: Overview -->
    <div class="ws-content" id="tabOverview">
        <!-- Quick actions: go to SEO / Semantics -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:24px">
            <a href="/seo_page.php" class="settings-section" style="text-decoration:none;display:flex;align-items:center;gap:14px;cursor:pointer;transition:border-color .2s;margin-bottom:0" onmouseover="this.style.borderColor='#6366f1'" onmouseout="this.style.borderColor='#334155'">
                <div style="width:44px;height:44px;border-radius:10px;background:#312e81;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0">&#9998;</div>
                <div>
                    <div style="font-size:.95rem;font-weight:700;color:#f1f5f9">SEO &amp; Контент</div>
                    <div style="font-size:.75rem;color:#64748b">Статьи, каталоги, шаблоны, публикация</div>
                </div>
                <div style="margin-left:auto;color:#475569;font-size:1.1rem">&rarr;</div>
            </a>
            <a href="/seo_clustering_page.php" class="settings-section" style="text-decoration:none;display:flex;align-items:center;gap:14px;cursor:pointer;transition:border-color .2s;margin-bottom:0" onmouseover="this.style.borderColor='#6366f1'" onmouseout="this.style.borderColor='#334155'">
                <div style="width:44px;height:44px;border-radius:10px;background:#1e3a5f;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0">&#128270;</div>
                <div>
                    <div style="font-size:.95rem;font-weight:700;color:#f1f5f9">Семантика</div>
                    <div style="font-size:.75rem;color:#64748b">Ключевые слова, кластеризация, интенты</div>
                </div>
                <div style="margin-left:auto;color:#475569;font-size:1.1rem">&rarr;</div>
            </a>
        </div>
        <div class="stats-grid" id="statsGrid"></div>
        <div class="settings-section">
            <h3>Описание проекта</h3>
            <div id="overviewDesc" style="font-size:.85rem;color:#94a3b8"></div>
        </div>
    </div>

    <!-- Tab: Settings -->
    <div class="ws-content" id="tabSettings" style="display:none">
        <div class="settings-section">
            <h3>Основные данные</h3>
            <div class="form-grid">
                <div class="form-row"><label>Название</label><input type="text" id="sName"></div>
                <div class="form-row"><label>Slug</label><input type="text" id="sSlug"></div>
                <div class="form-row"><label>Домен</label><input type="text" id="sDomain" placeholder="example.com"></div>
                <div class="form-row"><label>Ниша</label><input type="text" id="sNiche" placeholder="Медицина, e-commerce..."></div>
                <div class="form-row"><label>Бренд</label><input type="text" id="sBrand" placeholder="Название бренда"></div>
                <div class="form-row"><label>Язык</label>
                    <select id="sLang"><option value="ru">Русский</option><option value="en">English</option><option value="uk">Українська</option></select>
                </div>
                <div class="form-row"><label>Тон</label>
                    <select id="sTone">
                        <option value="professional">Профессиональный</option><option value="friendly">Дружелюбный</option>
                        <option value="academic">Академический</option><option value="casual">Разговорный</option>
                        <option value="persuasive">Убеждающий</option>
                    </select>
                </div>
                <div class="form-row"><label>Статус</label>
                    <select id="sActive"><option value="1">Активен</option><option value="0">Неактивен</option></select>
                </div>
                <div class="form-row full"><label>Описание проекта</label><textarea id="sDescription" rows="3" placeholder="Подробное описание проекта..."></textarea></div>
            </div>
        </div>
        <div class="settings-section">
            <h3>GPT Настройки</h3>
            <div class="form-row"><label>GPT Персона (системный промпт)</label><textarea id="sPersona" rows="4" placeholder="Ты — профессиональный SEO-копирайтер..."></textarea></div>
            <div class="form-row"><label>Доп. правила генерации</label><textarea id="sRules" rows="3" placeholder="Дополнительные инструкции для GPT..."></textarea></div>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
            <button class="btn btn-primary" onclick="saveSettings()">Сохранить настройки</button>
        </div>
    </div>

    <!-- Tab: Branding -->
    <div class="ws-content" id="tabBranding" style="display:none">
        <div class="settings-section">
            <h3>Иконка профиля</h3>
            <div style="display:flex;gap:20px;align-items:flex-start">
                <div class="icon-upload" id="brandIconUpload" onclick="document.getElementById('brandIconFile').click()">
                    <input type="file" id="brandIconFile" accept="image/png,image/jpeg,image/webp,image/svg+xml,image/gif" onchange="uploadBrandIcon(this)">
                    <span class="icon-upload-text">Перетащите или нажмите</span>
                    <button class="remove-icon" onclick="event.stopPropagation();removeBrandIcon()">&times;</button>
                </div>
                <div style="flex:1">
                    <div style="font-size:.82rem;color:#94a3b8;margin-bottom:8px">Загрузите иконку для профиля. Рекомендуемый размер: 256x256 px.</div>
                    <div style="font-size:.72rem;color:#475569">Форматы: PNG, JPEG, WebP, SVG, GIF. Макс. 2 МБ.</div>
                </div>
            </div>
        </div>
        <div class="settings-section">
            <h3>Цвета и ссылки</h3>
            <div class="form-grid">
                <div class="form-row">
                    <label>Цветовая схема</label>
                    <div style="display:flex;gap:8px;align-items:center">
                        <input type="color" id="bColor" style="width:44px;height:36px;padding:2px;cursor:pointer">
                        <input type="text" id="bColorText" placeholder="#6366f1" style="flex:1" oninput="document.getElementById('bColor').value=this.value">
                    </div>
                </div>
                <div class="form-row"><label>Logo URL</label><input type="url" id="bLogo" placeholder="https://example.com/logo.png"></div>
                <div class="form-row full"><label>Base URL</label><input type="url" id="bBaseUrl" placeholder="https://example.com"></div>
            </div>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
            <button class="btn btn-primary" onclick="saveBranding()">Сохранить брендинг</button>
        </div>
    </div>

    <!-- Tab: Templates -->
    <div class="ws-content" id="tabTemplates" style="display:none">
        <div class="section-title">
            <span>Шаблоны профиля</span>
            <button class="ai-badge" onclick="openGenModal()">+ AI Шаблон</button>
        </div>
        <div id="templatesList"></div>
    </div>

    <!-- Tab: Intents -->
    <div class="ws-content" id="tabIntents" style="display:none">
        <div class="section-title"><span>Интенты профиля</span></div>
        <div id="intentsList"></div>
    </div>
</div>

<!-- ═══════════════════ WIZARD: New Profile ═══════════════════ -->
<div class="wizard-overlay" id="wizardOverlay">
    <div class="wizard">
        <div class="wizard-header">
            <div class="wizard-title">Создание нового профиля</div>
        </div>
        <div class="wizard-steps">
            <div class="wizard-step active" id="wizStep1">
                <div class="wizard-step-line"></div>
                <div class="wizard-step-dot">1</div>
                <div class="wizard-step-label">Описание</div>
            </div>
            <div class="wizard-step" id="wizStep2">
                <div class="wizard-step-line"></div>
                <div class="wizard-step-dot">2</div>
                <div class="wizard-step-label">Брендинг</div>
            </div>
            <div class="wizard-step" id="wizStep3">
                <div class="wizard-step-dot">3</div>
                <div class="wizard-step-label">Готово</div>
            </div>
        </div>

        <!-- Step 1: Description + AI -->
        <div class="wizard-body" id="wizBody1">
            <div class="form-row full">
                <label>Опишите ваш проект</label>
                <textarea id="wizDesc" rows="4" placeholder="Например: Медицинский портал для пациентов. Публикуем статьи о здоровье, симптомах, профилактике. Целевая аудитория — русскоязычные пользователи 25-55 лет."></textarea>
                <div class="form-hint">Подробное описание поможет AI заполнить остальные поля автоматически</div>
            </div>
            <div style="margin-bottom:16px">
                <button class="ai-badge" id="btnAiGen" onclick="aiGenerateProfile()" style="font-size:.78rem;padding:6px 14px">
                    AI: Заполнить по описанию
                </button>
            </div>
            <div id="aiGenStatus" style="display:none;font-size:.78rem;color:#a78bfa;margin-bottom:12px"><span class="spinner"></span> Генерация...</div>

            <div class="form-grid">
                <div class="form-row"><label>Название</label><input type="text" id="wizName" placeholder="Мой проект"></div>
                <div class="form-row"><label>Slug</label><input type="text" id="wizSlug" placeholder="my-project"></div>
                <div class="form-row"><label>Ниша</label><input type="text" id="wizNiche" placeholder="Медицина, e-commerce..."></div>
                <div class="form-row"><label>Бренд</label><input type="text" id="wizBrand" placeholder="Название бренда"></div>
                <div class="form-row"><label>Язык</label>
                    <select id="wizLang"><option value="ru">Русский</option><option value="en">English</option><option value="uk">Українська</option></select>
                </div>
                <div class="form-row"><label>Тон</label>
                    <select id="wizTone">
                        <option value="professional">Профессиональный</option><option value="friendly">Дружелюбный</option>
                        <option value="academic">Академический</option><option value="casual">Разговорный</option>
                        <option value="persuasive">Убеждающий</option>
                    </select>
                </div>
                <div class="form-row full"><label>GPT Персона</label><textarea id="wizPersona" rows="3" placeholder="Ты — профессиональный SEO-копирайтер..."></textarea></div>
                <div class="form-row full"><label>Доп. правила</label><textarea id="wizRules" rows="2" placeholder="Дополнительные инструкции для GPT..."></textarea></div>
            </div>
        </div>

        <!-- Step 2: Branding -->
        <div class="wizard-body" id="wizBody2" style="display:none">
            <div style="display:flex;gap:24px;align-items:flex-start;margin-bottom:20px">
                <div>
                    <label style="display:block;font-size:.7rem;text-transform:uppercase;letter-spacing:.4px;color:#64748b;margin-bottom:6px">Иконка</label>
                    <div class="icon-upload" id="wizIconUpload" onclick="document.getElementById('wizIconFile').click()">
                        <input type="file" id="wizIconFile" accept="image/png,image/jpeg,image/webp,image/svg+xml,image/gif" onchange="previewWizIcon(this)">
                        <span class="icon-upload-text">Загрузить</span>
                        <button class="remove-icon" onclick="event.stopPropagation();clearWizIcon()">&times;</button>
                    </div>
                </div>
                <div style="flex:1">
                    <div class="form-grid">
                        <div class="form-row">
                            <label>Цвет</label>
                            <div style="display:flex;gap:8px;align-items:center">
                                <input type="color" id="wizColor" value="#6366f1" style="width:44px;height:36px;padding:2px;cursor:pointer">
                                <input type="text" id="wizColorText" placeholder="#6366f1" value="#6366f1" style="flex:1">
                            </div>
                        </div>
                        <div class="form-row"><label>Домен</label><input type="text" id="wizDomain" placeholder="example.com"></div>
                        <div class="form-row full"><label>Logo URL</label><input type="url" id="wizLogo" placeholder="https://example.com/logo.png"></div>
                        <div class="form-row full"><label>Base URL</label><input type="url" id="wizBaseUrl" placeholder="https://example.com"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 3: Confirmation -->
        <div class="wizard-body" id="wizBody3" style="display:none">
            <div class="preview-card" id="wizPreview"></div>
        </div>

        <div class="wizard-footer">
            <button class="btn btn-ghost" id="wizBtnCancel" onclick="closeWizard()">Отмена</button>
            <div style="display:flex;gap:8px">
                <button class="btn btn-ghost" id="wizBtnBack" onclick="wizardBack()" style="display:none">&larr; Назад</button>
                <button class="btn btn-primary" id="wizBtnNext" onclick="wizardNext()">Далее &rarr;</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════ MODAL: AI Template Generation (SSE) ═══════════════════ -->
<div class="wizard-overlay" id="genModal">
    <div class="wizard" style="max-width:800px">
        <div class="wizard-header" style="padding-bottom:16px">
            <div class="wizard-title">AI Генерация шаблона</div>
        </div>
        <div class="wizard-body">
            <!-- Input form -->
            <div id="genForm">
                <div class="form-row">
                    <label>Назначение шаблона — тип статьи</label>
                    <textarea id="genPurpose" rows="3" placeholder="Опишите для чего будет этот шаблон. Например: Обзорная статья товара с таблицей характеристик и сравнением с аналогами. Информационная статья о симптомах и лечении заболевания."></textarea>
                    <div class="form-hint">Подробное описание поможет AI правильно подобрать блоки и написать подсказки</div>
                </div>
                <div class="form-row">
                    <label>Дополнительные подсказки для AI (необязательно)</label>
                    <textarea id="genHints" rows="2" placeholder="Особые требования: нужны таблицы сравнения, обязательно FAQ, не использовать графики..."></textarea>
                </div>
                <div style="display:flex;gap:8px;align-items:center;margin-top:4px">
                    <button class="btn btn-primary" id="btnGenStart" onclick="startTemplateGeneration()">Сгенерировать шаблон</button>
                </div>
            </div>
            <!-- SSE Progress -->
            <div id="genProgress" style="display:none">
                <div style="margin-bottom:16px">
                    <div class="gen-step" id="genStep1">
                        <span class="gen-step-icon" id="genStep1Icon">1</span>
                        <span class="gen-step-label">Генерация шаблона</span>
                        <span class="gen-step-status" id="genStep1Status"></span>
                    </div>
                    <div class="gen-step" id="genStep2">
                        <span class="gen-step-icon" id="genStep2Icon">2</span>
                        <span class="gen-step-label">Ревью качества</span>
                        <span class="gen-step-status" id="genStep2Status"></span>
                    </div>
                    <div class="gen-step" id="genStep3">
                        <span class="gen-step-icon" id="genStep3Icon">3</span>
                        <span class="gen-step-label">Сохранение</span>
                        <span class="gen-step-status" id="genStep3Status"></span>
                    </div>
                </div>
                <div id="genPreview" style="display:none" class="gen-proposal"></div>
                <div id="genReview" style="display:none;margin-top:12px"></div>
            </div>
        </div>
        <div class="wizard-footer">
            <button class="btn btn-ghost" id="btnGenClose" onclick="closeGenModal()">Закрыть</button>
        </div>
    </div>
</div>

<!-- ═══════════════════ MODAL: Template AI Editor ═══════════════════ -->
<div class="wizard-overlay" id="tplEditorModal">
    <div class="wizard" style="max-width:900px">
        <div class="wizard-header" style="padding-bottom:16px">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                <div class="wizard-title" id="tplEditorTitle">Шаблон</div>
                <div class="tpl-ai-bar" id="tplEditorActions">
                    <button class="ai-badge" onclick="runAiReview()" id="btnAiReview">AI Ревью</button>
                    <button class="ai-badge" onclick="openRegenForm()" id="btnAiRegen" style="background:#1e3a5f;color:#7dd3fc">Перегенерировать</button>
                </div>
            </div>
        </div>
        <div class="wizard-body" style="max-height:60vh;overflow-y:auto">
            <div id="tplEditorInfo"></div>
            <div id="tplEditorBlocks" style="margin-top:16px"></div>
            <div id="tplReviewResult" style="display:none;margin-top:16px"></div>
            <div id="tplRegenForm" style="display:none;margin-top:16px"></div>
            <div id="tplRegenProgress" style="display:none;margin-top:16px"></div>
        </div>
        <div class="wizard-footer">
            <button class="btn btn-ghost" onclick="closeTplEditor()">Закрыть</button>
            <div style="display:flex;gap:8px" id="tplEditorFooterActions"></div>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const API = 'controllers/router.php';
let profiles = [];
let currentProfile = null;
let wizardStep = 1;
let wizIconFile = null;
let genRunning = false;

const $ = id => document.getElementById(id);

async function api(path, opts = {}) {
    const res = await fetch(`${API}?r=${path}`, opts);
    return res.json();
}

function toast(msg, isError = false) {
    const t = $('toast');
    t.textContent = msg;
    t.className = 'toast show' + (isError ? ' error' : '');
    setTimeout(() => t.classList.remove('show'), 3000);
}

function esc(s) {
    if (!s) return '';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

function iconUrl(profile) {
    if (profile.icon_path) return `${API}?r=profiles/${profile.id}/icon&_=${profile.updated_at || ''}`;
    return '';
}

function iconHtml(profile, size) {
    const url = iconUrl(profile);
    if (url) return `<img src="${esc(url)}" alt="">`;
    const letter = (profile.name || '?')[0].toUpperCase();
    const bg = profile.color_scheme || '#6366f1';
    return `<span style="color:${bg};font-size:${Math.round(size * 0.5)}px;font-weight:700">${esc(letter)}</span>`;
}

const toneLabels = { professional: 'Профессиональный', friendly: 'Дружелюбный', academic: 'Академический', casual: 'Разговорный', persuasive: 'Убеждающий' };
const langLabels = { ru: 'Русский', en: 'English', uk: 'Українська' };

// ═══════════════════ LIST VIEW ═══════════════════

async function loadProfiles() {
    try {
        const res = await api('profiles');
        profiles = res.data || [];
        renderProfiles();
    } catch(e) { console.error(e); }
}

function renderProfiles() {
    const grid = $('profileGrid');
    if (profiles.length === 0) {
        grid.innerHTML = `
            <div class="empty-state" style="grid-column:1/-1">
                <div class="empty-state-icon">&#128194;</div>
                <div class="empty-state-title">Нет профилей</div>
                <div class="empty-state-text">Создайте первый профиль для начала работы</div>
                <button class="btn btn-primary" onclick="openWizard()">+ Создать профиль</button>
            </div>`;
        return;
    }
    grid.innerHTML = profiles.map(p => `
        <div class="card" onclick="openWorkspace(${p.id})">
            <div class="card-header">
                <div class="card-icon" style="width:48px;height:48px">${iconHtml(p, 48)}</div>
                <div class="card-info">
                    <div class="card-title">${esc(p.name)}</div>
                    <div class="card-subtitle">${esc(p.slug)}${p.domain ? ' &middot; ' + esc(p.domain) : ''}</div>
                </div>
                <span class="badge ${p.is_active ? 'badge-active' : 'badge-inactive'}">${p.is_active ? 'Активен' : 'Неактивен'}</span>
            </div>
            ${p.description ? `<div class="card-desc">${esc(p.description)}</div>` : ''}
            <div class="card-stats">
                ${p.niche ? `<div class="card-stat">Ниша: <b>${esc(p.niche)}</b></div>` : ''}
                <div class="card-stat">Тон: <b>${esc(toneLabels[p.tone] || p.tone)}</b></div>
                <div class="card-stat">Язык: <b>${esc(langLabels[p.language] || p.language)}</b></div>
            </div>
        </div>
    `).join('');
}

// ═══════════════════ WORKSPACE ═══════════════════

async function openWorkspace(id) {
    try {
        const res = await api(`profiles/${id}`);
        if (!res.success) { toast(res.error || 'Ошибка', true); return; }
        currentProfile = res.data;
    } catch(e) { toast('Ошибка сети', true); return; }

    // Set as active profile for SEO/Semantics pages
    localStorage.setItem('seo_profile_id', String(id));

    $('topbarList').style.display = 'none';
    $('topbarWs').style.display = '';
    $('viewList').style.display = 'none';
    $('viewWorkspace').style.display = '';

    renderWsHeader();
    switchWsTab('overview');
}

function goToList() {
    $('topbarWs').style.display = 'none';
    $('topbarList').style.display = '';
    $('viewWorkspace').style.display = 'none';
    $('viewList').style.display = '';
    currentProfile = null;
    localStorage.removeItem('seo_profile_id');
    loadProfiles();
}

function renderWsHeader() {
    const p = currentProfile;
    $('wsIcon').innerHTML = iconHtml(p, 56);
    $('wsTitle').textContent = p.name;
    $('wsMeta').textContent = `${p.slug}${p.domain ? ' · ' + p.domain : ''}${p.niche ? ' · ' + p.niche : ''}`;
    const badge = $('wsBadge');
    badge.className = 'badge ' + (p.is_active ? 'badge-active' : 'badge-inactive');
    badge.textContent = p.is_active ? 'Активен' : 'Неактивен';

    // Update workspace topbar
    $('topbarWsName').textContent = p.name;
    $('topbarWsMeta').textContent = (p.slug || '') + (p.domain ? ' \u00b7 ' + p.domain : '');
    const iconEl = $('topbarWsIcon');
    if (p.icon_path) {
        iconEl.innerHTML = '<img src="' + API + '?r=profiles/' + p.id + '/icon" style="width:100%;height:100%;object-fit:cover;border-radius:8px">';
    } else {
        iconEl.textContent = (p.name || '?')[0].toUpperCase();
        iconEl.style.color = p.color_scheme || '#6366f1';
    }
}

function switchWsTab(tab) {
    document.querySelectorAll('.ws-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
    ['Overview','Settings','Branding','Templates','Intents'].forEach(t => {
        $('tab' + t).style.display = t.toLowerCase() === tab ? '' : 'none';
    });

    if (tab === 'overview') loadOverview();
    else if (tab === 'settings') fillSettings();
    else if (tab === 'branding') fillBranding();
    else if (tab === 'templates') loadTemplates();
    else if (tab === 'intents') loadIntents();
}

// ── Overview ──
async function loadOverview() {
    const p = currentProfile;
    try {
        const res = await api(`profiles/${p.id}/stats`);
        if (res.success) {
            const s = res.data;
            $('statsGrid').innerHTML = [
                ['Каталогов', s.catalogs],
                ['Шаблонов', s.templates],
                ['Статей', s.articles],
                ['Опубликовано', s.published],
                ['Интентов', s.intents],
                ['Задач сбора', s.keyword_jobs],
                ['Кластеров', s.clusters],
                ['Хостов', s.publish_targets],
            ].map(([label, val]) => `
                <div class="stat-card"><div class="stat-value">${val}</div><div class="stat-label">${label}</div></div>
            `).join('');
        }
    } catch(e) {}
    $('overviewDesc').textContent = p.description || p.niche || 'Описание не задано';
}

// ── Settings ──
function fillSettings() {
    const p = currentProfile;
    $('sName').value = p.name || '';
    $('sSlug').value = p.slug || '';
    $('sDomain').value = p.domain || '';
    $('sNiche').value = p.niche || '';
    $('sBrand').value = p.brand_name || '';
    $('sLang').value = p.language || 'ru';
    $('sTone').value = p.tone || 'professional';
    $('sActive').value = p.is_active ? '1' : '0';
    $('sDescription').value = p.description || '';
    $('sPersona').value = p.gpt_persona || '';
    $('sRules').value = p.gpt_rules || '';
}

async function saveSettings() {
    const body = {
        name: $('sName').value, slug: $('sSlug').value,
        domain: $('sDomain').value || null, niche: $('sNiche').value || null,
        brand_name: $('sBrand').value || null, language: $('sLang').value,
        tone: $('sTone').value, is_active: parseInt($('sActive').value),
        description: $('sDescription').value || null,
        gpt_persona: $('sPersona').value || null, gpt_rules: $('sRules').value || null,
    };
    try {
        const res = await api(`profiles/${currentProfile.id}`, {
            method: 'PUT', headers: {'Content-Type':'application/json'}, body: JSON.stringify(body)
        });
        if (!res.success) { toast(res.error || 'Ошибка', true); return; }
        Object.assign(currentProfile, res.data);
        renderWsHeader();
        toast('Настройки сохранены');
    } catch(e) { toast('Ошибка сети', true); }
}

// ── Branding ──
function fillBranding() {
    const p = currentProfile;
    $('bColor').value = p.color_scheme || '#6366f1';
    $('bColorText').value = p.color_scheme || '#6366f1';
    $('bLogo').value = p.logo_url || '';
    $('bBaseUrl').value = p.base_url || '';

    const upload = $('brandIconUpload');
    if (p.icon_path) {
        upload.innerHTML = `<img src="${esc(iconUrl(p))}" alt=""><button class="remove-icon" onclick="event.stopPropagation();removeBrandIcon()">&times;</button>`;
        upload.classList.add('has-image');
    } else {
        upload.innerHTML = `<input type="file" id="brandIconFile" accept="image/png,image/jpeg,image/webp,image/svg+xml,image/gif" onchange="uploadBrandIcon(this)"><span class="icon-upload-text">Перетащите или нажмите</span><button class="remove-icon" onclick="event.stopPropagation();removeBrandIcon()">&times;</button>`;
        upload.classList.remove('has-image');
    }
    upload.onclick = () => {
        let input = upload.querySelector('input[type="file"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/png,image/jpeg,image/webp,image/svg+xml,image/gif';
            input.style.display = 'none';
            input.onchange = () => uploadBrandIcon(input);
            upload.appendChild(input);
        }
        input.click();
    };

    $('bColor').oninput = () => $('bColorText').value = $('bColor').value;
}

async function uploadBrandIcon(input) {
    if (!input.files || !input.files[0]) return;
    const fd = new FormData();
    fd.append('icon', input.files[0]);
    try {
        const res = await api(`profiles/${currentProfile.id}/icon`, { method: 'POST', body: fd });
        if (!res.success) { toast(res.error || 'Ошибка загрузки', true); return; }
        currentProfile.icon_path = res.data.icon_path;
        currentProfile.updated_at = new Date().toISOString();
        fillBranding();
        renderWsHeader();
        toast('Иконка загружена');
    } catch(e) { toast('Ошибка сети', true); }
}

async function removeBrandIcon() {
    try {
        const res = await api(`profiles/${currentProfile.id}/icon`, { method: 'DELETE' });
        if (!res.success) { toast(res.error || 'Ошибка', true); return; }
        currentProfile.icon_path = null;
        fillBranding();
        renderWsHeader();
        toast('Иконка удалена');
    } catch(e) { toast('Ошибка сети', true); }
}

async function saveBranding() {
    const body = {
        color_scheme: $('bColorText').value || '#6366f1',
        logo_url: $('bLogo').value || null,
        base_url: $('bBaseUrl').value || null,
    };
    try {
        const res = await api(`profiles/${currentProfile.id}`, {
            method: 'PUT', headers: {'Content-Type':'application/json'}, body: JSON.stringify(body)
        });
        if (!res.success) { toast(res.error || 'Ошибка', true); return; }
        Object.assign(currentProfile, res.data);
        renderWsHeader();
        toast('Брендинг сохранён');
    } catch(e) { toast('Ошибка сети', true); }
}

// ── Templates ──
async function loadTemplates() {
    const pid = currentProfile.id;
    try {
        const res = await api(`templates?profile_id=${pid}`);
        const templates = res.data || [];
        if (templates.length === 0) {
            $('templatesList').innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">&#128196;</div>
                    <div class="empty-state-title">Нет шаблонов</div>
                    <div class="empty-state-text">Создайте шаблон через AI — опишите тип статьи и AI подберёт блоки</div>
                    <button class="ai-badge" onclick="openGenModal()" style="font-size:.82rem;padding:8px 16px">+ AI Шаблон</button>
                </div>`;
            return;
        }
        $('templatesList').innerHTML = templates.map(t => `
            <div class="settings-section tpl-card" style="margin-bottom:10px" onclick="openTplEditor(${t.id})">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <div style="flex:1;min-width:0">
                        <div style="font-weight:600;color:#f1f5f9">${esc(t.name)}</div>
                        <div style="font-size:.72rem;color:#64748b;margin-top:2px">${esc(t.slug || '')} &middot; ${(t.blocks || []).length} блоков</div>
                    </div>
                    <span class="ai-badge" style="font-size:.65rem;padding:2px 8px" onclick="event.stopPropagation();openTplEditor(${t.id})">AI</span>
                </div>
            </div>
        `).join('');
    } catch(e) { $('templatesList').innerHTML = '<div style="color:#64748b">Ошибка загрузки</div>'; }
}

// ── Intents ──
async function loadIntents() {
    const pid = currentProfile.id;
    try {
        const res = await api(`intents?profile_id=${pid}`);
        const intents = res.data || [];
        if (intents.length === 0) {
            $('intentsList').innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">&#127919;</div>
                    <div class="empty-state-title">Нет интентов</div>
                    <div class="empty-state-text">Интенты для этого профиля можно настроить на странице Семантика</div>
                </div>`;
            return;
        }
        $('intentsList').innerHTML = intents.map(i => `
            <div class="settings-section" style="margin-bottom:8px;padding:14px 20px;display:flex;align-items:center;gap:12px">
                <span style="width:12px;height:12px;border-radius:50%;background:${esc(i.color || '#6366f1')};flex-shrink:0"></span>
                <div style="flex:1;min-width:0">
                    <div style="font-weight:600;color:#f1f5f9;font-size:.88rem">${esc(i.display_name || i.code)}</div>
                    ${i.description ? `<div style="font-size:.72rem;color:#64748b;margin-top:2px">${esc(i.description)}</div>` : ''}
                </div>
                <span style="font-size:.72rem;color:#475569">${esc(i.code)}</span>
            </div>
        `).join('');
    } catch(e) { $('intentsList').innerHTML = '<div style="color:#64748b">Ошибка загрузки</div>'; }
}

// ── Delete profile ──
async function deleteCurrentProfile() {
    if (!confirm('Удалить профиль "' + currentProfile.name + '"? Все связанные данные могут быть потеряны.')) return;
    try {
        const res = await api(`profiles/${currentProfile.id}?force=1`, { method: 'DELETE' });
        if (!res.success) { toast(res.error || 'Ошибка', true); return; }
        toast('Профиль удалён');
        goToList();
    } catch(e) { toast('Ошибка сети', true); }
}

// ═══════════════════ WIZARD ═══════════════════

function openWizard() {
    wizardStep = 1;
    wizIconFile = null;
    ['wizDesc','wizName','wizSlug','wizNiche','wizBrand','wizLogo','wizBaseUrl','wizPersona','wizRules','wizDomain'].forEach(id => $(id).value = '');
    $('wizLang').value = 'ru';
    $('wizTone').value = 'professional';
    $('wizColor').value = '#6366f1';
    $('wizColorText').value = '#6366f1';
    clearWizIcon();
    updateWizardUI();
    $('wizardOverlay').classList.add('show');
}

function closeWizard() {
    $('wizardOverlay').classList.remove('show');
}

function updateWizardUI() {
    [1,2,3].forEach(i => {
        const step = $(`wizStep${i}`);
        step.classList.toggle('active', i === wizardStep);
        step.classList.toggle('done', i < wizardStep);
        $(`wizBody${i}`).style.display = i === wizardStep ? '' : 'none';
    });

    $('wizBtnBack').style.display = wizardStep > 1 ? '' : 'none';
    $('wizBtnNext').textContent = wizardStep === 3 ? 'Создать профиль' : 'Далее \u2192';
    $('wizBtnNext').className = wizardStep === 3 ? 'btn btn-success' : 'btn btn-primary';
}

function wizardNext() {
    if (wizardStep === 1) {
        if (!$('wizName').value.trim()) { toast('Укажите название', true); return; }
        if (!$('wizSlug').value.trim()) {
            $('wizSlug').value = slugify($('wizName').value);
        }
        wizardStep = 2;
    } else if (wizardStep === 2) {
        renderWizPreview();
        wizardStep = 3;
    } else if (wizardStep === 3) {
        createProfileFromWizard();
        return;
    }
    updateWizardUI();
}

function wizardBack() {
    if (wizardStep > 1) {
        wizardStep--;
        updateWizardUI();
    }
}

function renderWizPreview() {
    const color = $('wizColorText').value || '#6366f1';
    const iconPreview = wizIconFile
        ? `<img src="${URL.createObjectURL(wizIconFile)}" style="width:56px;height:56px;border-radius:12px;object-fit:cover">`
        : `<div style="width:56px;height:56px;border-radius:12px;background:#334155;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:700;color:${color}">${($('wizName').value || '?')[0].toUpperCase()}</div>`;

    $('wizPreview').innerHTML = `
        <div style="display:flex;gap:16px;align-items:center;margin-bottom:16px">
            ${iconPreview}
            <div>
                <div style="font-size:1.2rem;font-weight:700;color:#f1f5f9">${esc($('wizName').value)}</div>
                <div style="font-size:.82rem;color:#64748b">${esc($('wizSlug').value)}${$('wizDomain').value ? ' · ' + esc($('wizDomain').value) : ''}</div>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;font-size:.82rem">
            <div><span style="color:#64748b">Ниша:</span> <span style="color:#e2e8f0">${esc($('wizNiche').value || '—')}</span></div>
            <div><span style="color:#64748b">Бренд:</span> <span style="color:#e2e8f0">${esc($('wizBrand').value || '—')}</span></div>
            <div><span style="color:#64748b">Язык:</span> <span style="color:#e2e8f0">${langLabels[$('wizLang').value] || $('wizLang').value}</span></div>
            <div><span style="color:#64748b">Тон:</span> <span style="color:#e2e8f0">${toneLabels[$('wizTone').value] || $('wizTone').value}</span></div>
            <div style="grid-column:1/-1"><span style="color:#64748b">Цвет:</span> <span style="display:inline-block;width:14px;height:14px;border-radius:3px;background:${color};vertical-align:middle"></span> ${esc(color)}</div>
        </div>
        ${$('wizPersona').value ? `<div style="margin-top:12px;padding-top:12px;border-top:1px solid #334155"><div style="font-size:.7rem;color:#64748b;text-transform:uppercase;margin-bottom:4px">GPT Персона</div><div style="font-size:.8rem;color:#94a3b8;white-space:pre-wrap">${esc($('wizPersona').value)}</div></div>` : ''}
    `;
}

async function createProfileFromWizard() {
    $('wizBtnNext').disabled = true;
    const body = {
        name: $('wizName').value.trim(),
        slug: $('wizSlug').value.trim() || slugify($('wizName').value),
        domain: $('wizDomain').value || null,
        niche: $('wizNiche').value || null,
        description: $('wizDesc').value || null,
        brand_name: $('wizBrand').value || null,
        language: $('wizLang').value,
        tone: $('wizTone').value,
        color_scheme: $('wizColorText').value || '#6366f1',
        logo_url: $('wizLogo').value || null,
        base_url: $('wizBaseUrl').value || null,
        gpt_persona: $('wizPersona').value || null,
        gpt_rules: $('wizRules').value || null,
    };

    try {
        const res = await api('profiles', {
            method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(body)
        });
        if (!res.success) { toast(res.error || 'Ошибка', true); $('wizBtnNext').disabled = false; return; }

        const newId = res.data.id;

        // Upload icon if selected
        if (wizIconFile) {
            const fd = new FormData();
            fd.append('icon', wizIconFile);
            await api(`profiles/${newId}/icon`, { method: 'POST', body: fd });
        }

        toast('Профиль создан!');
        closeWizard();
        loadProfiles();
        // Open workspace for new profile
        setTimeout(() => openWorkspace(newId), 300);
    } catch(e) {
        toast('Ошибка сети', true);
    } finally {
        $('wizBtnNext').disabled = false;
    }
}

// ── AI Generate profile ──
async function aiGenerateProfile() {
    const desc = $('wizDesc').value.trim();
    if (!desc) { toast('Опишите проект', true); return; }

    $('btnAiGen').disabled = true;
    $('aiGenStatus').style.display = '';

    try {
        const res = await api('profiles/generate-from-description', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ description: desc }),
        });

        if (!res.success) { toast(res.error || 'Ошибка GPT', true); return; }

        const p = res.data.profile;
        if (p.name) $('wizName').value = p.name;
        if (p.slug) $('wizSlug').value = p.slug;
        if (p.niche) $('wizNiche').value = p.niche;
        if (p.brand_name) $('wizBrand').value = p.brand_name;
        if (p.language) $('wizLang').value = p.language;
        if (p.tone) $('wizTone').value = p.tone;
        if (p.gpt_persona) $('wizPersona').value = p.gpt_persona;
        if (p.gpt_rules) $('wizRules').value = p.gpt_rules;
        if (p.color_scheme) {
            $('wizColor').value = p.color_scheme;
            $('wizColorText').value = p.color_scheme;
        }

        toast('AI заполнил поля!');
    } catch(e) {
        toast('Ошибка сети', true);
    } finally {
        $('btnAiGen').disabled = false;
        $('aiGenStatus').style.display = 'none';
    }
}

// ── Wizard icon preview ──
function previewWizIcon(input) {
    if (!input.files || !input.files[0]) return;
    wizIconFile = input.files[0];
    const upload = $('wizIconUpload');
    upload.innerHTML = `<img src="${URL.createObjectURL(wizIconFile)}" alt=""><button class="remove-icon" onclick="event.stopPropagation();clearWizIcon()">&times;</button>`;
    upload.classList.add('has-image');
}

function clearWizIcon() {
    wizIconFile = null;
    const upload = $('wizIconUpload');
    upload.innerHTML = `<input type="file" id="wizIconFile" accept="image/png,image/jpeg,image/webp,image/svg+xml,image/gif" onchange="previewWizIcon(this)"><span class="icon-upload-text">Загрузить</span><button class="remove-icon" onclick="event.stopPropagation();clearWizIcon()">&times;</button>`;
    upload.classList.remove('has-image');
}

// ═══════════════════ AI TEMPLATE GENERATION (SSE) ═══════════════════

function openGenModal() {
    $('genPurpose').value = '';
    $('genHints').value = '';
    $('genForm').style.display = '';
    $('genProgress').style.display = 'none';
    $('genPreview').style.display = 'none';
    $('genPreview').innerHTML = '';
    $('genReview').style.display = 'none';
    $('genReview').innerHTML = '';
    $('btnGenStart').disabled = false;
    $('btnGenStart').textContent = 'Сгенерировать шаблон';
    $('btnGenClose').textContent = 'Закрыть';
    ['genStep1','genStep2','genStep3'].forEach(function(id) {
        $(id).className = 'gen-step';
        $(id + 'Icon').textContent = id.replace('genStep', '');
        $(id + 'Status').textContent = '';
        $(id + 'Status').innerHTML = '';
    });
    genRunning = false;
    $('genModal').classList.add('show');
}

function closeGenModal() {
    if (genRunning) {
        if (!confirm('Генерация в процессе. Закрыть?')) return;
    }
    $('genModal').classList.remove('show');
    genRunning = false;
}

async function startTemplateGeneration() {
    const purpose = $('genPurpose').value.trim();
    if (!purpose) { toast('Опишите назначение шаблона', true); return; }

    $('btnGenStart').disabled = true;
    $('btnGenStart').innerHTML = '<span class="spinner"></span> Генерация...';
    $('genForm').style.display = 'none';
    $('genProgress').style.display = '';
    genRunning = true;

    try {
        const response = await fetch(API + '?r=profiles/' + currentProfile.id + '/generate-template-sse', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                purpose: purpose,
                hints: $('genHints').value.trim() || null,
            }),
        });

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const {value, done} = await reader.read();
            if (done) break;

            buffer += decoder.decode(value, {stream: true});
            const lines = buffer.split('\n');
            buffer = lines.pop();

            let eventName = '';
            for (const line of lines) {
                if (line.startsWith('event: ')) {
                    eventName = line.substring(7).trim();
                } else if (line.startsWith('data: ') && eventName) {
                    try {
                        const data = JSON.parse(line.substring(6));
                        handleTemplateGenEvent(eventName, data);
                    } catch(e) {}
                    eventName = '';
                }
            }
        }
    } catch(e) {
        toast('Ошибка: ' + e.message, true);
    }

    genRunning = false;
    $('btnGenClose').textContent = 'Закрыть';
}

function handleTemplateGenEvent(event, data) {
    switch (event) {
        case 'start':
            $('genStep1').className = 'gen-step active';
            $('genStep1Status').innerHTML = '<span class="spinner"></span>';
            break;

        case 'generation_start':
            $('genStep1').className = 'gen-step active';
            $('genStep1Status').innerHTML = '<span class="spinner"></span> AI подбирает блоки...';
            break;

        case 'generation_done':
            $('genStep1').className = 'gen-step done';
            $('genStep1Icon').innerHTML = '&#10003;';
            $('genStep1Status').textContent = 'Готово';
            if (data.template) {
                $('genPreview').style.display = '';
                const blocks = data.template.blocks || [];
                $('genPreview').innerHTML =
                    '<div style="font-weight:700;color:#f1f5f9;margin-bottom:4px">' + esc(data.template.name) + '</div>'
                    + '<div style="font-size:.78rem;color:#94a3b8;margin-bottom:10px">' + esc(data.template.description || '') + '</div>'
                    + '<div style="font-size:.72rem;color:#64748b;margin-bottom:6px">Блоки (' + blocks.length + '):</div>'
                    + blocks.map(function(b, i) {
                        return '<div class="gen-tpl-item" style="padding:10px;margin-bottom:6px">'
                            + '<div style="display:flex;gap:8px;align-items:center;margin-bottom:4px">'
                            + '<span class="gen-block-chip">' + esc(b.type) + '</span>'
                            + '<span style="font-size:.82rem;font-weight:600;color:#e2e8f0">' + esc(b.name) + '</span>'
                            + (b.is_required ? '<span style="font-size:.6rem;color:#fcd34d">&#9733; обяз.</span>' : '')
                            + '</div>'
                            + (b.hint ? '<div style="font-size:.72rem;color:#94a3b8">' + esc(b.hint) + '</div>' : '')
                            + '</div>';
                    }).join('');
            }
            break;

        case 'review_start':
            $('genStep2').className = 'gen-step active';
            $('genStep2Status').innerHTML = '<span class="spinner"></span> AI проверяет качество...';
            break;

        case 'review_done':
            $('genStep2').className = 'gen-step done';
            $('genStep2Icon').innerHTML = '&#10003;';
            const review = data.review || {};
            const score = review.score || 0;
            const scoreColor = score >= 8 ? '#4ade80' : score >= 5 ? '#fbbf24' : '#f87171';
            $('genStep2Status').innerHTML = '<span style="color:' + scoreColor + ';font-weight:700">' + score + '/10</span>';

            if (review.suggestions && review.suggestions.length) {
                $('genReview').style.display = '';
                $('genReview').innerHTML =
                    '<div style="font-size:.72rem;color:#64748b;margin-bottom:6px;text-transform:uppercase;letter-spacing:.3px">Рекомендации AI:</div>'
                    + review.suggestions.map(function(s) {
                        return '<div class="gen-review-suggestion">&#8226; ' + esc(s) + '</div>';
                    }).join('');
            }

            // Update preview with improved template if available
            if (data.template && data.template.blocks) {
                const blocks = data.template.blocks;
                $('genPreview').innerHTML =
                    '<div style="font-weight:700;color:#f1f5f9;margin-bottom:4px">' + esc(data.template.name) + '</div>'
                    + '<div style="font-size:.78rem;color:#94a3b8;margin-bottom:10px">' + esc(data.template.description || '') + '</div>'
                    + '<div style="font-size:.72rem;color:#64748b;margin-bottom:6px">Блоки (' + blocks.length + '):</div>'
                    + blocks.map(function(b) {
                        return '<div class="gen-tpl-item" style="padding:10px;margin-bottom:6px">'
                            + '<div style="display:flex;gap:8px;align-items:center;margin-bottom:4px">'
                            + '<span class="gen-block-chip">' + esc(b.type) + '</span>'
                            + '<span style="font-size:.82rem;font-weight:600;color:#e2e8f0">' + esc(b.name) + '</span>'
                            + (b.is_required ? '<span style="font-size:.6rem;color:#fcd34d">&#9733; обяз.</span>' : '')
                            + '</div>'
                            + (b.hint ? '<div style="font-size:.72rem;color:#94a3b8">' + esc(b.hint) + '</div>' : '')
                            + '</div>';
                    }).join('');
            }
            break;

        case 'save_start':
            $('genStep3').className = 'gen-step active';
            $('genStep3Status').innerHTML = '<span class="spinner"></span> Сохранение...';
            break;

        case 'save_done':
            $('genStep3').className = 'gen-step done';
            $('genStep3Icon').innerHTML = '&#10003;';
            $('genStep3Status').textContent = 'Шаблон #' + data.template_id;
            toast('Шаблон создан!');
            loadTemplates();
            break;

        case 'done':
            const usage = data.usage || {};
            if (usage.total_tokens) {
                $('genStep3Status').textContent += ' (' + usage.total_tokens + ' токенов)';
            }
            break;

        case 'error':
            toast('Ошибка: ' + (data.message || 'Неизвестная ошибка'), true);
            ['genStep1','genStep2','genStep3'].forEach(function(id) {
                if ($(id).classList.contains('active')) {
                    $(id).className = 'gen-step error';
                    $(id + 'Icon').innerHTML = '&#10007;';
                    $(id + 'Status').textContent = data.message || 'Ошибка';
                }
            });
            genRunning = false;
            break;
    }
}

// ═══════════════════ TEMPLATE AI EDITOR ═══════════════════

let tplEditorData = null;
let tplPreReviewData = null;
let tplReviewImproved = null;
let tplRegenRunning = false;

async function openTplEditor(templateId) {
    try {
        const res = await api('templates/' + templateId);
        if (!res.success) { toast(res.error || 'Ошибка', true); return; }
        tplEditorData = res.data;
    } catch(e) { toast('Ошибка сети', true); return; }

    tplPreReviewData = null;
    tplReviewImproved = null;
    tplRegenRunning = false;
    renderTplEditor();
    $('tplEditorModal').classList.add('show');
}

function closeTplEditor() {
    if (tplRegenRunning && !confirm('Генерация в процессе. Закрыть?')) return;
    $('tplEditorModal').classList.remove('show');
    tplRegenRunning = false;
}

function parseTplBlockConfig(b) {
    if (!b.config) return {};
    if (typeof b.config === 'object') return b.config;
    try { return JSON.parse(b.config); } catch(e) { return {}; }
}

function renderTplEditor() {
    var t = tplEditorData;
    $('tplEditorTitle').textContent = t.name;

    $('tplEditorInfo').innerHTML =
        '<div style="font-size:.82rem;color:#94a3b8;margin-bottom:8px">' + esc(t.description || '') + '</div>'
        + (t.gpt_system_prompt
            ? '<div style="font-size:.72rem;color:#475569;padding:8px;background:#0f172a;border-radius:6px;white-space:pre-wrap;word-break:break-word">' + esc(t.gpt_system_prompt) + '</div>'
            : '');

    var blocks = t.blocks || [];
    $('tplEditorBlocks').innerHTML =
        '<div style="font-size:.72rem;color:#64748b;margin-bottom:8px;text-transform:uppercase;letter-spacing:.3px">Блоки (' + blocks.length + ')</div>'
        + blocks.map(function(b) {
            var cfg = parseTplBlockConfig(b);
            return '<div class="gen-tpl-item" style="padding:10px;margin-bottom:6px">'
                + '<div style="display:flex;gap:8px;align-items:center;margin-bottom:4px">'
                + '<span class="gen-block-chip">' + esc(b.type) + '</span>'
                + '<span style="font-size:.82rem;font-weight:600;color:#e2e8f0">' + esc(b.name) + '</span>'
                + (b.is_required ? '<span style="font-size:.6rem;color:#fcd34d">&#9733; обяз.</span>' : '')
                + '</div>'
                + (cfg.hint ? '<div style="font-size:.72rem;color:#94a3b8">' + esc(cfg.hint) + '</div>' : '')
                + '</div>';
        }).join('');

    $('tplReviewResult').style.display = 'none';
    $('tplRegenForm').style.display = 'none';
    $('tplRegenProgress').style.display = 'none';
    $('btnAiReview').disabled = false;
    $('btnAiReview').innerHTML = 'AI Ревью';
    $('btnAiRegen').disabled = false;
    $('tplEditorFooterActions').innerHTML = '';
}

// ── AI Review ──
async function runAiReview() {
    $('btnAiReview').disabled = true;
    $('btnAiReview').innerHTML = '<span class="spinner"></span> Анализ...';
    $('btnAiRegen').disabled = true;
    $('tplRegenForm').style.display = 'none';
    $('tplRegenProgress').style.display = 'none';
    $('tplReviewResult').style.display = '';
    $('tplReviewResult').innerHTML = '<div style="text-align:center;padding:20px"><span class="spinner"></span><div style="font-size:.82rem;color:#94a3b8;margin-top:8px">AI анализирует шаблон...</div></div>';

    tplPreReviewData = JSON.parse(JSON.stringify(tplEditorData));

    try {
        var res = await api('templates/' + tplEditorData.id + '/ai-review', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({}),
        });

        if (!res.success) { toast(res.error || 'Ошибка', true); resetReviewUI(); return; }

        var d = res.data;
        var score = d.score || 0;
        var scoreColor = score >= 8 ? '#4ade80' : score >= 5 ? '#fbbf24' : '#f87171';
        tplReviewImproved = d.improved_template;

        var html = '<div class="tpl-review-box">'
            + '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">'
            + '<span style="font-size:.82rem;font-weight:700;color:#f1f5f9">Результат ревью</span>'
            + '<span class="tpl-score" style="color:' + scoreColor + '">' + score + '/10</span>'
            + '</div>';

        if (d.suggestions && d.suggestions.length) {
            html += '<div style="margin-bottom:12px">';
            d.suggestions.forEach(function(s) {
                html += '<div class="gen-review-suggestion">&#8226; ' + esc(s) + '</div>';
            });
            html += '</div>';
        }

        if (tplReviewImproved && tplReviewImproved.blocks && tplReviewImproved.blocks.length) {
            html += '<div style="font-size:.72rem;color:#64748b;margin-bottom:6px;margin-top:12px;text-transform:uppercase">Улучшенная версия — блоки (' + tplReviewImproved.blocks.length + '):</div>';
            tplReviewImproved.blocks.forEach(function(b) {
                html += '<div class="gen-tpl-item tpl-diff-block" style="padding:8px;margin-bottom:4px">'
                    + '<div style="display:flex;gap:8px;align-items:center">'
                    + '<span class="gen-block-chip">' + esc(b.type) + '</span>'
                    + '<span style="font-size:.8rem;font-weight:600;color:#e2e8f0">' + esc(b.name) + '</span>'
                    + '</div>'
                    + (b.hint ? '<div style="font-size:.72rem;color:#94a3b8;margin-top:4px">' + esc(b.hint) + '</div>' : '')
                    + '</div>';
            });
        }

        html += '</div>';
        $('tplReviewResult').innerHTML = html;

        var footerHtml = '';
        if (tplReviewImproved && tplReviewImproved.blocks && tplReviewImproved.blocks.length) {
            footerHtml += '<button class="btn btn-success btn-sm" onclick="applyReview()">Применить улучшения</button>';
        }
        footerHtml += '<button class="btn btn-ghost btn-sm" onclick="dismissReview()">Оставить как есть</button>';
        $('tplEditorFooterActions').innerHTML = footerHtml;

    } catch(e) {
        toast('Ошибка: ' + e.message, true);
        resetReviewUI();
    }

    $('btnAiReview').innerHTML = 'AI Ревью';
    $('btnAiReview').disabled = false;
    $('btnAiRegen').disabled = false;
}

function resetReviewUI() {
    $('btnAiReview').innerHTML = 'AI Ревью';
    $('btnAiReview').disabled = false;
    $('btnAiRegen').disabled = false;
    $('tplReviewResult').style.display = 'none';
    $('tplEditorFooterActions').innerHTML = '';
}

async function applyReview() {
    if (!tplReviewImproved) return;

    try {
        var res = await api('templates/' + tplEditorData.id + '/ai-apply', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ template: tplReviewImproved }),
        });

        if (!res.success) { toast(res.error || 'Ошибка', true); return; }

        tplEditorData = res.data;
        renderTplEditor();
        toast('Улучшения применены');
        $('tplEditorFooterActions').innerHTML = '<button class="btn btn-warn btn-sm" onclick="rollbackReview()">Откатить изменения</button>';
        loadTemplates();
    } catch(e) { toast('Ошибка: ' + e.message, true); }
}

async function rollbackReview() {
    if (!tplPreReviewData) { toast('Нет данных для отката', true); return; }

    var original = tplPreReviewData;
    var templateData = {
        name: original.name,
        description: original.description,
        gpt_system_prompt: original.gpt_system_prompt,
        css_class: original.css_class,
        blocks: (original.blocks || []).map(function(b, i) {
            var cfg = parseTplBlockConfig(b);
            return {
                type: b.type,
                name: b.name,
                hint: cfg.hint || '',
                fields: cfg.fields || [],
                sort_order: b.sort_order || (i + 1),
                is_required: b.is_required,
            };
        }),
    };

    try {
        var res = await api('templates/' + tplEditorData.id + '/ai-apply', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ template: templateData }),
        });

        if (!res.success) { toast(res.error || 'Ошибка', true); return; }

        tplEditorData = res.data;
        tplPreReviewData = null;
        renderTplEditor();
        toast('Откат выполнен');
        loadTemplates();
    } catch(e) { toast('Ошибка: ' + e.message, true); }
}

function dismissReview() {
    $('tplReviewResult').style.display = 'none';
    $('tplEditorFooterActions').innerHTML = '';
    tplReviewImproved = null;
}

// ── Regenerate ──
function openRegenForm() {
    $('tplReviewResult').style.display = 'none';
    $('tplRegenProgress').style.display = 'none';
    $('tplEditorFooterActions').innerHTML = '';
    $('tplRegenForm').style.display = '';
    $('tplRegenForm').innerHTML =
        '<div class="tpl-review-box">'
        + '<div style="font-size:.82rem;font-weight:700;color:#f1f5f9;margin-bottom:12px">Перегенерация шаблона</div>'
        + '<div class="form-row">'
        + '<label>Назначение шаблона — тип статьи</label>'
        + '<textarea id="tplRegenPurpose" rows="3" placeholder="Опишите тип статьи...">' + esc(tplEditorData.description || '') + '</textarea>'
        + '<div class="form-hint">Опишите подробно — AI сгенерирует новую структуру блоков</div>'
        + '</div>'
        + '<div class="form-row">'
        + '<label>Дополнительные подсказки (необязательно)</label>'
        + '<textarea id="tplRegenHints" rows="2" placeholder="Особые требования: нужны таблицы, обязательно FAQ..."></textarea>'
        + '</div>'
        + '<div style="display:flex;gap:8px;margin-top:8px">'
        + '<button class="btn btn-primary btn-sm" onclick="startRegeneration()">Перегенерировать</button>'
        + '<button class="btn btn-ghost btn-sm" onclick="$(\'tplRegenForm\').style.display=\'none\'">Отмена</button>'
        + '</div>'
        + '</div>';
}

async function startRegeneration() {
    var purpose = $('tplRegenPurpose').value.trim();
    if (!purpose) { toast('Опишите назначение', true); return; }

    tplPreReviewData = JSON.parse(JSON.stringify(tplEditorData));

    $('tplRegenForm').style.display = 'none';
    $('tplRegenProgress').style.display = '';
    $('tplRegenProgress').innerHTML =
        '<div style="margin-bottom:16px">'
        + '<div class="gen-step" id="regenStep1"><span class="gen-step-icon" id="regenStep1Icon">1</span><span class="gen-step-label">Генерация шаблона</span><span class="gen-step-status" id="regenStep1Status"></span></div>'
        + '<div class="gen-step" id="regenStep2"><span class="gen-step-icon" id="regenStep2Icon">2</span><span class="gen-step-label">Ревью качества</span><span class="gen-step-status" id="regenStep2Status"></span></div>'
        + '<div class="gen-step" id="regenStep3"><span class="gen-step-icon" id="regenStep3Icon">3</span><span class="gen-step-label">Сохранение</span><span class="gen-step-status" id="regenStep3Status"></span></div>'
        + '</div>'
        + '<div id="regenPreview" style="display:none" class="gen-proposal"></div>'
        + '<div id="regenReview" style="display:none;margin-top:12px"></div>';

    $('btnAiReview').disabled = true;
    $('btnAiRegen').disabled = true;
    tplRegenRunning = true;

    var hints = $('tplRegenHints') ? $('tplRegenHints').value.trim() || null : null;

    try {
        var response = await fetch(API + '?r=templates/' + tplEditorData.id + '/ai-regenerate-sse', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ purpose: purpose, hints: hints }),
        });

        var reader = response.body.getReader();
        var decoder = new TextDecoder();
        var buffer = '';

        while (true) {
            var chunk = await reader.read();
            if (chunk.done) break;

            buffer += decoder.decode(chunk.value, {stream: true});
            var lines = buffer.split('\n');
            buffer = lines.pop();

            var eventName = '';
            for (var li = 0; li < lines.length; li++) {
                var line = lines[li];
                if (line.indexOf('event: ') === 0) {
                    eventName = line.substring(7).trim();
                } else if (line.indexOf('data: ') === 0 && eventName) {
                    try {
                        var evData = JSON.parse(line.substring(6));
                        handleRegenEvent(eventName, evData);
                    } catch(e) {}
                    eventName = '';
                }
            }
        }
    } catch(e) {
        toast('Ошибка: ' + e.message, true);
    }

    tplRegenRunning = false;
    $('btnAiReview').disabled = false;
    $('btnAiRegen').disabled = false;
}

function handleRegenEvent(event, data) {
    switch (event) {
        case 'start':
            $('regenStep1').className = 'gen-step active';
            $('regenStep1Status').innerHTML = '<span class="spinner"></span>';
            break;

        case 'generation_start':
            $('regenStep1').className = 'gen-step active';
            $('regenStep1Status').innerHTML = '<span class="spinner"></span> AI подбирает блоки...';
            break;

        case 'generation_done':
            $('regenStep1').className = 'gen-step done';
            $('regenStep1Icon').innerHTML = '&#10003;';
            $('regenStep1Status').textContent = 'Готово';
            if (data.template) renderRegenPreview(data.template);
            break;

        case 'review_start':
            $('regenStep2').className = 'gen-step active';
            $('regenStep2Status').innerHTML = '<span class="spinner"></span> AI проверяет...';
            break;

        case 'review_done':
            $('regenStep2').className = 'gen-step done';
            $('regenStep2Icon').innerHTML = '&#10003;';
            var review = data.review || {};
            var score = review.score || 0;
            var scoreColor = score >= 8 ? '#4ade80' : score >= 5 ? '#fbbf24' : '#f87171';
            $('regenStep2Status').innerHTML = '<span style="color:' + scoreColor + ';font-weight:700">' + score + '/10</span>';

            if (review.suggestions && review.suggestions.length) {
                $('regenReview').style.display = '';
                $('regenReview').innerHTML =
                    '<div style="font-size:.72rem;color:#64748b;margin-bottom:6px;text-transform:uppercase">Рекомендации:</div>'
                    + review.suggestions.map(function(s) { return '<div class="gen-review-suggestion">&#8226; ' + esc(s) + '</div>'; }).join('');
            }

            if (data.template) renderRegenPreview(data.template);
            break;

        case 'save_start':
            $('regenStep3').className = 'gen-step active';
            $('regenStep3Status').innerHTML = '<span class="spinner"></span> Сохранение...';
            break;

        case 'save_done':
            $('regenStep3').className = 'gen-step done';
            $('regenStep3Icon').innerHTML = '&#10003;';
            $('regenStep3Status').textContent = 'Сохранено';
            toast('Шаблон перегенерирован');
            reloadTplEditor(data.template_id);
            loadTemplates();
            $('tplEditorFooterActions').innerHTML = '<button class="btn btn-warn btn-sm" onclick="rollbackReview()">Откатить изменения</button>';
            break;

        case 'done':
            var usage = data.usage || {};
            if (usage.total_tokens && $('regenStep3Status')) {
                $('regenStep3Status').textContent += ' (' + usage.total_tokens + ' токенов)';
            }
            break;

        case 'error':
            toast('Ошибка: ' + (data.message || 'Неизвестная ошибка'), true);
            ['regenStep1','regenStep2','regenStep3'].forEach(function(id) {
                if ($(id) && $(id).classList.contains('active')) {
                    $(id).className = 'gen-step error';
                    $(id + 'Icon').innerHTML = '&#10007;';
                    $(id + 'Status').textContent = data.message || 'Ошибка';
                }
            });
            tplRegenRunning = false;
            $('btnAiReview').disabled = false;
            $('btnAiRegen').disabled = false;
            break;
    }
}

function renderRegenPreview(template) {
    var blocks = template.blocks || [];
    $('regenPreview').style.display = '';
    $('regenPreview').innerHTML =
        '<div style="font-weight:700;color:#f1f5f9;margin-bottom:4px">' + esc(template.name) + '</div>'
        + '<div style="font-size:.78rem;color:#94a3b8;margin-bottom:10px">' + esc(template.description || '') + '</div>'
        + '<div style="font-size:.72rem;color:#64748b;margin-bottom:6px">Блоки (' + blocks.length + '):</div>'
        + blocks.map(function(b) {
            return '<div class="gen-tpl-item" style="padding:10px;margin-bottom:6px">'
                + '<div style="display:flex;gap:8px;align-items:center;margin-bottom:4px">'
                + '<span class="gen-block-chip">' + esc(b.type) + '</span>'
                + '<span style="font-size:.82rem;font-weight:600;color:#e2e8f0">' + esc(b.name) + '</span>'
                + (b.is_required ? '<span style="font-size:.6rem;color:#fcd34d">&#9733; обяз.</span>' : '')
                + '</div>'
                + (b.hint ? '<div style="font-size:.72rem;color:#94a3b8">' + esc(b.hint) + '</div>' : '')
                + '</div>';
        }).join('');
}

async function reloadTplEditor(templateId) {
    try {
        var res = await api('templates/' + templateId);
        if (res.success) {
            tplEditorData = res.data;
            $('tplEditorTitle').textContent = tplEditorData.name;
        }
    } catch(e) {}
}

// ═══════════════════ UTILS ═══════════════════

function slugify(text) {
    const ru = {'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'yo','ж':'zh','з':'z','и':'i','й':'y','к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r','с':'s','т':'t','у':'u','ф':'f','х':'h','ц':'ts','ч':'ch','ш':'sh','щ':'sch','ъ':'','ы':'y','ь':'','э':'e','ю':'yu','я':'ya'};
    return text.toLowerCase().split('').map(c => ru[c] || c).join('')
        .replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').substring(0, 100);
}

$('wizColor').oninput = () => $('wizColorText').value = $('wizColor').value;
$('wizColorText').oninput = () => { if (/^#[0-9a-f]{6}$/i.test($('wizColorText').value)) $('wizColor').value = $('wizColorText').value; };
$('wizName').oninput = () => { if (!$('wizSlug').dataset.manual) $('wizSlug').value = slugify($('wizName').value); };
$('wizSlug').oninput = () => { $('wizSlug').dataset.manual = '1'; };

// ── Init ──
loadProfiles();
</script>
</body>
</html>
