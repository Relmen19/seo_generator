<?php
require_once __DIR__ . '/../auth.php';
requireAuth();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO — Семантическое ядро</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; }
        .topbar { background: #1e293b; border-bottom: 1px solid #334155; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; }
        .topbar h1 { font-size: 1.1rem; color: #f1f5f9; }
        .topbar nav { display: flex; gap: 8px; align-items: center; }
        .topbar nav a { color: #94a3b8; text-decoration: none; padding: 6px 14px; border-radius: 6px; font-size: .85rem; transition: .2s; }
        .topbar nav a:hover { background: #334155; color: #e2e8f0; }
        .topbar nav a.active { background: #6366f1; color: #fff; }
        .btn-logout { color: #f87171 !important; }
        /* profile-selector removed — profile is set via workspace */

        .page { display: flex; height: calc(100vh - 53px); }
        .sidebar { width: 360px; border-right: 1px solid #334155; display: flex; flex-direction: column; flex-shrink: 0; background: #1e293b; }
        .main-panel { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }
        .main-scroll { flex: 1; overflow-y: auto; padding: 24px; }

        .sb-section { padding: 14px; border-bottom: 1px solid #334155; }
        .sb-section h3 { font-size: .75rem; text-transform: uppercase; letter-spacing: .5px; color: #64748b; margin-bottom: 10px; }
        .sb-actions { display: flex; gap: 6px; flex-wrap: wrap; }

        input[type="text"], input[type="number"], textarea, select { width: 100%; padding: 8px 10px; background: #0f172a; border: 1px solid #334155; border-radius: 6px; color: #e2e8f0; font-size: .85rem; outline: none; transition: border .2s; }
        input:focus, textarea:focus, select:focus { border-color: #6366f1; }
        textarea { resize: vertical; min-height: 60px; font-family: inherit; }
        .form-row { margin-bottom: 10px; }
        .form-row label { display: block; font-size: .75rem; text-transform: uppercase; letter-spacing: .4px; color: #64748b; margin-bottom: 4px; }

        .btn { padding: 7px 16px; border: none; border-radius: 6px; font-size: .8rem; font-weight: 600; cursor: pointer; transition: .15s; display: inline-flex; align-items: center; gap: 5px; }
        .btn-primary { background: #6366f1; color: #fff; } .btn-primary:hover { background: #818cf8; }
        .btn-success { background: #059669; color: #fff; } .btn-success:hover { background: #10b981; }
        .btn-warn { background: #d97706; color: #fff; } .btn-warn:hover { background: #f59e0b; }
        .btn-danger { background: #dc2626; color: #fff; } .btn-danger:hover { background: #ef4444; }
        .btn-ghost { background: transparent; color: #94a3b8; border: 1px solid #334155; } .btn-ghost:hover { background: #334155; color: #e2e8f0; }
        .btn-sm { padding: 4px 10px; font-size: .72rem; }
        .btn:disabled { opacity: .4; cursor: not-allowed; }

        .job-list { flex: 1; overflow-y: auto; }
        .job-item { padding: 12px 14px; border-bottom: 1px solid rgba(51,65,85,.5); cursor: pointer; transition: .15s; }
        .job-item:hover { background: rgba(99,102,241,.08); }
        .job-item.active { background: rgba(99,102,241,.15); border-left: 3px solid #6366f1; }
        .job-title { font-weight: 600; font-size: .9rem; color: #f1f5f9; margin-bottom: 3px; }
        .job-meta { font-size: .72rem; color: #64748b; display: flex; gap: 12px; flex-wrap: wrap; }

        .badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 100px; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
        .badge-pending { background: #1e3a5f; color: #60a5fa; }
        .badge-collecting { background: #422006; color: #fbbf24; }
        .badge-clustering { background: #3b0764; color: #c084fc; }
        .badge-done { background: #052e16; color: #4ade80; }
        .badge-error { background: #450a0a; color: #fca5a5; }
        .badge-new { background: #1e3a5f; color: #60a5fa; }
        .badge-approved { background: #052e16; color: #4ade80; }
        .badge-rejected { background: #450a0a; color: #fca5a5; }
        .badge-article_created { background: #134e4a; color: #5eead4; }
        /* Intent badge colors — динамически генерируются из seo_intent_types, fallback */
        .badge-intent { font-size: .7rem; }
        .badge-info { background: #1e3a5f; color: #60a5fa; }
        .badge-symptom_check { background: #500724; color: #f472b6; }
        .badge-diagnosis_interpret { background: #2e1065; color: #a78bfa; }
        .badge-action_plan { background: #052e16; color: #34d399; }
        .badge-risk_assessment { background: #431407; color: #fb923c; }
        .badge-life_context { background: #042f2e; color: #2dd4bf; }
        .badge-doctor_prep { background: #422006; color: #fbbf24; }
        .badge-comparison { background: #3b0764; color: #c084fc; }
        .badge-transactional { background: #052e16; color: #4ade80; }
        .badge-navigational { background: #1e293b; color: #94a3b8; }
        .badge-myth_debunk { background: #450a0a; color: #f87171; }

        .intent-tooltip {
            position: relative;
            cursor: help;
        }
        .intent-tooltip::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: calc(100% + 6px);
            left: 50%;
            transform: translateX(-50%);
            background: #1e293b;
            border: 1px solid #475569;
            color: #e2e8f0;
            font-size: .72rem;
            font-weight: 400;
            padding: 6px 10px;
            border-radius: 6px;
            white-space: nowrap;
            max-width: 300px;
            white-space: normal;
            pointer-events: none;
            opacity: 0;
            transition: opacity .15s;
            z-index: 100;
            line-height: 1.4;
        }
        .intent-tooltip:hover::after { opacity: 1; }

        .card { background: #1e293b; border: 1px solid #334155; border-radius: 10px; margin-bottom: 16px; overflow: hidden; }
        .card-header { padding: 14px 18px; border-bottom: 1px solid #334155; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .card-header h2 { font-size: .95rem; color: #f1f5f9; }
        .card-header .card-actions { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
        .card-body { padding: 16px 18px; }
        .card-body-np { padding: 0; }

        /* ── Cluster toolbar ── */
        .cluster-toolbar {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 16px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        .toolbar-group {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .toolbar-label {
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: #475569;
            white-space: nowrap;
        }
        .toolbar-sep { width: 1px; height: 20px; background: #334155; flex-shrink: 0; }
        .fbtn {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: .75rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid #334155;
            background: transparent;
            color: #64748b;
            transition: .15s;
            white-space: nowrap;
        }
        .fbtn:hover { background: #334155; color: #e2e8f0; }
        .fbtn.active { background: #6366f1; color: #fff; border-color: #6366f1; }
        .fbtn.active-green { background: #059669; color: #fff; border-color: #059669; }
        .fbtn.active-red { background: #dc2626; color: #fff; border-color: #dc2626; }
        .fbtn.active-teal { background: #0d9488; color: #fff; border-color: #0d9488; }
        .cluster-summary {
            margin-left: auto;
            font-size: .75rem;
            color: #475569;
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            align-items: center;
        }
        .cluster-summary span b { color: #94a3b8; }

        /* ── Cluster group section ── */
        .cluster-group { margin-bottom: 24px; }
        .cluster-group-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #334155;
        }
        .cluster-group-title {
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #64748b;
        }
        .cluster-group-count {
            font-size: .68rem;
            background: #334155;
            color: #94a3b8;
            padding: 1px 7px;
            border-radius: 100px;
        }

        /* ── Cluster grid / list ── */
        .cluster-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 8px; }
        .cluster-list { display: flex; flex-direction: column; gap: 2px; }

        /* ── Cluster card (grid) ── */
        .cluster-card {
            background: #111827;
            border: 1px solid #1e293b;
            border-left: 3px solid #1e293b;
            border-radius: 8px;
            padding: 18px 20px 14px;
            transition: background .15s, border-left-color .15s;
            position: relative;
            cursor: default;
        }
        .cluster-card:hover { background: #161f30; border-left-color: #4f5aff; }
        .cluster-card.active { background: #161f30; border-left-color: #6366f1; outline: 1px solid #2d3580; }
        .cluster-card.status-approved  { border-left-color: #059669; }
        .cluster-card.status-approved.active { border-left-color: #059669; outline-color: #134d33; }
        .cluster-card.status-rejected  { border-left-color: #7f1d1d; opacity: .55; }
        .cluster-card.status-article_created { border-left-color: #0d9488; }
        .cluster-card.status-article_created.active { border-left-color: #0d9488; outline-color: #134e4a; }

        /* Name — dominant */
        .cc-name {
            font-size: 1.18rem;
            font-weight: 600;
            color: #e2e8f0;
            line-height: 1.4;
            margin-bottom: 10px;
        }

        /* Meta row: intent label · stats · status dot */
        .cc-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: .9rem;
            color: #4a5d75;
            flex-wrap: wrap;
        }
        .cc-meta-sep { color: #2d3f55; }
        .cc-meta-intent { color: #5a7a9a; }
        .cc-meta-stat { font-variant-numeric: tabular-nums; color: #4a5d75; }
        .cc-meta-stat b { color: #6a8eaa; font-weight: 600; }
        .cc-status-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            flex-shrink: 0;
            background: #2d3f55;
        }
        .cc-status-dot.s-approved { background: #10b981; }
        .cc-status-dot.s-article_created { background: #14b8a6; }
        .cc-status-dot.s-rejected { background: #ef4444; opacity: .6; }

        /* Actions — hidden by default, appear on hover */
        .cc-actions {
            display: flex;
            gap: 3px;
            margin-top: 9px;
            opacity: 0.3;
            transition: opacity .15s;
        }
        .cluster-card:hover .cc-actions { opacity: 1; }

        /* Ghost icon buttons — no colors */
        .btn-icon {
            padding: 5px 10px;
            border: 1px solid #1e293b;
            border-radius: 5px;
            background: transparent;
            color: #475569;
            font-size: .8rem;
            cursor: pointer;
            transition: background .12s, color .12s, border-color .12s;
            white-space: nowrap;
        }
        .btn-icon:hover { background: #1e293b; color: #94a3b8; border-color: #334155; }
        .btn-icon.ok:hover  { color: #4ade80; border-color: #166534; }
        .btn-icon.del:hover { color: #f87171; border-color: #7f1d1d; }
        .btn-icon.art { color: #6366f1; border-color: #312e81; }
        .btn-icon.art:hover { background: #1e1b4b; color: #818cf8; }

        /* ── Cluster row (list view) ── */
        .cluster-row {
            background: transparent;
            border-bottom: 1px solid #0f172a;
            border-left: 3px solid transparent;
            padding: 13px 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            cursor: pointer;
            transition: background .12s, border-left-color .12s;
        }
        .cluster-row:hover  { background: #111827; border-left-color: #4f5aff; }
        .cluster-row.active { background: #131e35; border-left-color: #6366f1; }
        .cluster-row.status-approved  { border-left-color: #059669; }
        .cluster-row.status-rejected  { border-left-color: #7f1d1d; opacity: .55; }
        .cluster-row.status-article_created { border-left-color: #0d9488; }
        .cluster-row.active.status-approved { border-left-color: #059669; }

        .cr-name { flex: 1; font-size: 1rem; font-weight: 500; color: #94a3b8; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .cluster-row:hover .cr-name, .cluster-row.active .cr-name { color: #f1f5f9; }
        .cr-intent { font-size: .8rem; color: #3d5570; flex-shrink: 0; max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .cr-vol  { font-size: .82rem; color: #3d5570; font-variant-numeric: tabular-nums; flex-shrink: 0; min-width: 56px; text-align: right; }
        .cr-count { font-size: .82rem; color: #3d5570; font-variant-numeric: tabular-nums; flex-shrink: 0; min-width: 36px; text-align: right; }
        .cluster-row:hover .cr-vol, .cluster-row.active .cr-vol,
        .cluster-row:hover .cr-count, .cluster-row.active .cr-count,
        .cluster-row:hover .cr-intent, .cluster-row.active .cr-intent { color: #64748b; }

        /* ── Cluster detail panel (list mode) ── */
        .cluster-detail-panel {
            width: 380px;
            flex-shrink: 0;
            border-left: 1px solid #1e293b;
            background: #0d1525;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        .cdp-header {
            padding: 16px 18px 12px;
            border-bottom: 1px solid #1e293b;
        }
        .cdp-name { font-size: 1.2rem; font-weight: 600; color: #e2e8f0; line-height: 1.4; margin-bottom: 10px; }
        .cdp-meta { display: flex; flex-direction: column; gap: 6px; }
        .cdp-meta-row { display: flex; justify-content: space-between; align-items: center; font-size: 1rem; }
        .cdp-meta-label { color: #475569; }
        .cdp-meta-value { color: #94a3b8; font-weight: 500; font-size: .9rem; }
        .cdp-body { padding: 16px 18px; display: flex; flex-direction: column; gap: 12px; flex: 1; }
        .cdp-angle { font-size: .95rem; color: #94a3b8; line-height: 1.6; padding: 14px 16px; background: #111827; border-radius: 6px; border: 1px solid #1e293b; white-space: pre-wrap; word-break: break-word; }
        .cdp-actions { display: flex; flex-direction: column; gap: 7px; }
        .cdp-actions .btn { width: 100%; justify-content: center; font-size: 1rem; padding: 9px 16px; }
        .cdp-empty { padding: 40px 20px; text-align: center; color: #2d3f55; font-size: 1rem; }

        /* ── Detail panel action buttons — soft colors ── */
        .btn-soft { padding: 9px 16px; border: none; border-radius: 7px; font-size: .9rem; font-weight: 600; cursor: pointer; transition: .15s; display: inline-flex; align-items: center; justify-content: center; gap: 6px; width: 100%; }
        .btn-soft-approve  { background: #0d2e1e; color: #6ee7b7; border: 1px solid #134d33; }
        .btn-soft-approve:hover  { background: #134d33; color: #a7f3d0; }
        .btn-soft-reject   { background: #1c1c2e; color: #94a3b8; border: 1px solid #2d3050; }
        .btn-soft-reject:hover   { background: #2d3050; color: #cbd5e1; }
        .btn-soft-article  { background: #1a1b3a; color: #a5b4fc; border: 1px solid #2d2f6a; }
        .btn-soft-article:hover  { background: #2d2f6a; color: #c7d2fe; }
        .btn-soft-keywords { background: #0f1e2e; color: #7dd3fc; border: 1px solid #1a3a52; }
        .btn-soft-keywords:hover { background: #1a3a52; color: #bae6fd; }
        .btn-soft-danger   { background: #1c1010; color: #fca5a5; border: 1px solid #3d1515; }
        .btn-soft-danger:hover   { background: #3d1515; color: #fecaca; }
        .btn-soft-unlink   { background: #1c1c1c; color: #6b7280; border: 1px solid #2d2d2d; }
        .btn-soft-unlink:hover   { background: #2d2d2d; color: #9ca3af; }
        .pri-pill {
            display: inline-flex; align-items: center; justify-content: center;
            width: 20px; height: 20px; border-radius: 50%;
            font-size: .6rem; font-weight: 800; flex-shrink: 0;
            background: #1e293b; color: #334155;
        }
        .pri-mid { background: #172033; color: #334155; }
        .pri-high { background: #1c1a10; color: #44390a; }
        .pri-top  { background: #1c1410; color: #3d2a08; }

        /* ── Sort select in toolbar ── */
        .toolbar-select {
            padding: 4px 8px !important;
            font-size: .75rem !important;
            width: auto !important;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 6px;
            color: #94a3b8;
        }

        /* ── View toggle ── */
        .view-toggle { display: flex; gap: 2px; }
        .vtbtn {
            padding: 4px 8px;
            border: 1px solid #334155;
            background: transparent;
            color: #475569;
            cursor: pointer;
            font-size: .8rem;
            transition: .15s;
        }
        .vtbtn:first-child { border-radius: 6px 0 0 6px; }
        .vtbtn:last-child { border-radius: 0 6px 6px 0; margin-left: -1px; }
        .vtbtn.active { background: #334155; color: #e2e8f0; }

        /* ── Keywords table ── */
        .kw-table { width: 100%; border-collapse: collapse; font-size: 1.05rem; }
        .kw-table th { text-align: left; padding: 14px 18px; font-size: .82rem; text-transform: uppercase; letter-spacing: .4px; color: #475569; border-bottom: 2px solid #1e2d45; background: #0d1a2e; position: sticky; top: 0; z-index: 1; }
        .kw-table td { padding: 11px 18px; border-bottom: 1px solid rgba(30,45,69,.8); color: #94a3b8; vertical-align: middle; }
        .kw-table td:first-child { color: #e2e8f0; font-size: 1.05rem; }
        .kw-table tr:hover td { background: rgba(99,102,241,.06); }
        .kw-table tr:hover td:first-child { color: #f1f5f9; }
        .kw-volume { font-weight: 600; color: #34d399; font-variant-numeric: tabular-nums; }
        .kw-cluster-tag { font-size: .75rem; padding: 3px 9px; border-radius: 100px; background: #1a2d45; color: #60a5fa; border: 1px solid #1e3a5f; }
        .kw-editable { background: transparent; border: 1px solid transparent; color: #a5b4fc; padding: 3px 7px; border-radius: 4px; width: 88px; font-size: 1rem; text-align: right; font-variant-numeric: tabular-nums; }
        .kw-editable:hover { border-color: #334155; }
        .kw-editable:focus { border-color: #6366f1; background: #0d1a2e; outline: none; }

        .progress-bar { height: 4px; background: #334155; border-radius: 100px; overflow: hidden; margin: 8px 0; }
        .progress-fill { height: 100%; background: #6366f1; border-radius: 100px; transition: width .4s; width: 0%; }
        .log-box { background: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 10px 14px; font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace; font-size: .75rem; max-height: 200px; overflow-y: auto; line-height: 1.8; display: none; }
        .log-box.show { display: block; }
        .log-info { color: #94a3b8; } .log-ok { color: #4ade80; } .log-err { color: #f87171; }

        .empty-state { text-align: center; padding: 60px 24px; color: #64748b; }
        .empty-state h3 { font-size: 1.05rem; color: #94a3b8; margin-bottom: 8px; }
        .empty-state p { font-size: .82rem; max-width: 360px; margin: 0 auto; }

        .tab-bar { display: flex; border-bottom: 1px solid #334155; background: #1e293b; flex-shrink: 0; }
        .tab-btn { padding: 10px 20px; font-size: .85rem; font-weight: 600; color: #64748b; cursor: pointer; border-bottom: 2px solid transparent; transition: .2s; user-select: none; }
        .tab-btn:hover { color: #94a3b8; }
        .tab-btn.active { color: #a5b4fc; border-bottom-color: #6366f1; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .toast { position: fixed; bottom: 24px; right: 24px; background: #334155; color: #f1f5f9; padding: 12px 20px; border-radius: 8px; font-size: .85rem; font-weight: 500; opacity: 0; transform: translateY(10px); transition: .3s; z-index: 9999; pointer-events: none; }
        .toast.show { opacity: 1; transform: none; }
        .toast.error { background: #991b1b; }

        .kw-table-wrap { flex: 1; overflow-y: auto; }
        .muted { color: #64748b; font-size: .82rem; }
        .search-input { max-width: 200px; padding: 5px 8px !important; font-size: .75rem !important; }
        .per-page-select { width: auto !important; padding: 4px 8px !important; font-size: .72rem !important; }
        .manual-counter { font-size: .75rem; color: #64748b; margin-top: 4px; }

        @media (max-width: 768px) { .sidebar { width: 100%; max-height: 40vh; } .page { flex-direction: column; } .cluster-grid { grid-template-columns: 1fr; } }

        /* ── Page-level sub-nav ── */
        .page-subnav { background: #1e293b; border-bottom: 1px solid #334155; display: flex; gap: 2px; padding: 0 16px; }
        .page-subnav-btn { padding: 9px 18px; font-size: .8rem; font-weight: 600; color: #64748b; cursor: pointer; border-bottom: 2px solid transparent; transition: .2s; user-select: none; }
        .page-subnav-btn:hover { color: #94a3b8; }
        .page-subnav-btn.active { color: #a5b4fc; border-bottom-color: #6366f1; }

        /* ── Intents section ── */
        #intentSection { display: none; height: calc(100vh - 90px); overflow-y: auto; }
        .intent-page-wrap { display: flex; gap: 20px; padding: 20px; align-items: flex-start; max-width: 1400px; }
        .intent-list-panel { flex: 1; min-width: 0; }
        .intent-form-panel { width: 420px; flex-shrink: 0; position: sticky; top: 20px; }

        .intent-row { display: flex; align-items: center; gap: 10px; padding: 11px 16px; border-bottom: 1px solid rgba(51,65,85,.4); cursor: pointer; transition: background .15s; }
        .intent-row:last-child { border-bottom: none; }
        .intent-row:hover { background: rgba(99,102,241,.07); }
        .intent-row.active { background: rgba(99,102,241,.14); }
        .intent-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .intent-row-code { font-family: 'SF Mono', Monaco, monospace; font-size: .72rem; color: #64748b; width: 160px; flex-shrink: 0; }
        .intent-row-labels { flex: 1; min-width: 0; }
        .intent-row-ru { font-size: .85rem; font-weight: 600; color: #f1f5f9; }
        .intent-row-en { font-size: .72rem; color: #64748b; }
        .intent-row-sort { font-size: .72rem; color: #475569; width: 28px; text-align: right; flex-shrink: 0; }

        .toggle-switch { position: relative; display: inline-block; width: 32px; height: 18px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; inset: 0; background: #334155; border-radius: 18px; transition: .2s; }
        .toggle-slider::before { content: ''; position: absolute; width: 12px; height: 12px; left: 3px; bottom: 3px; background: #64748b; border-radius: 50%; transition: .2s; }
        .toggle-switch input:checked + .toggle-slider { background: #059669; }
        .toggle-switch input:checked + .toggle-slider::before { transform: translateX(14px); background: #fff; }

        .color-input-wrap { display: flex; gap: 8px; align-items: center; }
        .color-preview { width: 28px; height: 28px; border-radius: 6px; border: 1px solid #334155; flex-shrink: 0; cursor: pointer; }
        .field-hint { font-size: .68rem; color: #475569; margin-top: 3px; line-height: 1.4; }
        .intent-form-scroll { padding: 16px 18px; display: flex; flex-direction: column; gap: 12px; }
        .intent-form-actions { display: flex; gap: 8px; padding-top: 4px; }

        /* ── Topbar profile ── */
        .topbar-left { display: flex; align-items: center; gap: 12px; }
        .topbar-back { color: #64748b; text-decoration: none; font-size: 1.1rem; padding: 4px 8px; border-radius: 6px; transition: .2s; }
        .topbar-back:hover { background: #334155; color: #e2e8f0; }
        .topbar-profile { display: flex; align-items: center; gap: 10px; }
        .topbar-profile-icon { width: 32px; height: 32px; border-radius: 8px; background: #334155; display: flex; align-items: center; justify-content: center; font-size: .9rem; font-weight: 700; color: #6366f1; flex-shrink: 0; overflow: hidden; }
        .topbar-profile-icon img { width: 100%; height: 100%; object-fit: cover; }
        .topbar-profile-name { font-size: .95rem; font-weight: 700; color: #f1f5f9; line-height: 1.2; }
        .topbar-profile-meta { font-size: .72rem; color: #64748b; }

        .sb-section-title { font-size: .85rem; font-weight: 700; color: #f1f5f9; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid #334155; }

        .cdp-toolbar { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid #1e293b; flex-shrink: 0; }
        .cdp-toolbar-title { font-size: .72rem; text-transform: uppercase; letter-spacing: .4px; color: #475569; font-weight: 600; }
    </style>
</head>
<body>
<div class="topbar">
    <div class="topbar-left">
        <a href="/admin_advanced/seo_profile_page.php" class="topbar-back" title="К профилям">&larr;</a>
        <div class="topbar-profile" id="topbarProfileInfo">
            <div class="topbar-profile-icon" id="topbarProfileIcon"></div>
            <div>
                <div class="topbar-profile-name" id="topbarProfileName">Семантика</div>
                <div class="topbar-profile-meta" id="topbarProfileMeta"></div>
            </div>
        </div>
    </div>
    <nav>
        <a href="/admin_advanced/seo_page.php">SEO</a>
        <a href="/admin_advanced/seo_clustering_page.php" class="active">Семантика</a>
        <a href="/admin_advanced/seo_profile_page.php">Профили</a>
        <a href="/admin_simple/articles.php" title="Упрощённая версия" style="color:#fbbf24">◐ Simple</a>
        <a href="/logout.php" class="btn-logout">Выйти</a>
    </nav>
</div>

<div class="page-subnav">
    <div class="page-subnav-btn active" id="subnavSemantic" onclick="switchPageView('semantic')">Семантика</div>
    <div class="page-subnav-btn" id="subnavIntents" onclick="switchPageView('intents')">Интенты</div>
</div>

<div id="intentSection">
    <div class="intent-page-wrap">
        <!-- List -->
        <div class="intent-list-panel">
            <div class="card">
                <div class="card-header">
                    <h2>Типы поисковых интентов</h2>
                    <div class="card-actions">
                        <button class="btn btn-sm btn-primary" onclick="openIntentForm(null)">+ Добавить</button>
                    </div>
                </div>
                <div id="intentListBody">
                    <div class="empty-state" style="padding:40px"><p class="muted">Загрузка...</p></div>
                </div>
            </div>
        </div>
        <!-- Form panel -->
        <div class="intent-form-panel" id="intentFormPanel" style="display:none">
            <div class="card">
                <div class="card-header">
                    <h2 id="intentFormTitle">Новый интент</h2>
                    <button class="btn btn-sm btn-ghost" onclick="closeIntentForm()">✕</button>
                </div>
                <div class="intent-form-scroll">
                    <div class="form-row">
                        <label>Код (code)</label>
                        <input type="text" id="intentCode" placeholder="action_plan" maxlength="30">
                        <div class="field-hint" id="intentCodeHint">Только строчные a-z, 0-9, _. Нельзя изменить после создания.</div>
                    </div>
                    <div class="form-row">
                        <label>Название RU</label>
                        <input type="text" id="intentLabelRu" placeholder="План действий">
                    </div>
                    <div class="form-row">
                        <label>Название EN</label>
                        <input type="text" id="intentLabelEn" placeholder="Action Plan">
                    </div>
                    <div class="form-row">
                        <label>Цвет бейджа (HEX)</label>
                        <div class="color-input-wrap">
                            <div class="color-preview" id="intentColorPreview" onclick="$('intentColorPicker').click()" title="Выбрать цвет"></div>
                            <input type="text" id="intentColorHex" placeholder="#6366f1" maxlength="7" oninput="syncColorFromHex()" style="flex:1">
                            <input type="color" id="intentColorPicker" style="position:absolute;opacity:0;pointer-events:none;width:0;height:0" oninput="syncColorFromPicker()">
                        </div>
                    </div>
                    <div class="form-row">
                        <label>Порядок сортировки</label>
                        <input type="number" id="intentSortOrder" value="0" min="0" max="255" style="width:90px">
                    </div>
                    <div class="form-row">
                        <label>Описание (для людей)</label>
                        <textarea id="intentDescription" rows="3" placeholder="Что означает этот интент..."></textarea>
                    </div>
                    <div class="form-row">
                        <label>GPT-инструкция (gpt_hint)</label>
                        <textarea id="intentGptHint" rows="4" placeholder="Назначай intent=X когда запрос содержит..."></textarea>
                        <div class="field-hint">Когда GPT должен назначать этот интент при кластеризации.</div>
                    </div>
                    <div class="form-row">
                        <label>Тон статьи (article_tone) <span class="muted">необязательно</span></label>
                        <textarea id="intentArticleTone" rows="3" placeholder="Тон — энциклопедический..."></textarea>
                    </div>
                    <div class="form-row">
                        <label>Пример открытия статьи (article_open) <span class="muted">необязательно</span></label>
                        <textarea id="intentArticleOpen" rows="3" placeholder="Первый абзац статьи для этого интента..."></textarea>
                    </div>
                    <div class="form-row" style="display:flex;align-items:center;gap:10px">
                        <label style="margin:0">Активен</label>
                        <label class="toggle-switch">
                            <input type="checkbox" id="intentIsActive" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="intent-form-actions">
                        <button class="btn btn-primary" onclick="saveIntent()" id="intentSaveBtn">Сохранить</button>
                        <button class="btn btn-danger btn-sm" id="intentDeleteBtn" onclick="deleteIntent()" style="display:none">Удалить</button>
                        <button class="btn btn-ghost btn-sm" onclick="closeIntentForm()">Отмена</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="page">
    <div class="sidebar">
        <div class="sb-section">
            <div class="sb-section-title">Новая задача сбора</div>
            <div class="form-row">
                <label>Базовый запрос (seed)</label>
                <input type="text" id="seedInput" placeholder="ключевое слово 1, ключевое слово 2, ...">
            </div>
            <div class="form-row">
                <label>Метод сбора</label>
                <select id="sourceSelect">
                    <option value="gpt">GPT-генерация запросов</option>
                    <option value="manual">Ручной ввод</option>
                    <option value="yandex">Yandex Wordstat API</option>
                </select>
            </div>
            <div class="sb-actions">
                <button class="btn btn-primary" onclick="createJob()">Создать</button>
            </div>
        </div>
        <div class="sb-section" style="padding:10px 14px 6px; flex-shrink:0">
            <h3>Задачи сбора</h3>
        </div>
        <div class="job-list" id="jobList">
            <div class="empty-state" style="padding:30px"><p class="muted">Загрузка...</p></div>
        </div>
    </div>

    <div class="main-panel">
        <div class="tab-bar" id="mainTabs" style="display:none">
            <div class="tab-btn active" data-tab="clusters" onclick="switchMainTab('clusters')">Кластеры</div>
            <div class="tab-btn" data-tab="keywords" onclick="switchMainTab('keywords')">Запросы</div>
            <div class="tab-btn" data-tab="log" onclick="switchMainTab('log')">Журнал</div>
        </div>
        <div class="main-scroll" id="mainScroll">
            <div id="emptyMain" class="empty-state">
                <h3>Выберите задачу</h3>
                <p>Создайте новую задачу сбора или выберите существующую для работы с семантическим ядром</p>
            </div>

            <!-- Clusters tab -->
            <div class="tab-content active" id="tabClusters" style="display:none">
                <div class="card">
                    <div class="card-header">
                        <h2 id="jobTitle">-</h2>
                        <div class="card-actions">
                            <button class="btn btn-sm btn-primary" id="btnCollect" onclick="showCollectPanel()">Добавить запросы</button>
                            <button class="btn btn-sm btn-warn" id="btnCluster" onclick="clusterKeywords()">Кластеризовать</button>
                            <button class="btn btn-sm btn-danger" onclick="deleteCurrentJob()">Удалить</button>
                        </div>
                    </div>
                    <div class="card-body" id="jobInfo" style="padding:10px 18px">
                        <div class="job-meta" style="font-size:.78rem">
                            <span id="jobStatusBadge"></span>
                            <span>Запросов: <b id="jobKwCount">0</b></span>
                            <span>Кластеров: <b id="jobClCount">0</b></span>
                        </div>
                        <div class="progress-bar" id="progressBar" style="display:none">
                            <div class="progress-fill" id="progressFill"></div>
                        </div>
                    </div>
                </div>

                <!-- Collect panel -->
                <div class="card" id="collectCard" style="display:none">
                    <div class="card-header">
                        <h2>Добавление запросов</h2>
                        <button class="btn btn-sm btn-ghost" onclick="$('collectCard').style.display='none'">x</button>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <label>Метод</label>
                            <select id="collectSource" onchange="onCollectSourceChange()">
                                <option value="gpt">GPT-генерация</option>
                                <option value="manual">Ручной ввод</option>
                            </select>
                        </div>
                        <div id="gptOptions">
                            <div class="form-row">
                                <label>Количество запросов</label>
                                <input type="number" id="collectMax" value="200" min="10" max="2000">
                            </div>
                            <button class="btn btn-primary" onclick="doCollect()">Сгенерировать</button>
                        </div>
                        <div id="manualOptions" style="display:none">
                            <div class="form-row">
                                <label>Поисковые запросы (по одному на строку)</label>
                                <textarea id="manualInput" rows="10" oninput="updateManualCounter()" placeholder="запрос 1&#10;запрос 2&#10;запрос 3"></textarea>
                                <div class="manual-counter">Введено: <b id="manualCount">0</b> запросов | В базе: <b id="existingCount">0</b></div>
                            </div>
                            <button class="btn btn-primary" onclick="doCollect()">Сохранить запросы</button>
                        </div>
                    </div>
                </div>

                <!-- Cluster toolbar -->
                <div class="cluster-toolbar" id="clusterToolbar" style="display:none">
                    <!-- Status filter -->
                    <div class="toolbar-group">
                        <span class="toolbar-label">Статус</span>
                        <div id="statusFilter" style="display:flex;gap:4px;flex-wrap:wrap">
                            <button class="fbtn active" data-val="" onclick="setStatusFilter(this, '')">Все</button>
                            <button class="fbtn" data-val="new" onclick="setStatusFilter(this, 'new')">Новые</button>
                            <button class="fbtn" data-val="approved" onclick="setStatusFilter(this, 'approved')">Утверждённые</button>
                            <button class="fbtn" data-val="rejected" onclick="setStatusFilter(this, 'rejected')">Отклонённые</button>
                            <button class="fbtn" data-val="article_created" onclick="setStatusFilter(this, 'article_created')">Статья создана</button>
                        </div>
                    </div>
                    <div class="toolbar-sep"></div>
                    <!-- Group by -->
                    <div class="toolbar-group">
                        <span class="toolbar-label">Группировка</span>
                        <div id="groupByBtns" style="display:flex;gap:4px">
                            <button class="fbtn active" data-val="" onclick="setGroupBy(this, '')">Нет</button>
                            <button class="fbtn" data-val="intent" onclick="setGroupBy(this, 'intent')">По интенту</button>
                            <button class="fbtn" data-val="status" onclick="setGroupBy(this, 'status')">По статусу</button>
                        </div>
                    </div>
                    <div class="toolbar-sep"></div>
                    <!-- Sort -->
                    <div class="toolbar-group">
                        <span class="toolbar-label">Сортировка</span>
                        <select class="toolbar-select" id="clusterSort" onchange="applyClusterView()">
                            <option value="priority_desc">Приоритет ↓</option>
                            <option value="volume_desc">Объём ↓</option>
                            <option value="count_desc">Запросов ↓</option>
                            <option value="name_asc">По алфавиту</option>
                        </select>
                    </div>
                    <div class="toolbar-sep"></div>
                    <!-- View toggle -->
                    <div class="toolbar-group">
                        <span class="toolbar-label">Вид</span>
                        <div class="view-toggle">
                            <button class="vtbtn active" id="viewGrid" title="Сетка" onclick="setView('grid')">⊞</button>
                            <button class="vtbtn" id="viewList" title="Список" onclick="setView('list')">☰</button>
                        </div>
                    </div>
                    <!-- Summary -->
                    <div class="cluster-summary" id="clusterSummary"></div>
                    <div class="toolbar-sep"></div>
                    <!-- Batch article generation -->
                    <div class="toolbar-group">
                        <button class="btn btn-sm btn-primary" onclick="generateAllApproved()" title="Создать статьи для всех утверждённых кластеров">📝 Создать статьи</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteAllClusters()" title="Удалить все кластеры задачи">🗑 Удалить все</button>
                    </div>
                </div>

                <div style="display:flex;min-height:0">
                    <div id="clusterArea" style="flex:1;min-width:0"></div>
                    <div class="cluster-detail-panel" id="clusterDetailPanel" style="display:none;flex-direction:column">
                        <div class="cdp-toolbar">
                            <span class="cdp-toolbar-title">Кластер</span>
                            <button class="btn-icon" onclick="document.getElementById('clusterDetailPanel').style.display='none';document.querySelectorAll('.cluster-row,.cluster-card').forEach(r=>r.classList.remove('active'))">✕</button>
                        </div>
                        <div id="cdpContent"><div class="cdp-empty">Выберите кластер</div></div>
                    </div>
                </div>
            </div>

            <!-- Keywords tab -->
            <div class="tab-content" id="tabKeywords" style="display:none">
                <div class="card" style="display:flex;flex-direction:column;height:calc(100vh - 110px)">
                    <div class="card-header">
                        <h2>Поисковые запросы</h2>
                        <div class="card-actions">
                            <input type="text" id="kwSearch" class="search-input" placeholder="Поиск..." oninput="debounceSearch()">
                            <select id="kwFilterCluster" class="per-page-select" onchange="kwPage=1;loadKeywords()">
                                <option value="">Все кластеры</option>
                                <option value="0">Без кластера</option>
                            </select>
                            <select id="kwSort" class="per-page-select" onchange="kwPage=1;loadKeywords()">
                                <option value="volume_desc">Частотность ↓</option>
                                <option value="volume_asc">Частотность ↑</option>
                                <option value="alpha">По алфавиту</option>
                            </select>
                            <select id="kwPerPage" class="per-page-select" onchange="kwPage=1;loadKeywords()">
                                <option value="50">50</option>
                                <option value="100" selected>100</option>
                                <option value="250">250</option>
                                <option value="500">500</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body-np" style="flex:1;display:flex;flex-direction:column;min-height:0;overflow:hidden">
                        <div class="kw-table-wrap">
                            <table class="kw-table">
                                <thead>
                                <tr>
                                    <th>Запрос</th>
                                    <th style="width:90px">Частотность</th>
                                    <th style="width:90px">Конкуренция</th>
                                    <th style="width:80px">CPC, руб.</th>
                                    <th style="width:130px">Кластер</th>
                                    <th style="width:50px"></th>
                                </tr>
                                </thead>
                                <tbody id="kwTableBody">
                                <tr><td colspan="6" class="muted" style="text-align:center;padding:20px">-</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div style="padding:10px 14px; display:flex; justify-content:space-between; align-items:center">
                            <span class="muted" id="kwPagInfo"></span>
                            <div style="display:flex;gap:4px">
                                <button class="btn btn-sm btn-ghost" id="kwPrev" onclick="kwPageNav(-1)" disabled>Назад</button>
                                <button class="btn btn-sm btn-ghost" id="kwNext" onclick="kwPageNav(1)">Далее</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Log tab -->
            <div class="tab-content" id="tabLog" style="display:none">
                <div class="card">
                    <div class="card-header"><h2>Журнал операций</h2></div>
                    <div class="card-body"><div class="log-box show" id="logBox" style="max-height:500px"><span class="log-info">Журнал пуст</span></div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
    const API = '../controllers/router.php';
    let currentJobId = null, currentJob = null, kwPage = 1, allClusters = [];
    let searchTimer = null;
    let clusterView = { status: '', group: '', sort: 'priority_desc', layout: 'grid' };
    let currentProfileId = localStorage.getItem('seo_profile_id') || '';

    /** @type {Object<string, {code:string, label_ru:string, label_en:string, color:string, description:string}>} */
    let intentTypes = {};

    async function loadIntentTypes() {
        try {
            const res = await api('keywords/intents');
            const list = res.data || [];
            intentTypes = {};
            list.forEach(t => { intentTypes[t.code] = t; });
        } catch (e) {
            console.warn('Failed to load intent types:', e);
            intentTypes = { info: { code: 'info', label_ru: 'Справочный', color: '#60a5fa', description: '' } };
        }
    }

    function switchPageView(view) {
        const isSemantic = view === 'semantic';
        $('subnavSemantic').classList.toggle('active', isSemantic);
        $('subnavIntents').classList.toggle('active', !isSemantic);
        $('intentSection').style.display = isSemantic ? 'none' : 'block';
        document.querySelector('.page').style.display = isSemantic ? '' : 'none';
        if (!isSemantic) renderIntentList();
    }

    let editingIntentCode = null;

    function renderIntentList() {
        const list = Object.values(intentTypes).sort(function(a, b) {
            return (a.sort_order || 0) - (b.sort_order || 0);
        });
        if (!list.length) {
            $('intentListBody').innerHTML = '<div class="empty-state" style="padding:40px"><p class="muted">Нет интентов</p></div>';
            return;
        }
        $('intentListBody').innerHTML = list.map(function(t) {
            return '<div class="intent-row ' + (editingIntentCode === t.code ? 'active' : '') + '" onclick="openIntentForm(\'' + esc(t.code) + '\')">'
                + '<div class="intent-dot" style="background:' + esc(t.color || '#6366f1') + '"></div>'
                + '<div class="intent-row-code">' + esc(t.code) + '</div>'
                + '<div class="intent-row-labels">'
                + '<div class="intent-row-ru">' + esc(t.label_ru) + '</div>'
                + '<div class="intent-row-en">' + esc(t.label_en || '') + '</div>'
                + '</div>'
                + '<div class="intent-row-sort">' + (t.sort_order || 0) + '</div>'
                + '<div onclick="event.stopPropagation()">'
                + '<label class="toggle-switch" title="' + (t.is_active ? 'Активен' : 'Неактивен') + '">'
                + '<input type="checkbox" ' + (t.is_active ? 'checked' : '') + ' onchange="toggleIntentActive(\'' + esc(t.code) + '\', this.checked)">'
                + '<span class="toggle-slider"></span>'
                + '</label>'
                + '</div>'
                + '</div>';
        }).join('');
    }

    function openIntentForm(code) {
        editingIntentCode = code;
        const isNew = code === null;
        const t = isNew ? {} : (intentTypes[code] || {});

        $('intentFormTitle').textContent = isNew ? 'Новый интент' : 'Редактировать: ' + code;
        $('intentCode').value     = isNew ? '' : code;
        $('intentCode').readOnly  = !isNew;
        $('intentCode').style.opacity = isNew ? '' : '.5';
        $('intentCodeHint').style.display = isNew ? '' : 'none';
        $('intentLabelRu').value  = t.label_ru || '';
        $('intentLabelEn').value  = t.label_en || '';
        $('intentSortOrder').value = t.sort_order !== undefined ? t.sort_order : 0;
        $('intentDescription').value  = t.description || '';
        $('intentGptHint').value      = t.gpt_hint || '';
        $('intentArticleTone').value  = t.article_tone || '';
        $('intentArticleOpen').value  = t.article_open || '';
        $('intentIsActive').checked   = isNew ? true : !!t.is_active;
        setIntentColor(t.color || '#6366f1');
        $('intentDeleteBtn').style.display = isNew ? 'none' : '';
        $('intentFormPanel').style.display = '';
        renderIntentList();
    }

    function closeIntentForm() {
        editingIntentCode = null;
        $('intentFormPanel').style.display = 'none';
        renderIntentList();
    }

    function setIntentColor(hex) {
        $('intentColorHex').value = hex;
        $('intentColorPicker').value = hex;
        $('intentColorPreview').style.background = hex;
    }

    function syncColorFromHex() {
        const v = $('intentColorHex').value.trim();
        if (/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(v)) {
            $('intentColorPicker').value = v;
            $('intentColorPreview').style.background = v;
        }
    }

    function syncColorFromPicker() {
        setIntentColor($('intentColorPicker').value);
    }

    async function saveIntent() {
        const isNew = editingIntentCode === null;
        const code  = isNew ? $('intentCode').value.trim() : editingIntentCode;

        if (!code) { toast('Введите код интента', true); return; }
        if (isNew && !/^[a-z0-9_]{1,30}$/.test(code)) {
            toast('Код: только строчные латинские буквы, цифры, _', true); return;
        }
        const color = $('intentColorHex').value.trim();
        if (!/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(color)) {
            toast('Некорректный HEX-цвет', true); return;
        }

        const body = {
            label_ru:     $('intentLabelRu').value.trim(),
            label_en:     $('intentLabelEn').value.trim(),
            color:        color,
            sort_order:   parseInt($('intentSortOrder').value) || 0,
            description:  $('intentDescription').value.trim(),
            gpt_hint:     $('intentGptHint').value.trim(),
            article_tone: $('intentArticleTone').value.trim() || null,
            article_open: $('intentArticleOpen').value.trim() || null,
            is_active:    $('intentIsActive').checked ? 1 : 0,
        };

        if (!body.label_ru || !body.description || !body.gpt_hint) {
            toast('Заполните название RU, описание и GPT-инструкцию', true); return;
        }

        $('intentSaveBtn').disabled = true;
        try {
            if (isNew) {
                body.code = code;
                await api('keywords/intents', { body: body });
                toast('Интент создан');
            } else {
                await api('intents/' + code, { method: 'PATCH', body: body });
                toast('Интент сохранён');
            }
            await loadIntentTypes();
            renderIntentList();
            if (isNew) closeIntentForm();
            else openIntentForm(code);
        } catch (e) {
            toast('Ошибка: ' + e.message, true);
        }
        $('intentSaveBtn').disabled = false;
    }

    async function deleteIntent() {
        if (!editingIntentCode) return;
        const code = editingIntentCode;
        if (!confirm('Удалить интент «' + code + '»?\nКластеры с этим интентом потеряют связь (intent → NULL).')) return;
        try {
            await api('intents/' + code, { method: 'DELETE' });
            toast('Интент удалён');
            closeIntentForm();
            await loadIntentTypes();
            renderIntentList();
        } catch (e) {
            toast('Ошибка: ' + e.message, true);
        }
    }

    async function toggleIntentActive(code, isActive) {
        try {
            await api('intents/' + code, { method: 'PATCH', body: { is_active: isActive ? 1 : 0 } });
            intentTypes[code].is_active = isActive;
        } catch (e) {
            toast('Ошибка сохранения', true);
            renderIntentList();
        }
    }

    function intentLabel(code) {
        return intentTypes[code]?.label_ru || code || '—';
    }

    function intentBadge(code) {
        if (!code) return '';
        const t = intentTypes[code];
        const label = t?.label_ru || code;
        const desc = t?.description || '';
        if (desc) {
            return `<span class="badge badge-${esc(code)} intent-tooltip" data-tooltip="${esc(desc)}">${esc(label)}</span>`;
        }
        return `<span class="badge badge-${esc(code)}">${esc(label)}</span>`;
    }

    async function api(resource, opts = {}) {
        const params = new URLSearchParams({ r: resource });
        if (opts.params) {
            for (const [k, v] of Object.entries(opts.params)) {
                if (v !== '' && v !== null && v !== undefined) params.set(k, String(v));
            }
        }
        const url = API + '?' + params.toString();
        const fetchOpts = { headers: { 'Content-Type': 'application/json' } };
        if (opts.body) { fetchOpts.method = opts.method || 'POST'; fetchOpts.body = JSON.stringify(opts.body); }
        else { fetchOpts.method = opts.method || 'GET'; }
        const resp = await fetch(url, fetchOpts);
        const data = await resp.json();
        if (!data.success) throw new Error(data.error || 'API error');
        return data;
    }

    function $(id) { return document.getElementById(id); }
    function esc(s) { if (s == null) return ''; const d = document.createElement('div'); d.textContent = String(s); return d.innerHTML; }
    function toast(msg, err) { const t = $('toast'); t.textContent = msg; t.className = 'toast' + (err ? ' error' : ''); setTimeout(() => t.classList.add('show'), 10); setTimeout(() => t.classList.remove('show'), 3000); }
    function fmtNum(n) { return n ? Number(n).toLocaleString('ru-RU') : '—'; }

    function switchMainTab(tab) {
        document.querySelectorAll('.tab-btn').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
        const map = { clusters: 'tabClusters', keywords: 'tabKeywords', log: 'tabLog' };
        Object.entries(map).forEach(([k, id]) => $(id).style.display = k === tab ? 'block' : 'none');
    }

    async function loadJobs() {
        try {
            const params = { per_page: 50 };
            if (currentProfileId) params.profile_id = currentProfileId;
            const res = await api('keywords/jobs', { params });
            renderJobList(res.data || []);
        } catch (e) { $('jobList').innerHTML = '<div class="empty-state" style="padding:20px"><p class="muted">Ошибка загрузки</p></div>'; }
    }

    function renderJobList(jobs) {
        if (!jobs.length) { $('jobList').innerHTML = '<div class="empty-state" style="padding:30px"><p class="muted">Нет задач</p></div>'; return; }
        $('jobList').innerHTML = jobs.map(j => `
        <div class="job-item ${j.id == currentJobId ? 'active' : ''}" onclick="selectJob(${j.id})">
            <div class="job-title">${esc(j.seed_keyword)}</div>
            <div class="job-meta">
                <span class="badge badge-${j.status}">${j.status}</span>
                <span>${j.keyword_count || j.total_found || 0} запросов</span>
                <span>${j.cluster_count || j.total_clusters || 0} кластеров</span>
            </div>
        </div>`).join('');
    }

    async function createJob() {
        const seed = $('seedInput').value.trim();
        if (!seed) { toast('Введите базовый запрос', true); return; }
        const source = $('sourceSelect').value;
        try {
            const res = await api('keywords/jobs', { body: { seed_keyword: seed, source } });
            $('seedInput').value = '';
            toast('Задача создана');
            await loadJobs();
            await selectJob(res.data.id);
            if (source === 'gpt') await doCollectDirect('gpt', { max_keywords: 200 });
        } catch (e) { toast('Ошибка: ' + (e.message || e), true); }
    }

    async function selectJob(id) {
        currentJobId = id;
        document.querySelectorAll('.job-item').forEach(el => {
            el.classList.toggle('active', el.querySelector('.job-title') && el.onclick.toString().includes('(' + id + ')'));
        });
        try { currentJob = (await api('keywords/jobs/' + id)).data; } catch (e) { toast('Ошибка загрузки', true); return; }
        $('emptyMain').style.display = 'none';
        $('mainTabs').style.display = 'flex';
        switchMainTab('clusters');
        renderJobHeader();
        await loadClusters();
        loadKeywords();
        updateClusterFilter();
    }

    function renderJobHeader() {
        const j = currentJob;
        $('jobTitle').textContent = j.seed_keyword;
        $('jobStatusBadge').innerHTML = `<span class="badge badge-${j.status}">${j.status}</span>`;
        $('jobKwCount').textContent = j.total_found || 0;
        $('jobClCount').textContent = j.total_clusters || 0;
        $('existingCount').textContent = j.total_found || 0;
    }

    async function deleteCurrentJob() {
        if (!currentJobId || !confirm('Удалить задачу "' + currentJob.seed_keyword + '" со всеми данными?')) return;
        try {
            await api('keywords/jobs/' + currentJobId, { method: 'DELETE' });
            currentJobId = null; currentJob = null;
            $('emptyMain').style.display = ''; $('mainTabs').style.display = 'none';
            ['tabClusters','tabKeywords','tabLog'].forEach(id => $(id).style.display = 'none');
            $('clusterToolbar').style.display = 'none';
            toast('Задача удалена'); await loadJobs();
        } catch (e) { toast('Ошибка: ' + e.message, true); }
    }

    function showCollectPanel() { $('collectCard').style.display = ''; onCollectSourceChange(); }

    function onCollectSourceChange() {
        const isManual = $('collectSource').value === 'manual';
        $('gptOptions').style.display = isManual ? 'none' : '';
        $('manualOptions').style.display = isManual ? '' : 'none';
        if (isManual) updateManualCounter();
    }

    function updateManualCounter() {
        const lines = $('manualInput').value.split('\n').filter(l => l.trim());
        $('manualCount').textContent = lines.length;
    }

    async function doCollect() {
        const source = $('collectSource').value;
        if (source === 'manual') {
            const text = $('manualInput').value.trim();
            if (!text) { toast('Введите поисковые запросы', true); return; }
            await doCollectDirect('manual', { keywords: text });
        } else {
            const max = parseInt($('collectMax').value) || 200;
            await doCollectDirect(source, { max_keywords: max });
        }
    }

    async function doCollectDirect(source, extra = {}) {
        if (!currentJobId) return;
        $('btnCollect').disabled = true;
        logMsg('Сбор запросов (' + source + ')...', 'log-info');
        try {
            const body = { source, config: { max_keywords: extra.max_keywords || 200 } };
            if (source === 'manual') body.keywords = extra.keywords;
            const res = await api('keywords/collect/' + currentJobId, { body });
            const count = res.data?.imported || 0;
            toast('Добавлено ' + count + ' запросов');
            logMsg('Добавлено ' + count + ' запросов (' + source + ')', 'log-ok');
            $('collectCard').style.display = 'none';
            $('manualInput').value = '';
            await selectJob(currentJobId);
        } catch (e) { toast('Ошибка: ' + e.message, true); logMsg('Ошибка: ' + e.message, 'log-err'); }
        $('btnCollect').disabled = false;
    }

    async function clusterKeywords() {
        if (!currentJobId || !confirm('Запустить GPT-кластеризацию? Существующие кластеры будут пересозданы.')) return;
        $('btnCluster').disabled = true;
        $('progressBar').style.display = '';
        setProgress(0);
        logMsg('--- Запуск кластеризации ---', 'log-info');

        try {
            const response = await fetch(API + '?r=keywords/cluster/' + currentJobId + '/sse', {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ model: 'gpt-4o' }),
            });
            const reader = response.body.getReader();
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
                    else if (line.startsWith('data: ') && eventName) { try { handleClusterEvent(eventName, JSON.parse(line.substring(6))); } catch(e){} eventName = ''; }
                }
            }
        } catch (e) { logMsg('Ошибка: ' + e.message, 'log-err'); toast('Ошибка кластеризации', true); }
        $('btnCluster').disabled = false;
        $('progressBar').style.display = 'none';
        await selectJob(currentJobId);
    }

    function handleClusterEvent(event, data) {
        switch (event) {
            case 'start': logMsg('Кластеризация: "' + data.seed + '"', 'log-info'); break;
            case 'progress':
                if (data.phase === 'clustering') logMsg(data.total_keywords + ' запросов, ' + data.total_batches + ' батч(ей)', 'log-info');
                else if (data.phase === 'merging') { logMsg('Объединение кластеров из ' + data.batches + ' батчей...', 'log-info'); setProgress(80); }
                else if (data.phase === 'saving') { logMsg('Сохранение ' + data.clusters + ' кластеров...', 'log-info'); setProgress(90); }
                break;
            case 'batch_start': logMsg('Батч ' + data.batch + '/' + data.total + ' (' + data.keywords_in_batch + ' запросов)...', 'log-info'); setProgress(Math.round((data.batch / data.total) * 70)); break;
            case 'batch_done': logMsg('Батч ' + data.batch + ': ' + data.clusters_found + ' кластеров', 'log-ok'); break;
            case 'batch_error': logMsg('Батч ' + data.batch + ': ' + data.error, 'log-err'); break;
            case 'done': logMsg('--- Готово: ' + data.total_clusters + ' кластеров из ' + data.total_keywords + ' запросов ---', 'log-ok'); setProgress(100); toast('Кластеризация: ' + data.total_clusters + ' кластеров'); break;
            case 'error': logMsg('ОШИБКА: ' + data.message, 'log-err'); toast('Ошибка: ' + data.message, true); break;
        }
    }

    async function loadClusters() {
        if (!currentJobId) return;
        try {
            const res = await api('keywords/clusters/' + currentJobId);
            allClusters = res.data || [];
            if (allClusters.length) $('clusterToolbar').style.display = 'flex';
            applyClusterView();
        } catch (e) { $('clusterArea').innerHTML = '<p class="muted">Ошибка загрузки кластеров</p>'; }
    }

    function setStatusFilter(btn, val) {
        clusterView.status = val;
        document.querySelectorAll('#statusFilter .fbtn').forEach(b => b.className = 'fbtn');
        const activeClass = val === 'approved' ? 'fbtn active-green' : val === 'rejected' ? 'fbtn active-red' : val === 'article_created' ? 'fbtn active-teal' : 'fbtn active';
        btn.className = activeClass;
        applyClusterView();
    }

    function setGroupBy(btn, val) {
        clusterView.group = val;
        document.querySelectorAll('#groupByBtns .fbtn').forEach(b => b.className = 'fbtn');
        btn.className = 'fbtn active';
        applyClusterView();
    }

    function setView(layout) {
        clusterView.layout = layout;
        $('viewGrid').classList.toggle('active', layout === 'grid');
        $('viewList').classList.toggle('active', layout === 'list');
        if (layout === 'grid') {
            const panel = $('clusterDetailPanel');
            if (panel) panel.style.display = 'none';
        }
        applyClusterView();
    }

    function applyClusterView() {
        clusterView.sort = $('clusterSort').value;

        // Filter
        let filtered = allClusters.filter(c => {
            if (clusterView.status && c.status !== clusterView.status) return false;
            return true;
        });

        // Sort
        filtered = sortClusters(filtered, clusterView.sort);

        // Summary
        const totalVol = filtered.reduce((s, c) => s + (Number(c.total_volume) || 0), 0);
        const approved = filtered.filter(c => c.status === 'approved').length;
        const withArticle = filtered.filter(c => c.status === 'article_created').length;
        const readyToGenerate = filtered.filter(c => c.status === 'approved' && c.intent && !c.article_id).length;
        $('clusterSummary').innerHTML =
            `<span>Показано: <b>${filtered.length}</b></span>` +
            `<span>Объём: <b>${fmtNum(totalVol)}</b></span>` +
            (approved ? `<span>Утверждено: <b>${approved}</b></span>` : '') +
            (withArticle ? `<span>Со статьёй: <b>${withArticle}</b></span>` : '') +
            (readyToGenerate ? `<span style="color:#a5b4fc">К генерации: <b>${readyToGenerate}</b></span>` : '');

        // Render
        if (!filtered.length) {
            $('clusterArea').innerHTML = '<div class="empty-state" style="padding:40px"><h3>Нет кластеров</h3><p>Нет кластеров по выбранному фильтру</p></div>';
            return;
        }

        if (clusterView.group) {
            renderGrouped(filtered);
        } else {
            renderFlat(filtered);
        }
    }

    function sortClusters(list, sort) {
        return [...list].sort((a, b) => {
            switch (sort) {
                case 'priority_desc': return (b.priority || 0) - (a.priority || 0);
                case 'volume_desc':   return (Number(b.total_volume) || 0) - (Number(a.total_volume) || 0);
                case 'count_desc':    return (b.keyword_count || 0) - (a.keyword_count || 0);
                case 'name_asc':      return (a.name || '').localeCompare(b.name || '', 'ru');
                default: return 0;
            }
        });
    }

    function renderGrouped(clusters) {
        const key = clusterView.group; // 'intent' or 'status'

        // Для intent — лейблы из загруженного справочника
        const statusLabels = { new: 'Новые', approved: 'Утверждённые', rejected: 'Отклонённые', article_created: 'Статья создана', '': 'Без статуса' };

        function getGroupLabel(gKey) {
            if (key === 'status') return statusLabels[gKey] || gKey || '—';
            // intent — из справочника
            return intentTypes[gKey]?.label_ru || gKey || 'Без интента';
        }

        const groups = {};
        clusters.forEach(c => {
            const gKey = c[key] || '';
            if (!groups[gKey]) groups[gKey] = [];
            groups[gKey].push(c);
        });

        // Для intent — порядок из справочника; для status — фиксированный
        let order;
        if (key === 'intent') {
            order = Object.keys(intentTypes);
            order.push(''); // Без интента в конце
        } else {
            order = ['new', 'approved', 'article_created', 'rejected', ''];
        }

        const sorted = Object.keys(groups).sort((a, b) => {
            const ia = order.indexOf(a), ib = order.indexOf(b);
            return (ia === -1 ? 99 : ia) - (ib === -1 ? 99 : ib);
        });

        $('clusterArea').innerHTML = sorted.map(gKey => {
            const items = groups[gKey];
            const label = getGroupLabel(gKey);
            return `<div class="cluster-group">
                <div class="cluster-group-header">
                    <span class="cluster-group-title">${esc(label)}</span>
                    <span class="cluster-group-count">${items.length}</span>
                </div>
                ${renderCardsHTML(items)}
            </div>`;
        }).join('');
    }

    function renderFlat(clusters) {
        $('clusterArea').innerHTML = renderCardsHTML(clusters);
    }

    function renderCardsHTML(clusters) {
        if (clusterView.layout === 'list') {
            return `<div style="background:#0d1525;border-radius:8px;overflow:hidden;border:1px solid #1e293b">
                <div style="display:flex;gap:14px;padding:8px 16px;border-bottom:1px solid #1e293b;font-size:.72rem;text-transform:uppercase;letter-spacing:.4px;color:#1e3a5f">
                    <span style="flex:1">Название</span>
                    <span style="width:120px">Интент</span>
                    <span style="width:36px;text-align:right">Кл.</span>
                    <span style="width:56px;text-align:right">Vol</span>
                </div>
                <div class="cluster-list">` + clusters.map(c => renderClusterRow(c)).join('') + `</div>
            </div>`;
        }
        return '<div class="cluster-grid">' + clusters.map(c => renderClusterCard(c)).join('') + '</div>';
    }

    function priClass(p) {
        if (!p || p <= 0) return 'pri-low';
        if (p <= 3) return 'pri-low';
        if (p <= 5) return 'pri-mid';
        if (p <= 7) return 'pri-high';
        return 'pri-top';
    }

    function renderClusterCard(c) {
        const sc = c.status ? ' status-' + c.status : '';
        const hasArticle = !!(c.article_id && c.status === 'article_created');
        const canApprove  = c.status !== 'approved' && c.status !== 'article_created';
        const canReject   = !hasArticle && c.status !== 'rejected';
        const canGenerate = !hasArticle && c.status === 'approved' && c.intent;
        const canDelete   = !hasArticle;

        const intentLabel = c.intent ? (intentTypes[c.intent]?.label_ru || c.intent) : '';
        const dotClass = c.status ? ' s-' + c.status : '';

        const statusText = { new: 'Новый', approved: 'Утверждён', rejected: 'Отклонён', article_created: 'Статья создана' };

        let articleBtn = '';
        if (hasArticle) {
            articleBtn = `<a class="btn-icon art" href="seo_page.php?article_id=${c.article_id}">📄 #${c.article_id}</a>
                          <button class="btn-icon del" onclick="deleteClusterArticle(${c.id})" title="Открепить статью">✕</button>`;
        } else if (canGenerate) {
            articleBtn = `<button class="btn-icon art" onclick="generateArticle(${c.id})">📝 Статья</button>`;
        }

        return `<div class="cluster-card${sc}" id="cluster_${c.id}" onclick="openClusterDetail(${c.id})" style="cursor:pointer">
            <div class="cc-name">${esc(c.name)}</div>
            <div class="cc-meta">
                ${intentLabel ? `<span class="cc-meta-intent">${esc(intentLabel)}</span><span class="cc-meta-sep">·</span>` : ''}
                <span class="cc-meta-stat"><b>${c.keyword_count || 0}</b> кл.</span>
                <span class="cc-meta-sep">·</span>
                <span class="cc-meta-stat"><b>${fmtNum(c.total_volume)}</b> vol</span>
                ${c.priority > 0 ? `<span class="cc-meta-sep">·</span><span class="cc-meta-stat">P<b>${c.priority}</b></span>` : ''}
                <span style="flex:1"></span>
                <span class="cc-status-dot${dotClass}" title="${esc(statusText[c.status] || c.status || '')}"></span>
            </div>
        </div>`;
    }

    function renderClusterRow(c) {
        const sc = c.status ? ' status-' + c.status : '';
        const intentLabel = c.intent ? (intentTypes[c.intent]?.label_ru || c.intent) : '—';
        return `<div class="cluster-row${sc}" id="cluster_${c.id}" onclick="openClusterDetail(${c.id})">
            <div class="cr-name" title="${esc(c.name)}">${esc(c.name)}</div>
            <div class="cr-intent">${esc(intentLabel)}</div>
            <div class="cr-count">${c.keyword_count || 0}</div>
            <div class="cr-vol">${fmtNum(c.total_volume)}</div>
        </div>`;
    }

    let selectedClusterId = null;

    function openClusterDetail(id) {
        selectedClusterId = id;
        document.querySelectorAll('.cluster-row').forEach(r => r.classList.toggle('active', r.id === 'cluster_' + id));
        document.querySelectorAll('.cluster-card').forEach(r => r.classList.toggle('active', r.id === 'cluster_' + id));
        const c = allClusters.find(c => c.id == id);
        if (!c) return;
        const panel = document.getElementById('clusterDetailPanel');
        if (!panel) return;
        panel.style.display = 'flex';

        const hasArticle = !!(c.article_id && c.status === 'article_created');
        const canApprove  = c.status !== 'approved' && c.status !== 'article_created';
        const canReject   = !hasArticle && c.status !== 'rejected';
        const canGenerate = !hasArticle && c.status === 'approved' && c.intent;
        const canDelete   = !hasArticle;
        const statusText  = { new: 'Новый', approved: 'Утверждён', rejected: 'Отклонён', article_created: 'Статья создана' };
        const intentLabelFull = c.intent ? (intentTypes[c.intent]?.label_ru || c.intent) : '—';

        let articleBtn = '';
        if (hasArticle) {
            articleBtn = `<a class="btn-soft btn-soft-article" href="seo_page.php?article_id=${c.article_id}">📄 Статья #${c.article_id}</a>
                          <button class="btn-soft btn-soft-unlink" onclick="deleteClusterArticle(${c.id})">✕ Открепить статью</button>`;
        } else if (canGenerate) {
            articleBtn = `<button class="btn-soft btn-soft-article" onclick="generateArticle(${c.id})">📝 Создать статью</button>`;
        }

        document.getElementById('cdpContent').innerHTML = `
            <div class="cdp-header">
                <div class="cdp-name">${esc(c.name)}</div>
                <div class="cdp-meta">
                    <div class="cdp-meta-row"><span class="cdp-meta-label">Статус</span><span class="cdp-meta-value">${esc(statusText[c.status] || c.status || '—')}</span></div>
                    <div class="cdp-meta-row"><span class="cdp-meta-label">Интент</span><span class="cdp-meta-value">${esc(intentLabelFull)}</span></div>
                    <div class="cdp-meta-row"><span class="cdp-meta-label">Запросов</span><span class="cdp-meta-value">${c.keyword_count || 0}</span></div>
                    <div class="cdp-meta-row"><span class="cdp-meta-label">Объём</span><span class="cdp-meta-value">${fmtNum(c.total_volume)}</span></div>
                    ${c.priority > 0 ? `<div class="cdp-meta-row"><span class="cdp-meta-label">Приоритет</span><span class="cdp-meta-value">${c.priority}</span></div>` : ''}
                    ${c.template_name ? `<div class="cdp-meta-row"><span class="cdp-meta-label">Шаблон</span><span class="cdp-meta-value">${esc(c.template_name)}</span></div>` : ''}
                </div>
            </div>
            <div class="cdp-body">
                ${c.article_angle ? `<div class="cdp-angle">${esc(c.article_angle)}</div>` : ''}
                <div class="cdp-actions">
                    ${articleBtn}
                    ${canApprove ? `<button class="btn-soft btn-soft-approve" onclick="approveCluster(${c.id})">✓ Утвердить</button>` : ''}
                    ${canReject  ? `<button class="btn-soft btn-soft-reject"  onclick="rejectCluster(${c.id})">✗ Отклонить</button>` : ''}
                    <button class="btn-soft btn-soft-keywords" onclick="showClusterKeywords(${c.id})">🔍 Запросы кластера</button>
                    ${canDelete  ? `<button class="btn-soft btn-soft-danger"  onclick="deleteCluster(${c.id})">🗑 Удалить кластер</button>` : ''}
                </div>
            </div>`;
    }

    async function approveCluster(id) {
        try { await api('keywords/clusters/approve/' + id, { body: {} }); toast('Кластер утверждён'); await loadClusters(); if (selectedClusterId == id) openClusterDetail(id); } catch(e) { toast(e.message, true); }
    }
    async function rejectCluster(id) {
        try { await api('keywords/clusters/reject/' + id, { body: {} }); toast('Кластер отклонён'); await loadClusters(); if (selectedClusterId == id) openClusterDetail(id); } catch(e) { toast(e.message, true); }
    }
    async function deleteCluster(id) {
        if (!confirm('Удалить кластер? Привязанные запросы станут нераспределёнными.')) return;
        try {
            await api('keywords/clusters/detail/' + id, { method: 'DELETE' });
            toast('Кластер удалён');
            await loadClusters();
            loadKeywords();
            try { currentJob = (await api('keywords/jobs/' + currentJobId)).data; renderJobHeader(); } catch(_) {}
        } catch(e) { toast(e.message, true); }
    }
    async function deleteAllClusters() {
        if (!currentJobId) return;
        const count = allClusters.length;
        if (!count) { toast('Нет кластеров для удаления', true); return; }
        if (!confirm(`Удалить все ${count} кластеров задачи?\nПривязанные запросы станут нераспределёнными.`)) return;
        try {
            const res = await api('keywords/clusters/all/' + currentJobId, { method: 'DELETE' });
            toast(`Удалено ${res.data?.deleted ?? count} кластеров`);
            await loadClusters();
            loadKeywords();
            try { currentJob = (await api('keywords/jobs/' + currentJobId)).data; renderJobHeader(); } catch(_) {}
        } catch(e) { toast('Ошибка: ' + e.message, true); }
    }
    async function deleteClusterArticle(clusterId) {
        if (!confirm('Открепить статью от кластера?\nСама статья останется в системе.')) return;
        try {
            await api('keywords/clusters/article/' + clusterId, { method: 'DELETE' });
            toast('Статья откреплена от кластера');
            await loadClusters();
        } catch(e) { toast('Ошибка: ' + e.message, true); }
    }

    async function generateArticle(clusterId) {
        const cluster = allClusters.find(c => c.id == clusterId);
        if (!cluster) { toast('Кластер не найден', true); return; }
        if (!cluster.intent) { toast('У кластера не задан интент — невозможно выбрать шаблон', true); return; }

        const name = cluster.name || 'Без названия';
        if (!confirm(`Создать статью из кластера «${name}»?\n\nИнтент: ${intentLabel(cluster.intent)}\nШаблон будет подобран автоматически по интенту.`)) return;

        const btn = document.querySelector(`#cluster_${clusterId} .btn-primary`);
        if (btn) { btn.disabled = true; btn.textContent = '⏳'; }

        try {
            const res = await api('keywords/clusters/generate-article/' + clusterId, {
                body: { strategy: 'rotate' }
            });

            const articleId = res.article_id || res.data?.article_id;
            const templateId = res.template_id || res.data?.template_id;

            toast(`Статья #${articleId} создана (шаблон #${templateId})`);
            logMsg(`Статья #${articleId} создана для кластера «${name}» [${cluster.intent}]`, 'log-ok');

            await loadClusters();
        } catch (e) {
            toast('Ошибка создания статьи: ' + e.message, true);
            logMsg('Ошибка создания статьи: ' + e.message, 'log-err');
            if (btn) { btn.disabled = false; btn.textContent = '📝 Создать статью'; }
        }
    }

    async function generateAllApproved() {
        const approved = allClusters.filter(c => c.status === 'approved' && c.intent && !c.article_id);
        if (!approved.length) { toast('Нет утверждённых кластеров без статьи', true); return; }
        if (!confirm(`Создать статьи для ${approved.length} утверждённых кластеров?\n\nШаблоны будут подобраны по интентам с ротацией.`)) return;

        logMsg(`Пакетная генерация: ${approved.length} кластеров`, 'log-info');
        switchMainTab('log');
        let ok = 0, fail = 0;

        for (const c of approved) {
            try {
                logMsg(`Создаю статью для «${c.name}» [${c.intent}]...`, 'log-info');
                await api('keywords/clusters/generate-article/' + c.id, {
                    body: { strategy: 'rotate' }
                });
                ok++;
                logMsg(`✓ Статья для «${c.name}» создана`, 'log-ok');
            } catch (e) {
                fail++;
                logMsg(`✗ «${c.name}»: ${e.message}`, 'log-err');
            }
        }

        logMsg(`--- Готово: ${ok} создано, ${fail} ошибок ---`, ok > 0 ? 'log-ok' : 'log-err');
        toast(`Создано ${ok} из ${approved.length} статей`);
        await loadClusters();
    }
    function showClusterKeywords(cid) { $('kwFilterCluster').value = cid; switchMainTab('keywords'); kwPage = 1; loadKeywords(); }

    function debounceSearch() { clearTimeout(searchTimer); searchTimer = setTimeout(() => { kwPage = 1; loadKeywords(); }, 300); }

    async function loadKeywords() {
        if (!currentJobId) return;
        const params = { page: kwPage, per_page: parseInt($('kwPerPage').value) || 100, sort: $('kwSort').value };
        const cl = $('kwFilterCluster').value;
        if (cl !== '') params.cluster_id = cl;
        const search = $('kwSearch').value.trim();
        if (search) params.search = search;
        try {
            const res = await api('keywords/raw/' + currentJobId, { params });
            renderKeywords(res.data || [], res.meta || {});
        } catch (e) { $('kwTableBody').innerHTML = '<tr><td colspan="6" class="muted" style="text-align:center;padding:20px">Ошибка загрузки</td></tr>'; }
    }

    function renderKeywords(keywords, meta) {
        if (!keywords.length) {
            $('kwTableBody').innerHTML = '<tr><td colspan="6" class="muted" style="text-align:center;padding:20px">Нет данных</td></tr>';
            $('kwPagInfo').textContent = ''; return;
        }
        $('kwTableBody').innerHTML = keywords.map(kw => {
            const clName = allClusters.find(c => c.id == kw.cluster_id)?.name || '';
            return `<tr>
            <td>${esc(kw.keyword)}</td>
            <td><input class="kw-editable" value="${kw.volume ?? ''}" onchange="updateKw(${kw.id},'volume',this.value)" title="Частотность"></td>
            <td><input class="kw-editable" value="${kw.competition ?? ''}" onchange="updateKw(${kw.id},'competition',this.value)" title="Конкуренция" style="width:60px"></td>
            <td><input class="kw-editable" value="${kw.cpc ?? ''}" onchange="updateKw(${kw.id},'cpc',this.value)" title="CPC" style="width:60px"></td>
            <td>${clName ? '<span class="kw-cluster-tag">' + esc(clName) + '</span>' : '<span class="muted">-</span>'}</td>
            <td><button class="btn btn-sm btn-ghost" onclick="deleteKeyword(${kw.id})" title="Удалить">✕</button></td>
        </tr>`;
        }).join('');
        const total = meta.total || keywords.length;
        const pages = meta.pages || 1;
        $('kwPagInfo').textContent = total + ' запросов / стр. ' + kwPage + ' из ' + pages;
        $('kwPrev').disabled = kwPage <= 1;
        $('kwNext').disabled = kwPage >= pages;
    }

    function kwPageNav(dir) { kwPage = Math.max(1, kwPage + dir); loadKeywords(); }
    async function deleteKeyword(id) { try { await api('keywords/raw/item/' + id, { method: 'DELETE' }); loadKeywords(); } catch(e) { toast('Ошибка', true); } }

    async function updateKw(id, field, value) {
        const body = {};
        body[field] = value === '' ? null : (field === 'competition' || field === 'cpc' ? parseFloat(value) : parseInt(value));
        try { await api('keywords/raw/update/' + id, { method: 'PUT', body }); } catch(e) { toast('Ошибка сохранения', true); }
    }

    function updateClusterFilter() {
        const sel = $('kwFilterCluster');
        while (sel.options.length > 2) sel.remove(2);
        allClusters.forEach(c => { const o = document.createElement('option'); o.value = c.id; o.textContent = c.name; sel.appendChild(o); });
    }

    function logMsg(msg, cls = 'log-info') {
        const box = $('logBox');
        const first = box.querySelector('.log-info:only-child');
        if (first && first.textContent === 'Журнал пуст') first.remove();
        const line = document.createElement('div');
        line.className = cls;
        line.textContent = '[' + new Date().toLocaleTimeString('ru-RU') + '] ' + msg;
        box.appendChild(line);
        box.scrollTop = box.scrollHeight;
    }

    function setProgress(pct) { $('progressFill').style.width = Math.min(100, Math.max(0, pct)) + '%'; }

    async function loadProfileHeader() {
        if (!currentProfileId) {
            window.location.href = '/admin_advanced/seo_profile_page.php';
            return;
        }
        try {
            const res = await api('profiles/' + currentProfileId);
            if (!res.success) { window.location.href = '/admin_advanced/seo_profile_page.php'; return; }
            const p = res.data;
            $('topbarProfileName').textContent = p.name;
            $('topbarProfileMeta').textContent = (p.slug || '') + (p.domain ? ' \u00b7 ' + p.domain : '');
            const iconEl = $('topbarProfileIcon');
            if (p.icon_path) {
                iconEl.innerHTML = '<img src="../controllers/router.php?r=profiles/' + p.id + '/icon" style="width:100%;height:100%;object-fit:cover;border-radius:8px">';
            } else {
                iconEl.textContent = (p.name || '?')[0].toUpperCase();
                iconEl.style.color = p.color_scheme || '#6366f1';
            }
        } catch(e) { console.error('loadProfileHeader', e); }
    }

    (async function init() {
        if (!currentProfileId) { window.location.href = '/admin_advanced/seo_profile_page.php'; return; }
        loadProfileHeader();
        await loadIntentTypes();
        loadJobs();
    })();
</script>
</body>
</html>