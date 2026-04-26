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
<link rel="stylesheet" href="assets/articles.css">
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
        <a href="../admin_advanced/seo_page.php" class="topbar-nav-link" style="color:var(--accent);font-weight:600">⚡ Advanced</a>
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
        <button class="btn btn-primary" onclick="openCreateModal()">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M8 3v10M3 8h10"/></svg>
            Новая статья
        </button>
    </div>
    <div class="filters">
        <input type="text" id="filterSearch" placeholder="Поиск по заголовку...">
        <select id="filterProfile"><option value="">— выберите профиль —</option></select>
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

<!-- ─── Wizard modal: создание статьи ─── -->
<div class="modal-backdrop" id="wizModal">
    <div class="modal">
        <div class="modal-head">
            <span class="modal-title" id="wizTitle">Новая статья</span>
            <button class="modal-close" onclick="closeCreateModal()">×</button>
        </div>
        <div class="modal-body">
            <div class="wiz-steps">
                <div class="wiz-step-pill" data-step="1"></div>
                <div class="wiz-step-pill" data-step="2"></div>
                <div class="wiz-step-pill" data-step="3"></div>
            </div>

            <!-- Step 1: profile -->
            <div id="wizStep1">
                <div class="field">
                    <label>Выберите профиль</label>
                    <div class="tpl-pick-grid" id="wizProfiles"></div>
                </div>
            </div>

            <!-- Step 2: template -->
            <div id="wizStep2" style="display:none">
                <div class="field">
                    <label>Выберите шаблон</label>
                    <div class="tpl-pick-grid" id="wizTemplates"></div>
                </div>
            </div>

            <!-- Step 3: title -->
            <div id="wizStep3" style="display:none">
                <div class="field">
                    <label>Заголовок статьи</label>
                    <input type="text" id="wizTitleInput" placeholder="Напр. Как выбрать кроссовки для бега">
                </div>
                <div class="field">
                    <label>Slug (URL)</label>
                    <div style="display:flex;gap:6px;align-items:stretch">
                        <input type="text" id="wizSlugInput" placeholder="kak-vybrat-krossovki" style="flex:1">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="regenWizSlug()" title="Сгенерировать slug из заголовка">
                            ⚡ Сгенерировать
                        </button>
                    </div>
                    <div class="field-hint">Авто-генерация из заголовка. Можно редактировать вручную.</div>
                </div>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-ghost" id="wizBack" onclick="wizBack()" style="display:none">Назад</button>
            <button class="btn btn-primary" id="wizNext" onclick="wizNext()" disabled>Далее</button>
        </div>
    </div>
</div>

