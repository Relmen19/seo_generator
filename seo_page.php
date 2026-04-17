<?php
require_once __DIR__ . '/auth.php';
requireAuth();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO Статьи</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
        }

        .topbar {
            background: #1e293b;
            border-bottom: 1px solid #334155;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .topbar h1 { font-size: 1.1rem; color: #f1f5f9; }
        .topbar nav { display: flex; gap: 8px; align-items: center; }
        .topbar nav a {
            color: #94a3b8;
            text-decoration: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: .85rem;
            transition: .2s;
        }
        .topbar nav a:hover { background: #334155; color: #e2e8f0; }
        .topbar nav a.active { background: #6366f1; color: #fff; }
        .btn-logout { color: #f87171 !important; }
        /* profile-selector removed — profile is set via workspace */

        .page { display: flex; height: calc(100vh - 53px); position: relative; }
        .list-panel {
            width: 400px;
            border-right: 1px solid #334155;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            background: #1e293b;
            transition: width .25s, min-width .25s;
            overflow: hidden;
            min-width: 400px;
        }
        .list-panel.collapsed { width: 0; min-width: 0; border-right: none; }
        .editor-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .panel-toggle {
            position: absolute;
            left: 400px;
            top: 0;
            z-index: 50;
            width: 24px;
            height: 48px;
            background: #1e293b;
            border: 1px solid #334155;
            border-left: none;
            border-radius: 0 8px 8px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #64748b;
            font-size: .75rem;
            transition: left .25s;
        }
        .panel-toggle:hover { color: #e2e8f0; background: #334155; }
        .panel-toggle.shifted { left: 0; }

        .list-tabs {
            display: flex;
            border-bottom: 1px solid #334155;
            flex-shrink: 0;
            flex-wrap: wrap;
        }
        .list-tab {
            flex: 1;
            padding: 9px 6px;
            text-align: center;
            font-size: .75rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: .2s;
            user-select: none;
            white-space: nowrap;
        }
        .list-tab:hover { color: #94a3b8; background: rgba(15,23,42,.2); }
        .list-tab.active { color: #a5b4fc; border-bottom-color: #6366f1; }
        .list-tab-content { display: none; flex-direction: column; flex: 1; overflow: hidden; }
        .list-tab-content.active { display: flex; }

        .list-toolbar {
            padding: 12px 14px;
            border-bottom: 1px solid #334155;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .search-row { display: flex; gap: 8px; }
        .search-row input {
            flex: 1;
            padding: 7px 10px;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 6px;
            color: #e2e8f0;
            font-size: .85rem;
            outline: none;
        }
        .search-row input:focus { border-color: #6366f1; }
        .filter-row { display: flex; gap: 6px; flex-wrap: wrap; }
        .filter-row select {
            padding: 5px 8px;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 6px;
            color: #e2e8f0;
            font-size: .78rem;
            outline: none;
        }
        .filter-row select:focus { border-color: #6366f1; }

        .btn {
            padding: 7px 14px;
            background: #6366f1;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: .82rem;
            transition: .2s;
            white-space: nowrap;
        }
        .btn:hover { background: #4f46e5; }
        .btn-sm { padding: 5px 10px; font-size: .75rem; }
        .btn-xs { padding: 3px 8px; font-size: .7rem; }
        .btn-green { background: #059669; }
        .btn-green:hover { background: #047857; }
        .btn-red { background: #dc2626; }
        .btn-red:hover { background: #b91c1c; }
        .btn-ghost { background: transparent; color: #94a3b8; border: 1px solid #334155; }
        .btn-ghost:hover { border-color: #6366f1; color: #e2e8f0; }
        .btn-orange { background: #d97706; }
        .btn-orange:hover { background: #b45309; }
        .btn-cyan { background: #0891b2; }
        .btn-cyan:hover { background: #0e7490; }
        .btn-purple { background: #7c3aed; }
        .btn-purple:hover { background: #6d28d9; }

        .item-list { flex: 1; overflow-y: auto; }
        .list-item {
            padding: 10px 14px;
            border-bottom: 1px solid #0f172a;
            cursor: pointer;
            transition: .15s;
            display: flex;
            align-items: start;
            gap: 10px;
        }
        .list-item:hover { background: #334155; }
        .list-item.selected { background: #312e81; border-left: 3px solid #6366f1; }
        .list-item-body { flex: 1; min-width: 0; }
        .list-item-name {
            font-weight: 600; font-size: .85rem; color: #f1f5f9;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .list-item-sub {
            font-size: .75rem; color: #64748b; margin-top: 2px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .list-item-meta { display: flex; gap: 5px; margin-top: 5px; flex-wrap: wrap; }
        .tag {
            font-size: .72rem; padding: 2px 7px; border-radius: 4px;
            background: #1e293b; border: 1px solid #334155; color: #94a3b8;
        }
        .tag.type { border-color: #6366f1; color: #a5b4fc; }
        .tag.cat { border-color: #0891b2; color: #67e8f9; }
        .tag.status-draft { border-color: #3b82f6; color: #93c5fd; }
        .tag.status-review { border-color: #f59e0b; color: #fcd34d; }
        .tag.status-published { border-color: #10b981; color: #6ee7b7; }
        .tag.status-unpublished { border-color: #ef4444; color: #fca5a5; }
        .list-count {
            padding: 7px 14px; font-size: .75rem; color: #64748b;
            border-top: 1px solid #334155; text-align: center; flex-shrink: 0;
        }

        .editor-toolbar {
            padding: 10px 18px;
            border-bottom: 1px solid #334155;
            display: flex; align-items: center; justify-content: space-between;
            gap: 8px; background: #1e293b; flex-wrap: wrap;
        }
        .editor-toolbar-left { display: flex; gap: 8px; align-items: center; }
        .editor-toolbar-right { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .editor-id { font-size: .78rem; color: #64748b; }

        .editor-body { flex: 1; overflow-y: auto; padding: 18px; }
        .form-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;
        }
        .form-group { display: flex; flex-direction: column; gap: 4px; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label {
            font-size: .75rem; text-transform: uppercase; letter-spacing: .5px; color: #64748b;
        }
        .form-group input, .form-group select, .form-group textarea {
            padding: 10px 14px; background: #0f172a; border: 1px solid #334155;
            border-radius: 6px; color: #e2e8f0; font-size: .9rem; outline: none;
            font-family: inherit; line-height: 1.4;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #6366f1; }
        .form-group input:disabled, .form-group textarea:disabled { opacity: .5; cursor: not-allowed; }
        .form-group select:disabled { opacity: .5; cursor: not-allowed; }
        .label-hint { font-size: .72rem; color: #475569; text-transform: none; letter-spacing: 0; margin-left: 4px; font-weight: 400; }

        .version-badge {
            display: inline-flex; align-items: center;
            background: #1e293b; border: 1px solid #4338ca; color: #a5b4fc;
            padding: 6px 14px; border-radius: 20px; font-size: .85rem; font-weight: 600;
            letter-spacing: .5px;
        }
        .url-field-wrap {
            display: flex; align-items: center; gap: 6px;
            background: #0f172a; border: 1px solid #334155;
            border-radius: 6px; padding: 8px 10px; min-height: 42px;
        }
        .url-link {
            flex: 1; color: #22d3ee; font-size: .85rem; text-decoration: none;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .url-link:hover { text-decoration: underline; }
        .url-link.empty { color: #475569; pointer-events: none; }

        .json-editor {
            font-family: 'SF Mono', 'Fira Code', 'Consolas', monospace;
            font-size: .82rem;
            line-height: 1.5;
            tab-size: 2;
            white-space: pre;
            min-height: 200px;
            resize: vertical;
        }
        .json-editor.error { border-color: #ef4444 !important; }

        /* 3-view block tabs */
        .block-view-tabs {
            display: flex; gap: 2px; margin-bottom: 10px;
            border-bottom: 1px solid #334155; padding-bottom: 0;
        }
        .bvt {
            padding: 5px 14px; font-size: .75rem; font-weight: 600;
            color: #64748b; background: transparent; border: none;
            border-bottom: 2px solid transparent; cursor: pointer;
            transition: .15s; margin-bottom: -1px;
        }
        .bvt:hover { color: #94a3b8; }
        .bvt.active { color: #a5b4fc; border-bottom-color: #6366f1; }

        .block-tab-content { padding-top: 2px; }

        .block-form-editor { display: flex; flex-direction: column; gap: 10px; }
        .block-form-editor .form-group { margin-bottom: 0; }

        .array-field-list { display: flex; flex-direction: column; gap: 8px; margin-top: 6px; }
        .block-form-array-item {
            background: #0a1628; border: 1px solid #334155; border-radius: 6px; padding: 10px;
        }
        .array-item-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 8px; font-size: .72rem; color: #64748b; font-weight: 600;
            text-transform: uppercase; letter-spacing: .4px;
        }
        .block-form-array-item .form-group { margin-bottom: 6px; }

        .json-toolbar {
            display: flex; gap: 6px; margin-bottom: 6px;
        }
        .block-cm-editor { border: 1px solid #334155; border-radius: 6px; overflow: hidden; }
        .block-cm-editor .CodeMirror { height: auto; min-height: 220px; font-size: .82rem; line-height: 1.5; }

        .block-preview-frame {
            width: 100%; min-height: 600px; max-height: 600px;
            border: 1px solid #334155; border-radius: 6px; background: #fff;
        }

        .no-schema-hint {
            padding: 14px; color: #64748b; font-size: .82rem; text-align: center;
            background: #0a1628; border-radius: 6px; border: 1px dashed #334155;
        }
        .no-schema-hint a { color: #6366f1; cursor: pointer; }
        .no-schema-hint a:hover { text-decoration: underline; }

        .empty-state {
            display: flex; flex-direction: column; align-items: center;
            justify-content: center; height: 100%; color: #475569; gap: 12px;
        }
        .empty-state .icon { font-size: 3rem; }
        .empty-state p { font-size: .9rem; }

        .toast {
            position: fixed; bottom: 24px; right: 24px; background: #059669; color: #fff;
            padding: 12px 20px; border-radius: 8px; font-size: .85rem;
            box-shadow: 0 10px 30px rgba(0,0,0,.3); transform: translateY(100px);
            opacity: 0; transition: .3s; z-index: 100;
        }
        .toast.error { background: #dc2626; }
        .toast.show { transform: translateY(0); opacity: 1; }

        .modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,.6);
            z-index: 200; align-items: center; justify-content: center;
        }
        .modal-overlay.show { display: flex; }
        .modal {
            background: #1e293b; border: 1px solid #334155; border-radius: 12px;
            padding: 24px; max-width: 440px; width: 90%;
        }
        .modal h3 { margin-bottom: 10px; color: #f1f5f9; }
        .modal p { color: #94a3b8; margin-bottom: 16px; font-size: .85rem; }
        .modal-btns { display: flex; gap: 10px; justify-content: flex-end; }

        kbd {
            background: #334155; border: 1px solid #475569; border-radius: 3px;
            padding: 1px 5px; font-size: .7rem; color: #94a3b8; font-family: inherit;
        }

        .section-block {
            background: #1e293b; border: 1px solid #334155;
            border-radius: 10px; padding: 18px; margin-bottom: 16px;
        }
        .section-block h3 {
            font-size: .85rem; color: #f1f5f9; margin-bottom: 14px;
            display: flex; align-items: center; gap: 8px;
        }
        .section-block h3 .section-icon { font-size: 1rem; }

        .status-badge {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: .72rem; padding: 3px 9px; border-radius: 20px;
            font-weight: 600; letter-spacing: .3px;
        }
        .status-draft { background: #1e3a5f; color: #7dd3fc; }
        .status-review { background: #422006; color: #fbbf24; }
        .status-published { background: #064e3b; color: #6ee7b7; }
        .status-unpublished { background: #7f1d1d; color: #fca5a5; }

        .status-dot {
            width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; margin-top: 6px;
        }
        .status-dot.draft { background: #7dd3fc; }
        .status-dot.review { background: #fbbf24; }
        .status-dot.published { background: #6ee7b7; }
        .status-dot.unpublished { background: #fca5a5; }

        .blocks-list { display: flex; flex-direction: column; gap: 8px; }
        .block-item {
            background: #0f172a; border: 1px solid #334155; border-radius: 8px;
            overflow: hidden;
        }
        .block-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 8px 12px; background: #1a2332; cursor: pointer; user-select: none;
        }
        .block-header:hover { background: #1e293b; }
        .block-header-left { display: flex; align-items: center; gap: 8px; }
        .block-header-left .drag-handle { cursor: grab; color: #475569; font-size: .8rem; }
        .block-header-left .block-type {
            font-size: .7rem; padding: 2px 6px; border-radius: 3px;
            background: #312e81; color: #a5b4fc; font-weight: 600;
        }
        .block-header-left .block-name { font-size: .8rem; color: #94a3b8; }
        .block-header-right { display: flex; align-items: center; gap: 6px; }
        .block-header .collapse-arrow { font-size: .65rem; color: #475569; transition: .2s; }
        .block-header.collapsed .collapse-arrow { transform: rotate(-90deg); }
        .block-body { padding: 12px; }
        .block-body.hidden { display: none; }

        .block-item.dragging { opacity: .4; }
        .block-item.drag-over { border-color: #6366f1; background: rgba(99,102,241,.08); }
        .drag-handle { cursor: grab !important; padding: 0 4px; color: #475569; font-size: .9rem; line-height: 1; user-select: none; }
        .drag-handle:active { cursor: grabbing !important; }

        .json-status { font-size: .7rem; margin-top: 2px; }
        .json-status.valid { color: #10b981; }
        .json-status.invalid { color: #ef4444; }


        .tree-item[data-depth="1"] { padding-left: 20px; }
        .tree-item[data-depth="2"] { padding-left: 40px; }
        .tree-item[data-depth="3"] { padding-left: 60px; }

        /* ── CATALOG TREE ── */
        .cat-tree { flex:1; overflow-y:auto; }
        .cat-node {}
        .cat-row {
            display:flex; align-items:center; gap:0;
            padding:0; border-bottom:1px solid #0f172a;
            transition:.15s;
        }
        .cat-row:hover { background:#1e293b; }
        .cat-row.selected { background:#1e3a5f; border-left:3px solid #0891b2; }
        .cat-toggle {
            flex-shrink:0; width:28px; height:36px;
            display:flex; align-items:center; justify-content:center;
            cursor:pointer; color:#475569; font-size:.65rem;
            transition:transform .15s; user-select:none;
        }
        .cat-toggle:hover { color:#94a3b8; }
        .cat-toggle.open { transform:rotate(0deg); }
        .cat-toggle.leaf { cursor:default; color:#1e293b; }
        .cat-icon { font-size:.85rem; margin-right:4px; flex-shrink:0; }
        .cat-label {
            flex:1; min-width:0; padding:8px 6px 8px 0;
            cursor:pointer; font-size:.83rem; color:#e2e8f0;
            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
        }
        .cat-meta { font-size:.7rem; color:#475569; padding-right:6px; flex-shrink:0; white-space:nowrap; }
        .cat-actions { display:flex; gap:2px; padding-right:8px; flex-shrink:0; opacity:0; transition:.15s; }
        .cat-row:hover .cat-actions { opacity:1; }
        .cat-children { display:none; }
        .cat-children.open { display:block; }

        /* article rows inside catalog */
        .cat-art-row {
            display:flex; align-items:center; gap:6px;
            padding:5px 10px 5px 0;
            border-bottom:1px solid #0a1628;
            cursor:pointer; transition:.15s;
            background:#080f1c;
        }
        .cat-art-row:hover { background:#111827; }
        .cat-art-row.selected { background:#1a2e4a; border-left:3px solid #6366f1; }
        .cat-art-icon { font-size:.7rem; flex-shrink:0; }
        .cat-art-name { flex:1; min-width:0; font-size:.78rem; color:#94a3b8; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .cat-art-name:hover { color:#e2e8f0; }
        .cat-art-status { flex-shrink:0; }
        .cat-art-actions { display:flex; gap:2px; flex-shrink:0; opacity:0; transition:.15s; }
        .cat-art-row:hover .cat-art-actions { opacity:1; }

        .cat-add-art {
            display:flex; align-items:center; gap:5px;
            padding:4px 8px 4px 0;
            font-size:.7rem; color:#334155;
            cursor:pointer; transition:.15s; border-bottom:1px solid #0a1628;
        }
        .cat-add-art:hover { color:#0891b2; background:#0a1f2e; }

        .gen-panel { background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); border: 1px solid #4338ca; border-radius: 8px; padding: 16px; }
        .gen-panel h3 { color: #c7d2fe; margin-bottom: 12px; font-size: .95rem; }
        .gen-controls { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; row-gap: 10px; }
        .gen-controls select { background: #1e1b4b; border: 1px solid #4338ca; color: #e0e7ff; padding: 6px 10px; border-radius: 6px; font-size: .8rem; }
        .gen-controls .btn-gen { background: linear-gradient(135deg, #7c3aed, #6366f1); color: #fff; padding: 8px 16px; font-size: .8rem; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; transition: all .2s; }
        .gen-controls .btn-gen:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(99,102,241,.4); }
        .gen-controls .btn-gen:disabled { opacity: .5; cursor: not-allowed; transform: none; box-shadow: none; }
        .gen-controls .btn-gen-meta { background: linear-gradient(135deg, #0891b2, #06b6d4); }
        .gen-controls .btn-gen-meta:hover { box-shadow: 0 4px 15px rgba(6,182,212,.4); }
        .gen-progress { margin-top: 12px; display: none; }
        .gen-progress.active { display: flex; flex-direction: column; }
        .gen-progress-bar { width: 100%; height: 6px; background: #1e1b4b; border-radius: 3px; overflow: hidden; flex-shrink: 0; }
        .gen-progress-fill { height: 100%; background: linear-gradient(90deg, #7c3aed, #06b6d4); border-radius: 3px; transition: width .3s ease; width: 0; }
        .gen-progress-text { font-size: .75rem; color: #a5b4fc; margin-top: 6px; white-space: normal; word-break: break-word; overflow: visible; line-height: 1.4; }

        .img-layout-row { display: flex; flex-direction: column; gap: 6px; margin-top: 0; }
        .img-layout-row label { font-size: .7rem; color: #64748b; text-transform: uppercase; letter-spacing: .4px; white-space: nowrap; }
        .img-layout-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 3px; }
        .img-layout-btn {
            width: 40px; height: 30px; border: 2px solid #334155; border-radius: 5px;
            background: #0f172a; cursor: pointer; position: relative; transition: all .15s;
            display: flex; align-items: center; justify-content: center; padding: 3px;
        }
        .img-layout-btn:hover { border-color: #6366f1; }
        .img-layout-btn.active { border-color: #22d3ee; background: #1e1b4b; box-shadow: 0 0 0 1px rgba(34,211,238,.3); }
        .img-layout-btn svg { width: 100%; height: 100%; }
        .img-layout-btn .l-block { fill: #475569; }
        .img-layout-btn.active .l-block { fill: #22d3ee; }
        .img-layout-btn .l-line { stroke: #334155; stroke-width: 1.5; }
        .img-layout-btn.active .l-line { stroke: #64748b; }
        .img-layout-btn .l-text { fill: #64748b; font-size: 6px; font-family: sans-serif; }
        .img-layout-btn.active .l-text { fill: #22d3ee; }
        .img-layout-sep { grid-column: 1 / -1; height: 1px; background: #1e293b; margin: 2px 0; }
        .gen-log { margin-top: 8px; max-height: 200px; overflow-y: auto; font-size: .72rem; font-family: 'JetBrains Mono', monospace; background: #0f0d2e; border: 1px solid #312e81; border-radius: 6px; padding: 8px; color: #c7d2fe; line-height: 1.5; }
        .gen-log .log-ok { color: #6ee7b7; }
        .gen-log .log-err { color: #fca5a5; }
        .gen-log .log-info { color: #93c5fd; }
        .gen-log .log-warn { color: #fbbf24; }
        .btn-regen { background: transparent; border: 1px solid #4338ca; color: #a5b4fc; padding: 2px 8px; font-size: .7rem; border-radius: 4px; cursor: pointer; transition: all .15s; white-space: nowrap; }
        .btn-regen:hover { background: #312e81; border-color: #6366f1; color: #e0e7ff; }
        .btn-regen:disabled { opacity: .4; cursor: not-allowed; }
        .gen-token-info { display: inline-flex; align-items: center; gap: 4px; font-size: .72rem; color: #818cf8; background: #1e1b4b; padding: 3px 8px; border-radius: 4px; }

        .ss-wrap { position: relative; }
        .ss-input { width: 100%; background: #0f172a; border: 1px solid #334155; color: #e2e8f0; padding: 7px 32px 7px 10px; border-radius: 6px; font-size: .82rem; outline: none; transition: border-color .15s; }
        .ss-input:focus { border-color: #6366f1; }
        .ss-input::placeholder { color: #475569; }
        .ss-clear { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #64748b; cursor: pointer; font-size: 1rem; line-height: 1; padding: 0 4px; display: none; }
        .ss-clear:hover { color: #e2e8f0; }
        .ss-wrap.has-value .ss-clear { display: block; }
        .ss-dropdown { position: absolute; top: 100%; left: 0; right: 0; background: #1e293b; border: 1px solid #334155; border-top: none; border-radius: 0 0 6px 6px; max-height: 220px; overflow-y: auto; z-index: 200; display: none; }
        .ss-dropdown.open { display: block; }
        .ss-option { padding: 7px 10px; cursor: pointer; font-size: .8rem; color: #cbd5e1; transition: background .1s; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ss-option:hover, .ss-option.highlighted { background: #334155; color: #e2e8f0; }
        .ss-option.selected { background: #312e81; color: #c7d2fe; }
        .ss-option .ss-depth { color: #475569; margin-right: 2px; }
        .ss-option .ss-match { color: #fbbf24; font-weight: 600; }
        .ss-empty { padding: 10px; text-align: center; color: #475569; font-size: .78rem; }

        .pub-panel { background: linear-gradient(135deg, #064e3b 0%, #065f46 100%); border: 1px solid #059669; border-radius: 8px; padding: 16px; }
        .pub-panel h3 { color: #a7f3d0; margin-bottom: 12px; font-size: .95rem; }
        .pub-controls { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .pub-controls select, .pub-controls .ss-wrap .ss-input { background: #064e3b; border: 1px solid #059669; color: #d1fae5; padding: 6px 10px; border-radius: 6px; font-size: .8rem; }
        .pub-controls .ss-wrap .ss-dropdown { background: #064e3b; border-color: #059669; }
        .pub-controls .ss-wrap .ss-option:hover, .pub-controls .ss-wrap .ss-option.highlighted { background: #065f46; }
        .btn-pub { background: linear-gradient(135deg, #059669, #10b981); color: #fff; padding: 8px 16px; font-size: .8rem; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; transition: all .2s; }
        .btn-pub:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(16,185,129,.4); }
        .btn-pub:disabled { opacity: .5; cursor: not-allowed; transform: none; }
        .btn-pub-preview { background: linear-gradient(135deg, #0891b2, #06b6d4); }
        .btn-pub-preview:hover { box-shadow: 0 4px 15px rgba(6,182,212,.4); }
        .btn-pub-unpub { background: linear-gradient(135deg, #dc2626, #ef4444); }
        .btn-pub-unpub:hover { box-shadow: 0 4px 15px rgba(239,68,68,.4); }
        .pub-result { margin-top: 10px; padding: 10px; background: #022c22; border: 1px solid #065f46; border-radius: 6px; font-size: .78rem; color: #6ee7b7; display: none; }
        .preview-frame { width: 100%; min-height: 800px; max-height: 1500px; border: 1px solid #334155; border-radius: 6px; background: #fff; margin-top: 10px; display: none; }

        /* Telegram Preview */
        .tg-preview-wrap { background: #0e1621; border-radius: 12px; overflow: hidden; max-width: 420px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        .tg-msg { background: #182533; margin: 0; padding: 0; }
        .tg-msg + .tg-msg { margin-top: 2px; }
        .tg-msg-header { display: flex; align-items: center; gap: 8px; padding: 8px 12px 4px; }
        .tg-msg-avatar { width: 32px; height: 32px; border-radius: 50%; background: #2b5278; flex-shrink: 0; overflow: hidden; display: flex; align-items: center; justify-content: center; font-size: .9rem; font-weight: 700; color: #fff; }
        .tg-msg-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .tg-msg-channel-name { font-size: .82rem; font-weight: 600; color: #6ab2f2; }
        .tg-msg-media { position: relative; }
        .tg-msg-media img { width: 100%; display: block; }
        .tg-msg-media-grid { display: grid; gap: 2px; }
        .tg-msg-media-grid.count-2 { grid-template-columns: 1fr 1fr; }
        .tg-msg-media-grid.count-3 { grid-template-columns: 1fr 1fr; }
        .tg-msg-media-grid.count-3 img:first-child { grid-row: 1/3; }
        .tg-msg-caption { padding: 6px 12px 4px; font-size: .82rem; color: #e1e3e6; line-height: 1.4; word-wrap: break-word; }
        .tg-msg-caption b { font-weight: 600; }
        .tg-msg-caption a { color: #6ab2f2; text-decoration: none; }
        .tg-msg-footer { display: flex; align-items: center; justify-content: flex-end; gap: 6px; padding: 2px 12px 6px; font-size: .68rem; color: #6d7f8e; }
        .tg-msg-footer-views::before { content: ''; display: inline-block; width: 12px; height: 10px; background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 11' fill='%236d7f8e'%3E%3Cpath d='M8 1C4.5 1 1.5 3.5 0 5.5c1.5 2 4.5 4.5 8 4.5s6.5-2.5 8-4.5C14.5 3.5 11.5 1 8 1zm0 7.5A3 3 0 118 2.5a3 3 0 010 6z'/%3E%3C/svg%3E") no-repeat center; margin-right: 3px; }
        .tg-msg-reactions { display: flex; gap: 4px; padding: 4px 12px 8px; flex-wrap: wrap; }
        .tg-msg-reaction { background: #1e3246; border-radius: 12px; padding: 3px 8px; font-size: .72rem; color: #6ab2f2; border: 1px solid #2b5278; }

        .img-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px,1fr)); gap: 10px; }
        .img-card { background: #1e293b; border: 2px solid #334155; border-radius: 8px; overflow: hidden; cursor: pointer; transition: all .15s; position: relative; }
        .img-card:hover { border-color: #6366f1; transform: translateY(-2px); }
        .img-card.selected { border-color: #22d3ee; box-shadow: 0 0 0 2px rgba(34,211,238,.3); }
        .img-card-thumb { width: 100%; aspect-ratio: 4/3; object-fit: cover; display: block; background: #0f172a; }
        .img-card-info { padding: 6px 8px; }
        .img-card-name { font-size: .72rem; color: #cbd5e1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .img-card-meta { font-size: .65rem; color: #64748b; margin-top: 2px; }
        .img-card-badge { position: absolute; top: 4px; right: 4px; font-size: .6rem; padding: 1px 5px; border-radius: 3px; background: rgba(0,0,0,.7); }
        .img-card-badge.uploaded { color: #6ee7b7; }
        .img-card-badge.generated { color: #c4b5fd; }
        .img-card-actions { position: absolute; top: 4px; left: 4px; display: flex; gap: 3px; opacity: 0; transition: opacity .15s; }
        .img-card:hover .img-card-actions { opacity: 1; }
        .img-card-actions button { background: rgba(0,0,0,.75); border: none; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: .7rem; cursor: pointer; }
        .img-card-actions button:hover { background: rgba(99,102,241,.8); }
        .img-upload-zone { border: 2px dashed #334155; border-radius: 8px; padding: 24px; text-align: center; cursor: pointer; transition: all .2s; color: #64748b; font-size: .85rem; min-height: 80px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; }
        .img-upload-zone:hover, .img-upload-zone.dragover { border-color: #6366f1; background: rgba(99,102,241,.05); color: #a5b4fc; }
        .img-upload-zone input[type=file] { display: none; }
        .img-preview-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.85); z-index: 10000; display: none; align-items: center; justify-content: center; flex-direction: column; }
        .img-preview-overlay.show { display: flex; }
        .img-preview-overlay img { max-width: 90vw; max-height: 80vh; border-radius: 8px; box-shadow: 0 8px 40px rgba(0,0,0,.5); }
        .img-preview-overlay .close-preview { position: absolute; top: 16px; right: 24px; font-size: 2rem; color: #fff; background: none; border: none; cursor: pointer; }
        .img-preview-meta { color: #94a3b8; font-size: .82rem; margin-top: 12px; text-align: center; }
        .img-edit-row { display: flex; gap: 8px; align-items: center; margin-top: 8px; }
        .img-edit-row input { flex: 1; }
        .img-modal-gallery { display: grid; grid-template-columns: repeat(auto-fill,minmax(120px,1fr)); gap: 8px; max-height: 50vh; overflow-y: auto; padding: 8px 0; }

        /* ── Art Settings compact row ── */
        .art-settings-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 20px;
            align-items: flex-start;
        }
        .art-settings-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
        }
        .art-settings-field > label {
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #64748b;
            white-space: nowrap;
        }
        .art-settings-field > select {
            padding: 7px 10px;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 6px;
            color: #e2e8f0;
            font-size: .82rem;
            outline: none;
            min-width: 155px;
        }
        .art-settings-field > select:focus { border-color: #6366f1; }
        .art-settings-field > input[type=text] {
            padding: 7px 10px;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 6px;
            color: #e2e8f0;
            font-size: .82rem;
            outline: none;
            width: 130px;
        }
        .art-settings-field > input[type=text]:focus { border-color: #6366f1; }
        .art-settings-field.field-url { flex: 1; min-width: 200px; max-width: 380px; }
        .art-settings-field .version-badge { align-self: flex-start; margin-top: 1px; }
        .art-settings-log { margin-top: 12px; border-top: 1px solid #233044; padding-top: 12px; }
        .art-settings-log > label {
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #64748b;
            cursor: pointer;
            user-select: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 0;
        }

        /* ── Article Plan editor ── */
        .plan-badge {
            font-size: .62rem;
            padding: 1px 7px;
            background: #1e1b4b;
            border: 1px solid #3730a3;
            border-radius: 20px;
            color: #818cf8;
            text-transform: none;
            letter-spacing: 0;
            font-weight: 400;
            margin-left: 6px;
            vertical-align: middle;
        }
        .form-group.plan-field textarea {
            font-family: 'SF Mono', 'Fira Code', 'Consolas', monospace;
            font-size: .8rem;
            line-height: 1.9;
            color: #c4b5fd;
            background: #09101e;
            border-color: #312e81;
            min-height: 90px;
        }
        .form-group.plan-field textarea:focus { border-color: #6366f1; }
        .form-group.plan-field textarea::placeholder { color: #2a3258; font-style: italic; }

        /* ── Image generation settings ── */
        .img-gen-settings { background: #1e1b4b; border: 1px solid #312e81; border-radius: 8px; padding: 16px; margin-bottom: 12px; }
        .img-gen-title { font-size: .75rem; color: #a5b4fc; font-weight: 600; margin-bottom: 12px; text-transform: uppercase; letter-spacing: .5px; }
        .img-gen-grid { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
        .img-gen-field { display: flex; flex-direction: column; gap: 4px; }
        .img-gen-field label { font-size: .72rem; color: #94a3b8; text-transform: uppercase; letter-spacing: .4px; }
        .img-gen-field select,
        .img-gen-field textarea { background: #0f172a; border: 1px solid #4338ca; color: #e0e7ff; padding: 7px 10px; border-radius: 6px; font-size: .82rem; outline: none; font-family: inherit; resize: vertical; }
        .img-gen-field select:focus,
        .img-gen-field textarea:focus { border-color: #6366f1; }
        .img-gen-manual { margin-top: 12px; }
        .img-gen-manual-row { display: flex; gap: 8px; margin-top: 6px; }
        .img-gen-manual-row textarea { flex: 1; }
        .img-gen-hint { font-size: .72rem; color: #64748b; margin-top: 4px; }

        /* ── Section headers with actions ── */
        .section-header { display: flex; align-items: center; gap: 8px; margin-bottom: 14px; }
        .section-header h3 { font-size: .88rem; color: #f1f5f9; margin: 0; display: flex; align-items: center; gap: 8px; }
        .section-header-actions { display: flex; gap: 6px; margin-left: auto; align-items: center; flex-shrink: 0; }
        .section-subtitle { font-size: .75rem; font-weight: 400; color: #64748b; }

        /* ── Topbar profile ── */
        .topbar-profile { display: flex; align-items: center; gap: 10px; }
        .topbar-profile-icon { width: 32px; height: 32px; border-radius: 8px; background: #334155; display: flex; align-items: center; justify-content: center; font-size: .9rem; font-weight: 700; color: #6366f1; flex-shrink: 0; overflow: hidden; }
        .topbar-profile-icon img { width: 100%; height: 100%; object-fit: cover; }
        .topbar-profile-name { font-size: .95rem; font-weight: 700; color: #f1f5f9; line-height: 1.2; }
        .topbar-profile-meta { font-size: .72rem; color: #64748b; }
        .topbar-left { display: flex; align-items: center; gap: 12px; }
        .topbar-back { color: #64748b; text-decoration: none; font-size: 1.1rem; padding: 4px 8px; border-radius: 6px; transition: .2s; }
        .topbar-back:hover { background: #334155; color: #e2e8f0; }

        @media (max-width: 800px) {
            .page { flex-direction: column; height: auto; }
            .list-panel { width: 100% !important; min-width: 0 !important; max-height: 40vh; }
            .list-panel.collapsed { max-height: 0; }
            .panel-toggle { display: none; }
            .form-grid { grid-template-columns: 1fr; }
            .gen-progress.active { flex-direction: column-reverse; }
            .gen-progress-text { margin-top: 0; margin-bottom: 6px; font-size: .72rem; }
            .img-layout-row { flex-wrap: wrap; }
            .img-layout-grid { grid-template-columns: repeat(4, 1fr); }
            .art-settings-row { gap: 8px 16px; }
            .art-settings-field.field-url { max-width: 100%; }
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/codemirror@5/lib/codemirror.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/codemirror@5/theme/dracula.min.css">
    <script src="https://cdn.jsdelivr.net/npm/codemirror@5/lib/codemirror.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/codemirror@5/mode/javascript/javascript.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/codemirror@5/addon/edit/matchbrackets.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/codemirror@5/addon/edit/closebrackets.min.js"></script>
</head>
<body>
<div class="topbar">
    <div class="topbar-left">
        <a href="/seo_profile_page.php" class="topbar-back" title="К профилям">&larr;</a>
        <div class="topbar-profile" id="topbarProfileInfo">
            <div class="topbar-profile-icon" id="topbarProfileIcon"></div>
            <div>
                <div class="topbar-profile-name" id="topbarProfileName">SEO Статьи</div>
                <div class="topbar-profile-meta" id="topbarProfileMeta"></div>
            </div>
        </div>
    </div>
    <nav>
        <a href="/seo_page.php" class="active">SEO</a>
        <a href="/seo_clustering_page.php" id="navSemLink">Семантика</a>
        <a href="/seo_profile_page.php">Профили</a>
        <a href="/logout.php" class="btn-logout">Выйти</a>
    </nav>
</div>
<div class="page">
    <div class="panel-toggle" id="panelToggle" onclick="togglePanel()" title="Скрыть/показать">&#9664;</div>
    <div class="list-panel" id="listPanel">
        <div class="list-tabs">
            <div class="list-tab active" data-tab="articles" onclick="switchTab('articles')">Статьи</div>
            <div class="list-tab" data-tab="catalogs" onclick="switchTab('catalogs')">Каталоги</div>
            <div class="list-tab" data-tab="templates" onclick="switchTab('templates')">Шаблоны</div>
            <div class="list-tab" data-tab="links" onclick="switchTab('links')">Ссылки</div>
            <div class="list-tab" data-tab="targets" onclick="switchTab('targets')">Хосты</div>
            <div class="list-tab" data-tab="audit" onclick="switchTab('audit')">Лог</div>
        </div>
        <div class="list-tab-content active" id="tabArticles">
            <div class="list-toolbar">
                <div class="search-row">
                    <input type="text" id="searchArticle" placeholder="Поиск статей...">
                    <button class="btn btn-green btn-sm" onclick="newArticle()">+ Новая</button>
                </div>
                <div class="filter-row">
                    <div class="ss-wrap" id="ss_filterCatalog">
                        <input type="text" class="ss-input" placeholder="Все каталоги" autocomplete="off">
                        <input type="hidden" id="filterCatalog" value="">
                        <button class="ss-clear" type="button">&times;</button>
                        <div class="ss-dropdown"></div>
                    </div>
                    <select id="filterStatus">
                        <option value="">Все статусы</option>
                        <option value="draft">Черновик</option>
                        <option value="review">На ревью</option>
                        <option value="published">Опубликована</option>
                        <option value="unpublished">Снята</option>
                    </select>
                </div>
            </div>
            <div class="item-list" id="articleList"></div>
            <div class="list-count" id="articleCount">&mdash;</div>
        </div>
        <div class="list-tab-content" id="tabCatalogs">
            <div class="list-toolbar">
                <div class="search-row">
                    <input type="text" id="searchCatalog" placeholder="Поиск каталогов и статей...">
                    <button class="btn btn-cyan btn-sm" onclick="newCatalog()">+ Каталог</button>
                </div>
                <div class="filter-row">
                    <select id="filterCatStatus" onchange="renderCatalogTree()">
                        <option value="">Все статусы</option>
                        <option value="draft">Черновик</option>
                        <option value="review">На ревью</option>
                        <option value="published">Опубликована</option>
                        <option value="unpublished">Снята</option>
                    </select>
                    <label style="display:inline-flex;align-items:center;gap:4px;font-size:.75rem;color:#64748b;cursor:pointer;white-space:nowrap">
                        <input type="checkbox" id="catShowArticles" checked onchange="renderCatalogTree()" style="accent-color:#0891b2">
                        Статьи
                    </label>
                </div>
            </div>
            <div class="cat-tree" id="catalogTree"></div>
            <div class="list-count" id="catalogCount">&mdash;</div>
        </div>
        <div class="list-tab-content" id="tabTemplates">
            <div class="list-toolbar">
                <div class="search-row">
                    <input type="text" id="searchTemplate" placeholder="Поиск шаблонов...">
                    <a href="/seo_profile_page.php" class="btn btn-ghost btn-sm" title="Создание шаблонов — в разделе Профиля" style="white-space:nowrap;font-size:.72rem">&#8599; Профиль</a>
                </div>
            </div>
            <div class="item-list" id="templateList"></div>
            <div class="list-count" id="templateCount">&mdash;</div>
        </div>
        <div class="list-tab-content" id="tabLinks">
            <div class="list-toolbar">
                <div class="search-row">
                    <input type="text" id="searchLink" placeholder="Поиск ссылок...">
                    <button class="btn btn-orange btn-sm" onclick="newLink()">+ Новая</button>
                </div>
                <div class="filter-row">
                    <select id="filterLinkScope">
                        <option value="">Все</option>
                        <option value="global">Глобальные</option>
                        <option value="local">Локальные</option>
                    </select>
                </div>
            </div>
            <div class="item-list" id="linkList"></div>
            <div class="list-count" id="linkCount">&mdash;</div>
        </div>
        <div class="list-tab-content" id="tabTargets">
            <div class="list-toolbar">
                <div class="search-row">
                    <input type="text" id="searchTarget" placeholder="Поиск хостов...">
                    <button class="btn btn-green btn-sm" onclick="newTarget()">+ Новый</button>
                </div>
            </div>
            <div class="item-list" id="targetList"></div>
            <div class="list-count" id="targetCount">&mdash;</div>
        </div>
        <div class="list-tab-content" id="tabAudit">
            <div class="list-toolbar">
                <div class="filter-row">
                    <select id="filterAuditEntity">
                        <option value="">Все сущности</option>
                        <option value="article">Статьи</option>
                        <option value="template">Шаблоны</option>
                        <option value="catalog">Каталоги</option>
                        <option value="link">Ссылки</option>
                        <option value="image">Изображения</option>
                    </select>
                    <select id="filterAuditAction">
                        <option value="">Все действия</option>
                        <option value="create">create</option>
                        <option value="update">update</option>
                        <option value="delete">delete</option>
                        <option value="publish">publish</option>
                        <option value="generate">generate</option>
                    </select>
                </div>
            </div>
            <div class="item-list" id="auditList"></div>
            <div class="list-count" id="auditCount">&mdash;</div>
        </div>
    </div>
    <div class="editor-panel">
        <div class="editor-toolbar">
            <div class="editor-toolbar-left" style="margin-left: 30px">
                <span class="editor-id" id="editorId"></span>
                <span id="editorStatus"></span>
            </div>
            <div class="editor-toolbar-right">
                <span style="font-size:.72rem;color:#475569"><kbd>Ctrl+S</kbd> сохранить</span>
                <button class="btn btn-ghost btn-sm" id="btnDuplicate" style="display:none" onclick="duplicateCurrent()">Дубль</button>
                <button class="btn btn-red btn-sm" id="btnDelete" style="display:none" onclick="confirmDelete()">Удалить</button>
                <button class="btn" id="btnSave" onclick="saveCurrentEditor()">Сохранить</button>
            </div>
        </div>
        <div class="editor-body" id="editorBody">
            <div class="empty-state" id="emptyState">
                <div class="icon" style="font-size:2rem;opacity:.3">&#9998;</div>
                <p>Выберите элемент или создайте новый</p>
            </div>
            <div id="articleEditor" style="display:none">
                <div class="section-block">
                    <h3>Основные данные</h3>
                    <div class="form-grid" style="grid-template-columns:1fr auto;gap:10px">
                        <div class="form-group">
                            <label>Заголовок (H1 / title)</label>
                            <input type="text" id="artTitle" placeholder="Название статьи">
                        </div>
                        <div class="form-group">
                            <label>Статус</label>
                            <select id="artStatus" style="min-width:140px">
                                <option value="draft">Черновик</option>
                                <option value="review">На ревью</option>
                                <option value="published">Опубликована</option>
                                <option value="unpublished">Снята</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Slug (URL)</label>
                            <div style="display:flex;gap:6px">
                                <input type="text" id="artSlug" placeholder="slug-stati" style="flex:1">
                                <button class="btn btn-sm btn-ghost" onclick="generateSlugFromTitle()" title="Создать slug из заголовка" style="white-space:nowrap">Создать</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Ключевые слова</label>
                            <input type="text" id="artKeywords" placeholder="ключевое слово 1, ключевое слово 2, ...">
                        </div>
                    </div>
                    <div class="form-grid" style="gap:10px;margin-top:0">
                        <div class="form-group">
                            <label>Каталог</label>
                            <div class="ss-wrap" id="ss_artCatalog">
                                <input type="text" class="ss-input" placeholder="Выберите каталог..." autocomplete="off">
                                <input type="hidden" id="artCatalog" value="">
                                <button class="ss-clear" type="button">&times;</button>
                                <div class="ss-dropdown"></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Шаблон</label>
                            <div class="ss-wrap" id="ss_artTemplate">
                                <input type="text" class="ss-input" placeholder="Выберите шаблон..." autocomplete="off">
                                <input type="hidden" id="artTemplate" value="">
                                <button class="ss-clear" type="button">&times;</button>
                                <div class="ss-dropdown"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="section-block">
                    <div class="section-header">
                        <h3>SEO Meta</h3>
                        <div class="section-header-actions">
                            <button class="btn btn-sm btn-ghost" style="color:#22d3ee;border-color:#0891b2" onclick="generateMeta()" id="btnGenMeta" title="Сгенерировать мета-теги через GPT">Meta</button>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group full">
                            <label>Meta Title</label>
                            <input type="text" id="artMetaTitle" placeholder="SEO-заголовок (60-70 символов)">
                        </div>
                        <div class="form-group full">
                            <label>Meta Description</label>
                            <textarea id="artMetaDesc" rows="2" placeholder="Краткое описание страницы (150-160 символов)"></textarea>
                        </div>
                        <div class="form-group full">
                            <label>Meta Keywords</label>
                            <input type="text" id="artMetaKeywords" placeholder="ключевые слова через запятую">
                        </div>
                        <div class="form-group full plan-field">
                            <label>Article Plan <span class="plan-badge">редакторский план</span></label>
                            <textarea id="artArticlePlan" rows="5" placeholder="[hero] Введение&#10;[richtext] Основной контент&#10;[faq] FAQ и вопросы&#10;[image] Изображение&#10;[cta] Призыв к действию"></textarea>
                        </div>
                    </div>
                </div>
                <div class="section-block">
                    <div class="gen-panel" id="genPanel">
                        <h3>Генерация контента (GPT)</h3>
                        <div class="gen-controls">
                            <select id="genModel">
                                <option value="gpt-4o">GPT-4o</option>
                                <option value="gpt-4o-mini">GPT-4o Mini</option>
                                <option value="gpt-4-turbo">GPT-4 Turbo</option>
                                <option value="gpt-4.1">GPT-4.1</option>
                                <option value="gpt-4.1-mini">GPT-4.1 Mini</option>
                                <option value="o3-mini">o3-mini</option>
                            </select>
                            <select id="genTemp">
                                <option value="0.5">Точнее (0.5)</option>
                                <option value="0.7" selected>Баланс (0.7)</option>
                                <option value="0.9">Креативнее (0.9)</option>
                                <option value="1.0">Макс. креатив (1.0)</option>
                            </select>
                            <label style="display:inline-flex;align-items:center;gap:4px;font-size:.78rem;color:#c7d2fe;cursor:pointer">
                                <input type="checkbox" id="genOverwrite" checked style="accent-color:#7c3aed"> Перезаписать
                            </label>
                            <button class="btn-gen" id="btnGenAll" onclick="generateAllBlocks()">Генерировать все блоки</button>
                            <button class="btn-gen btn-gen-meta" id="btnGenMetaFull" onclick="generateMeta()">Генерировать Meta</button>
                            <button class="btn-gen" id="btnGenPipeline" onclick="generateFullPipeline()" style="background:linear-gradient(135deg,#059669,#10b981)">Meta → Блоки (пайплайн)</button>
                        </div>
                        <div class="gen-progress" id="genProgress">
                            <div class="gen-progress-bar"><div class="gen-progress-fill" id="genProgressFill"></div></div>
                            <div class="gen-progress-text" id="genProgressText">Подготовка...</div>
                        </div>
                        <div class="gen-log" id="genLog" style="display:none"></div>
                    </div>
                </div>

                <div class="section-block">
                    <div class="section-header">
                        <h3>Блоки контента</h3>
                        <div class="section-header-actions">
                            <button class="btn btn-sm btn-red" onclick="deleteAllBlocks()" title="Удалить все блоки">Удалить все</button>
                            <button class="btn btn-sm btn-ghost" onclick="addArticleBlock()">+ Блок</button>
                        </div>
                    </div>
                    <div class="blocks-list" id="artBlocksList"></div>
                    <div id="artBlocksEmpty" style="font-size:.82rem;color:#475569;padding:10px 0;text-align:center;">
                        Нет блоков. Добавьте первый блок или используйте генерацию.
                    </div>
                </div>

                <div class="section-block">
                    <div class="section-header">
                        <h3><span class="section-icon">&#128247;</span> Изображения <span id="artImgCount" style="font-size:.75rem;color:#64748b;font-weight:400"></span></h3>
                        <div class="section-header-actions">
                            <button class="btn btn-sm btn-ghost" onclick="showUploadZone()">+ Загрузить</button>
                            <button class="btn btn-sm" style="background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff;border:none;font-size:.72rem;padding:3px 10px" onclick="generateAllImages()" id="btnGenAllImg" title="Сгенерировать изображения для всех image-блоков">&#127912; AI-Генерация</button>
                        </div>
                    </div>
                    <div class="img-gen-settings">
                        <div class="img-gen-title">&#127912; Настройки генерации изображений</div>
                        <div class="img-gen-grid">
                            <div class="img-gen-field">
                                <label>Модель</label>
                                <select id="imgGenModel">
                                    <option value="dall-e-3">DALL-E 3 (лучшее качество)</option>
                                    <option value="dall-e-2">DALL-E 2 (быстрее)</option>
                                    <option value="gpt-image-1">GPT-Image-1 (новинка)</option>
                                    <option value="gemini-2.5-flash-image">Google Imagen 3 (Nano Banano)</option>
                                </select>
                            </div>
                            <div class="img-gen-field">
                                <label>Размер</label>
                                <select id="imgGenSize">
                                    <option value="1024x1024">1024&times;1024</option>
                                    <option value="1792x1024">1792&times;1024 (широкий)</option>
                                    <option value="1024x1792">1024&times;1792 (высокий)</option>
                                </select>
                            </div>
                        </div>
                        <div class="img-gen-manual">
                            <div class="img-gen-field">
                                <label>Ручной промпт (для создания изображения без привязки к блоку)</label>
                            </div>
                            <div class="img-gen-manual-row">
                                <textarea id="imgManualPrompt" rows="2" placeholder="Опишите изображение подробно... Оставьте пустым — промпт возьмётся из блока автоматически" class="img-gen-field"></textarea>
                                <button class="btn btn-sm" style="background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff;border:none;align-self:flex-end;white-space:nowrap;padding:8px 14px" onclick="generateManualImage()" id="btnGenManual" title="Сгенерировать по введённому промпту">&#127912; Создать</button>
                            </div>
                            <div class="img-gen-hint">Изображение будет добавлено в галерею статьи. Затем вы можете привязать его к любому блоку.</div>
                        </div>
                    </div>
                    <div id="imgGenStatus" style="display:none;padding:8px 12px;margin-bottom:8px;background:#1e1b4b;border:1px solid #4c1d95;border-radius:6px;font-size:.78rem;color:#c4b5fd"></div>
                    <div id="artImgUploadZone" class="img-upload-zone" style="display:none">
                        <span style="font-size:1.5rem">&#128228;</span>
                        <span>Перетащите файлы сюда или <strong>нажмите</strong></span>
                        <span style="font-size:.72rem;color:#475569">JPEG, PNG, WebP, GIF, SVG · до 10 МБ</span>
                        <input type="file" id="artImgFileInput" accept="image/*" multiple>
                    </div>
                    <div class="img-gallery" id="artImgGallery"></div>
                    <div id="artImgEmpty" style="font-size:.82rem;color:#475569;padding:10px 0;text-align:center;">
                        Нет изображений.
                    </div>
                </div>

                <div class="section-block">
                    <div class="section-header">
                        <h3>Настройки статьи</h3>
                        <span class="section-subtitle">AI-модель и системные данные</span>
                    </div>
                    <div class="art-settings-row">
                        <div class="art-settings-field">
                            <label>AI Модель <span class="label-hint">при генерации</span></label>
                            <select id="artGptModel">
                                <option value="gpt-4o">GPT-4o</option>
                                <option value="gpt-4o-mini">GPT-4o Mini</option>
                                <option value="gpt-4-turbo">GPT-4 Turbo</option>
                                <option value="gpt-4.1">GPT-4.1</option>
                                <option value="gpt-4.1-mini">GPT-4.1 Mini</option>
                                <option value="o3-mini">o3-mini</option>
                            </select>
                        </div>
                        <div class="art-settings-field">
                            <label>Создатель</label>
                            <input type="text" id="artCreatedBy" placeholder="manager">
                        </div>
                        <div class="art-settings-field">
                            <label>Версия</label>
                            <span class="version-badge" id="artVersionBadge">—</span>
                        </div>
                        <div class="art-settings-field field-url">
                            <label>Опубликованный URL</label>
                            <div class="url-field-wrap">
                                <a id="artPublishedUrl" href="#" target="_blank" class="url-link empty">не опубликована</a>
                                <button class="btn btn-xs btn-ghost" onclick="copyPublishedUrl()" title="Скопировать URL">⎘</button>
                            </div>
                        </div>
                    </div>
                    <div class="art-settings-log">
                        <label onclick="toggleGenLog()">
                            Лог генерации
                            <span id="genLogToggleIcon" style="color:#64748b;font-size:.7rem">▶ показать</span>
                        </label>
                        <textarea id="artGenLog" class="json-editor" rows="6" disabled style="display:none;margin-top:6px"></textarea>
                    </div>
                </div>
                <div class="section-block" style="padding:10px 14px">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.82rem;color:#cbd5e1;margin:0">
                        <input type="checkbox" id="artTgExport">
                        Экспорт в Telegram
                    </label>
                </div>
                <div class="section-block">
                    <div class="pub-panel" id="pubPanel">
                        <h3>Публикация</h3>
                        <div class="pub-controls">
                            <div class="ss-wrap" id="ss_pubTarget" style="min-width:200px">
                                <input type="text" class="ss-input" placeholder="Выберите хост...">
                                <input type="hidden" id="pubTarget" value="">
                                <button class="ss-clear" type="button">&times;</button>
                                <div class="ss-dropdown"></div>
                            </div>
                            <button class="btn-pub" id="btnPublish" onclick="publishArticle()">Опубликовать</button>
                            <button class="btn-pub btn-pub-preview" onclick="previewArticle()">Предпросмотр</button>
                            <button class="btn-pub btn-pub-unpub" onclick="unpublishArticle()" id="btnUnpublish" style="display:none">Снять</button>
                        </div>
                        <div class="pub-result" id="pubResult"></div>
                        <iframe class="preview-frame" id="previewFrame" sandbox="allow-same-origin allow-scripts"></iframe>
                    </div>
                </div>
                <!-- Telegram Post Panel -->
                <div class="section-block" id="tgPostPanel" style="display:none">
                    <h3>Telegram пост</h3>
                    <div id="tgPostControls">
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
                            <button class="btn-pub" onclick="buildTgPreview()" id="btnTgBuild">Подготовить пост</button>
                            <button class="btn-pub" onclick="sendTgNow()" id="btnTgSend" style="display:none">Отправить сейчас</button>
                            <button class="btn-pub btn-pub-preview" onclick="showTgSchedule()" id="btnTgScheduleShow" style="display:none">Запланировать</button>
                        </div>
                        <div id="tgScheduleRow" style="display:none;margin-bottom:12px;gap:8px;align-items:center">
                            <input type="datetime-local" id="tgScheduleAt" style="background:#1e293b;border:1px solid #334155;color:#e2e8f0;padding:6px 10px;border-radius:6px;font-size:.82rem">
                            <button class="btn-pub" onclick="scheduleTgPost()">Запланировать отгрузку</button>
                        </div>
                        <div id="tgBuildStatus" style="display:none;font-size:.82rem;color:#a78bfa;margin-bottom:12px">
                            <span class="spinner"></span> Подготовка поста...
                        </div>
                    </div>

                    <!-- Telegram Preview -->
                    <div id="tgPreviewContainer" style="display:none">
                        <h4 style="font-size:.82rem;color:#64748b;margin:0 0 8px">Предпросмотр</h4>
                        <div id="tgPreview" class="tg-preview-wrap"></div>
                    </div>

                    <!-- Image presets -->
                    <div id="tgImagePresets" style="display:none;margin-top:12px">
                        <h4 style="font-size:.82rem;color:#64748b;margin:0 0 8px">Изображения</h4>
                        <div id="tgImageGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px"></div>
                    </div>

                    <!-- Post result/status -->
                    <div id="tgPostResult" style="display:none;margin-top:12px"></div>

                    <!-- Post history -->
                    <div id="tgPostHistory" style="margin-top:16px">
                        <h4 style="font-size:.82rem;color:#64748b;margin:0 0 8px">История постов</h4>
                        <div id="tgHistoryList"></div>
                    </div>
                </div>
            </div>
            <div id="catalogEditor" style="display:none">
                <div class="section-block">
                    <h3>Каталог</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Название</label>
                            <input type="text" id="catName" placeholder="Название категории">
                        </div>
                        <div class="form-group">
                            <label>Slug</label>
                            <input type="text" id="catSlug" placeholder="slug-kategorii">
                        </div>
                        <div class="form-group">
                            <label>Родительский каталог</label>
                            <div class="ss-wrap" id="ss_catParent">
                                <input type="text" class="ss-input" placeholder="— корневой —" autocomplete="off">
                                <input type="hidden" id="catParent" value="">
                                <button class="ss-clear" type="button">&times;</button>
                                <div class="ss-dropdown"></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Порядок сортировки</label>
                            <input type="number" id="catSortOrder" value="0">
                        </div>
                        <div class="form-group full">
                            <label>Описание</label>
                            <textarea id="catDescription" rows="3" placeholder="Описание каталога..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Активен</label>
                            <select id="catIsActive">
                                <option value="1">Да</option>
                                <option value="0">Нет</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div id="templateEditor" style="display:none">
                <div class="section-block">
                    <h3>Шаблон</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Название</label>
                            <input type="text" id="tplName" placeholder="full-landing">
                        </div>
                        <div class="form-group">
                            <label>Slug</label>
                            <input type="text" id="tplSlug" placeholder="full-landing">
                        </div>
                        <div class="form-group">
                            <label>CSS класс</label>
                            <input type="text" id="tplCssClass" placeholder="page-full-landing">
                        </div>
                        <div class="form-group">
                            <label>Активен</label>
                            <select id="tplIsActive">
                                <option value="1">Да</option>
                                <option value="0">Нет</option>
                            </select>
                        </div>
                        <div class="form-group full">
                            <label>Описание</label>
                            <textarea id="tplDescription" rows="2" placeholder="Полноценный лендинг..."></textarea>
                        </div>
                        <div class="form-group full">
                            <label>GPT System Prompt</label>
                            <textarea id="tplGptPrompt" rows="6" placeholder="Ты — SEO-копирайтер..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="section-block">
                    <div class="section-header">
                        <h3>Блоки шаблона</h3>
                        <div class="section-header-actions">
                            <button class="btn btn-sm btn-ghost" onclick="addTemplateBlock()">+ Блок</button>
                        </div>
                    </div>
                    <div class="blocks-list" id="tplBlocksList"></div>
                    <div id="tplBlocksEmpty" style="font-size:.82rem;color:#475569;padding:10px 0;text-align:center;">
                        Нет блоков в шаблоне
                    </div>
                </div>
            </div>
            <div id="linkEditor" style="display:none">
                <div class="section-block">
                    <h3>Константа ссылки</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Ключ ({{link:KEY}})</label>
                            <input type="text" id="lnkKey" placeholder="MAIN_SITE">
                        </div>
                        <div class="form-group">
                            <label>Привязка к статье</label>
                            <div class="ss-wrap" id="ss_lnkArticle">
                                <input type="text" class="ss-input" placeholder="Глобальная (все статьи)" autocomplete="off">
                                <input type="hidden" id="lnkArticle" value="">
                                <button class="ss-clear" type="button">&times;</button>
                                <div class="ss-dropdown"></div>
                            </div>
                        </div>
                        <div class="form-group full">
                            <label>URL</label>
                            <input type="text" id="lnkUrl" placeholder="https://example.com">
                        </div>
                        <div class="form-group">
                            <label>Label (текст ссылки)</label>
                            <input type="text" id="lnkLabel" placeholder="Перейти на сайт">
                        </div>
                        <div class="form-group">
                            <label>Target</label>
                            <select id="lnkTarget">
                                <option value="_blank">_blank</option>
                                <option value="_self">_self</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Nofollow</label>
                            <select id="lnkNofollow">
                                <option value="0">Нет</option>
                                <option value="1">Да</option>
                            </select>
                        </div>
                        <div class="form-group full">
                            <label>Описание</label>
                            <input type="text" id="lnkDescription" placeholder="Ссылка на главную">
                        </div>
                    </div>
                    <div style="margin-top:12px;padding:10px;background:#0f172a;border:1px solid #334155;border-radius:6px;font-size:.78rem;color:#64748b;">
                        Использование: <code style="color:#a5b4fc">{{link:KEY}}</code> в контенте блоков &rarr; заменяется при сборке HTML
                    </div>
                </div>
            </div>
            <div id="targetEditor" style="display:none">
                <div class="section-block">
                    <h3>Хост публикации</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Название</label>
                            <input type="text" id="tgtName" placeholder="Hostia Production">
                        </div>
                        <div class="form-group">
                            <label>Тип</label>
                            <select id="tgtType" onchange="updateTgtConfigFormVisibility()">
                                <option value="hostia">Hostia</option>
                                <option value="ftp">FTP</option>
                                <option value="ssh">SSH</option>
                                <option value="api">API</option>
                            </select>
                        </div>
                        <div class="form-group full">
                            <label>Base URL</label>
                            <input type="text" id="tgtBaseUrl" placeholder="https://articles.example.com">
                        </div>
                        <div class="form-group">
                            <label>Активен</label>
                            <select id="tgtIsActive">
                                <option value="1">Да</option>
                                <option value="0">Нет</option>
                            </select>
                        </div>
                        <div class="form-group full">
                            <label>Config</label>
                            <div class="block-view-tabs" style="margin-bottom:8px">
                                <button class="bvt active" data-tab="form" onclick="switchTgtConfigTab('form',this)">Форма</button>
                                <button class="bvt" data-tab="json" onclick="switchTgtConfigTab('json',this)">JSON</button>
                            </div>
                            <div id="tgtConfigTabForm" class="block-tab-content">
                                <div id="tgtConfigFormHostia">
                                    <div class="form-group" style="margin-bottom:8px">
                                        <label style="font-size:.72rem;color:#64748b">publish_endpoint</label>
                                        <input type="text" id="tgtCfgPublishEndpoint" placeholder="https://example.com/admin/seo_generator/deploy/publish.php" onchange="syncTgtFormToJson()">
                                    </div>
                                </div>
                                <div id="tgtConfigFormFtp" style="display:none">
                                    <div style="display:grid;grid-template-columns:1fr 100px;gap:8px;margin-bottom:8px">
                                        <div class="form-group"><label style="font-size:.72rem;color:#64748b">host</label>
                                            <input type="text" id="tgtCfgHost" placeholder="ftp.example.com" onchange="syncTgtFormToJson()"></div>
                                        <div class="form-group"><label style="font-size:.72rem;color:#64748b">port</label>
                                            <input type="text" id="tgtCfgPort" placeholder="21" onchange="syncTgtFormToJson()"></div>
                                    </div>
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">
                                        <div class="form-group"><label style="font-size:.72rem;color:#64748b">username</label>
                                            <input type="text" id="tgtCfgUsername" onchange="syncTgtFormToJson()"></div>
                                        <div class="form-group"><label style="font-size:.72rem;color:#64748b">password</label>
                                            <input type="password" id="tgtCfgPassword" onchange="syncTgtFormToJson()"></div>
                                    </div>
                                    <div class="form-group" style="margin-bottom:8px">
                                        <label style="font-size:.72rem;color:#64748b">document_root</label>
                                        <input type="text" id="tgtCfgDocRoot" placeholder="/var/www/html" onchange="syncTgtFormToJson()">
                                    </div>
                                </div>
                                <div id="tgtConfigFormApi" style="display:none">
                                    <div class="form-group" style="margin-bottom:8px">
                                        <label style="font-size:.72rem;color:#64748b">endpoint</label>
                                        <input type="text" id="tgtCfgApiEndpoint" placeholder="https://api.example.com/publish" onchange="syncTgtFormToJson()">
                                    </div>
                                    <div class="form-group" style="margin-bottom:8px">
                                        <label style="font-size:.72rem;color:#64748b">api_key</label>
                                        <input type="text" id="tgtCfgApiKey" onchange="syncTgtFormToJson()">
                                    </div>
                                </div>
                                <span style="font-size:.7rem;color:#475569;display:block;margin-top:4px">
                                    Дополнительные поля можно добавить через вкладку JSON.
                                </span>
                            </div>
                            <div id="tgtConfigTabJson" class="block-tab-content" style="display:none">
                                <div class="json-toolbar">
                                    <button class="btn btn-xs btn-ghost" onclick="formatTgtConfigJson()">Format</button>
                                    <button class="btn btn-xs btn-ghost" onclick="copyTgtConfigJson()">Copy</button>
                                </div>
                                <div id="tgtConfigCm" class="block-cm-editor"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="auditViewer" style="display:none">
                <div class="section-block">
                    <h3>Запись лога</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Сущность</label>
                            <input type="text" id="auditEntity" disabled>
                        </div>
                        <div class="form-group">
                            <label>ID сущности</label>
                            <input type="text" id="auditEntityId" disabled>
                        </div>
                        <div class="form-group">
                            <label>Действие</label>
                            <input type="text" id="auditAction" disabled>
                        </div>
                        <div class="form-group">
                            <label>Актор</label>
                            <input type="text" id="auditActor" disabled>
                        </div>
                        <div class="form-group full">
                            <label>Детали (JSON)</label>
                            <textarea id="auditDetails" class="json-editor" rows="12" disabled></textarea>
                        </div>
                        <div class="form-group">
                            <label>Дата</label>
                            <input type="text" id="auditDate" disabled>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<div class="modal-overlay" id="deleteModal">
    <div class="modal">
        <h3 id="deleteTitle">Удалить?</h3>
        <p id="deleteMsg">Это действие необратимо.</p>
        <div class="modal-btns">
            <button class="btn btn-ghost" onclick="closeModal('deleteModal')">Отмена</button>
            <button class="btn btn-red" onclick="doDelete()">Удалить</button>
        </div>
    </div>
</div>
<div class="modal-overlay" id="blockTypeModal">
    <div class="modal">
        <h3>Добавить блок</h3>
        <div style="display:flex;flex-direction:column;gap:6px;max-height:350px;overflow-y:auto" id="blockTypeList"></div>
        <div class="modal-btns" style="margin-top:14px">
            <button class="btn btn-ghost" onclick="closeModal('blockTypeModal')">Отмена</button>
        </div>
    </div>
</div>
<div class="img-preview-overlay" id="imgPreviewOverlay" onclick="closeImgPreview(event)">
    <button class="close-preview" onclick="closeImgPreview()">&times;</button>
    <img id="imgPreviewFull" src="" alt="">
    <div class="img-preview-meta" id="imgPreviewMeta"></div>
    <div class="img-edit-row" id="imgEditRow" style="margin-top:12px;flex-wrap:wrap">
        <input type="text" id="imgEditName" placeholder="Название" style="max-width:250px">
        <input type="text" id="imgEditAlt" placeholder="Alt текст" style="max-width:250px">
        <button class="btn btn-sm btn-green" onclick="event.stopPropagation();saveImgMeta()">Сохранить</button>
        <button class="btn btn-sm btn-red" onclick="event.stopPropagation();deleteImgFromPreview()">Удалить</button>
    </div>
</div>
<div class="modal-overlay" id="imgPickerModal">
    <div class="modal" style="max-width:680px">
        <h3>Выбрать изображение</h3>
        <div class="img-modal-gallery" id="imgPickerGallery"></div>
        <div id="imgPickerEmpty" style="text-align:center;color:#475569;padding:16px;font-size:.85rem">Нет изображений. Загрузите в секции «Изображения».</div>
        <div class="modal-btns" style="margin-top:14px">
            <button class="btn btn-ghost" onclick="closeModal('imgPickerModal')">Отмена</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
    const API = 'controllers/router.php';
    let activeTab = 'articles';
    let activeEditor = null;
    let dirty = false;
    let loading = false;

    let artId = null, catId = null, tplId = null, lnkId = null, tgtId = null, auditId = null;

    let allCatalogs = [], allCatalogsFlat = [], allTemplates = [];
    let artBlocks = [], tplBlocks = [];
    let artImages = [];
    let allArticlesCache = [], allTargetsCache = [];

    let artTemplateTplBlocks = [];
    let currentProfileId = localStorage.getItem('seo_profile_id') || '';
    let currentProfile = null;

    const STATUS_LABELS = {draft:'Черновик',review:'На ревью',published:'Опубликована',unpublished:'Снята'};
    const BLOCK_TYPES = [
        {type:'hero',name:'Hero секция'},
        {type:'stats_counter',name:'Счётчик статистики'},
        {type:'richtext',name:'Текстовый блок'},
        {type:'range_table',name:'Таблица диапазонов'},
        {type:'accordion',name:'Аккордеон'},
        {type:'chart',name:'График/Диаграмма'},
        {type:'comparison_table',name:'Таблица сравнения'},
        {type:'image_section',name:'Секция с изображением'},
        {type:'faq',name:'FAQ (Schema.org)'},
        {type:'cta',name:'Call to Action'},
        {type:'feature_grid',name:'Сетка фичей'},
        {type:'testimonial',name:'Отзывы'},
        {type:'gauge_chart',name:'Шкала/Gauge'},
        {type:'timeline',name:'Таймлайн'},
        {type:'heatmap',name:'Тепловая карта'},
        {type:'funnel',name:'Воронка'},
        {type:'spark_metrics',name:'Мини-метрики'},
        {type:'radar_chart',name:'Радар'},
        {type:'before_after',name:'До/После'},
        {type:'stacked_area',name:'Stacked Area'},
        {type:'score_rings',name:'Кольца оценки'},
        {type:'range_comparison',name:'Сравнение диапазонов'},
        {type:'value_checker',name:'Проверка значения'},
        {type:'criteria_checklist',name:'Чек-лист критериев'},
        {type:'prep_checklist',name:'Чек-лист подготовки'},
        {type:'info_cards',name:'Информационные карточки'},
        {type:'story_block',name:'Кейс / История'},
        {type:'verdict_card',name:'Вердикт'},
        {type:'numbered_steps',name:'Пошаговая инструкция'},
        {type:'warning_block',name:'Предупреждение'},
        {type:'mini_calculator',name:'Мини-калькулятор'},
        {type:'comparison_cards',name:'Карточки сравнения'},
        {type:'progress_tracker',name:'Трекер прогресса'},
        {type:'key_takeaways',name:'Ключевые выводы'},
        {type:'expert_panel',name:'Мнение эксперта'},
    ];

    class SearchSelect {
        constructor(wrapperId, options = {}) {
            this.wrap = document.getElementById(wrapperId);
            if (!this.wrap) return;
            this.input = this.wrap.querySelector('.ss-input');
            this.hidden = this.wrap.querySelector('input[type=hidden]');
            this.clearBtn = this.wrap.querySelector('.ss-clear');
            this.dropdown = this.wrap.querySelector('.ss-dropdown');
            this.placeholder = options.placeholder || this.input.placeholder || '';
            this.emptyLabel = options.emptyLabel || '';
            this.items = []; // [{value, label, depth?}]
            this.highlighted = -1;
            this._onChange = options.onChange || null;
            this._bind();
            this.input.value = '';
        }
        _bind() {
            this.input.addEventListener('focus', () => this._open());
            this.input.addEventListener('input', () => { this.highlighted=-1; this._render(); });
            this.input.addEventListener('keydown', e => this._key(e));
            this.clearBtn.addEventListener('click', () => this.clear());
            document.addEventListener('click', e => { if (!this.wrap.contains(e.target)) this._close(); });
        }
        setItems(items) { this.items = items; }
        getValue() { return this.hidden.value; }
        setValue(val, silent) {
            this.hidden.value = val||'';
            const item = this.items.find(i => String(i.value) === String(val));
            this.input.value = item ? item.label : '';
            this.wrap.classList.toggle('has-value', !!val);
            this.input.placeholder = val ? '' : this.placeholder;
            if (!silent && this._onChange) this._onChange(val);
        }
        clear() {
            this.hidden.value = '';
            this.input.value = '';
            this.input.placeholder = this.placeholder;
            this.wrap.classList.remove('has-value');
            this._close();
            this.hidden.dispatchEvent(new Event('change', {bubbles:true}));
            if (this._onChange) this._onChange('');
        }
        _open() {
            this._render();
            this.dropdown.classList.add('open');
        }
        _close() { this.dropdown.classList.remove('open'); this.highlighted=-1; }
        _key(e) {
            const visible = this.dropdown.querySelectorAll('.ss-option');
            if (e.key==='ArrowDown') { e.preventDefault(); this.highlighted = Math.min(this.highlighted+1, visible.length-1); this._highlightEl(visible); }
            else if (e.key==='ArrowUp') { e.preventDefault(); this.highlighted = Math.max(this.highlighted-1, 0); this._highlightEl(visible); }
            else if (e.key==='Enter') { e.preventDefault(); if (this.highlighted>=0 && visible[this.highlighted]) visible[this.highlighted].click(); }
            else if (e.key==='Escape') { this._close(); this.input.blur(); }
        }
        _highlightEl(els) {
            els.forEach((el,i) => el.classList.toggle('highlighted', i===this.highlighted));
            if (els[this.highlighted]) els[this.highlighted].scrollIntoView({block:'nearest'});
        }
        _render() {
            const q = this.input.value.toLowerCase().trim();
            const filtered = q ? this.items.filter(i => i.label.toLowerCase().includes(q)) : this.items;
            if (!filtered.length) {
                this.dropdown.innerHTML = '<div class="ss-empty">Ничего не найдено</div>';
                return;
            }
            this.dropdown.innerHTML = (this.emptyLabel ? '<div class="ss-option" data-value=""><span style="color:#475569">'+esc(this.emptyLabel)+'</span></div>' : '')
                + filtered.map(i => {
                    const sel = String(i.value) === String(this.hidden.value) ? ' selected' : '';
                    const depthStr = i.depth ? '<span class="ss-depth">'+'— '.repeat(i.depth)+'</span>' : '';
                    let label = esc(i.label);
                    if (q) label = label.replace(new RegExp('('+q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+')','gi'), '<span class="ss-match">$1</span>');
                    return '<div class="ss-option'+sel+'" data-value="'+esc(String(i.value))+'">'+depthStr+label+'</div>';
                }).join('');

            this.dropdown.querySelectorAll('.ss-option').forEach(opt => {
                opt.addEventListener('click', () => {
                    const v = opt.dataset.value;
                    this.setValue(v);
                    this._close();
                    this.hidden.dispatchEvent(new Event('change', {bubbles:true}));
                });
            });
        }
    }

    const ssFilterCatalog = new SearchSelect('ss_filterCatalog', {placeholder:'Все каталоги', emptyLabel:'Все каталоги', onChange: () => loadArticlesList()});
    const ssArtCatalog    = new SearchSelect('ss_artCatalog',    {placeholder:'Выберите каталог...', emptyLabel:'— без каталога —'});
    const ssArtTemplate   = new SearchSelect('ss_artTemplate',   {placeholder:'Выберите шаблон...', emptyLabel:'— без шаблона —'});
    const ssCatParent     = new SearchSelect('ss_catParent',     {placeholder:'— корневой —', emptyLabel:'— корневой —'});
    const ssLnkArticle    = new SearchSelect('ss_lnkArticle',    {placeholder:'Глобальная (все статьи)', emptyLabel:'Глобальная'});
    const ssPubTarget     = new SearchSelect('ss_pubTarget',     {placeholder:'Выберите хост...', emptyLabel:''});

    async function loadProfileHeader() {
        if (!currentProfileId) {
            window.location.href = '/seo_profile_page.php';
            return;
        }
        try {
            const res = await fetch(`${API}?r=profiles/${currentProfileId}`);
            const json = await res.json();
            if (!json.success) { window.location.href = '/seo_profile_page.php'; return; }
            const p = json.data;
            currentProfile = p;
            $('topbarProfileName').textContent = p.name;
            $('topbarProfileMeta').textContent = (p.slug || '') + (p.domain ? ' \u00b7 ' + p.domain : '');
            const iconEl = $('topbarProfileIcon');
            if (p.icon_path) {
                iconEl.innerHTML = '<img src="' + API + '?r=profiles/' + p.id + '/icon" style="width:100%;height:100%;object-fit:cover;border-radius:8px">';
            } else {
                iconEl.textContent = (p.name || '?')[0].toUpperCase();
                iconEl.style.color = p.color_scheme || '#6366f1';
            }
        } catch(e) { console.error('loadProfileHeader', e); }
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (!currentProfileId) { window.location.href = '/seo_profile_page.php'; return; }
        loadProfileHeader();
        loadBlockTypeSchemas();
        loadCatalogsList();
        loadArticlesList();
        loadTemplatesList();
        loadLinksList();
        loadTargetsList();
        loadAuditList();
        loadArticlesCache();
        loadPublishTargets();
        initImageUpload();
    });

    document.addEventListener('keydown', e => {
        if ((e.ctrlKey||e.metaKey) && e.key==='s') { e.preventDefault(); saveCurrentEditor(); }
        if (e.key==='Escape' && $('imgPreviewOverlay').classList.contains('show')) { closeImgPreview(); }
    });

    const debounce = (fn,ms=300) => { let t; return (...a) => { clearTimeout(t); t=setTimeout(()=>fn(...a),ms); }; };
    $('searchArticle').addEventListener('input', debounce(loadArticlesList));
    $('filterStatus').addEventListener('change', loadArticlesList);
    $('searchCatalog').addEventListener('input', debounce(renderCatalogTree));
    $('searchTemplate').addEventListener('input', debounce(loadTemplatesList));
    $('searchLink').addEventListener('input', debounce(loadLinksList));
    $('filterLinkScope').addEventListener('change', loadLinksList);
    $('searchTarget').addEventListener('input', debounce(loadTargetsList));
    $('filterAuditEntity').addEventListener('change', loadAuditList);
    $('filterAuditAction').addEventListener('change', loadAuditList);

    document.addEventListener('input', e => {
        if (!loading && e.target.closest('#articleEditor,#catalogEditor,#templateEditor,#linkEditor,#targetEditor')) dirty = true;
    });
    document.addEventListener('change', e => {
        if (!loading && e.target.closest('#articleEditor,#catalogEditor,#templateEditor,#linkEditor,#targetEditor')) dirty = true;
    });

    async function api(resource, opts={}) {
        const url = API + '?r=' + resource;
        const fetchOpts = { headers:{'Content-Type':'application/json'} };
        if (opts.body) {
            fetchOpts.method = opts.method || 'POST';
            fetchOpts.body = JSON.stringify(opts.body);
        } else {
            fetchOpts.method = opts.method || 'GET';
        }
        const resp = await fetch(url, fetchOpts);
        const data = await resp.json();
        if (!data.success) throw new Error(data.error || 'Ошибка API');
        return data;
    }

    const SLUG_MAP = {'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'yo','ж':'zh','з':'z','и':'i','й':'j','к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r','с':'s','т':'t','у':'u','ф':'f','х':'kh','ц':'ts','ч':'ch','ш':'sh','щ':'shch','ъ':'','ы':'y','ь':'','э':'e','ю':'yu','я':'ya'};
    function generateSlugFromTitle() {
        const title = $('artTitle').value;
        if (!title) { toast('Заполните заголовок', true); return; }
        let slug = title.toLowerCase().split('').map(c => SLUG_MAP[c] !== undefined ? SLUG_MAP[c] : c).join('');
        slug = slug.replace(/[^a-z0-9\-]+/g, '-').replace(/-{2,}/g, '-').replace(/^-|-$/g, '');
        if (slug.length > 120) slug = slug.substring(0, 120).replace(/-[^-]*$/, '');
        $('artSlug').value = slug;
        dirty = true;
    }

    function setArticlePlan(raw) {
        const val = (raw || '').replace(/\s*→\s*/g, '\n');
        $('artArticlePlan').value = val;
    }
    function getArticlePlan() {
        return $('artArticlePlan').value.split('\n').map(s => s.trim()).filter(Boolean).join(' → ');
    }

    function switchTab(tab) {
        activeTab = tab;
        document.querySelectorAll('.list-tab').forEach(t => t.classList.toggle('active', t.dataset.tab===tab));
        const m = {articles:'tabArticles',catalogs:'tabCatalogs',templates:'tabTemplates',links:'tabLinks',targets:'tabTargets',audit:'tabAudit'};
        Object.entries(m).forEach(([k,id]) => $(id).classList.toggle('active', k===tab));
    }

    function togglePanel() {
        const p=$('listPanel'), b=$('panelToggle');
        p.classList.toggle('collapsed');
        const c = p.classList.contains('collapsed');
        b.classList.toggle('shifted', c);
        b.innerHTML = c ? '&#9654;' : '&#9664;';
    }

    function hideAllEditors() {
        ['emptyState','articleEditor','catalogEditor','templateEditor','linkEditor','targetEditor','auditViewer']
            .forEach(id => $(id).style.display = 'none');
    }

    function showEmptyState() {
        hideAllEditors();
        $('emptyState').style.display = 'flex';
        activeEditor = null;
        $('editorId').textContent = '';
        $('editorStatus').innerHTML = '';
        $('btnDelete').style.display = 'none';
        $('btnDuplicate').style.display = 'none';
        $('btnSave').style.display = 'inline-flex';
    }

    function showEditor(editorId, label, entityId, statusHtml) {
        hideAllEditors();
        $(editorId).style.display = 'block';
        $('editorId').textContent = label;
        $('editorStatus').innerHTML = statusHtml || '';
        $('btnDelete').style.display = entityId ? 'inline-flex' : 'none';
        $('btnDuplicate').style.display = entityId ? 'inline-flex' : 'none';
        $('btnSave').style.display = 'inline-flex';
        dirty = false;
    }

    async function loadArticlesList() {
        try {
            const search = $('searchArticle').value;
            const catalog = $('filterCatalog').value;
            const status = $('filterStatus').value;
            let q = 'articles?per_page=100';
            if (currentProfileId) q += '&profile_id=' + currentProfileId;
            if (search) q += '&search=' + encodeURIComponent(search);
            if (catalog) q += '&catalog_id=' + catalog;
            if (status) q += '&status=' + status;
            const data = await api(q);
            renderArticleList(data.data || []);
        } catch(e) { toast(e.message, true); }
    }

    function renderArticleList(rows) {
        $('articleCount').textContent = rows.length + ' статей';
        if (!rows.length) { $('articleList').innerHTML = '<div style="padding:30px;text-align:center;color:#475569">Нет статей</div>'; return; }
        $('articleList').innerHTML = rows.map(r => '<div class="list-item '+(activeEditor==='article'&&r.id==artId?'selected':'')+'" onclick="selectArticle('+r.id+')">'
            +'<div class="status-dot '+esc(r.status||'draft')+'"></div>'
            +'<div class="list-item-body">'
            +'<div class="list-item-name">'+esc(r.title)+'</div>'
            +'<div class="list-item-sub">/'+esc(r.slug)+'</div>'
            +'<div class="list-item-meta">'
            +'<span class="tag status-'+esc(r.status)+'">'+esc(STATUS_LABELS[r.status]||r.status)+'</span>'
            +(r.catalog_name?'<span class="tag cat">'+esc(r.catalog_name)+'</span>':'')
            +(r.template_name?'<span class="tag type">'+esc(r.template_name)+'</span>':'')
            +'<span class="tag">v'+(r.version||1)+'</span>'
            +'</div></div></div>'
        ).join('');
    }

    async function selectArticle(id) {
        if (dirty && !confirm('Несохранённые изменения. Продолжить?')) return;
        try {
            const data = await api('articles/'+id);
            const art = data.data;
            artId = art.id;
            activeEditor = 'article';

            if (art.template_id) {
                try {
                    const tplData = await api('templates/' + art.template_id);
                    artTemplateTplBlocks = tplData.data?.blocks || [];
                } catch(e) { artTemplateTplBlocks = []; }
            } else {
                artTemplateTplBlocks = [];
            }

            showEditor('articleEditor', 'Статья #'+art.id, art.id,
                '<span class="status-badge status-'+art.status+'">'+esc(STATUS_LABELS[art.status])+'</span>');

            loading = true;
            $('artTitle').value = art.title||'';
            $('artSlug').value = art.slug||'';
            $('artStatus').value = art.status||'draft';
            ssArtCatalog.setValue(art.catalog_id||'', true);
            ssArtTemplate.setValue(art.template_id||'', true);
            $('artKeywords').value = art.keywords||'';
            $('artMetaTitle').value = art.meta_title||'';
            $('artMetaDesc').value = art.meta_description||'';
            $('artMetaKeywords').value = art.meta_keywords||'';
            setArticlePlan(art.article_plan||'');
            $('artGptModel').value = art.gpt_model||'gpt-4o';
            $('genModel').value = art.gpt_model||'gpt-4o';
            setVersionBadge(art.version||1);
            $('artCreatedBy').value = art.created_by||'';
            setPublishedUrl(art.published_url||'');
            $('artGenLog').value = art.generation_log ? jsonPretty(art.generation_log) : '';
            $('artGenLog').style.display = 'none';
            $('genLogToggleIcon').textContent = '▶ показать';

            $('btnUnpublish').style.display = art.status==='published' ? 'inline-flex' : 'none';
            $('pubResult').style.display = 'none';
            $('previewFrame').style.display = 'none';

            // Telegram
            $('artTgExport').checked = !!art.tg_export;
            updateTgPanelVisibility(art);
            loading = false;

            await loadArticleBlocks(art.id);
            await loadArticleImages(art.id);
            $('genLog').style.display='none'; $('genLog').innerHTML='';
            $('genProgress').classList.remove('active');
            $('imgGenStatus').style.display='none';
            loadArticlesList();
        } catch(e) { toast(e.message, true); }
    }

    function newArticle() {
        if (dirty && !confirm('Несохранённые изменения. Продолжить?')) return;
        artId = null; artBlocks = []; artImages = []; artTemplateTplBlocks = [];
        activeEditor = 'article';
        showEditor('articleEditor', 'Новая статья', null, '<span class="status-badge status-draft">Черновик</span>');
        loading = true;
        ['artTitle','artSlug','artKeywords','artMetaTitle','artMetaDesc','artMetaKeywords','artArticlePlan','artCreatedBy','artGenLog'].forEach(id => $(id).value='');
        $('artStatus').value = 'draft'; ssArtCatalog.clear(); ssArtTemplate.clear();
        $('artGptModel').value = 'gpt-4o'; setVersionBadge(1); setPublishedUrl('');
        $('artGenLog').style.display = 'none'; $('genLogToggleIcon').textContent = '▶ показать';
        $('genModel').value = 'gpt-4o';
        $('genLog').style.display='none'; $('genLog').innerHTML='';
        $('genProgress').classList.remove('active');
        $('pubResult').style.display='none'; $('previewFrame').style.display='none';
        $('btnUnpublish').style.display='none';
        $('imgGenStatus').style.display='none';
        $('artTgExport').checked = false;
        $('tgPostPanel').style.display = 'none';
        loading = false;
        renderArticleBlocks([]);
        renderImageGallery([]);
        switchTab('articles');
        $('artTitle').focus();
    }

    async function saveArticle() {
        const body = {
            title: $('artTitle').value, slug: $('artSlug').value, status: $('artStatus').value,
            catalog_id: $('artCatalog').value||null, template_id: $('artTemplate').value||null,
            keywords: $('artKeywords').value, meta_title: $('artMetaTitle').value,
            meta_description: $('artMetaDesc').value, meta_keywords: $('artMetaKeywords').value,
            article_plan: getArticlePlan(),
            gpt_model: $('artGptModel').value, created_by: $('artCreatedBy').value,
            profile_id: currentProfileId || null,
            tg_export: $('artTgExport').checked ? 1 : 0,
        };
        try {
            if (artId) { await api('articles/'+artId, {method:'PUT', body}); toast('Статья сохранена'); }
            else { const d = await api('articles', {method:'POST', body}); artId = d.data.id; toast('Статья создана'); }
            dirty = false;
            $('editorId').textContent = 'Статья #'+artId;
            $('btnDelete').style.display = 'inline-flex';
            $('btnDuplicate').style.display = 'inline-flex';
            loadArticlesList();
            // Обновляем дерево каталогов чтобы статья появилась там
            await loadAllCatalogArticles();
            renderCatalogTree();
        } catch(e) { toast(e.message, true); }
    }

    /* ── Настройки статьи: вспомогательные функции ── */
    function setVersionBadge(version) {
        $('artVersionBadge').textContent = 'v' + (version || 1);
    }

    function setPublishedUrl(url) {
        const a = $('artPublishedUrl');
        if (url) {
            a.href = url;
            a.textContent = url;
            a.classList.remove('empty');
        } else {
            a.href = '#';
            a.textContent = 'не опубликована';
            a.classList.add('empty');
        }
    }

    function copyPublishedUrl() {
        const a = $('artPublishedUrl');
        if (a.classList.contains('empty')) { toast('URL не задан', true); return; }
        navigator.clipboard.writeText(a.href).then(() => toast('URL скопирован')).catch(() => toast('Не удалось скопировать', true));
    }

    function toggleGenLog() {
        const ta = $('artGenLog');
        const icon = $('genLogToggleIcon');
        const visible = ta.style.display !== 'none';
        ta.style.display = visible ? 'none' : 'block';
        icon.textContent = visible ? '▶ показать' : '▼ скрыть';
    }

    /* ── Схемы блоков ── */
    let blockTypeSchemas = {};

    async function loadBlockTypeSchemas() {
        try {
            const data = await api('block-types');
            const items = data.data || data.items || data || [];
            items.forEach(function(bt) {
                if (bt.code && bt.json_schema) {
                    blockTypeSchemas[bt.code] = bt.json_schema;
                }
            });
        } catch(e) { console.warn('loadBlockTypeSchemas:', e); }
    }

    /* ── Состояние контента блоков ── */
    let blockContents = {};
    let jsonEditors = {};

    function getBlockJsonValue(blockId) {
        return jsonEditors[blockId] ? jsonEditors[blockId].getValue() : '';
    }

    function setBlockJsonValue(blockId, str) {
        const ed = jsonEditors[blockId];
        if (!ed) return;
        if (ed.getValue() !== str) ed.setValue(str);
    }

    function initJsonEditor(blockId) {
        const el = document.getElementById('bc_' + blockId);
        if (!el) return;
        if (jsonEditors[blockId]) { jsonEditors[blockId].refresh(); return; }
        const editor = CodeMirror(el, {
            value: jsonPretty(blockContents[blockId] || {}),
            mode: { name: 'javascript', json: true },
            theme: 'dracula',
            lineNumbers: true,
            matchBrackets: true,
            autoCloseBrackets: true,
            tabSize: 2,
            indentWithTabs: false,
            lineWrapping: true
        });
        editor.on('change', function(cm) {
            try {
                const parsed = JSON.parse(cm.getValue());
                blockContents[blockId] = parsed;
                const block = artBlocks.find(function(b) { return b.id === blockId; });
                const formTab = document.getElementById('btab_form_' + blockId);
                if (formTab && block) formTab.innerHTML = renderBlockFormEditor(blockId, block.type, parsed);
            } catch(e) { /* invalid JSON */ }
        });
        editor.on('blur', function() { saveBlockJson(blockId); });
        jsonEditors[blockId] = editor;
    }

    /* ── 3-view: переключение табов ── */
    function switchBlockTab(blockId, tab, btn) {
        ['form','json','preview'].forEach(function(t) {
            const el = document.getElementById('btab_' + t + '_' + blockId);
            if (el) el.style.display = t === tab ? 'block' : 'none';
        });
        if (btn) {
            const wrap = btn.closest('.block-view-tabs');
            if (wrap) wrap.querySelectorAll('.bvt').forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
        }
        if (tab === 'preview') renderBlockPreview(blockId);
        if (tab === 'json') initJsonEditor(blockId);
    }

    /* ── 3-view: HTML-превью блока ── */
    async function renderBlockPreview(blockId) {
        const content = blockContents[blockId] || {};
        const block = artBlocks.find(function(b) { return b.id === blockId; });
        if (!block) return;

        const frame = document.getElementById('bprev_' + blockId);
        if (!frame) return;
        frame.srcdoc = '<div style="padding:20px;font-family:system-ui,sans-serif;color:#888">Загрузка превью...</div>';

        try {
            const resp = await fetch(API + '?r=articles/render-block', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({type: block.type, content: content})
            });
            const html = await resp.text();
            frame.onload = () => {
                const doc = frame.contentDocument;
                doc.querySelectorAll('.reveal').forEach(el => {
                    el.classList.add('vis');
                });
            };
            frame.srcdoc = html;
        } catch(e) {
            frame.srcdoc = '<div style="padding:20px;font-family:system-ui,sans-serif;color:#ef4444">Ошибка рендера: ' + e.message + '</div>';
        }
    }

    /* ── 3-view: JSON-редактор ── */
    function formatBlockJson(blockId) {
        const ed = jsonEditors[blockId];
        if (!ed) return;
        try {
            ed.setValue(JSON.stringify(JSON.parse(ed.getValue()), null, 2));
        } catch(e) { toast('Невалидный JSON', true); }
    }

    function copyBlockJson(blockId) {
        navigator.clipboard.writeText(getBlockJsonValue(blockId))
            .then(() => toast('JSON скопирован'))
            .catch(() => toast('Не удалось скопировать', true));
    }

    async function saveBlockJson(blockId) {
        const val = getBlockJsonValue(blockId);
        if (!val) return;
        await updateArtBlock(blockId, 'content', val);
    }

    function syncFormToJson(blockId) {
        setBlockJsonValue(blockId, jsonPretty(blockContents[blockId]));
    }

    /* ── 3-view: обновление из формы ── */
    function updateBlockField(blockId, field, value) {
        if (!blockContents[blockId]) blockContents[blockId] = {};
        blockContents[blockId][field] = value;
        syncFormToJson(blockId);
        updateArtBlock(blockId, 'content', JSON.stringify(blockContents[blockId]));
    }

    function updateArrayItem(blockId, field, idx, subKey, value) {
        if (!blockContents[blockId]) blockContents[blockId] = {};
        if (!Array.isArray(blockContents[blockId][field])) blockContents[blockId][field] = [];
        if (!blockContents[blockId][field][idx]) blockContents[blockId][field][idx] = {};
        blockContents[blockId][field][idx][subKey] = value;
        syncFormToJson(blockId);
        updateArtBlock(blockId, 'content', JSON.stringify(blockContents[blockId]));
    }

    function addArrayItem(blockId, field) {
        if (!blockContents[blockId]) blockContents[blockId] = {};
        if (!Array.isArray(blockContents[blockId][field])) blockContents[blockId][field] = [];
        const schema = blockTypeSchemas[artBlocks.find(function(b) { return b.id === blockId; })?.type] || {};
        const fieldDef = (schema.fields || {})[field] || {};
        const subFields = fieldDef.items || {};
        const newItem = {};
        Object.keys(subFields).forEach(function(k) { newItem[k] = ''; });
        blockContents[blockId][field].push(newItem);
        syncFormToJson(blockId);
        updateArtBlock(blockId, 'content', JSON.stringify(blockContents[blockId]));
        // Перерисовать форму
        const block = artBlocks.find(function(b) { return b.id === blockId; });
        const formTab = document.getElementById('btab_form_' + blockId);
        if (formTab && block) {
            formTab.innerHTML = renderBlockFormEditor(blockId, block.type, blockContents[blockId]);
        }
    }

    function removeArrayItem(blockId, field, idx) {
        if (!blockContents[blockId] || !Array.isArray(blockContents[blockId][field])) return;
        blockContents[blockId][field].splice(idx, 1);
        syncFormToJson(blockId);
        updateArtBlock(blockId, 'content', JSON.stringify(blockContents[blockId]));
        const block = artBlocks.find(function(b) { return b.id === blockId; });
        const formTab = document.getElementById('btab_form_' + blockId);
        if (formTab && block) {
            formTab.innerHTML = renderBlockFormEditor(blockId, block.type, blockContents[blockId]);
        }
    }

    /* ── 3-view: генератор структурных форм ── */
    function renderBlockFormEditor(blockId, blockType, content) {
        const schema = blockTypeSchemas[blockType];
        if (!schema || !schema.fields) {
            return '<div class="no-schema-hint">Схема полей не найдена для типа <b>' + esc(blockType) + '</b>. '
                + '<a onclick="switchBlockTab(' + blockId + ',\'json\',document.querySelector(\'#blockItem_' + blockId + ' .bvt[data-tab=json]\'))">Открыть JSON</a></div>';
        }

        var html = '<div class="block-form-editor">';
        Object.keys(schema.fields).forEach(function(field) {
            var def = schema.fields[field];
            var val = (content && content[field] !== undefined) ? content[field] : '';
            var type = (typeof def === 'string') ? def : (def.type || 'string');

            if (type === 'array') {
                html += renderArrayFieldEditor(blockId, field, def, Array.isArray(val) ? val : []);
            } else {
                var strVal = (val === null || val === undefined) ? '' : String(val);
                var isLong = strVal.length > 80 || field === 'text' || field === 'content' || field === 'html' || field === 'description';
                var required = def.required ? ' *' : '';
                html += '<div class="form-group">';
                html += '<label>' + esc(field) + required + '</label>';
                if (isLong) {
                    html += '<textarea rows="4" onchange="updateBlockField(' + blockId + ',\'' + field + '\',this.value)">' + esc(strVal) + '</textarea>';
                } else {
                    html += '<input type="text" value="' + esc(strVal) + '" onchange="updateBlockField(' + blockId + ',\'' + field + '\',this.value)">';
                }
                html += '</div>';
            }
        });
        html += '</div>';
        return html;
    }

    function renderArrayFieldEditor(blockId, field, def, items) {
        var subFields = (typeof def === 'object' && def.items && typeof def.items === 'object') ? def.items : {};
        var html = '<div class="form-group full"><label>' + esc(field) + '</label>';
        html += '<div class="array-field-list" id="afl_' + blockId + '_' + field + '">';

        items.forEach(function(item, idx) {
            html += '<div class="block-form-array-item">';
            html += '<div class="array-item-header"><span>' + esc(field) + ' #' + (idx + 1) + '</span>';
            html += '<button class="btn btn-xs btn-red" onclick="removeArrayItem(' + blockId + ',\'' + field + '\',' + idx + ')">✕</button></div>';

            Object.keys(subFields).forEach(function(subKey) {
                var subVal = (item && item[subKey] !== undefined) ? String(item[subKey]) : '';
                var isLong = subKey === 'answer' || subKey === 'content' || subKey === 'text' || subKey === 'description' || subKey === 'html' || subVal.length > 80;
                html += '<div class="form-group">';
                html += '<label>' + esc(subKey) + '</label>';
                if (isLong) {
                    html += '<textarea rows="3" onchange="updateArrayItem(' + blockId + ',\'' + field + '\',' + idx + ',\'' + subKey + '\',this.value)">' + esc(subVal) + '</textarea>';
                } else {
                    html += '<input type="text" value="' + esc(subVal) + '" onchange="updateArrayItem(' + blockId + ',\'' + field + '\',' + idx + ',\'' + subKey + '\',this.value)">';
                }
                html += '</div>';
            });
            html += '</div>';
        });

        html += '</div>';
        html += '<button class="btn btn-xs btn-ghost" style="margin-top:6px" onclick="addArrayItem(' + blockId + ',\'' + field + '\')">+ Добавить ' + esc(field) + '</button>';
        html += '</div>';
        return html;
    }

    async function loadArticleBlocks(articleId) {
        try {
            const data = await api('articles/blocks/'+articleId);
            artBlocks = data.data || [];
            renderArticleBlocks(artBlocks);
        } catch(e) { artBlocks=[]; renderArticleBlocks([]); }
    }

    function renderArticleBlocks(blocks) {
        jsonEditors = {};
        $('artBlocksEmpty').style.display = blocks.length ? 'none' : 'block';
        if (!blocks.length) { $('artBlocksList').innerHTML=''; return; }
        $('artBlocksList').innerHTML = blocks.map(b => {
            let content = {};
            if (typeof b.content === 'object' && b.content) content = b.content;
            else if (typeof b.content === 'string' && b.content) { try { content = JSON.parse(b.content); } catch(e) {} }
            const hasImage = content.image_id;
            const imgThumb = hasImage ? '<img src="'+API+'?r=images/'+content.image_id+'/raw" style="max-height:60px;border-radius:4px;margin-right:8px">' : '';

            let curLayout = 'right';
            if (hasImage && content.image_layout) {
                curLayout = content.image_layout;
            }

            const imgBtn = '<button class="btn btn-xs btn-ghost" onclick="event.stopPropagation();pickImageForBlock('+b.id+')" title="Привязать изображение" style="margin-right:2px">🖼</button>'
                +(hasImage ? '<button class="btn btn-xs btn-ghost" onclick="event.stopPropagation();unlinkImageFromBlock('+b.id+')" title="Снять изображение с блока" style="margin-right:2px;color:#fca5a5;border-color:#7f1d1d">✕🖼</button>' : '')
                +'<button class="btn btn-xs" onclick="event.stopPropagation();generateBlockImage('+b.id+')" title="AI-генерация изображения" style="margin-right:2px;background:#7c3aed;color:#fff;border:none;font-size:.65rem;padding:1px 6px;border-radius:3px">&#127912;</button>';

            let imgSection = '';
            if (hasImage) {
                imgSection = '<div style="margin-bottom:10px;display:flex;align-items:flex-start;gap:12px;flex-wrap:wrap">'
                    + imgThumb
                    + '<div class="img-layout-row" id="blockImgLayout_'+b.id+'">'
                    + '<label>Позиция картинки в блоке</label>'
                    + '<div class="img-layout-grid">'
                    + buildLayoutBtn('left-top', '↖ Лево-верх', curLayout, b.id)
                    + buildLayoutBtn('top', '↑ Сверху', curLayout, b.id)
                    + buildLayoutBtn('right-top', '↗ Право-верх', curLayout, b.id)
                    + buildLayoutBtn('full', '⬛ Широкий', curLayout, b.id)
                    + buildLayoutBtn('left', '← Слева', curLayout, b.id)
                    + buildLayoutBtn('center', '◆ Центр', curLayout, b.id)
                    + buildLayoutBtn('right', '→ Справа', curLayout, b.id)
                    + buildLayoutBtn('background', '🖼 Фон', curLayout, b.id)
                    + buildLayoutBtn('left-bottom', '↙ Лево-низ', curLayout, b.id)
                    + buildLayoutBtn('bottom', '↓ Снизу', curLayout, b.id)
                    + buildLayoutBtn('right-bottom', '↘ Право-низ', curLayout, b.id)
                    + buildLayoutBtn('hidden', '🚫 Скрыть', curLayout, b.id)
                    + '</div></div></div>';
            }

            blockContents[b.id] = content;

            return '<div class="block-item" data-block-id="'+b.id+'" id="blockItem_'+b.id+'" draggable="true">'
                +'<div class="block-header" onclick="toggleBlock(this)">'
                +'<div class="block-header-left">'
                +'<span class="drag-handle" onclick="event.stopPropagation()" title="Перетащить">&#8942;&#8942;</span>'
                +'<span class="block-type">'+esc(b.type)+'</span>'
                +'<span class="block-name">'+esc(b.name||'Без названия')+'</span>'
                +(hasImage ? '<span style="margin-left:6px;font-size:.7rem;color:#6ee7b7">🖼 #'+content.image_id+' <span style="color:#818cf8;font-size:.65rem">'+curLayout+'</span></span>' : '')
                +'</div><div class="block-header-right">'
                +imgBtn
                +'<button class="btn-regen" onclick="event.stopPropagation();regenBlock('+b.id+',\''+esc(b.type)+'\')" title="Перегенерировать через GPT">Gen</button>'
                +'<button class="btn btn-xs btn-ghost" onclick="event.stopPropagation();toggleBlockVis('+b.id+','+(b.is_visible?0:1)+')" title="'+(b.is_visible?'Скрыть':'Показать')+'">'+(b.is_visible?'Видим':'Скрыт')+'</button>'
                +'<button class="btn btn-xs btn-red" onclick="event.stopPropagation();deleteArtBlock('+b.id+')" title="Удалить">&#10005;</button>'
                +'<span class="collapse-arrow">&#9660;</span>'
                +'</div></div>'
                +'<div class="block-body hidden">'
                +imgSection
                +'<div class="form-group" style="margin-bottom:10px"><label>Название блока</label>'
                +'<input type="text" value="'+esc(b.name||'')+'" onchange="updateArtBlock('+b.id+',\'name\',this.value)"></div>'
                // 3-view tabs
                +'<div class="block-view-tabs">'
                +'<button class="bvt active" data-tab="form" onclick="event.stopPropagation();switchBlockTab('+b.id+',\'form\',this)">Форма</button>'
                +'<button class="bvt" data-tab="json" onclick="event.stopPropagation();switchBlockTab('+b.id+',\'json\',this)">JSON</button>'
                +'<button class="bvt" data-tab="preview" onclick="event.stopPropagation();switchBlockTab('+b.id+',\'preview\',this)">Превью</button>'
                +'</div>'
                // Tab: Форма
                +'<div id="btab_form_'+b.id+'" class="block-tab-content">'
                +renderBlockFormEditor(b.id, b.type, content)
                +'</div>'
                // Tab: JSON
                +'<div id="btab_json_'+b.id+'" class="block-tab-content" style="display:none">'
                +'<div class="json-toolbar">'
                +'<button class="btn btn-xs btn-ghost" onclick="event.stopPropagation();formatBlockJson('+b.id+')">Format</button>'
                +'<button class="btn btn-xs btn-ghost" onclick="event.stopPropagation();copyBlockJson('+b.id+')">Copy</button>'
                +'</div>'
                +'<div id="bc_'+b.id+'" class="block-cm-editor"></div>'
                +'</div>'
                // Tab: Превью
                +'<div id="btab_preview_'+b.id+'" class="block-tab-content" style="display:none">'
                +'<button class="btn btn-xs btn-ghost" style="margin-bottom:8px" onclick="event.stopPropagation();renderBlockPreview('+b.id+')">&#8635; Обновить превью</button>'
                +'<iframe id="bprev_'+b.id+'" class="block-preview-frame" sandbox="allow-scripts allow-same-origin"></iframe>'
                +'</div>'
                // GPT Prompt (после табов)
                +'<div class="form-group" style="margin-top:10px"><label>GPT Prompt <span class="label-hint">(доп. инструкции для генерации этого блока)</span></label>'
                +'<textarea rows="3" id="bp_'+b.id+'" onchange="updateArtBlock('+b.id+',\'gpt_prompt\',this.value)">'+esc(b.gpt_prompt||'')+'</textarea></div>'
                +'</div></div>';
        }).join('');
        initDragSort('artBlocksList', saveArtBlocksOrder);
    }

    function buildLayoutBtn(layout, title, curLayout, blockId) {
        const active = layout === curLayout ? ' active' : '';
        const svgs = {
            'left-top':     '<svg viewBox="0 0 24 18"><rect class="l-block" x="1" y="1" width="9" height="7" rx="1.5"/><line class="l-line" x1="13" y1="2" x2="23" y2="2"/><line class="l-line" x1="13" y1="5" x2="23" y2="5"/><line class="l-line" x1="1" y1="11" x2="23" y2="11"/><line class="l-line" x1="1" y1="14" x2="23" y2="14"/><line class="l-line" x1="1" y1="17" x2="16" y2="17"/></svg>',
            'top':          '<svg viewBox="0 0 24 18"><rect class="l-block" x="3" y="1" width="18" height="7" rx="1.5"/><line class="l-line" x1="1" y1="11" x2="23" y2="11"/><line class="l-line" x1="1" y1="14" x2="23" y2="14"/><line class="l-line" x1="1" y1="17" x2="16" y2="17"/></svg>',
            'right-top':    '<svg viewBox="0 0 24 18"><rect class="l-block" x="14" y="1" width="9" height="7" rx="1.5"/><line class="l-line" x1="1" y1="2" x2="11" y2="2"/><line class="l-line" x1="1" y1="5" x2="11" y2="5"/><line class="l-line" x1="1" y1="11" x2="23" y2="11"/><line class="l-line" x1="1" y1="14" x2="23" y2="14"/><line class="l-line" x1="1" y1="17" x2="16" y2="17"/></svg>',
            'full':         '<svg viewBox="0 0 24 18"><rect class="l-block" x="1" y="1" width="22" height="10" rx="1.5"/><line class="l-line" x1="1" y1="14" x2="23" y2="14"/><line class="l-line" x1="1" y1="17" x2="16" y2="17"/></svg>',
            'left':         '<svg viewBox="0 0 24 18"><rect class="l-block" x="1" y="4" width="9" height="10" rx="1.5"/><line class="l-line" x1="13" y1="2" x2="23" y2="2"/><line class="l-line" x1="13" y1="5" x2="23" y2="5"/><line class="l-line" x1="13" y1="8" x2="23" y2="8"/><line class="l-line" x1="13" y1="11" x2="23" y2="11"/><line class="l-line" x1="1" y1="17" x2="23" y2="17"/></svg>',
            'center':       '<svg viewBox="0 0 24 18"><line class="l-line" x1="1" y1="2" x2="23" y2="2"/><rect class="l-block" x="5" y="5" width="14" height="8" rx="1.5"/><line class="l-line" x1="1" y1="16" x2="23" y2="16"/></svg>',
            'right':        '<svg viewBox="0 0 24 18"><rect class="l-block" x="14" y="4" width="9" height="10" rx="1.5"/><line class="l-line" x1="1" y1="2" x2="11" y2="2"/><line class="l-line" x1="1" y1="5" x2="11" y2="5"/><line class="l-line" x1="1" y1="8" x2="11" y2="8"/><line class="l-line" x1="1" y1="11" x2="11" y2="11"/><line class="l-line" x1="1" y1="17" x2="23" y2="17"/></svg>',
            'background':   '<svg viewBox="0 0 24 18"><rect class="l-block" x="1" y="1" width="22" height="16" rx="1.5" opacity=".4"/><line class="l-line" x1="4" y1="5" x2="20" y2="5" style="stroke:#94a3b8"/><line class="l-line" x1="4" y1="8" x2="20" y2="8" style="stroke:#94a3b8"/><line class="l-line" x1="4" y1="11" x2="16" y2="11" style="stroke:#94a3b8"/></svg>',
            'left-bottom':  '<svg viewBox="0 0 24 18"><line class="l-line" x1="1" y1="2" x2="23" y2="2"/><line class="l-line" x1="1" y1="5" x2="23" y2="5"/><line class="l-line" x1="1" y1="8" x2="16" y2="8"/><rect class="l-block" x="1" y="10" width="9" height="7" rx="1.5"/><line class="l-line" x1="13" y1="12" x2="23" y2="12"/><line class="l-line" x1="13" y1="15" x2="23" y2="15"/></svg>',
            'bottom':       '<svg viewBox="0 0 24 18"><line class="l-line" x1="1" y1="2" x2="23" y2="2"/><line class="l-line" x1="1" y1="5" x2="23" y2="5"/><line class="l-line" x1="1" y1="8" x2="16" y2="8"/><rect class="l-block" x="3" y="10" width="18" height="7" rx="1.5"/></svg>',
            'right-bottom': '<svg viewBox="0 0 24 18"><line class="l-line" x1="1" y1="2" x2="23" y2="2"/><line class="l-line" x1="1" y1="5" x2="23" y2="5"/><line class="l-line" x1="1" y1="8" x2="16" y2="8"/><rect class="l-block" x="14" y="10" width="9" height="7" rx="1.5"/><line class="l-line" x1="1" y1="12" x2="11" y2="12"/><line class="l-line" x1="1" y1="15" x2="11" y2="15"/></svg>',
            'hidden':       '<svg viewBox="0 0 24 18"><line class="l-line" x1="1" y1="5" x2="23" y2="5"/><line class="l-line" x1="1" y1="9" x2="23" y2="9"/><line class="l-line" x1="1" y1="13" x2="16" y2="13"/></svg>',
        };
        return '<button class="img-layout-btn'+active+'" data-layout="'+layout+'" title="'+title+'" '
            + 'onclick="event.stopPropagation();setBlockImgLayout(\''+layout+'\','+blockId+')">'
            + (svgs[layout]||'') + '</button>';
    }

    function addArticleBlock() {
        if (!artId) { toast('Сначала сохраните статью', true); return; }
        showBlockTypeModal('article');
    }
    async function doAddArtBlock(type) {
        closeModal('blockTypeModal');
        try {
            await api('articles/blocks/'+artId, {method:'POST', body:{type, name:BLOCK_TYPES.find(b=>b.type===type)?.name||type, content:{}, sort_order:artBlocks.length, is_visible:1}});
            toast('Блок добавлен'); await loadArticleBlocks(artId);
        } catch(e) { toast(e.message, true); }
    }
    async function updateArtBlock(blockId, field, value) {
        if (!artId) return;
        const body = {block_id: blockId};
        if (field==='content') { try { body.content = JSON.parse(value); } catch(e) { toast('Невалидный JSON', true); return; } }
        else body[field] = value;
        try { await api('articles/blocks/'+artId, {method:'PUT', body}); } catch(e) { toast(e.message, true); }
    }
    async function toggleBlockVis(blockId, vis) {
        try { await api('articles/blocks/'+artId, {method:'PUT', body:{block_id:blockId, is_visible:vis}}); await loadArticleBlocks(artId); } catch(e) { toast(e.message, true); }
    }
    async function deleteArtBlock(blockId) {
        if (!confirm('Удалить блок?')) return;
        try { await api('articles/blocks/'+artId, {method:'DELETE', body:{block_id:blockId}}); toast('Блок удалён'); await loadArticleBlocks(artId); } catch(e) { toast(e.message, true); }
    }

    let genInProgress = false;

    function genLog(msg, cls) {
        const el = $('genLog');
        el.style.display = 'block';
        const time = new Date().toLocaleTimeString();
        el.innerHTML += '<div class="'+(cls||'log-info')+'">['+time+'] '+esc(msg)+'</div>';
        el.scrollTop = el.scrollHeight;
    }

    function genProgressSet(pct, text) {
        $('genProgress').classList.add('active');
        $('genProgressFill').style.width = pct+'%';
        if (text) $('genProgressText').textContent = text;
    }

    function genReset() {
        genInProgress = false;
        $('btnGenAll').disabled = false;
        $('btnGenMetaFull').disabled = false;
        $('btnGenPipeline').disabled = false;
        document.querySelectorAll('.btn-regen').forEach(b => b.disabled = false);
    }

    function genLock() {
        genInProgress = true;
        $('btnGenAll').disabled = true;
        $('btnGenMetaFull').disabled = true;
        $('btnGenPipeline').disabled = true;
        document.querySelectorAll('.btn-regen').forEach(b => b.disabled = true);
    }

    async function generateAllBlocks() {
        if (!artId) { toast('Сначала сохраните статью', true); return; }
        if (!$('artTemplate').value) { toast('Выберите шаблон перед генерацией', true); return; }
        if (genInProgress) return;

        await saveArticle();

        genLock();
        $('genLog').innerHTML = '';
        $('genLog').style.display = 'block';
        genProgressSet(0, 'Подключение к GPT...');
        genLog('Запуск генерации для статьи #'+artId, 'log-info');

        const model = $('genModel').value;
        const temp  = parseFloat($('genTemp').value);
        const overwrite = $('genOverwrite').checked;

        try {
            const response = await fetch(API+'?r=generate/'+artId+'/sse', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({model, temperature: temp, overwrite}),
            });

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const {value, done} = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, {stream:true});
                const lines = buffer.split('\n');
                buffer = lines.pop();

                let eventName = '';
                for (const line of lines) {
                    if (line.startsWith('event: ')) {
                        eventName = line.substring(7).trim();
                    } else if (line.startsWith('data: ') && eventName) {
                        try {
                            const data = JSON.parse(line.substring(6));
                            handleGenEvent(eventName, data);
                        } catch(e) {}
                        eventName = '';
                    }
                }
            }
        } catch(e) {
            genLog('Ошибка: '+e.message, 'log-err');
            toast('Ошибка генерации: '+e.message, true);
        }

        genReset();
        await selectArticle(artId);
    }

    function handleGenEvent(event, data) {
        switch (event) {
            case 'start':
                genLog('Генерация: '+data.total_blocks+' блоков, модель '+data.model, 'log-info');
                break;
            case 'block_start':
                genProgressSet(
                    Math.round((data.index / data.total) * 100),
                    'Блок '+(data.index+1)+'/'+data.total+': '+data.name+'...'
                );
                genLog('→ Генерирую ['+data.type+'] '+data.name+'...', 'log-info');
                break;
            case 'block_done':
                genLog('✓ ['+data.type+'] '+data.name+' — готово'
                    + (data.usage ? ' ('+data.usage.total_tokens+' tok)' : ''), 'log-ok');
                const el = document.getElementById('blockItem_'+data.block_id);
                if (el) {
                    el.style.borderColor = '#6ee7b7';
                    setTimeout(() => el.style.borderColor = '', 2000);
                }
                break;
            case 'block_error':
                genLog('✗ ['+data.type+'] '+data.name+' — ОШИБКА: '+data.error, 'log-err');
                break;
            case 'done':
                genProgressSet(100, 'Готово! '+data.total_blocks+' блоков, '+data.total_usage.total_tokens+' токенов');
                genLog('═══ Генерация завершена: '+data.total_blocks+' блоков, '
                    + data.total_usage.total_tokens+' токенов ═══', 'log-ok');
                toast('Генерация завершена: '+data.total_blocks+' блоков');
                break;
            case 'error':
                genLog('ОШИБКА: '+data.message, 'log-err');
                toast('Ошибка GPT: '+data.message, true);
                break;
        }
    }

    async function regenBlock(blockId, blockType) {
        if (!artId) return;
        if (genInProgress) { toast('Генерация уже запущена', true); return; }
        if (!confirm('Перегенерировать блок ['+blockType+']?\nТекущий контент будет заменён.')) return;

        genLock();
        const btn = document.querySelector('#blockItem_'+blockId+' .btn-regen');
        if (btn) btn.textContent = '...';

        $('genLog').innerHTML = '';
        $('genLog').style.display = 'block';
        genLog('Перегенерация блока #'+blockId+' ['+blockType+']...', 'log-info');

        try {
            const model = $('genModel').value;
            const temp  = parseFloat($('genTemp').value);

            const result = await api('generate/'+artId+'/block', {
                method: 'POST',
                body: {block_id: blockId, model, temperature: temp}
            });
            const d = result.data;

            genLog('✓ Блок перегенерирован, модель: '+d.model
                +', токены: '+d.usage.total_tokens, 'log-ok');
            toast('Блок перегенерирован');

            setBlockJsonValue(blockId, jsonPretty(d.content));
            // Обновляем состояние и форму
            try {
                const newContent = typeof d.content === 'object' ? d.content : JSON.parse(d.content);
                blockContents[blockId] = newContent;
                const block = artBlocks.find(function(b) { return b.id === blockId; });
                const formTab = document.getElementById('btab_form_'+blockId);
                if (formTab && block) {
                    formTab.innerHTML = renderBlockFormEditor(blockId, block.type, newContent);
                }
            } catch(e) {}

            const el = document.getElementById('blockItem_'+blockId);
            if (el) {
                el.style.borderColor = '#6ee7b7';
                setTimeout(() => el.style.borderColor = '', 2000);
            }

            await refreshGenLog();

        } catch(e) {
            genLog('✗ Ошибка: '+e.message, 'log-err');
            toast('Ошибка: '+e.message, true);
        }

        if (btn) btn.textContent = 'Gen';
        genReset();
    }

    async function generateMeta() {
        if (!artId) { toast('Сначала сохраните статью', true); return; }
        if (genInProgress) { toast('Генерация уже запущена', true); return; }

        genLock();
        $('genLog').innerHTML = '';
        $('genLog').style.display = 'block';
        genLog('Генерация мета-тегов...', 'log-info');

        try {
            const model = $('genModel').value;
            const temp  = parseFloat($('genTemp').value);

            const result = await api('generate/'+artId+'/meta', {
                method: 'POST',
                body: {model, temperature: temp}
            });
            const d = result.data;

            if (d.meta_title) $('artMetaTitle').value = d.meta_title;
            if (d.meta_description) $('artMetaDesc').value = d.meta_description;
            if (d.meta_keywords) $('artMetaKeywords').value = d.meta_keywords;
            if (d.article_plan) setArticlePlan(d.article_plan);

            genLog('✓ Мета-теги сгенерированы, модель: '+d.model
                +', токены: '+d.usage.total_tokens, 'log-ok');
            toast('Мета-теги сгенерированы');
            dirty = true;

        } catch(e) {
            genLog('✗ Ошибка: '+e.message, 'log-err');
            toast('Ошибка: '+e.message, true);
        }

        genReset();
    }

    /**
     * Полный пайплайн: сохранить → meta+plan → все блоки (SSE)
     * Стандартизированный порядок: сначала meta, потом контент
     */
    async function generateFullPipeline() {
        if (!artId) { toast('Сначала сохраните статью', true); return; }
        if (!$('artTemplate').value) { toast('Выберите шаблон перед генерацией', true); return; }
        if (genInProgress) return;

        await saveArticle();

        genLock();
        $('genLog').innerHTML = '';
        $('genLog').style.display = 'block';
        genProgressSet(0, 'Пайплайн: генерация Meta + Plan...');
        genLog('═══ Пайплайн: Meta → Блоки ═══', 'log-info');

        const model = $('genModel').value;
        const temp  = parseFloat($('genTemp').value);
        const overwrite = $('genOverwrite').checked;

        // ── Шаг 1: Meta + Article Plan ──
        genLog('→ Генерация мета-тегов и плана...', 'log-info');
        try {
            const metaResult = await api('generate/'+artId+'/meta', {
                method: 'POST',
                body: {model, temperature: temp}
            });
            const md = metaResult.data;

            if (md.meta_title) $('artMetaTitle').value = md.meta_title;
            if (md.meta_description) $('artMetaDesc').value = md.meta_description;
            if (md.meta_keywords) $('artMetaKeywords').value = md.meta_keywords;
            if (md.article_plan) setArticlePlan(md.article_plan);
            if (md.slug) $('artSlug').value = md.slug;

            genLog('✓ Meta готово ('+md.usage.total_tokens+' tok). План: '
                + (md.article_plan||'').substring(0,80)+'...', 'log-ok');
            genProgressSet(10, 'Meta готово. Генерация блоков...');

            // Сохраняем мета-данные перед генерацией блоков
            await saveArticle();
        } catch(e) {
            genLog('⚠ Meta ошибка: '+e.message+' — продолжаем без плана', 'log-warn');
        }

        // ── Шаг 2: Все блоки (SSE) ──
        genLog('→ Генерация блоков контента...', 'log-info');

        try {
            const response = await fetch(API+'?r=generate/'+artId+'/sse', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({model, temperature: temp, overwrite}),
            });

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const {value, done} = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, {stream:true});
                const lines = buffer.split('\n');
                buffer = lines.pop();

                let eventName = '';
                for (const line of lines) {
                    if (line.startsWith('event: ')) {
                        eventName = line.substring(7).trim();
                    } else if (line.startsWith('data: ') && eventName) {
                        try {
                            const data = JSON.parse(line.substring(6));
                            handleGenEvent(eventName, data);
                        } catch(e) {}
                        eventName = '';
                    }
                }
            }
        } catch(e) {
            genLog('Ошибка: '+e.message, 'log-err');
            toast('Ошибка генерации: '+e.message, true);
        }

        genLog('═══ Пайплайн завершён ═══', 'log-ok');
        genReset();
        await selectArticle(artId);
    }

    async function refreshGenLog() {
        try {
            const data = await api('articles/'+artId);
            const art = data.data;
            $('artGenLog').value = art.generation_log ? jsonPretty(art.generation_log) : '';
            setVersionBadge(art.version||1);
        } catch(e) {}
    }

    // ─── CATALOG TREE STATE ───────────────────────────────────────────
    let catTreeExpanded = {};      // {catId: true/false}
    let catArticlesByCat = {};     // {catId: [articles]}
    let catArticlesLoaded = {};    // {catId: true} — уже загружены

    async function loadCatalogsList() {
        try {
            const data = await api('catalogs/tree' + (currentProfileId ? '?profile_id=' + currentProfileId : ''));
            allCatalogs = data.data || [];
            allCatalogsFlat = flattenTree(allCatalogs);
            // Auto-expand roots on first load
            allCatalogs.forEach(c => { if (!(c.id in catTreeExpanded)) catTreeExpanded[c.id] = true; });
            await loadAllCatalogArticles();
            renderCatalogTree();
            refreshCatDropdowns();
        } catch(e) { toast(e.message, true); }
    }

    async function loadAllCatalogArticles() {
        try {
            const data = await api('articles?per_page=500' + (currentProfileId ? '&profile_id=' + currentProfileId : ''));
            const arts = data.data || [];
            catArticlesByCat = {};
            catArticlesByCat['__none__'] = [];
            arts.forEach(a => {
                const cid = a.catalog_id || '__none__';
                if (!catArticlesByCat[cid]) catArticlesByCat[cid] = [];
                catArticlesByCat[cid].push(a);
            });
        } catch(e) {}
    }

    function flattenTree(nodes, depth) {
        depth = depth||0; let r = [];
        for (const n of nodes) { r.push({...n, depth}); if (n.children?.length) r.push(...flattenTree(n.children, depth+1)); }
        return r;
    }

    function renderCatalogTree() {
        const search   = $('searchCatalog').value.toLowerCase().trim();
        const stFilter = $('filterCatStatus').value;
        const showArts = $('catShowArticles').checked;
        const container = $('catalogTree');

        let totalCats = 0, totalArts = 0;

        function buildNode(cat, depth) {
            const indent = depth * 18;
            const children = cat.children || [];
            const arts = (catArticlesByCat[cat.id] || []).filter(a => {
                if (stFilter && a.status !== stFilter) return false;
                if (search && !a.title.toLowerCase().includes(search) && !cat.name.toLowerCase().includes(search)) return false;
                return true;
            });
            const hasChildren = children.length > 0 || arts.length > 0;
            const isOpen = catTreeExpanded[cat.id] !== false;
            const isSel = activeEditor === 'catalog' && cat.id == catId;

            // Skip if search active and nothing matches
            if (search) {
                const catMatch = cat.name.toLowerCase().includes(search);
                const artMatch = arts.length > 0;
                const childMatch = children.some(ch => nodeHasMatch(ch, search, stFilter));
                if (!catMatch && !artMatch && !childMatch) return '';
            }

            totalCats++;
            totalArts += arts.length;

            const toggleIcon = !hasChildren ? '&nbsp;' : (isOpen ? '&#9660;' : '&#9654;');
            const toggleCls  = !hasChildren ? 'leaf' : (isOpen ? 'open' : '');
            const catIcon    = depth === 0 ? '📁' : '📂';
            const artCount   = (catArticlesByCat[cat.id]||[]).length;
            const artLabel   = artCount > 0 ? '<span style="color:#0891b2;font-size:.68rem">'+artCount+'</span>' : '';

            let html = '<div class="cat-node" id="catnode_'+cat.id+'">';
            html += '<div class="cat-row'+(isSel?' selected':'')+'" id="catrow_'+cat.id+'" style="padding-left:'+indent+'px">';
            html += '<span class="cat-toggle '+toggleCls+'" onclick="toggleCatNode('+cat.id+',event)" title="Развернуть/свернуть">'+toggleIcon+'</span>';
            html += '<span class="cat-icon">'+catIcon+'</span>';
            html += '<span class="cat-label" onclick="selectCatalog('+cat.id+')" title="/'+esc(cat.slug)+'">'+esc(cat.name)+'</span>';
            html += '<span class="cat-meta">'+artLabel+'</span>';
            html += '<div class="cat-actions">';
            html += '<button class="btn btn-xs btn-cyan" onclick="event.stopPropagation();newArticleInCatalog('+cat.id+')" title="Новая статья в этом каталоге">+ Статья</button>';
            html += '<button class="btn btn-xs btn-ghost" onclick="event.stopPropagation();newChildCatalog('+cat.id+')" title="Вложенный каталог">+ Подкат.</button>';
            html += '</div></div>';

            // Children (nested catalogs + articles)
            html += '<div class="cat-children'+(isOpen?' open':'')+'" id="catchildren_'+cat.id+'">';

            if (showArts) {
                arts.forEach(a => {
                    const isSA = activeEditor === 'article' && a.id == artId;
                    const stDot = '<span class="status-dot '+esc(a.status||'draft')+'" style="margin-top:0;width:6px;height:6px;flex-shrink:0"></span>';
                    const stTag = '<span class="tag status-'+esc(a.status)+'" style="font-size:.6rem;padding:1px 5px">'+esc(STATUS_LABELS[a.status]||a.status)+'</span>';
                    html += '<div class="cat-art-row'+(isSA?' selected':'')+'" style="padding-left:'+(indent+28)+'px" id="catart_'+a.id+'">';
                    html += stDot;
                    html += '<span class="cat-art-icon">📄</span>';
                    html += '<span class="cat-art-name" onclick="selectArticleFromTree('+a.id+')" title="'+esc(a.title)+'">'+esc(a.title)+'</span>';
                    html += '<span class="cat-art-status">'+stTag+'</span>';
                    html += '<div class="cat-art-actions">';
                    html += '<button class="btn btn-xs btn-ghost" onclick="event.stopPropagation();selectArticleFromTree('+a.id+')" title="Редактировать">&#9998;</button>';
                    html += '</div></div>';
                });
            }

            children.forEach(child => { html += buildNode(child, depth + 1); });

            if (showArts) {
                html += '<div class="cat-add-art" style="padding-left:'+(indent+28)+'px" onclick="newArticleInCatalog('+cat.id+')">'
                    + '<span style="color:#0891b2">+</span> новая статья</div>';
            }

            html += '</div></div>';
            return html;
        }

        // Build full tree
        let html = allCatalogs.map(c => buildNode(c, 0)).join('');

        // "Без каталога" section
        const noCatArts = (catArticlesByCat['__none__'] || []).filter(a => {
            if (stFilter && a.status !== stFilter) return false;
            if (search && !a.title.toLowerCase().includes(search)) return false;
            return true;
        });
        if (showArts && noCatArts.length > 0) {
            totalArts += noCatArts.length;
            html += '<div class="cat-node">';
            html += '<div class="cat-row" style="opacity:.6;padding-left:0">';
            html += '<span class="cat-toggle leaf">&nbsp;</span>';
            html += '<span class="cat-icon">📋</span>';
            html += '<span class="cat-label" style="color:#64748b;cursor:default">Без каталога</span>';
            html += '<span class="cat-meta"><span style="color:#475569;font-size:.68rem">'+noCatArts.length+'</span></span>';
            html += '</div><div class="cat-children open" style="background:#060d16">';
            noCatArts.forEach(a => {
                const isSA = activeEditor === 'article' && a.id == artId;
                const stDot = '<span class="status-dot '+esc(a.status||'draft')+'" style="margin-top:0;width:6px;height:6px;flex-shrink:0"></span>';
                const stTag = '<span class="tag status-'+esc(a.status)+'" style="font-size:.6rem;padding:1px 5px">'+esc(STATUS_LABELS[a.status]||a.status)+'</span>';
                html += '<div class="cat-art-row'+(isSA?' selected':'')+'" style="padding-left:28px" id="catart_'+a.id+'">';
                html += stDot;
                html += '<span class="cat-art-icon">📄</span>';
                html += '<span class="cat-art-name" onclick="selectArticleFromTree('+a.id+')" title="'+esc(a.title)+'">'+esc(a.title)+'</span>';
                html += '<span class="cat-art-status">'+stTag+'</span>';
                html += '<div class="cat-art-actions"><button class="btn btn-xs btn-ghost" onclick="event.stopPropagation();selectArticleFromTree('+a.id+')">&#9998;</button></div>';
                html += '</div>';
            });
            html += '</div></div>';
        }

        if (!html) {
            html = '<div style="padding:30px;text-align:center;color:#475569">Нет каталогов</div>';
        }
        container.innerHTML = html;
        $('catalogCount').textContent = totalCats + ' кат. · ' + totalArts + ' ст.';
    }

    function nodeHasMatch(cat, search, stFilter) {
        if (cat.name.toLowerCase().includes(search)) return true;
        const arts = catArticlesByCat[cat.id] || [];
        if (arts.some(a => {
            if (stFilter && a.status !== stFilter) return false;
            return a.title.toLowerCase().includes(search);
        })) return true;
        return (cat.children||[]).some(ch => nodeHasMatch(ch, search, stFilter));
    }

    function toggleCatNode(catId, e) {
        if (e) e.stopPropagation();
        catTreeExpanded[catId] = !catTreeExpanded[catId];
        const children = $('catchildren_'+catId);
        const row      = $('catrow_'+catId);
        if (children) children.classList.toggle('open', catTreeExpanded[catId]);
        if (row) {
            const toggle = row.querySelector('.cat-toggle');
            if (toggle && !toggle.classList.contains('leaf')) {
                toggle.innerHTML = catTreeExpanded[catId] ? '&#9660;' : '&#9654;';
                toggle.classList.toggle('open', catTreeExpanded[catId]);
            }
        }
    }

    function refreshCatDropdowns() {
        const items = allCatalogsFlat.map(c => ({value: c.id, label: '— '.repeat(c.depth) + c.name, depth: c.depth}));
        ssFilterCatalog.setItems(items);
        ssArtCatalog.setItems(items);
        ssCatParent.setItems(items);
        ssFilterCatalog.setValue(ssFilterCatalog.getValue(), true);
        ssArtCatalog.setValue(ssArtCatalog.getValue(), true);
        ssCatParent.setValue(ssCatParent.getValue(), true);
    }

    async function selectArticleFromTree(id) {
        switchTab('articles');
        await selectArticle(id);
        // Highlight in tree
        document.querySelectorAll('.cat-art-row').forEach(r => r.classList.remove('selected'));
        const el = $('catart_'+id);
        if (el) { el.classList.add('selected'); el.scrollIntoView({block:'nearest'}); }
    }

    async function selectCatalog(id) {
        if (dirty && !confirm('Несохранённые изменения. Продолжить?')) return;
        try {
            const data = await api('catalogs/'+id);
            const c = data.data;
            catId = c.id; activeEditor = 'catalog';
            showEditor('catalogEditor', 'Каталог #'+c.id, c.id);
            loading = true;
            $('catName').value = c.name||''; $('catSlug').value = c.slug||'';
            ssCatParent.setValue(c.parent_id||'', true); $('catSortOrder').value = c.sort_order||0;
            $('catDescription').value = c.description||''; $('catIsActive').value = c.is_active?'1':'0';
            loading = false;
            // Highlight selected row in tree
            document.querySelectorAll('.cat-row').forEach(r => r.classList.remove('selected'));
            const el = $('catrow_'+id);
            if (el) { el.classList.add('selected'); el.scrollIntoView({block:'nearest'}); }
        } catch(e) { toast(e.message, true); }
    }

    function newCatalog(parentId) {
        if (dirty && !confirm('Несохранённые изменения. Продолжить?')) return;
        catId = null; activeEditor = 'catalog';
        showEditor('catalogEditor', 'Новый каталог', null);
        loading = true;
        ['catName','catSlug','catDescription'].forEach(id => $(id).value='');
        ssCatParent.setValue(parentId ? String(parentId) : '', true);
        $('catSortOrder').value = 0; $('catIsActive').value = '1';
        loading = false;
        switchTab('catalogs'); $('catName').focus();
    }

    function newChildCatalog(parentId) { newCatalog(parentId); }

    function newArticleInCatalog(catId) {
        newArticle();
        ssArtCatalog.setValue(String(catId), true);
    }

    async function saveCatalog() {
        const body = {name:$('catName').value, slug:$('catSlug').value, parent_id:$('catParent').value||null,
            sort_order:parseInt($('catSortOrder').value)||0, description:$('catDescription').value, is_active:parseInt($('catIsActive').value)};
        try {
            if (catId) { await api('catalogs/'+catId, {method:'PUT', body}); toast('Каталог сохранён'); }
            else { const d = await api('catalogs', {method:'POST', body}); catId = d.data.id; toast('Каталог создан'); }
            dirty = false; $('editorId').textContent = 'Каталог #'+catId;
            $('btnDelete').style.display = 'inline-flex'; $('btnDuplicate').style.display = 'inline-flex';
            await loadCatalogsList();
        } catch(e) { toast(e.message, true); }
    }

    async function loadTemplatesList() {
        try {
            const data = await api('templates' + (currentProfileId ? '?profile_id=' + currentProfileId : ''));
            allTemplates = data.data || [];
            renderTemplateList(allTemplates);
            refreshTplDropdown();
        } catch(e) { toast(e.message, true); }
    }
    function renderTemplateList(rows) {
        const s = $('searchTemplate').value.toLowerCase();
        const f = s ? rows.filter(r => r.name.toLowerCase().includes(s)||r.slug.toLowerCase().includes(s)) : rows;
        $('templateCount').textContent = f.length + ' шаблонов';
        if (!f.length) { $('templateList').innerHTML = '<div style="padding:30px;text-align:center;color:#475569;font-size:.85rem">&#128196; Нет шаблонов<br><span style="font-size:.75rem;color:#334155;margin-top:6px;display:block">Создайте шаблон через раздел Профиля</span></div>'; return; }
        $('templateList').innerHTML = f.map(r =>
            '<div class="list-item '+(activeEditor==='template'&&r.id==tplId?'selected':'')+'" onclick="selectTemplate('+r.id+')">'
            +'<div class="list-item-body"><div class="list-item-name">'+esc(r.name)+'</div>'
            +'<div class="list-item-sub">'+esc(r.slug)+' &middot; '+(r.blocks_count||0)+' блоков</div>'
            +'<div class="list-item-meta"><span class="tag type">'+esc(r.css_class||'—')+'</span>'
            +'<span class="tag">'+(r.is_active?'Активен':'Неактивен')+'</span></div></div></div>'
        ).join('');
    }
    function refreshTplDropdown() {
        const items = allTemplates.map(t => ({value: t.id, label: t.name + ' (' + t.slug + ')'}));
        ssArtTemplate.setItems(items);
        ssArtTemplate.setValue(ssArtTemplate.getValue(), true);
    }
    async function selectTemplate(id) {
        if (dirty && !confirm('Несохранённые изменения. Продолжить?')) return;
        try {
            const data = await api('templates/'+id);
            const t = data.data;
            tplId = t.id; activeEditor = 'template';
            showEditor('templateEditor', 'Шаблон #'+t.id, t.id);
            loading = true;
            $('tplName').value = t.name||''; $('tplSlug').value = t.slug||'';
            $('tplCssClass').value = t.css_class||''; $('tplIsActive').value = t.is_active?'1':'0';
            $('tplDescription').value = t.description||''; $('tplGptPrompt').value = t.gpt_system_prompt||'';
            loading = false;
            tplBlocks = t.blocks || [];
            renderTplBlocks(tplBlocks);
            loadTemplatesList();
        } catch(e) { toast(e.message, true); }
    }
    function newTemplate() {
        if (dirty && !confirm('Несохранённые изменения. Продолжить?')) return;
        tplId = null; tplBlocks = []; activeEditor = 'template';
        showEditor('templateEditor', 'Новый шаблон', null);
        loading = true;
        ['tplName','tplSlug','tplCssClass','tplDescription','tplGptPrompt'].forEach(id => $(id).value='');
        $('tplIsActive').value = '1';
        loading = false; renderTplBlocks([]);
        switchTab('templates'); $('tplName').focus();
    }
    async function saveTemplate() {
        const body = {name:$('tplName').value, slug:$('tplSlug').value, css_class:$('tplCssClass').value,
            is_active:parseInt($('tplIsActive').value), description:$('tplDescription').value, gpt_system_prompt:$('tplGptPrompt').value,
            profile_id: currentProfileId || null};
        try {
            if (tplId) { await api('templates/'+tplId, {method:'PUT', body}); toast('Шаблон сохранён'); }
            else { const d = await api('templates', {method:'POST', body}); tplId = d.data.id; toast('Шаблон создан'); }
            dirty = false; $('editorId').textContent = 'Шаблон #'+tplId;
            $('btnDelete').style.display = 'inline-flex'; $('btnDuplicate').style.display = 'inline-flex';
            loadTemplatesList();
        } catch(e) { toast(e.message, true); }
    }

    let tplJsonEditors = {};
    let tplBlockConfigs = {};

    function renderTplBlocks(blocks) {
        tplJsonEditors = {};
        tplBlockConfigs = {};
        $('tplBlocksEmpty').style.display = blocks.length ? 'none' : 'block';
        if (!blocks.length) { $('tplBlocksList').innerHTML=''; return; }
        $('tplBlocksList').innerHTML = blocks.map(function(b) {
            let cfg = {};
            if (typeof b.config === 'object' && b.config) cfg = b.config;
            else if (typeof b.config === 'string' && b.config) { try { cfg = JSON.parse(b.config); } catch(e) {} }
            tplBlockConfigs[b.id] = cfg;
            return '<div class="block-item" data-block-id="'+b.id+'" draggable="true">'
                +'<div class="block-header" onclick="toggleBlock(this)">'
                +'<div class="block-header-left"><span class="drag-handle" onclick="event.stopPropagation()" title="Перетащить">&#8942;&#8942;</span>'
                +'<span class="block-type">'+esc(b.type)+'</span><span class="block-name">'+esc(b.name)+'</span>'
                +(b.is_required?'<span class="tag" style="border-color:#f59e0b;color:#fcd34d;font-size:.6rem">обяз.</span>':'')
                +'</div><div class="block-header-right">'
                +'<button class="btn btn-xs btn-red" onclick="event.stopPropagation();deleteTplBlock('+b.id+')" title="Удалить">&#10005;</button>'
                +'<span class="collapse-arrow">&#9660;</span>'
                +'</div></div><div class="block-body hidden">'
                +'<div class="form-group" style="margin-bottom:8px"><label>Название</label>'
                +'<input type="text" value="'+esc(b.name||'')+'" onchange="updateTplBlock('+b.id+',\'name\',this.value)"></div>'
                +'<div class="form-group" style="margin-bottom:8px"><label>Обязательный</label>'
                +'<select onchange="updateTplBlock('+b.id+',\'is_required\',parseInt(this.value))">'
                +'<option value="1" '+(b.is_required?'selected':'')+'>Да</option><option value="0" '+(!b.is_required?'selected':'')+'>Нет</option></select></div>'
                // Tabs: Form / JSON
                +'<div class="block-view-tabs">'
                +'<button class="bvt active" data-tab="form" onclick="event.stopPropagation();switchTplBlockTab('+b.id+',\'form\',this)">Форма</button>'
                +'<button class="bvt" data-tab="json" onclick="event.stopPropagation();switchTplBlockTab('+b.id+',\'json\',this)">JSON</button>'
                +'</div>'
                // Tab: Form
                +'<div id="tbtab_form_'+b.id+'" class="block-tab-content">'
                +'<div class="form-group" style="margin-bottom:8px"><label>Hint (подсказка для GPT)</label>'
                +'<textarea rows="3" onchange="updateTplBlockCfgField('+b.id+',\'hint\',this.value)">'+esc(cfg.hint||'')+'</textarea></div>'
                +'<div class="form-group" style="margin-bottom:8px"><label>Fields (поля)</label>'
                +'<textarea rows="2" onchange="updateTplBlockCfgField('+b.id+',\'fields\',this.value)" placeholder="Через запятую: title, text, items...">'+esc((cfg.fields||[]).join?.(', ')||'')+'</textarea></div>'
                +'</div>'
                // Tab: JSON
                +'<div id="tbtab_json_'+b.id+'" class="block-tab-content" style="display:none">'
                +'<div class="json-toolbar">'
                +'<button class="btn btn-xs btn-ghost" onclick="event.stopPropagation();formatTplBlockJson('+b.id+')">Format</button>'
                +'<button class="btn btn-xs btn-ghost" onclick="event.stopPropagation();copyTplBlockJson('+b.id+')">Copy</button>'
                +'</div>'
                +'<div id="tbc_'+b.id+'" class="block-cm-editor"></div>'
                +'</div>'
                +'</div></div>';
        }).join('');
        initDragSort('tplBlocksList', saveTplBlocksOrder);
    }

    function switchTplBlockTab(blockId, tab, btn) {
        ['form','json'].forEach(function(t) {
            var el = document.getElementById('tbtab_' + t + '_' + blockId);
            if (el) el.style.display = t === tab ? 'block' : 'none';
        });
        if (btn) {
            var wrap = btn.closest('.block-view-tabs');
            if (wrap) wrap.querySelectorAll('.bvt').forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
        }
        if (tab === 'json') initTplJsonEditor(blockId);
    }

    function initTplJsonEditor(blockId) {
        var el = document.getElementById('tbc_' + blockId);
        if (!el) return;
        if (tplJsonEditors[blockId]) { tplJsonEditors[blockId].refresh(); return; }
        var editor = CodeMirror(el, {
            value: jsonPretty(tplBlockConfigs[blockId] || {}),
            mode: { name: 'javascript', json: true },
            theme: 'dracula',
            lineNumbers: true,
            matchBrackets: true,
            autoCloseBrackets: true,
            tabSize: 2,
            indentWithTabs: false,
            lineWrapping: true
        });
        editor.on('blur', function() { saveTplBlockJson(blockId); });
        tplJsonEditors[blockId] = editor;
    }

    function saveTplBlockJson(blockId) {
        var ed = tplJsonEditors[blockId];
        if (!ed) return;
        var val = ed.getValue();
        try {
            var parsed = JSON.parse(val);
            tplBlockConfigs[blockId] = parsed;
            updateTplBlock(blockId, 'config', val);
        } catch(e) { /* invalid json, skip save */ }
    }

    function formatTplBlockJson(blockId) {
        var ed = tplJsonEditors[blockId];
        if (!ed) return;
        try { ed.setValue(JSON.stringify(JSON.parse(ed.getValue()), null, 2)); } catch(e) { toast('Невалидный JSON', true); }
    }

    function copyTplBlockJson(blockId) {
        var ed = tplJsonEditors[blockId];
        if (!ed) return;
        navigator.clipboard.writeText(ed.getValue()).then(function() { toast('JSON скопирован'); });
    }

    function updateTplBlockCfgField(blockId, field, value) {
        if (!tplBlockConfigs[blockId]) tplBlockConfigs[blockId] = {};
        if (field === 'fields') {
            tplBlockConfigs[blockId][field] = value.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
        } else {
            tplBlockConfigs[blockId][field] = value;
        }
        if (tplJsonEditors[blockId]) {
            tplJsonEditors[blockId].setValue(jsonPretty(tplBlockConfigs[blockId]));
        }
        updateTplBlock(blockId, 'config', JSON.stringify(tplBlockConfigs[blockId]));
    }
    function addTemplateBlock() {
        if (!tplId) { toast('Сначала сохраните шаблон', true); return; }
        showBlockTypeModal('template');
    }
    async function doAddTplBlock(type) {
        closeModal('blockTypeModal');
        try {
            await api('templates/blocks/'+tplId, {method:'POST', body:{type, name:BLOCK_TYPES.find(b=>b.type===type)?.name||type, config:{}, sort_order:tplBlocks.length, is_required:1}});
            toast('Блок добавлен');
            const d = await api('templates/'+tplId); tplBlocks = d.data.blocks||[]; renderTplBlocks(tplBlocks);
        } catch(e) { toast(e.message, true); }
    }
    async function updateTplBlock(blockId, field, value) {
        if (!tplId) return;
        const body = {block_id: blockId};
        if (field==='config') { try { body.config = JSON.parse(value); } catch(e) { toast('Невалидный JSON', true); return; } }
        else body[field] = value;
        try { await api('templates/blocks/'+tplId, {method:'PUT', body}); } catch(e) { toast(e.message, true); }
    }
    async function deleteTplBlock(blockId) {
        if (!confirm('Удалить блок шаблона?')) return;
        try { await api('templates/blocks/'+tplId, {method:'DELETE', body:{block_id:blockId}});
            toast('Блок удалён'); const d = await api('templates/'+tplId); tplBlocks=d.data.blocks||[]; renderTplBlocks(tplBlocks);
        } catch(e) { toast(e.message, true); }
    }

    async function loadLinksList() {
        try {
            let q = 'links?per_page=200';
            const scope = $('filterLinkScope').value;
            if (scope==='global') q += '&scope=global';
            if (scope==='local') q += '&scope=local';
            const data = await api(q);
            const rows = data.data||[];
            const s = $('searchLink').value.toLowerCase();
            const f = s ? rows.filter(r => r.key.toLowerCase().includes(s)||(r.url||'').toLowerCase().includes(s)) : rows;
            renderLinkList(f);
        } catch(e) { toast(e.message, true); }
    }
    function renderLinkList(rows) {
        $('linkCount').textContent = rows.length + ' ссылок';
        if (!rows.length) { $('linkList').innerHTML = '<div style="padding:30px;text-align:center;color:#475569;font-size:.85rem">&#128279; Нет ссылок</div>'; return; }
        $('linkList').innerHTML = rows.map(r =>
            '<div class="list-item '+(activeEditor==='link'&&r.id==lnkId?'selected':'')+'" onclick="selectLink('+r.id+')">'
            +'<div class="list-item-body"><div class="list-item-name">{{link:'+esc(r.key)+'}}</div>'
            +'<div class="list-item-sub">'+esc(r.url)+'</div>'
            +'<div class="list-item-meta"><span class="tag '+(r.article_id?'':'cat')+'">'+(r.article_id?'Статья #'+r.article_id:'Глобальная')+'</span>'
            +(r.nofollow?'<span class="tag" style="border-color:#ef4444;color:#fca5a5">nofollow</span>':'')
            +'</div></div></div>'
        ).join('');
    }
    async function selectLink(id) {
        if (dirty && !confirm('Несохранённые изменения. Продолжить?')) return;
        try {
            const data = await api('links/'+id); const l = data.data;
            lnkId = l.id; activeEditor = 'link';
            showEditor('linkEditor', 'Ссылка #'+l.id, l.id);
            loading = true;
            $('lnkKey').value = l.key||''; ssLnkArticle.setValue(l.article_id||'', true);
            $('lnkUrl').value = l.url||''; $('lnkLabel').value = l.label||'';
            $('lnkTarget').value = l.target||'_blank'; $('lnkNofollow').value = l.nofollow?'1':'0';
            $('lnkDescription').value = l.description||'';
            loading = false;
            loadLinksList();
        } catch(e) { toast(e.message, true); }
    }
    function newLink() {
        if (dirty && !confirm('Несохранённые изменения. Продолжить?')) return;
        lnkId = null; activeEditor = 'link';
        showEditor('linkEditor', 'Новая ссылка', null);
        loading = true;
        ['lnkKey','lnkUrl','lnkLabel','lnkDescription'].forEach(id => $(id).value='');
        ssLnkArticle.clear(); $('lnkTarget').value = '_blank'; $('lnkNofollow').value = '0';
        loading = false;
        switchTab('links'); $('lnkKey').focus();
    }
    async function saveLink() {
        const body = {key:$('lnkKey').value, article_id:$('lnkArticle').value||null, url:$('lnkUrl').value,
            label:$('lnkLabel').value, target:$('lnkTarget').value, nofollow:parseInt($('lnkNofollow').value), description:$('lnkDescription').value};
        try {
            if (lnkId) { await api('links/'+lnkId, {method:'PUT', body}); toast('Ссылка сохранена'); }
            else { const d = await api('links', {method:'POST', body}); lnkId = d.data.id; toast('Ссылка создана'); }
            dirty = false; $('editorId').textContent = 'Ссылка #'+lnkId;
            $('btnDelete').style.display = 'inline-flex'; $('btnDuplicate').style.display = 'inline-flex';
            loadLinksList();
        } catch(e) { toast(e.message, true); }
    }

    async function loadTargetsList() {
        try {
            const data = await api('publish-targets'); const rows = data.data||[];
            const s = $('searchTarget').value.toLowerCase();
            renderTargetList(s ? rows.filter(r => r.name.toLowerCase().includes(s)) : rows);
        } catch(e) { toast(e.message, true); }
    }
    function renderTargetList(rows) {
        $('targetCount').textContent = rows.length + ' хостов';
        if (!rows.length) { $('targetList').innerHTML = '<div style="padding:30px;text-align:center;color:#475569;font-size:.85rem">&#127760; Нет хостов для публикации</div>'; return; }
        $('targetList').innerHTML = rows.map(r =>
            '<div class="list-item '+(activeEditor==='target'&&r.id==tgtId?'selected':'')+'" onclick="selectTarget('+r.id+')">'
            +'<div class="list-item-body"><div class="list-item-name">'+esc(r.name)+'</div>'
            +'<div class="list-item-sub">'+esc(r.base_url)+'</div>'
            +'<div class="list-item-meta"><span class="tag type">'+esc(r.type)+'</span>'
            +'<span class="tag">'+(r.is_active?'Активен':'Неактивен')+'</span></div></div></div>'
        ).join('');
    }
    let tgtConfigEditor = null;
    let tgtConfigData = {};

    async function selectTarget(id) {
        if (dirty && !confirm('Несохранённые изменения. Продолжить?')) return;
        try {
            const data = await api('publish-targets/'+id); const t = data.data;
            tgtId = t.id; activeEditor = 'target';
            showEditor('targetEditor', 'Хост #'+t.id, t.id);
            loading = true;
            $('tgtName').value = t.name||''; $('tgtType').value = t.type||'hostia';
            $('tgtBaseUrl').value = t.base_url||''; $('tgtIsActive').value = t.is_active?'1':'0';
            tgtConfigData = (typeof t.config === 'object' && t.config) ? t.config : {};
            populateTgtConfigForm(tgtConfigData);
            updateTgtConfigFormVisibility();
            if (tgtConfigEditor) { tgtConfigEditor.setValue(jsonPretty(tgtConfigData)); }
            loading = false;
            switchTgtConfigTab('form', document.querySelector('#targetEditor .bvt[data-tab="form"]'));
            loadTargetsList();
        } catch(e) { toast(e.message, true); }
    }
    function newTarget() {
        if (dirty && !confirm('Несохранённые изменения. Продолжить?')) return;
        tgtId = null; activeEditor = 'target';
        showEditor('targetEditor', 'Новый хост', null);
        loading = true;
        ['tgtName','tgtBaseUrl'].forEach(id => $(id).value='');
        $('tgtType').value = 'hostia'; $('tgtIsActive').value = '1';
        tgtConfigData = {};
        populateTgtConfigForm({});
        updateTgtConfigFormVisibility();
        if (tgtConfigEditor) { tgtConfigEditor.setValue('{\n  \n}'); }
        loading = false;
        switchTgtConfigTab('form', document.querySelector('#targetEditor .bvt[data-tab="form"]'));
        switchTab('targets'); $('tgtName').focus();
    }
    async function saveTarget() {
        var config;
        if (tgtConfigEditor) {
            try { config = JSON.parse(tgtConfigEditor.getValue()); } catch(e) { toast('Config: невалидный JSON', true); return; }
        } else {
            config = tgtConfigData;
        }
        const body = {name:$('tgtName').value, type:$('tgtType').value, base_url:$('tgtBaseUrl').value,
            is_active:parseInt($('tgtIsActive').value), config};
        try {
            if (tgtId) { await api('publish-targets/'+tgtId, {method:'PUT', body}); toast('Хост сохранён'); }
            else { const d = await api('publish-targets', {method:'POST', body}); tgtId = d.data.id; toast('Хост создан'); }
            dirty = false; $('editorId').textContent = 'Хост #'+tgtId;
            $('btnDelete').style.display = 'inline-flex'; $('btnDuplicate').style.display = 'inline-flex';
            loadTargetsList();
        } catch(e) { toast(e.message, true); }
    }

    function switchTgtConfigTab(tab, btn) {
        ['Form','Json'].forEach(function(t) {
            var el = document.getElementById('tgtConfigTab' + t);
            if (el) el.style.display = t.toLowerCase() === tab ? 'block' : 'none';
        });
        if (btn) {
            var wrap = btn.closest('.block-view-tabs');
            if (wrap) wrap.querySelectorAll('.bvt').forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
        }
        if (tab === 'json') initTgtConfigEditor();
    }

    function initTgtConfigEditor() {
        var el = document.getElementById('tgtConfigCm');
        if (!el) return;
        if (tgtConfigEditor) { tgtConfigEditor.setValue(jsonPretty(tgtConfigData)); tgtConfigEditor.refresh(); return; }
        tgtConfigEditor = CodeMirror(el, {
            value: jsonPretty(tgtConfigData),
            mode: { name: 'javascript', json: true },
            theme: 'dracula',
            lineNumbers: true,
            matchBrackets: true,
            autoCloseBrackets: true,
            tabSize: 2,
            indentWithTabs: false,
            lineWrapping: true
        });
        tgtConfigEditor.on('blur', function() {
            try { tgtConfigData = JSON.parse(tgtConfigEditor.getValue()); populateTgtConfigForm(tgtConfigData); } catch(e) {}
        });
    }

    function formatTgtConfigJson() {
        if (!tgtConfigEditor) return;
        try { tgtConfigEditor.setValue(JSON.stringify(JSON.parse(tgtConfigEditor.getValue()), null, 2)); } catch(e) { toast('Невалидный JSON', true); }
    }
    function copyTgtConfigJson() {
        if (!tgtConfigEditor) return;
        navigator.clipboard.writeText(tgtConfigEditor.getValue()).then(function() { toast('JSON скопирован'); });
    }

    function updateTgtConfigFormVisibility() {
        var type = $('tgtType').value;
        $('tgtConfigFormHostia').style.display = (type === 'hostia') ? 'block' : 'none';
        $('tgtConfigFormFtp').style.display = (type === 'ftp' || type === 'ssh') ? 'block' : 'none';
        $('tgtConfigFormApi').style.display = (type === 'api') ? 'block' : 'none';
    }

    function populateTgtConfigForm(cfg) {
        $('tgtCfgPublishEndpoint').value = cfg.publish_endpoint || '';
        $('tgtCfgHost').value = cfg.host || '';
        $('tgtCfgPort').value = cfg.port || '';
        $('tgtCfgUsername').value = cfg.username || '';
        $('tgtCfgPassword').value = cfg.password || '';
        $('tgtCfgDocRoot').value = cfg.document_root || '';
        $('tgtCfgApiEndpoint').value = cfg.endpoint || '';
        $('tgtCfgApiKey').value = cfg.api_key || '';
    }

    function syncTgtFormToJson() {
        var type = $('tgtType').value;
        if (type === 'hostia') {
            var ep = $('tgtCfgPublishEndpoint').value.trim();
            if (ep) tgtConfigData.publish_endpoint = ep;
            else delete tgtConfigData.publish_endpoint;
        } else if (type === 'ftp' || type === 'ssh') {
            tgtConfigData.host = $('tgtCfgHost').value.trim();
            tgtConfigData.port = $('tgtCfgPort').value.trim() || (type === 'ftp' ? '21' : '22');
            tgtConfigData.username = $('tgtCfgUsername').value;
            tgtConfigData.password = $('tgtCfgPassword').value;
            tgtConfigData.document_root = $('tgtCfgDocRoot').value.trim();
        } else if (type === 'api') {
            tgtConfigData.endpoint = $('tgtCfgApiEndpoint').value.trim();
            tgtConfigData.api_key = $('tgtCfgApiKey').value;
        }
        if (tgtConfigEditor) { tgtConfigEditor.setValue(jsonPretty(tgtConfigData)); }
    }

    let imgPreviewId = null;
    let imgPickerCallback = null;

    async function loadArticleImages(articleId) {
        try {
            const data = await api('images?article_id='+articleId+'&per_page=200');
            artImages = data.data || [];
            renderImageGallery(artImages);
        } catch(e) { artImages = []; renderImageGallery([]); }
    }

    function renderImageGallery(images) {
        $('artImgEmpty').style.display = images.length ? 'none' : 'block';
        $('artImgCount').textContent = images.length ? images.length+' шт.' : '';
        if (!images.length) { $('artImgGallery').innerHTML = ''; return; }
        $('artImgGallery').innerHTML = images.map(img => {
            const src = API+'?r=images/'+img.id+'/raw';
            const sz = img.size_bytes > 1024*1024
                ? (img.size_bytes/1024/1024).toFixed(1)+'MB'
                : (img.size_bytes/1024).toFixed(0)+'KB';
            const dim = (img.width && img.height) ? img.width+'×'+img.height : '';
            return '<div class="img-card" data-img-id="'+img.id+'" onclick="openImgPreview('+img.id+')">'
                +'<div class="img-card-actions">'
                +'<button onclick="event.stopPropagation();copyImgId('+img.id+')" title="Копировать ID">📋</button>'
                +'<button onclick="event.stopPropagation();deleteImg('+img.id+')" title="Удалить">🗑</button>'
                +'</div>'
                +'<span class="img-card-badge '+esc(img.source)+'">'+esc(img.source)+'</span>'
                +'<img class="img-card-thumb" src="'+src+'" alt="'+esc(img.alt_text||'')+'" loading="lazy">'
                +'<div class="img-card-info"><div class="img-card-name">'+esc(img.name||'image_'+img.id)+'</div>'
                +'<div class="img-card-meta">'+sz+(dim?' · '+dim:'')+'</div></div></div>';
        }).join('');
    }

    function showUploadZone() {
        const z = $('artImgUploadZone');
        z.style.display = z.style.display === 'none' ? 'flex' : 'none';
    }

    function initImageUpload() {
        const zone = $('artImgUploadZone');
        const input = $('artImgFileInput');
        if (!zone || !input) return;

        zone.addEventListener('click', e => { if (e.target.tagName !== 'BUTTON') input.click(); });
        input.addEventListener('change', () => { if (input.files.length) uploadFiles(input.files); input.value=''; });

        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
        zone.addEventListener('drop', e => {
            e.preventDefault(); zone.classList.remove('dragover');
            if (e.dataTransfer.files.length) uploadFiles(e.dataTransfer.files);
        });
    }

    async function uploadFiles(fileList) {
        if (!artId) { toast('Сначала сохраните статью', true); return; }
        const files = Array.from(fileList).filter(f => f.type.startsWith('image/'));
        if (!files.length) { toast('Нет файлов-изображений', true); return; }

        let ok = 0, fail = 0;
        for (const file of files) {
            try {
                const fd = new FormData();
                fd.append('file', file);
                fd.append('article_id', artId);
                fd.append('name', file.name.replace(/\.[^.]+$/, ''));
                fd.append('source', 'uploaded');

                const resp = await fetch(API+'?r=images/upload', {method:'POST', body:fd});
                const data = await resp.json();
                if (!resp.ok) throw new Error(data.error||'Upload failed');
                ok++;
            } catch(e) { fail++; console.error('Upload failed:', file.name, e); }
        }

        toast(ok+' загружено'+(fail?' ('+fail+' ошибок)':''));
        await loadArticleImages(artId);
    }

    function openImgPreview(imgId) {
        imgPreviewId = imgId;
        const img = artImages.find(i => i.id === imgId);
        if (!img) return;

        const src = API+'?r=images/'+imgId+'/raw';
        $('imgPreviewFull').src = src;

        const dim = (img.width && img.height) ? img.width+'×'+img.height : '?';
        const sz = img.size_bytes > 1024*1024
            ? (img.size_bytes/1024/1024).toFixed(1)+' MB'
            : (img.size_bytes/1024).toFixed(0)+' KB';
        $('imgPreviewMeta').textContent = '#'+img.id+' · '+dim+' · '+sz+' · '+img.mime_type+' · '+(img.source||'');

        $('imgEditName').value = img.name||'';
        $('imgEditAlt').value = img.alt_text||'';
        setImgEditLayout(img.layout || 'center', true);
        $('imgPreviewOverlay').classList.add('show');
    }

    function closeImgPreview(e) {
        if (e && e.target !== $('imgPreviewOverlay') && e.target.tagName !== 'BUTTON') return;
        $('imgPreviewOverlay').classList.remove('show');
        imgPreviewId = null;
    }

    async function saveImgMeta() {
        if (!imgPreviewId) return;
        try {
            await api('images/'+imgPreviewId, {method:'PUT', body:{
                    name: $('imgEditName').value,
                    alt_text: $('imgEditAlt').value
                }});
            toast('Метаданные сохранены');
            if (artId) {
                await loadArticleImages(artId);
                await loadArticleBlocks(artId);
            }
        } catch(e) { toast(e.message, true); }
    }

    async function deleteImgFromPreview() {
        if (!imgPreviewId) return;
        if (!confirm('Удалить изображение #'+imgPreviewId+'?')) return;
        try {
            await api('images/'+imgPreviewId, {method:'DELETE'});
            closeImgPreview();
            toast('Изображение удалено');
            if (artId) await loadArticleImages(artId);
        } catch(e) { toast(e.message, true); }
    }

    /* setImgEditLayout removed — layout is managed at block level only */

    async function setBlockImgLayout(layout, blockId) {
        try {
            /* Read current block content, set image_layout, save back */
            const block = artBlocks.find(b => b.id === blockId);
            if (!block) { toast('Блок не найден', true); return; }
            let content = {};
            if (typeof block.content === 'object' && block.content) content = block.content;
            else if (typeof block.content === 'string' && block.content) { try { content = JSON.parse(block.content); } catch(e) {} }
            content.image_layout = layout;
            await api('articles/blocks/'+artId, {method:'PUT', body:{ block_id: blockId, content: content }});
            block.content = content;
            toast('Layout → '+layout);
            const container = document.querySelector('#blockImgLayout_'+blockId);
            if (container) {
                container.querySelectorAll('.img-layout-btn').forEach(btn => {
                    btn.classList.toggle('active', btn.dataset.layout === layout);
                });
            }
            blockContents[blockId] = content;
            setBlockJsonValue(blockId, jsonPretty(content));
        } catch(e) { toast(e.message, true); }
    }

    async function deleteImg(imgId) {
        if (!confirm('Удалить изображение #'+imgId+'?')) return;
        try {
            await api('images/'+imgId, {method:'DELETE'});
            toast('Изображение удалено');
            if (artId) await loadArticleImages(artId);
        } catch(e) { toast(e.message, true); }
    }

    function copyImgId(imgId) {
        navigator.clipboard.writeText(String(imgId)).then(() => toast('ID '+imgId+' скопирован'));
    }

    function openImgPicker(callback) {
        imgPickerCallback = callback;
        const gallery = $('imgPickerGallery');
        const empty = $('imgPickerEmpty');

        if (!artImages.length) {
            gallery.innerHTML = '';
            empty.style.display = 'block';
        } else {
            empty.style.display = 'none';
            gallery.innerHTML = artImages.map(img => {
                const src = API+'?r=images/'+img.id+'/raw';
                return '<div class="img-card" style="cursor:pointer" onclick="pickImage('+img.id+')">'
                    +'<img class="img-card-thumb" src="'+src+'" alt="'+esc(img.alt_text||'')+'" loading="lazy">'
                    +'<div class="img-card-info"><div class="img-card-name">'+esc(img.name||'#'+img.id)+'</div></div></div>';
            }).join('');
        }
        $('imgPickerModal').classList.add('show');
    }

    function pickImage(imgId) {
        closeModal('imgPickerModal');
        if (imgPickerCallback) {
            imgPickerCallback(imgId);
            imgPickerCallback = null;
        }
    }

    function pickImageForBlock(blockId) {
        openImgPicker(async (imgId) => {
            if (!blockContents[blockId]) blockContents[blockId] = {};
            blockContents[blockId].image_id = imgId;
            setBlockJsonValue(blockId, jsonPretty(blockContents[blockId]));
            await updateArtBlock(blockId, 'content', JSON.stringify(blockContents[blockId]));
            toast('Изображение #'+imgId+' привязано к блоку');
            if (artId) await loadArticleBlocks(artId);
        });
    }

    async function unlinkImageFromBlock(blockId) {
        if (!blockContents[blockId]) return;
        delete blockContents[blockId].image_id;
        setBlockJsonValue(blockId, jsonPretty(blockContents[blockId]));
        await updateArtBlock(blockId, 'content', JSON.stringify(blockContents[blockId]));
        toast('Изображение снято с блока');
        if (artId) await loadArticleBlocks(artId);
    }

    let imgGenInProgress = false;

    function getImgGenParams() {
        return {
            model: $('imgGenModel') ? $('imgGenModel').value : 'dall-e-3',
            size:  $('imgGenSize')  ? $('imgGenSize').value  : '1024x1024',
        };
    }

    async function generateManualImage() {
        if (!artId) { toast('Сначала сохраните статью', true); return; }
        if (imgGenInProgress) { toast('Генерация уже идёт', true); return; }

        const prompt = $('imgManualPrompt').value.trim();
        if (!prompt) { toast('Введите промпт для генерации', true); return; }

        const status = $('imgGenStatus');
        status.style.display = 'block';
        status.innerHTML = '⏳ Генерация изображения по вашему промпту...';
        imgGenInProgress = true;
        $('btnGenManual').disabled = true;

        try {
            const {model, size} = getImgGenParams();
            const body = {article_id: artId, custom_prompt: prompt, model, size};

            const result = await api('images/generate', {method:'POST', body});
            const d = result.data;

            status.innerHTML = '✅ Изображение #'+d.image_id+' создано ('
                + (d.size_bytes/1024).toFixed(0)+'KB)'
                + '<br><span style="font-size:.7rem;color:#a78bfa">Промпт: '+esc(d.prompt?.substring(0,200)||'')+'</span>';

            toast('Изображение сгенерировано');
            await loadArticleImages(artId);
        } catch(e) {
            status.innerHTML = '❌ Ошибка: '+esc(e.message);
            toast('Ошибка генерации: '+e.message, true);
        }
        imgGenInProgress = false;
        $('btnGenManual').disabled = false;
    }

    async function generateBlockImage(blockId) {
        if (!artId) { toast('Сначала сохраните статью', true); return; }
        if (imgGenInProgress) { toast('Генерация уже идёт', true); return; }

        const promptTa = $('bp_'+blockId);
        const blockPrompt = promptTa ? promptTa.value.trim() : '';
        const manualPrompt = $('imgManualPrompt') ? $('imgManualPrompt').value.trim() : '';
        // Manual prompt field overrides block prompt when filled
        const customPrompt = manualPrompt || blockPrompt;

        const status = $('imgGenStatus');
        status.style.display = 'block';
        status.innerHTML = '⏳ Генерация изображения для блока #'+blockId
            + (manualPrompt ? ' (ручной промпт)' : blockPrompt ? ' (промпт блока)' : ' (авто-промпт)') + '...';
        imgGenInProgress = true;

        try {
            const {model, size} = getImgGenParams();
            const body = {article_id: artId, block_id: blockId, model, size};
            if (customPrompt) body.custom_prompt = customPrompt;

            const result = await api('images/generate', {method:'POST', body});
            const d = result.data;

            status.innerHTML = '✅ Изображение #'+d.image_id+' создано ('
                + (d.size_bytes/1024).toFixed(0)+'KB)'
                + '<br><span style="font-size:.7rem;color:#a78bfa">Промпт: '+esc(d.prompt?.substring(0,200)||'')+'</span>';

            toast('Изображение сгенерировано');
            await loadArticleBlocks(artId);
            await loadArticleImages(artId);
        } catch(e) {
            status.innerHTML = '❌ Ошибка: '+esc(e.message);
            toast('Ошибка генерации: '+e.message, true);
        }
        imgGenInProgress = false;
    }

    async function generateAllImages() {
        if (!artId) { toast('Сначала сохраните статью', true); return; }
        if (imgGenInProgress) { toast('Генерация уже идёт', true); return; }

        if (!artBlocks.length) { toast('Нет блоков для генерации изображений', true); return; }

        const overwrite = confirm('Перезаписать существующие изображения?\n\nОК = перезаписать\nОтмена = только пустые');

        const status = $('imgGenStatus');
        status.style.display = 'block';
        status.innerHTML = '⏳ Генерация изображений для блоков... Это может занять 1-2 минуты.';
        imgGenInProgress = true;
        $('btnGenAllImg').disabled = true;

        try {
            const {model, size} = getImgGenParams();
            const result = await api('images/generate-all', {
                method: 'POST',
                body: {article_id: artId, overwrite, model, size}
            });
            const d = result.data;

            let html = '✅ Готово: '+d.generated+' создано, '+d.skipped+' пропущено';
            if (d.results) {
                for (const r of d.results) {
                    if (r.error) {
                        html += '<br><span style="color:#fca5a5">❌ Блок #'+r.block_id+': '+esc(r.error)+'</span>';
                    } else {
                        html += '<br><span style="color:#6ee7b7">✅ '
                            +esc(r.block_name||r.block_type||'')+' → #'+r.image_id
                            +' ('+(r.size_bytes/1024).toFixed(0)+'KB)</span>';
                    }
                }
            }
            status.innerHTML = html;
            toast('Изображения сгенерированы: '+d.generated);
            await loadArticleBlocks(artId);
            await loadArticleImages(artId);
        } catch(e) {
            status.innerHTML = '❌ Ошибка: '+esc(e.message);
            toast('Ошибка: '+e.message, true);
        }
        imgGenInProgress = false;
        $('btnGenAllImg').disabled = false;
    }

    async function loadArticlesCache() {
        try {
            const data = await api('articles?per_page=500' + (currentProfileId ? '&profile_id=' + currentProfileId : ''));
            allArticlesCache = (data.data||[]).map(a => ({value: a.id, label: a.title + ' (#'+a.id+')'}));
            ssLnkArticle.setItems(allArticlesCache);
        } catch(e) {}
    }

    async function loadPublishTargets() {
        try {
            const data = await api('publish-targets');
            allTargetsCache = (data.data||[]).filter(t => t.is_active);
            const items = allTargetsCache.map(t => ({value: t.id, label: t.name + ' ('+t.type+')'}));
            ssPubTarget.setItems(items);
            if (items.length === 1) ssPubTarget.setValue(items[0].value, true);
        } catch(e) {}
    }

    async function publishArticle() {
        if (!artId) { toast('Сначала сохраните статью', true); return; }
        const targetId = $('pubTarget').value;
        if (!targetId) { toast('Выберите целевой хост', true); return; }

        if (!confirm('Опубликовать статью на выбранный хост?')) return;

        $('btnPublish').disabled = true;
        $('pubResult').style.display = 'block';
        $('pubResult').innerHTML = '<span style="color:#fbbf24">Публикация...</span>';

        try {
            const result = await api('publish/'+artId, {
                method: 'POST',
                body: {target_id: parseInt(targetId)}
            });
            const d = result.data;

            $('pubResult').innerHTML = '<span style="color:#6ee7b7">Опубликовано!</span>'
                + '<br>URL: <a href="'+esc(d.published_url)+'" target="_blank" style="color:#22d3ee">'+esc(d.published_url)+'</a>'
                + '<br>Хост: '+esc(d.target_name||'')
                + '<br>Размер: '+(d.html_size/1024).toFixed(1)+' KB'
                + '<br>Время: '+esc(d.published_at);

            setPublishedUrl(d.published_url);
            $('artStatus').value = 'published';
            $('btnUnpublish').style.display = 'inline-flex';
            toast('Статья опубликована');
            dirty = false;
            loadArticlesList();
            await loadAllCatalogArticles();
            renderCatalogTree();
        } catch(e) {
            $('pubResult').innerHTML = '<span style="color:#fca5a5">✗ Ошибка: '+esc(e.message)+'</span>';
            toast('Ошибка публикации: '+e.message, true);
        }
        $('btnPublish').disabled = false;
    }

    async function previewArticle() {
        if (!artId) { toast('Сначала сохраните статью', true); return; }

        const frame = $('previewFrame');
        frame.style.display = 'block';
        frame.srcdoc = '<div style="text-align:center;padding:40px;color:#64748b">Загрузка предпросмотра...</div>';

        try {
            const response = await fetch(API+'?r=publish/'+artId+'/preview', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
            });
            if (!response.ok) {
                const errData = await response.json().catch(()=>null);
                throw new Error(errData?.error || 'HTTP '+response.status);
            }
            const html = await response.text();
            frame.srcdoc = html;
            toast('Предпросмотр загружен');
        } catch(e) {
            frame.srcdoc = '<div style="text-align:center;padding:40px;color:#ef4444">Ошибка: '+e.message+'</div>';
            toast('Ошибка: '+e.message, true);
        }
    }

    async function unpublishArticle() {
        if (!artId) return;
        if (!confirm('Снять статью с публикации?')) return;

        try {
            await api('publish/'+artId+'/unpublish', {method:'POST', body:{}});
            $('artStatus').value = 'unpublished';
            $('btnUnpublish').style.display = 'none';
            $('pubResult').style.display = 'block';
            $('pubResult').innerHTML = '<span style="color:#fbbf24">Статья снята с публикации</span>';
            toast('Статья снята с публикации');
            dirty = false;
            loadArticlesList();
            await loadAllCatalogArticles();
            renderCatalogTree();
        } catch(e) {
            toast('Ошибка: '+e.message, true);
        }
    }

    // ═══════════════════ TELEGRAM ═══════════════════

    let currentTgPostId = null;
    let currentTgPostData = null;

    function updateTgPanelVisibility(art) {
        const hasTgExport = !!art.tg_export;
        const isPublished = art.status === 'published';
        $('tgPostPanel').style.display = hasTgExport ? '' : 'none';
        $('btnTgBuild').disabled = !isPublished;
        if (hasTgExport) {
            loadTgPostHistory(art.id || artId);
        }
    }

    async function buildTgPreview() {
        if (!artId) return;
        $('tgBuildStatus').style.display = 'block';
        $('btnTgBuild').disabled = true;
        $('tgPreviewContainer').style.display = 'none';
        $('tgImagePresets').style.display = 'none';

        try {
            const res = await api('telegram/' + artId + '/build-preview', { method: 'POST', body: {} });
            if (!res.success) { toast(res.error || 'Ошибка', true); return; }

            currentTgPostId = res.data.id;
            currentTgPostData = res.data;

            renderTgPreview(res.data);
            renderTgImagePresets(res.data);
            $('btnTgSend').style.display = 'inline-flex';
            $('btnTgScheduleShow').style.display = 'inline-flex';

            loadTgPostHistory(artId);
            toast('Пост подготовлен');
        } catch(e) {
            toast('Ошибка: ' + e.message, true);
        } finally {
            $('tgBuildStatus').style.display = 'none';
            $('btnTgBuild').disabled = false;
        }
    }

    function renderTgPreview(postData) {
        const container = $('tgPreview');
        const pd = postData.post_data || {};
        const messages = pd.messages || [];
        const profile = currentProfile || {};

        let html = '';
        messages.forEach(function(msg, idx) {
            html += '<div class="tg-msg">';

            // Header with avatar (only on first message)
            if (idx === 0) {
                const channelName = profile.tg_channel_name || profile.name || 'Канал';
                const avatar = profile.tg_channel_avatar;
                html += '<div class="tg-msg-header">';
                if (avatar) {
                    html += '<div class="tg-msg-avatar"><img src="data:image/jpeg;base64,' + avatar + '"></div>';
                } else {
                    html += '<div class="tg-msg-avatar">' + esc(channelName[0]) + '</div>';
                }
                html += '<div class="tg-msg-channel-name">' + esc(channelName) + '</div>';
                html += '</div>';
            }

            // Media
            if (msg.type === 'media_group' || msg.type === 'photo') {
                var imgIds = msg.rendered_image_ids || (msg.rendered_image_id ? [msg.rendered_image_id] : []);
                if (imgIds.length > 0) {
                    var gridClass = 'tg-msg-media-grid count-' + Math.min(imgIds.length, 3);
                    html += '<div class="tg-msg-media"><div class="' + gridClass + '">';
                    imgIds.forEach(function(imgId) {
                        html += '<img src="' + API + '?r=telegram/rendered-image/' + imgId + '" loading="lazy">';
                    });
                    html += '</div></div>';
                }
            }

            // Caption/text
            var text = msg.caption || msg.text || '';
            if (text) {
                html += '<div class="tg-msg-caption">' + text + '</div>';
            }

            // Footer
            html += '<div class="tg-msg-footer">';
            html += '<span class="tg-msg-footer-views">1</span>';
            var now = new Date();
            html += '<span>' + now.getHours() + ':' + (now.getMinutes() < 10 ? '0' : '') + now.getMinutes() + '</span>';
            html += '</div>';

            html += '</div>';
        });

        container.innerHTML = html;
        $('tgPreviewContainer').style.display = '';
    }

    function renderTgImagePresets(postData) {
        const images = postData.rendered_images || [];
        if (images.length === 0) { $('tgImagePresets').style.display = 'none'; return; }

        $('tgImageGrid').innerHTML = images.map(function(img) {
            return '<div style="background:#1e293b;border-radius:8px;overflow:hidden;border:1px solid #334155">'
                + '<img src="' + API + '?r=telegram/rendered-image/' + img.id + '" style="width:100%;display:block" loading="lazy">'
                + '<div style="padding:4px 8px;font-size:.7rem;color:#64748b">' + esc(img.block_type) + '</div>'
                + '</div>';
        }).join('');
        $('tgImagePresets').style.display = '';
    }

    async function sendTgNow() {
        if (!currentTgPostId) { toast('Сначала подготовьте пост', true); return; }
        if (!confirm('Отправить пост в Telegram сейчас?')) return;

        try {
            const res = await api('telegram/' + artId + '/send', {
                method: 'POST',
                body: { post_id: currentTgPostId }
            });
            if (res.success) {
                const url = res.data.tg_post_url;
                $('tgPostResult').style.display = 'block';
                $('tgPostResult').innerHTML = '<div style="padding:10px;background:#022c22;border:1px solid #065f46;border-radius:6px;color:#6ee7b7;font-size:.82rem">'
                    + 'Пост отправлен!' + (url ? ' <a href="' + esc(url) + '" target="_blank" style="color:#34d399">Открыть в Telegram</a>' : '')
                    + '</div>';
                $('btnTgSend').style.display = 'none';
                $('btnTgScheduleShow').style.display = 'none';
                loadTgPostHistory(artId);
                toast('Пост отправлен в Telegram');
            } else {
                toast(res.error || 'Ошибка отправки', true);
            }
        } catch(e) { toast('Ошибка: ' + e.message, true); }
    }

    function showTgSchedule() {
        var row = $('tgScheduleRow');
        row.style.display = row.style.display === 'none' ? 'flex' : 'none';
    }

    async function scheduleTgPost() {
        if (!currentTgPostId) { toast('Сначала подготовьте пост', true); return; }
        var dt = $('tgScheduleAt').value;
        if (!dt) { toast('Выберите дату и время', true); return; }

        try {
            const res = await api('telegram/' + artId + '/schedule', {
                method: 'POST',
                body: { post_id: currentTgPostId, scheduled_at: dt.replace('T', ' ') + ':00' }
            });
            if (res.success) {
                $('tgPostResult').style.display = 'block';
                $('tgPostResult').innerHTML = '<div style="padding:10px;background:#1e1b4b;border:1px solid #4338ca;border-radius:6px;color:#a5b4fc;font-size:.82rem">'
                    + 'Пост запланирован на ' + esc(dt.replace('T', ' '))
                    + '</div>';
                $('tgScheduleRow').style.display = 'none';
                $('btnTgSend').style.display = 'none';
                $('btnTgScheduleShow').style.display = 'none';
                loadTgPostHistory(artId);
                toast('Пост запланирован');
            } else {
                toast(res.error || 'Ошибка', true);
            }
        } catch(e) { toast('Ошибка: ' + e.message, true); }
    }

    async function loadTgPostHistory(articleId) {
        try {
            const res = await api('telegram/' + articleId + '/posts');
            if (!res.success) return;
            var posts = res.data || [];
            if (!posts.length) {
                $('tgHistoryList').innerHTML = '<div style="color:#475569;font-size:.78rem;padding:8px 0">Нет постов</div>';
                return;
            }
            var statusLabel = { draft: 'Черновик', scheduled: 'Запланирован', sending: 'Отправляется...', sent: 'Отправлен', failed: 'Ошибка' };
            var statusColor = { draft: '#94a3b8', scheduled: '#a5b4fc', sending: '#fbbf24', sent: '#6ee7b7', failed: '#fca5a5' };
            $('tgHistoryList').innerHTML = posts.map(function(p) {
                var st = statusLabel[p.status] || p.status;
                var cl = statusColor[p.status] || '#94a3b8';
                var url = p.tg_post_url ? ' <a href="' + esc(p.tg_post_url) + '" target="_blank" style="color:#6ab2f2;font-size:.72rem">открыть</a>' : '';
                var sched = p.scheduled_at ? ' &middot; ' + esc(p.scheduled_at) : '';
                var err = p.error_message ? '<div style="color:#fca5a5;font-size:.7rem;margin-top:2px">' + esc(p.error_message) + '</div>' : '';
                var del = (p.status === 'draft' || p.status === 'scheduled' || p.status === 'failed')
                    ? ' <button onclick="deleteTgPost(' + p.id + ')" style="background:none;border:none;color:#f87171;cursor:pointer;font-size:.72rem;padding:0 4px">удалить</button>'
                    : '';
                return '<div style="padding:6px 0;border-bottom:1px solid #1e293b;font-size:.78rem">'
                    + '<span style="color:' + cl + ';font-weight:600">' + st + '</span>'
                    + ' &middot; ' + esc(p.post_format) + sched + url + del
                    + '<div style="color:#475569;font-size:.7rem;margin-top:2px">' + esc(p.created_at || '') + '</div>'
                    + err
                    + '</div>';
            }).join('');
        } catch(e) {}
    }

    async function deleteTgPost(postId) {
        if (!confirm('Удалить пост?')) return;
        try {
            await api('telegram/post/' + postId, { method: 'DELETE' });
            loadTgPostHistory(artId);
            toast('Пост удален');
        } catch(e) { toast(e.message, true); }
    }

    async function loadAuditList() {
        try {
            let q = 'audit-log?per_page=50';
            const ent = $('filterAuditEntity').value, act = $('filterAuditAction').value;
            if (ent) q += '&entity_type='+ent;
            if (act) q += '&action='+act;
            const data = await api(q);
            renderAuditList(data.data || []);
        } catch(e) { toast(e.message, true); }
    }
    function renderAuditList(rows) {
        $('auditCount').textContent = rows.length + ' записей';
        if (!rows.length) { $('auditList').innerHTML = '<div style="padding:30px;text-align:center;color:#475569;font-size:.85rem">&#128203; Нет записей в логе</div>'; return; }
        const actionIcon = {create:'&#43;', update:'&#9998;', delete:'&#10005;', publish:'&#9654;', unpublish:'&#9646;', generate:'&#10024;'};
        const actionColor = {create:'#6ee7b7', update:'#93c5fd', delete:'#fca5a5', publish:'#86efac', unpublish:'#fcd34d', generate:'#c4b5fd'};
        $('auditList').innerHTML = rows.map(r => {
            const icon = actionIcon[r.action] || '&#8226;';
            const color = actionColor[r.action] || '#94a3b8';
            return '<div class="list-item '+(activeEditor==='audit'&&r.id==auditId?'selected':'')+'" onclick="selectAudit('+r.id+')">'
                +'<div style="width:22px;height:22px;border-radius:4px;background:rgba(0,0,0,.3);border:1px solid '+color+';display:flex;align-items:center;justify-content:center;font-size:.65rem;color:'+color+';flex-shrink:0;margin-top:2px">'+icon+'</div>'
                +'<div class="list-item-body"><div class="list-item-name" style="color:'+color+'">'+esc(r.action)+'</div>'
                +'<div class="list-item-sub">'+esc(r.entity_type)+' #'+r.entity_id+' &middot; '+esc(r.actor||'system')+'</div>'
                +'<div class="list-item-sub" title="'+esc(fmtDate(r.created_at))+'">'+fmtRelative(r.created_at)+'</div>'
                +'</div></div>';
        }).join('');
    }
    async function selectAudit(id) {
        try {
            const data = await api('audit-log/'+id); const l = data.data;
            auditId = l.id; activeEditor = 'audit';
            hideAllEditors(); $('auditViewer').style.display = 'block';
            $('editorId').textContent = 'Лог #'+l.id; $('editorStatus').innerHTML = '';
            $('btnDelete').style.display = 'none'; $('btnDuplicate').style.display = 'none'; $('btnSave').style.display = 'none';
            $('auditEntity').value = l.entity_type||''; $('auditEntityId').value = l.entity_id||'';
            $('auditAction').value = l.action||''; $('auditActor').value = l.actor||'';
            $('auditDate').value = fmtDate(l.created_at); $('auditDetails').value = jsonPretty(l.details);
            loadAuditList();
        } catch(e) { toast(e.message, true); }
    }

    function saveCurrentEditor() {
        const fn = {article:saveArticle, catalog:saveCatalog, template:saveTemplate, link:saveLink, target:saveTarget};
        if (fn[activeEditor]) fn[activeEditor]();
    }

    function confirmDelete() {
        const map = {article:['статью',artId,$('artTitle')?.value], catalog:['каталог',catId,$('catName')?.value],
            template:['шаблон',tplId,$('tplName')?.value], link:['ссылку',lnkId,$('lnkKey')?.value], target:['хост',tgtId,$('tgtName')?.value]};
        const m = map[activeEditor]; if (!m||!m[1]) return;
        $('deleteTitle').textContent = 'Удалить '+m[0]+'?';
        $('deleteMsg').textContent = 'Удалить '+m[0]+' «'+m[2]+'»? Это необратимо.';
        $('deleteModal').classList.add('show');
    }

    async function doDelete() {
        closeModal('deleteModal');
        const map = {article:['articles/',artId,()=>{artId=null;loadArticlesList();}],
            catalog:['catalogs/',catId,()=>{catId=null;loadCatalogsList();}],
            template:['templates/',tplId,()=>{tplId=null;loadTemplatesList();}],
            link:['links/',lnkId,()=>{lnkId=null;loadLinksList();}],
            target:['publish-targets/',tgtId,()=>{tgtId=null;loadTargetsList();}]};
        const m = map[activeEditor]; if (!m) return;
        try {
            await api(m[0]+m[1], {method:'DELETE'});
            dirty=false; showEmptyState(); toast('Удалено'); m[2]();
            await loadAllCatalogArticles();
            renderCatalogTree();
        } catch(e) { toast(e.message, true); }
    }

    function duplicateCurrent() { toast('Создайте новый элемент и скопируйте данные', true); }

    function showBlockTypeModal(ctx) {
        let availableTypes;
        if (ctx === 'article') {
            if (artTemplateTplBlocks.length > 0) {
                availableTypes = artTemplateTplBlocks.map(b => ({
                    type: b.type,
                    name: b.name || (BLOCK_TYPES.find(bt => bt.type === b.type)?.name || b.type)
                }));
            } else {availableTypes = BLOCK_TYPES;
            }
        } else {
            availableTypes = BLOCK_TYPES;
        }
        $('blockTypeList').innerHTML = availableTypes.map(b =>
            '<button class="btn btn-ghost" style="text-align:left;justify-content:flex-start;display:flex;gap:10px" '
            + 'onclick="' + (ctx === 'article' ? 'doAddArtBlock' : 'doAddTplBlock') + "('" + b.type + "')\">"
            + '<span class="block-type" style="min-width:120px">' + b.type + '</span>'
            + '<span style="color:#94a3b8">' + esc(b.name) + '</span></button>'
        ).join('');

        $('blockTypeModal').classList.add('show');
    }

    function initDragSort(listId, onReorder) {
        const list = $(listId);
        if (!list) return;
        let dragSrc = null;

        list.querySelectorAll('.block-item').forEach(item => {
            item.addEventListener('dragstart', e => {
                dragSrc = item;
                item.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });
            item.addEventListener('dragend', () => {
                item.classList.remove('dragging');
                list.querySelectorAll('.block-item').forEach(i => i.classList.remove('drag-over'));
                onReorder();
            });
            item.addEventListener('dragover', e => {
                e.preventDefault();
                if (item === dragSrc) return;
                list.querySelectorAll('.block-item').forEach(i => i.classList.remove('drag-over'));
                item.classList.add('drag-over');
                const rect = item.getBoundingClientRect();
                const mid = rect.top + rect.height / 2;
                if (e.clientY < mid) list.insertBefore(dragSrc, item);
                else list.insertBefore(dragSrc, item.nextSibling);
            });
            item.addEventListener('dragleave', () => item.classList.remove('drag-over'));
            item.addEventListener('drop', e => { e.preventDefault(); item.classList.remove('drag-over'); });
        });
    }

    async function saveArtBlocksOrder() {
        if (!artId) return;
        const items = Array.from($('artBlocksList').querySelectorAll('.block-item'));
        if (!items.length) return;
        try {
            for (let idx = 0; idx < items.length; idx++) {
                const blockId = parseInt(items[idx].dataset.blockId);
                await api('articles/blocks/'+artId, {method:'PUT', body:{block_id: blockId, sort_order: idx}});
            }
        } catch(e) { toast('Ошибка сохранения порядка: '+e.message, true); }
    }

    async function saveTplBlocksOrder() {
        if (!tplId) return;
        const items = Array.from($('tplBlocksList').querySelectorAll('.block-item'));
        if (!items.length) return;
        try {
            for (let idx = 0; idx < items.length; idx++) {
                const blockId = parseInt(items[idx].dataset.blockId);
                await api('templates/blocks/'+tplId, {method:'PUT', body:{block_id: blockId, sort_order: idx}});
            }
        } catch(e) { toast('Ошибка сохранения порядка: '+e.message, true); }
    }

    function $(id) { return document.getElementById(id); }
    function esc(s) { if(s===null||s===undefined)return''; const d=document.createElement('div'); d.textContent=String(s); return d.innerHTML; }
    function fmtDate(d) { if(!d)return''; return new Date(d).toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}); }
    function fmtRelative(d) {
        if (!d) return '';
        const diff = Math.floor((Date.now() - new Date(d)) / 1000);
        if (diff < 60) return 'только что';
        if (diff < 3600) return Math.floor(diff/60) + ' мин. назад';
        if (diff < 86400) return Math.floor(diff/3600) + ' ч. назад';
        if (diff < 86400*7) return Math.floor(diff/86400) + ' дн. назад';
        return fmtDate(d);
    }
    function closeModal(id) { $(id).classList.remove('show'); }
    function jsonPretty(val) {
        if (!val) return '';
        if (typeof val==='string') { try { return JSON.stringify(JSON.parse(val),null,2); } catch(e) { return val; } }
        try { return JSON.stringify(val,null,2); } catch(e) { return String(val); }
    }
    function validateJsonField(fieldId, statusId) {
        const v = $(fieldId).value.trim(), s = $(statusId);
        if (!v) { s.textContent=''; $(fieldId).classList.remove('error'); return true; }
        try { JSON.parse(v); s.textContent='\u2713 Valid JSON'; s.className='json-status valid'; $(fieldId).classList.remove('error'); return true; }
        catch(e) { s.textContent='\u2717 '+e.message; s.className='json-status invalid'; $(fieldId).classList.add('error'); return false; }
    }
    function validateJsonInline(ta) {
        const v = ta.value.trim();
        if (!v||v==='{}'||v==='[]') { ta.classList.remove('error'); return; }
        try { JSON.parse(v); ta.classList.remove('error'); } catch(e) { ta.classList.add('error'); }
    }
    function toggleBlock(h) { h.classList.toggle('collapsed'); h.nextElementSibling.classList.toggle('hidden'); }
    function toast(msg, err) {
        const t=$('toast'); t.textContent=msg; t.className='toast'+(err?' error':'');
        setTimeout(()=>t.classList.add('show'),10); setTimeout(()=>t.classList.remove('show'),3000);
    }
    async function deleteAllBlocks() {
        if (!artId) { toast('Сначала сохраните статью', true); return; }
        if (!artBlocks.length) { toast('Нет блоков для удаления', true); return; }
        if (!confirm('Удалить все блоки статьи (' + artBlocks.length + ' шт.)? Это действие нельзя отменить.')) return;
        try {
            await api('articles/'+artId+'/clear-blocks', {method:'DELETE'});
            toast('Все блоки удалены');
            await loadArticleBlocks(artId);
        } catch(e) { toast(e.message, true); }
    }
</script>
</body>
</html>