<!-- ─── Publish modal ─── -->
<div class="modal-backdrop" id="fullPreviewModal">
    <div class="modal" style="max-width:1100px;width:95vw;height:90vh;display:flex;flex-direction:column">
        <div class="modal-head">
            <span class="modal-title">Предпросмотр статьи</span>
            <button class="modal-close" onclick="closeFullPreview()">×</button>
        </div>
        <div class="modal-body" style="flex:1;padding:0;overflow:hidden">
            <iframe id="fullPreviewFrame" style="width:100%;height:100%;border:0;background:#fff" sandbox="allow-scripts allow-same-origin"></iframe>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="pubModal">
    <div class="modal">
        <div class="modal-head">
            <span class="modal-title">Публикация</span>
            <button class="modal-close" onclick="closePublishModal()">×</button>
        </div>
        <div class="modal-body">
            <div class="field">
                <label>URL опубликованной страницы</label>
                <input type="url" id="pubUrl" placeholder="https://example.com/article">
                <div class="field-hint">После сохранения статус станет «Опубликовано».</div>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-ghost" onclick="closePublishModal()">Отмена</button>
            <button class="btn btn-secondary" id="btnUnpublish" onclick="doUnpublish()" style="display:none">Снять с публикации</button>
            <button class="btn btn-primary" onclick="doPublish()">
                <span id="pubSpin"></span> Опубликовать
            </button>
        </div>
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
            <div class="ed-status-group">
                <span class="status-pill draft" id="statusPill"><span class="status-pill-dot"></span><span id="statusPillText">Черновик</span></span>
                <span class="save-state saved" id="saveState"><span class="save-state-dot"></span><span id="saveStateText">Сохранено</span></span>
            </div>
            <div class="ed-btn-group" title="Предпросмотр">
                <button onclick="openFullPreview()" id="btnFullPreview" title="Предпросмотр всей статьи (модальное окно)">👁 Превью</button>
                <button onclick="openPublicPage()" id="btnViewPage" style="display:none" title="Открыть опубликованную страницу">🔗 На сайте</button>
            </div>
            <div class="ed-actions-primary">
                <button class="btn btn-primary btn-sm" onclick="openPublishModal()" id="btnPublish">📤 Опубликовать</button>
            </div>
            <div class="ed-actions-sep"></div>
            <div class="adv-toggle" id="advToggle" onclick="toggleAdvanced()">
                <span>Расширенный режим</span>
                <span class="adv-switch"></span>
            </div>
        </div>
    </div>

    <!-- Main -->
    <div class="section">
        <div class="section-head"><span class="section-head-title">Основные данные</span></div>
        <div class="section-body">
            <div class="form-row">
                <div class="field">
                    <label>Заголовок</label>
                    <input type="text" id="fTitle" placeholder="Заголовок статьи" data-autosave="main">
                </div>
                <div class="field">
                    <label>Slug (URL)</label>
                    <input type="text" id="fSlug" placeholder="url-slug" data-autosave="main">
                </div>
            </div>
            <div class="form-row adv-only">
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
    </div>

    <!-- SEO Meta -->
    <div class="section">
        <div class="section-head"><span class="section-head-title">SEO Meta</span></div>
        <div class="section-body">
            <div class="field">
                <label>Meta Title</label>
                <input type="text" id="fMetaTitle" placeholder="SEO title для &lt;title&gt; и OG" data-autosave="meta">
            </div>
            <div class="field">
                <label>Meta Description</label>
                <textarea id="fMetaDesc" rows="3" placeholder="Описание для поисковиков (до ~160 символов)" data-autosave="meta"></textarea>
            </div>
        </div>
    </div>

    <!-- Research dossier / Outline — advanced-only. См. admin_advanced. -->

    <!-- Generate -->
    <div class="gen-card">
        <div class="gen-card-icon">✨</div>
        <div class="gen-card-body">
            <div class="gen-card-title">AI-генерация контента</div>
            <div class="gen-card-desc">Одна кнопка сгенерирует все блоки и SEO meta-теги статьи. Если research/outline пусты — соберёт их первыми шагами.</div>
        </div>
        <button class="btn btn-primary" id="btnGenerate" onclick="generateAll()">
            <span id="genSpin"></span> Сгенерировать всё
        </button>
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

    <!-- Workflow -->
    <div class="section adv-only">
        <div class="section-head">
            <span class="section-head-title">Этап работы</span>
            <span style="display:flex;gap:8px;align-items:center">
                <select id="wfStatus" onchange="changeWorkflowStatus(this.value)" style="font-size:13px;padding:4px 8px">
                    <option value="draft">Черновик</option>
                    <option value="research_done">Research готов</option>
                    <option value="outline_done">Outline готов</option>
                    <option value="blocks_done">Блоки готовы</option>
                    <option value="ai_review">AI-ревью</option>
                    <option value="human_review">Ручное ревью</option>
                    <option value="review">Готов к публикации</option>
                    <option value="archived">Архив</option>
                </select>
                <label style="font-size:12px;color:var(--text-3);margin-left:12px">Тема:</label>
                <select id="artTheme" onchange="changeArticleTheme(this.value)" style="font-size:13px;padding:4px 8px">
                    <option value="">— наследовать от профиля —</option>
                </select>
            </span>
        </div>
    </div>

    <!-- Editorial QA -->
    <div class="section">
        <div class="section-head">
            <span class="section-head-title">Редакторские проверки</span>
            <span style="display:flex;gap:8px;align-items:center">
                <span id="qaSummary" style="font-size:12px;color:var(--text-3)"></span>
                <label style="font-size:12px;color:var(--text-3);display:inline-flex;align-items:center;gap:4px;cursor:pointer" title="GPT-проверка связности (~$0.01)">
                    <input type="checkbox" id="qaAiReview"> AI-ревью
                </label>
                <button class="btn btn-secondary btn-sm" onclick="runQaChecks()" id="btnRunQa">🧪 Прогнать проверки</button>
                <button class="btn btn-secondary btn-sm" onclick="runQaFix()" id="btnFixQa" title="Исправить repetition / banned_phrase / empty_chart автоматически">🛠 Исправить автоматически</button>
            </span>
        </div>
        <div class="section-body">
            <div id="qaIssuesList" style="display:flex;flex-direction:column;gap:6px"></div>
            <div id="qaForceWrap" style="display:none;margin-top:10px;padding:10px;border:1px solid var(--danger);background:var(--danger-light);border-radius:8px">
                <label style="display:flex;gap:8px;align-items:center;font-size:13px">
                    <input type="checkbox" id="qaForcePublish">
                    <span>Опубликовать несмотря на блокирующие ошибки (force-override)</span>
                </label>
            </div>
        </div>
    </div>

    <!-- Article settings (advanced only) -->
    <div class="section adv-only">
        <div class="section-head"><span class="section-head-title">Детали статьи</span></div>
        <div class="section-body">
            <div class="meta-pair"><span class="k">Опубликованный URL</span><span class="v" id="metaPubUrl">—</span></div>
            <div class="meta-pair"><span class="k">Версия</span><span class="v" id="metaVersion">—</span></div>
            <div class="meta-pair"><span class="k">Токенов потрачено</span><span class="v" id="metaTokens">—</span></div>
            <div class="meta-pair"><span class="k">GPT модель</span><span class="v" id="metaModel">—</span></div>
        </div>
    </div>
</div>

<script src="assets/articles/core.js"></script>
<script src="assets/articles/progress.js"></script>
<script src="assets/articles/list.js"></script>
<script src="assets/articles/qa.js"></script>
<script src="assets/articles/editor.js"></script>
<script src="assets/articles/wizard.js"></script>
<script src="assets/articles/publish.js"></script>
<script src="assets/articles/prep.js"></script>
<script src="assets/articles/generate.js"></script>
<script src="assets/articles/blocks_view.js"></script>
<script src="assets/articles/blocks_schema.js"></script>
<script src="assets/articles/blocks_actions.js"></script>
<script src="assets/articles/init.js"></script>
</body>
</html>
