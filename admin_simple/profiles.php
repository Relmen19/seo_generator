<?php
require_once __DIR__ . '/../auth.php';
requireAuth();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Профили — SEO Studio</title>
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
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; font-size: 14px; line-height: 1.5; }

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
.page { max-width: 1080px; margin: 0 auto; padding: 28px 24px; }

/* ─── Buttons ─── */
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border: 1px solid transparent; border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; cursor: pointer; transition: .15s; text-decoration: none; white-space: nowrap; }
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
.btn-icon { padding: 7px; border-radius: var(--radius-sm); }

/* ─── Cards ─── */
.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-sm); }
.card-body { padding: 20px; }
.card-header { padding: 16px 20px; border-bottom: 1px solid var(--border-light); display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.card-title { font-size: 14px; font-weight: 700; color: var(--text); }
.card-footer { padding: 14px 20px; border-top: 1px solid var(--border-light); display: flex; align-items: center; justify-content: flex-end; gap: 8px; }

/* ─── Profile grid ─── */
.profile-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
.profile-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); padding: 20px; cursor: pointer; transition: .2s; position: relative; overflow: hidden; }
.profile-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; border-radius: var(--radius-lg) 0 0 var(--radius-lg); background: var(--accent); }
.profile-card:hover { box-shadow: var(--shadow); border-color: rgba(91,91,214,.3); transform: translateY(-1px); }
.profile-card-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.profile-avatar { width: 44px; height: 44px; border-radius: 10px; background: var(--accent-light); display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 700; color: var(--accent); flex-shrink: 0; overflow: hidden; }
.profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
.profile-name { font-size: 15px; font-weight: 700; color: var(--text); line-height: 1.2; }
.profile-slug { font-size: 11px; color: var(--text-3); font-family: 'SF Mono', monospace; margin-top: 2px; }
.profile-desc { font-size: 12.5px; color: var(--text-2); line-height: 1.5; margin-bottom: 14px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 38px; }
.profile-stats { display: flex; gap: 16px; }
.profile-stat { font-size: 12px; color: var(--text-3); }
.profile-stat b { font-weight: 700; color: var(--text-2); }
.badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 100px; font-size: 11px; font-weight: 600; }
.badge-active { background: var(--success-light); color: var(--success); }
.badge-inactive { background: var(--danger-light); color: var(--danger); }

/* ─── Page header ─── */
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; gap: 12px; }
.page-header-title { font-size: 22px; font-weight: 700; color: var(--text); letter-spacing: -.3px; }
.page-header-sub { font-size: 13px; color: var(--text-2); margin-top: 2px; }

/* ─── Workspace ─── */
.ws-header { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); padding: 20px 24px; margin-bottom: 20px; display: flex; align-items: center; gap: 16px; }
.ws-back { background: none; border: 1px solid var(--border); border-radius: var(--radius-sm); width: 34px; height: 34px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--text-2); transition: .15s; flex-shrink: 0; }
.ws-back:hover { background: var(--bg); color: var(--text); }
.ws-avatar { width: 52px; height: 52px; border-radius: 12px; background: var(--accent-light); display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 700; color: var(--accent); flex-shrink: 0; overflow: hidden; }
.ws-avatar img { width: 100%; height: 100%; object-fit: cover; }
.ws-info { flex: 1; min-width: 0; }
.ws-name { font-size: 18px; font-weight: 700; color: var(--text); }
.ws-meta { font-size: 12px; color: var(--text-3); margin-top: 2px; font-family: 'SF Mono', monospace; }
.ws-actions { display: flex; gap: 8px; flex-shrink: 0; flex-wrap: wrap; }

/* ─── Tabs ─── */
.tabs-bar { display: flex; gap: 0; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-sm); margin-bottom: 20px; overflow-x: auto; }
.tab { padding: 12px 20px; font-size: 13px; font-weight: 600; color: var(--text-2); cursor: pointer; border-bottom: 2px solid transparent; transition: .15s; white-space: nowrap; user-select: none; }
.tab:hover { color: var(--text); }
.tab.active { color: var(--accent); border-bottom-color: var(--accent); }

/* ─── Forms ─── */
.field { margin-bottom: 16px; }
.field label { display: block; font-size: 12px; font-weight: 600; color: var(--text-2); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 6px; }
.field input[type="text"], .field input[type="url"], .field input[type="password"], .field textarea, .field select {
    width: 100%; padding: 9px 12px; background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius-sm);
    color: var(--text); font-size: 13px; font-family: inherit; outline: none; transition: border .15s;
}
.field input:focus, .field textarea:focus, .field select:focus { border-color: var(--accent); background: var(--surface); }
.field textarea { resize: vertical; min-height: 80px; }
.field-hint { font-size: 11.5px; color: var(--text-3); margin-top: 4px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-row.col-3 { grid-template-columns: 1fr 1fr 1fr; }
@media (max-width: 640px) { .form-row { grid-template-columns: 1fr; } }
.field-full { grid-column: 1 / -1; }

/* ─── Stats row (overview) ─── */
.stats-row { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; margin-bottom: 20px; }
.stat-box { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px 18px; text-align: center; box-shadow: var(--shadow-sm); }
.stat-box-value { font-size: 28px; font-weight: 800; color: var(--text); line-height: 1; }
.stat-box-label { font-size: 11px; font-weight: 600; color: var(--text-3); text-transform: uppercase; letter-spacing: .4px; margin-top: 4px; }

/* ─── Quick actions ─── */
.quick-links { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.quick-link { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px 18px; display: flex; align-items: center; gap: 12px; cursor: pointer; transition: .15s; text-decoration: none; }
.quick-link:hover { border-color: rgba(91,91,214,.3); box-shadow: var(--shadow); }
.quick-link-icon { width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
.quick-link-icon.purple { background: var(--accent-light); color: var(--accent); }
.quick-link-icon.blue { background: #dbeafe; color: #1d4ed8; }
.quick-link-text { font-size: 13px; font-weight: 600; color: var(--text); }
.quick-link-sub { font-size: 11.5px; color: var(--text-3); margin-top: 1px; }
.quick-link-arrow { margin-left: auto; color: var(--text-3); transition: .15s; }
.quick-link:hover .quick-link-arrow { color: var(--accent); transform: translateX(2px); }

/* ─── Section block ─── */
.section { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-sm); margin-bottom: 16px; overflow: hidden; }
.section-head { padding: 14px 20px; border-bottom: 1px solid var(--border-light); display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.section-head-title { font-size: 13px; font-weight: 700; color: var(--text); }
.section-body { padding: 20px; }
.section-foot { padding: 12px 20px; border-top: 1px solid var(--border-light); display: flex; justify-content: flex-end; gap: 8px; background: var(--bg); }

/* ─── Icon upload ─── */
.icon-drop { width: 100px; height: 100px; border: 2px dashed var(--border); border-radius: var(--radius-lg); display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; transition: .2s; background: var(--bg); position: relative; overflow: hidden; flex-shrink: 0; }
.icon-drop:hover { border-color: var(--accent); background: var(--accent-light); }
.icon-drop.has-img { border-style: solid; }
.icon-drop img { width: 100%; height: 100%; object-fit: cover; }
.icon-drop-hint { font-size: 10.5px; color: var(--text-3); text-align: center; padding: 4px; margin-top: 4px; }
.icon-drop input { display: none; }
.icon-drop .icon-remove { position: absolute; top: 4px; right: 4px; width: 20px; height: 20px; background: var(--danger); color: #fff; border: none; border-radius: 50%; cursor: pointer; font-size: 10px; display: none; align-items: center; justify-content: center; }
.icon-drop.has-img .icon-remove { display: flex; }

/* ─── Theme picker ─── */
.theme-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 10px; }
.theme-tile { border: 2px solid var(--border); border-radius: var(--radius); padding: 12px; cursor: pointer; transition: .15s; }
.theme-tile:hover { border-color: rgba(91,91,214,.4); }
.theme-tile.selected { border-color: var(--accent); background: var(--accent-light); }
.theme-tile-preview { height: 60px; border-radius: 6px; margin-bottom: 10px; overflow: hidden; display: flex; flex-direction: column; padding: 8px; gap: 4px; position: relative; }
.tp-bar { height: 4px; border-radius: 2px; }
.tp-line { height: 3px; border-radius: 1px; opacity: .7; }
.tp-line-sm { height: 3px; border-radius: 1px; opacity: .4; width: 60%; }
.theme-tile-name { font-size: 13px; font-weight: 700; color: var(--text); }
.theme-tile-desc { font-size: 11px; color: var(--text-3); margin-top: 2px; }
.theme-tile.selected .theme-tile-name { color: var(--accent); }

/* ─── Telegram section ─── */
.tg-connect { display: flex; align-items: flex-start; gap: 16px; }
.tg-connect-icon { width: 44px; height: 44px; background: #e8f0fe; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 20px; }
.tg-connect-body { flex: 1; min-width: 0; }
.tg-status { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 100px; }
.tg-status.connected { background: var(--success-light); color: var(--success); }
.tg-status.disconnected { background: var(--danger-light); color: var(--danger); }
.tg-channel-info { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: var(--bg); border-radius: var(--radius-sm); border: 1px solid var(--border); margin-top: 10px; }
.tg-ch-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--accent-light); display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--accent); font-size: 13px; overflow: hidden; flex-shrink: 0; }
.tg-ch-avatar img { width: 100%; height: 100%; object-fit: cover; }
.tg-ch-name { font-size: 13px; font-weight: 600; color: var(--text); }
.tg-ch-id { font-size: 11px; color: var(--text-3); }

/* ─── Format tiles ─── */
.format-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
.format-tile { border: 2px solid var(--border); border-radius: var(--radius); padding: 14px; cursor: pointer; transition: .15s; }
.format-tile:hover { border-color: rgba(91,91,214,.4); }
.format-tile.selected { border-color: var(--accent); background: var(--accent-light); }
.format-tile-icon { font-size: 22px; margin-bottom: 8px; }
.format-tile-name { font-size: 13px; font-weight: 700; color: var(--text); }
.format-tile-desc { font-size: 11px; color: var(--text-3); margin-top: 3px; line-height: 1.4; }
.format-tile.selected .format-tile-name { color: var(--accent); }

/* ─── Render blocks ─── */
.render-blocks-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; }
.rb-chip { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border: 1px solid var(--border); border-radius: var(--radius-sm); cursor: pointer; transition: .15s; user-select: none; font-size: 12px; font-weight: 500; color: var(--text-2); }
.rb-chip:hover { border-color: rgba(91,91,214,.4); background: var(--accent-light); }
.rb-chip.on { border-color: var(--accent); background: var(--accent-light); color: var(--accent); font-weight: 600; }
.rb-chip-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--border); transition: .15s; flex-shrink: 0; }
.rb-chip.on .rb-chip-dot { background: var(--accent); }

/* ─── Templates ─── */
.tpl-toolbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; gap: 12px; }
.tpl-view-toggle { display: flex; border: 1px solid var(--border); border-radius: var(--radius-sm); overflow: hidden; }
.tpl-view-btn { padding: 6px 10px; border: none; background: transparent; cursor: pointer; color: var(--text-3); transition: .15s; }
.tpl-view-btn.active { background: var(--accent); color: #fff; }
.tpl-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px; }
.tpl-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px; cursor: pointer; transition: .2s; }
.tpl-card:hover { border-color: rgba(91,91,214,.3); box-shadow: var(--shadow); }
.tpl-card-name { font-size: 14px; font-weight: 700; color: var(--text); margin-bottom: 4px; }
.tpl-card-meta { font-size: 11.5px; color: var(--text-3); margin-bottom: 10px; }
.tpl-blocks { display: flex; flex-wrap: wrap; gap: 4px; }
.tpl-block-chip { font-size: 10.5px; padding: 2px 7px; border-radius: 100px; background: var(--bg); border: 1px solid var(--border); color: var(--text-2); }

/* ─── Table view ─── */
.tpl-table { width: 100%; border-collapse: collapse; }
.tpl-table th { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; color: var(--text-3); padding: 10px 14px; text-align: left; border-bottom: 1px solid var(--border); background: var(--bg); }
.tpl-table td { padding: 11px 14px; border-bottom: 1px solid var(--border-light); vertical-align: middle; }
.tpl-table tr:last-child td { border-bottom: none; }
.tpl-table tr:hover td { background: #fafbfc; cursor: pointer; }

/* ─── Template detail ─── */
.tpl-detail-header { display: flex; align-items: center; gap: 14px; margin-bottom: 20px; }
.tpl-detail-title { font-size: 18px; font-weight: 700; color: var(--text); }
.tpl-detail-meta { font-size: 12px; color: var(--text-3); margin-top: 2px; }

/* ─── Blocks list ─── */
.blocks-list { display: flex; flex-direction: column; gap: 8px; }
.block-row { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 12px 16px; display: flex; align-items: center; gap: 12px; }
.block-row-sort { font-size: 11px; font-weight: 700; color: var(--text-3); width: 22px; text-align: center; flex-shrink: 0; }
.block-row-type { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 100px; background: var(--accent-light); color: var(--accent); flex-shrink: 0; }
.block-row-name { font-size: 13px; color: var(--text); flex: 1; }

/* ─── SSE / AI ─── */
.ai-progress { background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 14px 16px; margin-top: 12px; }
.ai-step { display: flex; align-items: center; gap: 10px; padding: 5px 0; font-size: 12.5px; color: var(--text-2); }
.ai-step-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--border); flex-shrink: 0; transition: .2s; }
.ai-step.active .ai-step-dot { background: var(--accent); animation: pulse .8s ease infinite; }
.ai-step.done .ai-step-dot { background: var(--success); }
.ai-step.error .ai-step-dot { background: var(--danger); }
.ai-step.active { color: var(--text); font-weight: 600; }
@keyframes pulse { 0%,100%{opacity:1}50%{opacity:.4} }

/* ─── Toast ─── */
.toast-wrap { position: fixed; bottom: 24px; right: 24px; z-index: 500; display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
.toast { background: var(--text); color: #fff; padding: 10px 18px; border-radius: var(--radius-sm); font-size: 13px; box-shadow: var(--shadow); opacity: 0; transform: translateY(8px); transition: .25s; max-width: 360px; }
.toast.show { opacity: 1; transform: translateY(0); }
.toast.ok { background: var(--success); }
.toast.err { background: var(--danger); }
.toast.info { background: var(--accent); }

/* ─── Spinner ─── */
.spin { display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(0,0,0,.1); border-top-color: currentColor; border-radius: 50%; animation: spin .6s linear infinite; vertical-align: middle; }
.spin-white { border-color: rgba(255,255,255,.3); border-top-color: #fff; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ─── Empty state ─── */
.empty { text-align: center; padding: 48px 20px; color: var(--text-3); }
.empty-icon { font-size: 36px; opacity: .5; margin-bottom: 12px; }
.empty-title { font-size: 15px; font-weight: 600; color: var(--text-2); margin-bottom: 6px; }
.empty-sub { font-size: 13px; }

/* ─── Overview desc block ─── */
.overview-desc { font-size: 14px; color: var(--text-2); line-height: 1.7; padding: 14px 0; }
.divider { height: 1px; background: var(--border-light); margin: 4px 0 16px; }

/* ─── Collapsible GPT ─── */
.gpt-toggle { display: flex; align-items: center; justify-content: space-between; cursor: pointer; user-select: none; padding-bottom: 10px; border-bottom: 1px solid var(--border-light); margin-bottom: 14px; }
.gpt-toggle-title { font-size: 13px; font-weight: 700; color: var(--text); }
.gpt-toggle-arrow { font-size: 11px; color: var(--text-3); transition: transform .2s; }
.gpt-toggle.open .gpt-toggle-arrow { transform: rotate(180deg); }
.gpt-body { display: none; }
.gpt-body.open { display: block; }

/* ─── Regenerate bar ─── */
.regen-bar { display: flex; align-items: center; gap: 8px; padding: 10px 14px; background: #faf5ff; border: 1px solid #e9d5ff; border-radius: var(--radius-sm); margin-bottom: 14px; }
.regen-bar-text { font-size: 12px; color: #7c3aed; flex: 1; }

/* ─── Review result ─── */
.review-box { background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 14px 16px; margin-top: 12px; }
.review-score { font-size: 22px; font-weight: 800; color: var(--accent); margin-bottom: 8px; }
.review-suggestions { font-size: 12.5px; color: var(--text-2); line-height: 1.7; }

/* ─── Color dot ─── */
.color-dot { width: 18px; height: 18px; border-radius: 50%; border: 2px solid rgba(0,0,0,.08); display: inline-block; vertical-align: middle; margin-right: 6px; }

/* ─── Advanced toggle ─── */
.adv-toggle { display: inline-flex; align-items: center; gap: 8px; padding: 5px 12px; border: 1px solid var(--border); border-radius: 100px; background: var(--surface); cursor: pointer; font-size: 12px; color: var(--text-2); transition: .15s; user-select: none; }
.adv-toggle:hover { border-color: var(--accent); color: var(--accent); }
.adv-toggle.on { background: var(--accent-light); border-color: var(--accent); color: var(--accent); font-weight: 600; }
.adv-switch { width: 28px; height: 16px; border-radius: 100px; background: var(--border); position: relative; transition: .15s; flex-shrink: 0; }
.adv-switch::after { content: ''; position: absolute; top: 2px; left: 2px; width: 12px; height: 12px; border-radius: 50%; background: #fff; transition: .15s; }
.adv-toggle.on .adv-switch { background: var(--accent); }
.adv-toggle.on .adv-switch::after { left: 14px; }
.adv-only { display: none; }
body.advanced .adv-only { display: revert; }
body.advanced .adv-only.form-row { display: grid; }
body.advanced .adv-only.section { display: block; }
body.advanced .adv-only.inline-flex { display: inline-flex; }
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
        <span class="topbar-page" id="topbarPage">Профили</span>
    </div>
    <div class="topbar-right">
        <a href="articles.php" class="topbar-nav-link">Статьи</a>
        <a href="../admin_advanced/seo_profile_page.php" class="topbar-nav-link" style="color:var(--accent);font-weight:600">⚡ Advanced</a>
        <a href="../logout.php" class="topbar-nav-link btn-logout">Выйти</a>
    </div>
</div>

<!-- Toast container -->
<div class="toast-wrap" id="toastWrap"></div>

<!-- ─── Profile list view ─── -->
<div class="page" id="listView">
    <div class="page-header">
        <div>
            <div class="page-header-title">Профили</div>
            <div class="page-header-sub" id="listSubtitle">Загрузка...</div>
        </div>
    </div>
    <div class="profile-grid" id="profileGrid">
        <div class="empty"><div class="spin"></div></div>
    </div>
</div>

<!-- ─── Profile workspace ─── -->
<div class="page" id="wsView" style="display:none">
    <!-- Workspace header -->
    <div class="ws-header" id="wsHeader">
        <button class="ws-back" onclick="showList()" title="Назад">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M10 3L5 8l5 5"/>
            </svg>
        </button>
        <div class="ws-avatar" id="wsAvatar">?</div>
        <div class="ws-info">
            <div class="ws-name" id="wsName">—</div>
            <div class="ws-meta" id="wsMeta">—</div>
        </div>
        <div class="ws-actions">
            <span class="badge" id="wsStatusBadge"></span>
            <div class="adv-toggle" id="advToggle" onclick="toggleAdvanced()" title="Показать расширенные настройки">
                <span>Расширенный</span>
                <span class="adv-switch"></span>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-bar">
        <div class="tab active" data-tab="overview">Обзор</div>
        <div class="tab" data-tab="settings">Настройки</div>
        <div class="tab" data-tab="branding">Брендинг</div>
        <div class="tab" data-tab="brief">AI Бриф</div>
        <div class="tab" data-tab="templates">Шаблоны</div>
        <div class="tab" data-tab="telegram">Telegram</div>
    </div>

    <!-- ─── Tab: Overview ─── -->
    <div id="tab-overview">
        <div class="stats-row" id="statsRow">
            <div class="stat-box"><div class="stat-box-value" id="stTemplates">—</div><div class="stat-box-label">Шаблонов</div></div>
            <div class="stat-box"><div class="stat-box-value" id="stArticles">—</div><div class="stat-box-label">Статей</div></div>
            <div class="stat-box"><div class="stat-box-value" id="stPublished">—</div><div class="stat-box-label">Опубликовано</div></div>
        </div>

        <div class="section" style="margin-bottom:16px">
            <div class="section-head"><span class="section-head-title">Описание</span></div>
            <div class="section-body">
                <div class="overview-desc" id="overviewDesc">—</div>
            </div>
        </div>

        <div class="quick-links" id="quickLinks">
            <a class="quick-link" id="linkArticles" href="#">
                <div class="quick-link-icon blue">📝</div>
                <div>
                    <div class="quick-link-text">Статьи</div>
                    <div class="quick-link-sub">Контент и публикация</div>
                </div>
                <div class="quick-link-arrow">→</div>
            </a>
        </div>
    </div>

    <!-- ─── Tab: Settings ─── -->
    <div id="tab-settings" style="display:none">
        <div class="section">
            <div class="section-head"><span class="section-head-title">Основное</span></div>
            <div class="section-body">
                <div class="form-row">
                    <div class="field">
                        <label>Название</label>
                        <input type="text" id="sName" placeholder="Название профиля">
                    </div>
                    <div class="field">
                        <label>Язык</label>
                        <select id="sLang">
                            <option value="ru">Русский</option>
                            <option value="en">English</option>
                            <option value="uk">Українська</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label>Тон</label>
                        <select id="sTone">
                            <option value="professional">Профессиональный</option>
                            <option value="friendly">Дружелюбный</option>
                            <option value="academic">Академический</option>
                            <option value="casual">Разговорный</option>
                            <option value="persuasive">Убеждающий</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Ниша</label>
                        <input type="text" id="sNiche" placeholder="Медицина, e-commerce...">
                    </div>
                </div>
                <div class="field">
                    <label>Описание проекта</label>
                    <textarea id="sDescription" rows="3" placeholder="Подробное описание проекта..."></textarea>
                </div>
            </div>
        </div>

        <div class="section adv-only">
            <div class="section-head">
                <span class="section-head-title">GPT-настройки</span>
                <button class="btn btn-secondary btn-sm" id="btnRegenGpt" onclick="regenGpt()">
                    <span id="regenGptIcon">✨</span> Перегенерировать
                </button>
            </div>
            <div class="section-body">
                <div id="regenProgress" style="display:none">
                    <div class="ai-progress">
                        <div class="ai-step" id="regenStep1"><div class="ai-step-dot"></div>Анализ описания проекта...</div>
                        <div class="ai-step" id="regenStep2"><div class="ai-step-dot"></div>Генерация GPT-персоны...</div>
                        <div class="ai-step" id="regenStep3"><div class="ai-step-dot"></div>Формирование правил...</div>
                    </div>
                </div>
                <div class="field">
                    <label>GPT-персона</label>
                    <textarea id="sGptPersona" rows="4" placeholder="Ты — эксперт в области..."></textarea>
                    <div class="field-hint">Системная роль AI при генерации контента</div>
                </div>
                <div class="field">
                    <label>Правила генерации</label>
                    <textarea id="sGptRules" rows="4" placeholder="— Всегда указывай источники&#10;— Используй заголовки H2/H3..."></textarea>
                    <div class="field-hint">Дополнительные инструкции (по одному на строку)</div>
                </div>
            </div>
            <div class="section-foot">
                <button class="btn btn-primary" onclick="saveSettings()">
                    <span id="saveSettingsSpinner"></span> Сохранить
                </button>
            </div>
        </div>
    </div>

    <!-- ─── Tab: Branding ─── -->
    <div id="tab-branding" style="display:none">
        <div class="section" style="margin-bottom:16px">
            <div class="section-head"><span class="section-head-title">Иконка профиля</span></div>
            <div class="section-body">
                <div style="display:flex;gap:20px;align-items:flex-start">
                    <div class="icon-drop" id="iconDrop" onclick="iconDropClick()">
                        <input type="file" id="iconFile" accept="image/*" onchange="iconFileChange(this)">
                        <div id="iconPlaceholder" style="display:flex;flex-direction:column;align-items:center;gap:4px">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#a0aec0" stroke-width="1.5">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            <span class="icon-drop-hint">Загрузить</span>
                        </div>
                        <img id="iconPreview" style="display:none;position:absolute;inset:0;width:100%;height:100%;object-fit:cover" src="" alt="">
                        <button class="icon-remove" id="iconRemoveBtn" onclick="removeIcon(event)" title="Удалить">✕</button>
                    </div>
                    <div>
                        <div style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:6px">Иконка профиля</div>
                        <div style="font-size:12px;color:var(--text-3);line-height:1.6">Отображается в списке профилей.<br>PNG, JPEG, WebP, SVG — до 2 МБ.</div>
                        <div style="margin-top:10px;display:flex;gap:8px">
                            <button class="btn btn-secondary btn-sm" onclick="iconDropClick()">Выбрать файл</button>
                            <button class="btn btn-ghost btn-sm" id="iconRemoveBtn2" onclick="removeIcon(event)" style="color:var(--danger)">Удалить</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-head"><span class="section-head-title">Тема оформления статей</span></div>
            <div class="section-body">
                <div class="theme-grid" id="themePicker"></div>
            </div>
            <div class="section-foot">
                <button class="btn btn-primary" onclick="saveBranding()">
                    <span id="saveBrandingSpinner"></span> Сохранить
                </button>
            </div>
        </div>
    </div>

    <!-- ─── Tab: Brief ─── -->
    <div id="tab-brief" style="display:none">
        <div class="section">
            <div class="section-head">
                <span class="section-head-title">AI Бриф (мастер)</span>
                <div style="display:flex;gap:8px">
                    <button class="btn btn-ghost btn-sm" onclick="sbReset()">Сбросить</button>
                    <button class="btn btn-primary btn-sm" onclick="sbSave()">Сохранить</button>
                </div>
            </div>
            <div class="section-body">
                <div style="font-size:12.5px;color:var(--text-2);margin-bottom:12px">
                    Клик-мастер: AI предлагает варианты, вы выбираете карточки. Из брифа автоматически собираются persona и правила для генерации статей.
                </div>
                <div id="sbProgress" style="font-size:12px;color:var(--text-3);margin-bottom:12px"></div>
                <div id="sbStep"></div>
                <div style="display:flex;gap:8px;justify-content:space-between;margin-top:16px">
                    <button class="btn btn-ghost btn-sm" id="sbBtnBack" onclick="sbPrev()">&larr; Назад</button>
                    <button class="btn btn-primary btn-sm" id="sbBtnNext" onclick="sbNext()">Далее &rarr;</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── Tab: Templates ─── -->
    <div id="tab-templates" style="display:none">
        <div class="tpl-toolbar">
            <span style="font-size:13px;color:var(--text-2)" id="tplCount">Загрузка...</span>
            <div class="tpl-view-toggle">
                <button class="tpl-view-btn active" id="btnTileView" onclick="setTplView('tiles')" title="Плитки">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><rect x="1" y="1" width="6" height="6" rx="1"/><rect x="9" y="1" width="6" height="6" rx="1"/><rect x="1" y="9" width="6" height="6" rx="1"/><rect x="9" y="9" width="6" height="6" rx="1"/></svg>
                </button>
                <button class="tpl-view-btn" id="btnListView" onclick="setTplView('list')" title="Список">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="4" x2="13" y2="4"/><line x1="3" y1="8" x2="13" y2="8"/><line x1="3" y1="12" x2="13" y2="12"/></svg>
                </button>
            </div>
        </div>
        <div id="tplContainer"></div>
    </div>

    <!-- ─── Tab: Telegram ─── -->
    <div id="tab-telegram" style="display:none">
        <div class="section" style="margin-bottom:16px">
            <div class="section-head">
                <span class="section-head-title">Подключение</span>
                <span class="tg-status disconnected" id="tgStatus">● Не подключено</span>
            </div>
            <div class="section-body">
                <div class="tg-connect">
                    <div class="tg-connect-icon">✈️</div>
                    <div class="tg-connect-body">
                        <div id="tgChannelInfo" style="display:none" class="tg-channel-info">
                            <div class="tg-ch-avatar" id="tgChAvatar"></div>
                            <div>
                                <div class="tg-ch-name" id="tgChName">—</div>
                                <div class="tg-ch-id" id="tgChId">—</div>
                            </div>
                            <button class="btn btn-ghost btn-xs" onclick="refreshTgChannel()" style="margin-left:auto">🔄 Обновить</button>
                        </div>
                        <div class="form-row" style="margin-top:10px">
                            <div class="field">
                                <label>Bot Token</label>
                                <input type="text" id="tgBotToken" placeholder="123456:ABC-DEF...">
                            </div>
                            <div class="field">
                                <label>Channel ID</label>
                                <input type="text" id="tgChannelId" placeholder="@mychannel или -100...">
                            </div>
                        </div>
                        <button class="btn btn-secondary btn-sm" id="btnTgTest" onclick="testTgConnection()">
                            <span id="tgTestSpinner"></span> Проверить подключение
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="section" style="margin-bottom:16px">
            <div class="section-head"><span class="section-head-title">Формат постов</span></div>
            <div class="section-body">
                <div class="format-grid" id="formatPicker">
                    <div class="format-tile" data-format="auto">
                        <div class="format-tile-icon">🤖</div>
                        <div class="format-tile-name">Авто</div>
                        <div class="format-tile-desc">Определяется по количеству блоков</div>
                    </div>
                    <div class="format-tile" data-format="single">
                        <div class="format-tile-icon">📎</div>
                        <div class="format-tile-name">Один пост</div>
                        <div class="format-tile-desc">Медиа-группа с подписью</div>
                    </div>
                    <div class="format-tile" data-format="series">
                        <div class="format-tile-icon">📚</div>
                        <div class="format-tile-name">Серия</div>
                        <div class="format-tile-desc">Несколько сообщений подряд</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-head">
                <span class="section-head-title">Блоки для рендера</span>
                <div style="display:flex;gap:8px">
                    <button class="btn btn-ghost btn-xs" onclick="toggleAllBlocks(true)">Все</button>
                    <button class="btn btn-ghost btn-xs" onclick="toggleAllBlocks(false)">Снять</button>
                </div>
            </div>
            <div class="section-body">
                <div style="font-size:12px;color:var(--text-3);margin-bottom:12px">Выберите типы блоков, которые будут рендериться как изображения в Telegram-постах.</div>
                <div class="render-blocks-grid" id="renderBlocksGrid"></div>
            </div>
            <div class="section-foot">
                <button class="btn btn-primary" onclick="saveTelegram()">
                    <span id="saveTgSpinner"></span> Сохранить
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ─── Template detail view ─── -->
<div class="page" id="tplView" style="display:none">
    <div class="tpl-detail-header">
        <button class="ws-back" onclick="backToTemplates()" title="К шаблонам">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M10 3L5 8l5 5"/>
            </svg>
        </button>
        <div>
            <div class="tpl-detail-title" id="tplDetailName">—</div>
            <div class="tpl-detail-meta" id="tplDetailMeta">—</div>
        </div>
        <div style="margin-left:auto;display:flex;gap:8px">
            <button class="btn btn-secondary btn-sm adv-only" id="btnTplReview" onclick="tplAiReview()">
                🔍 Ревью
            </button>
            <button class="btn btn-secondary btn-sm adv-only" id="btnTplRegen" onclick="tplAiRegen()">
                ✨ Перегенерировать
            </button>
        </div>
    </div>

    <div class="section adv-only" style="margin-bottom:16px">
        <div class="section-head"><span class="section-head-title">Описание</span></div>
        <div class="section-body">
            <div class="field">
                <div style="display:flex;gap:8px;margin-bottom:8px">
                    <button class="btn btn-secondary btn-sm" onclick="spLoadPurposes()" id="btnSpPurpose">✨ AI: варианты из брифа</button>
                    <button class="btn btn-ghost btn-sm" onclick="spClearPurposes()" id="btnSpClear" style="display:none">Скрыть варианты</button>
                </div>
                <div id="spPurposes" style="display:none;margin-bottom:8px"></div>
                <textarea id="tplDescription" rows="3" placeholder="Опишите назначение шаблона..."></textarea>
            </div>
        </div>
        <div class="section-foot">
            <button class="btn btn-primary btn-sm" onclick="saveTplDescription()">Сохранить</button>
        </div>
    </div>

    <div class="section">
        <div class="section-head"><span class="section-head-title">Блоки</span></div>
        <div class="section-body">
            <div id="tplBlocksList" class="blocks-list"></div>
        </div>
    </div>

    <!-- AI review result -->
    <div id="tplReviewResult" style="display:none" class="section" style="margin-top:16px">
        <div class="section-head"><span class="section-head-title">AI Ревью</span></div>
        <div class="section-body">
            <div class="review-box">
                <div class="review-score" id="reviewScore"></div>
                <div class="review-suggestions" id="reviewSuggestions"></div>
            </div>
        </div>
    </div>

    <!-- AI regen progress -->
    <div id="tplRegenProgress" style="display:none" class="section" style="margin-top:16px">
        <div class="section-head"><span class="section-head-title">Перегенерация...</span></div>
        <div class="section-body">
            <div class="ai-progress" id="tplRegenSteps"></div>
        </div>
    </div>
</div>

<script>
const API = '../controllers/router.php?r=';

// ─── State ───
const S = {
    profiles: [],
    profile: null,
    stats: null,
    templates: [],
    template: null,
    tab: 'overview',
    tplView: 'tiles',
    selectedFormat: 'auto',
    selectedTheme: 'default',
    enabledBlocks: new Set(),
};

const ALL_RENDER_BLOCKS = [
    { key: 'chart',            label: 'График' },
    { key: 'gauges',           label: 'Шкалы' },
    { key: 'before-after',     label: 'До/После' },
    { key: 'comparison-table', label: 'Сравнение' },
    { key: 'timeline',         label: 'Таймлайн' },
    { key: 'expert_panel',     label: 'Экспертная панель' },
    { key: 'feature_grid',     label: 'Feature Grid' },
    { key: 'info_cards',       label: 'Info Cards' },
    { key: 'radar_chart',      label: 'Радар' },
    { key: 'range_comparison', label: 'Диапазон' },
    { key: 'score_rings',      label: 'Кольца' },
    { key: 'spark_metrics',    label: 'Метрики' },
    { key: 'stacked_area',     label: 'Область' },
    { key: 'stats_counter',    label: 'Счётчики' },
    { key: 'verdict_card',     label: 'Вердикт' },
    { key: 'warning_block',    label: 'Предупреждение' },
];

const DEFAULT_BLOCKS = new Set([
    'chart','gauges','before-after','comparison-table','timeline',
    'expert_panel','feature_grid','info_cards','radar_chart',
    'range_comparison','score_rings','spark_metrics','stacked_area',
    'stats_counter','verdict_card','warning_block',
]);

const THEMES = [
    { key: 'default',   name: 'Apple Minimal', desc: 'Чистый, современный',
      bg: '#ffffff', accent: '#0071e3', line1: '#0071e3', line2: '#1d1d1f' },
    { key: 'editorial', name: 'Editorial',     desc: 'Журнальный стиль',
      bg: '#fafaf8', accent: '#c0392b', line1: '#c0392b', line2: '#2c2c2c' },
    { key: 'brutalist', name: 'Brutalist',     desc: 'Жирный, броский',
      bg: '#f5f500', accent: '#000000', line1: '#000000', line2: '#000000' },
];

// ─── Utils ───
function el(id) { return document.getElementById(id); }

function toast(msg, type='') {
    const wrap = el('toastWrap');
    const t = document.createElement('div');
    t.className = 'toast' + (type ? ' ' + type : '');
    t.textContent = msg;
    wrap.appendChild(t);
    setTimeout(() => t.classList.add('show'), 10);
    setTimeout(() => {
        t.classList.remove('show');
        setTimeout(() => t.remove(), 300);
    }, 3000);
}

async function api(path, method='GET', body=null) {
    const opts = { method, headers: {} };
    if (body !== null && body !== undefined) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    const res = await fetch(API + path, opts);
    const data = await res.json();
    if (!data.success) throw new Error(data.error || 'Ошибка API');
    return data.data;
}

function iconUrl(profileId) {
    return API + 'profiles/' + profileId + '/icon';
}

function profileInitial(name) {
    return (name || '?').charAt(0).toUpperCase();
}

// ─── Views ───
function showList() {
    el('listView').style.display = '';
    el('wsView').style.display = 'none';
    el('tplView').style.display = 'none';
    el('topbarPage').textContent = 'Профили';
    S.profile = null;
    S.template = null;
    loadProfiles();
}

function showWs(profile) {
    S.profile = profile;
    el('listView').style.display = 'none';
    el('wsView').style.display = 'block';
    el('tplView').style.display = 'none';

    // Header
    el('wsName').textContent = profile.name;
    el('wsMeta').textContent = profile.slug + (profile.niche ? ' · ' + profile.niche : '');
    el('topbarPage').textContent = profile.name;

    const badge = el('wsStatusBadge');
    badge.className = 'badge ' + (profile.is_active ? 'badge-active' : 'badge-inactive');
    badge.textContent = profile.is_active ? 'Активен' : 'Неактивен';

    const av = el('wsAvatar');
    if (profile.icon_path) {
        av.innerHTML = '<img src="' + iconUrl(profile.id) + '" alt="">';
    } else {
        av.innerHTML = '';
        av.textContent = profileInitial(profile.name);
        av.style.background = (profile.color_scheme || '#5b5bd6') + '22';
        av.style.color = profile.color_scheme || '#5b5bd6';
    }

    // Quick links
    el('linkArticles').href = 'articles.php?profile=' + profile.id;

    switchTab('overview');
    loadStats();
}

function switchTab(tab) {
    S.tab = tab;
    document.querySelectorAll('.tab').forEach(t => {
        t.classList.toggle('active', t.dataset.tab === tab);
    });
    ['overview','settings','branding','brief','templates','telegram'].forEach(t => {
        el('tab-' + t).style.display = t === tab ? '' : 'none';
    });

    if (tab === 'settings') fillSettings();
    if (tab === 'branding') fillBranding();
    if (tab === 'brief') sbInit();
    if (tab === 'templates') loadTemplates();
    if (tab === 'telegram') fillTelegram();
}

document.querySelectorAll('.tab').forEach(t => {
    t.addEventListener('click', () => switchTab(t.dataset.tab));
});

// ─── Profile list ───
async function loadProfiles() {
    el('profileGrid').innerHTML = '<div class="empty"><div class="spin"></div></div>';
    try {
        S.profiles = await api('profiles');
        renderProfileGrid();
    } catch(e) {
        el('profileGrid').innerHTML = '<div class="empty"><div class="empty-icon">⚠️</div><div class="empty-title">Ошибка загрузки</div><div class="empty-sub">' + e.message + '</div></div>';
    }
}

function renderProfileGrid() {
    const active = S.profiles.filter(p => p.is_active).length;
    el('listSubtitle').textContent = S.profiles.length + ' профилей · ' + active + ' активных';

    if (!S.profiles.length) {
        el('profileGrid').innerHTML = '<div class="empty"><div class="empty-icon">📁</div><div class="empty-title">Нет профилей</div></div>';
        return;
    }

    el('profileGrid').innerHTML = S.profiles.map(p => {
        const color = p.color_scheme || '#5b5bd6';
        const hasIcon = !!p.icon_path;
        const avatarHtml = hasIcon
            ? '<img src="' + iconUrl(p.id) + '" alt="">'
            : profileInitial(p.name);
        const avatarStyle = hasIcon ? '' : 'background:' + color + '22;color:' + color;
        return `
        <div class="profile-card" onclick="openProfile(${p.id})" style="--card-color:${color}">
            <div class="profile-card-header">
                <div class="profile-avatar" style="${avatarStyle}">${avatarHtml}</div>
                <div>
                    <div class="profile-name">${esc(p.name)}</div>
                    <div class="profile-slug">${esc(p.slug)}</div>
                </div>
                <span class="badge ${p.is_active ? 'badge-active' : 'badge-inactive'}" style="margin-left:auto">${p.is_active ? 'Вкл' : 'Выкл'}</span>
            </div>
            <div class="profile-desc">${esc(p.description || p.niche || '—')}</div>
            <div class="profile-stats">
                <div class="profile-stat"><b>${p.templates_count || '—'}</b> шаблонов</div>
                <div class="profile-stat"><b>${p.articles_count || '—'}</b> статей</div>
            </div>
        </div>`;
    }).join('');
}

async function openProfile(id) {
    try {
        const profile = await api('profiles/' + id);
        if (!profile || !profile.id) {
            throw new Error('Некорректные данные профиля');
        }
        try {
            showWs(profile);
        } catch (renderErr) {
            console.error('showWs failed', renderErr, profile);
            toast('Ошибка отрисовки: ' + renderErr.message, 'err');
        }
    } catch(e) {
        console.error('openProfile failed', e);
        toast(e.message, 'err');
    }
}

// ─── Stats ───
async function loadStats() {
    if (!S.profile) return;
    try {
        const stats = await api('profiles/' + S.profile.id + '/stats');
        S.stats = stats;
        el('stTemplates').textContent = stats.templates;
        el('stArticles').textContent = stats.articles;
        el('stPublished').textContent = stats.published;
        el('overviewDesc').textContent = S.profile.description || '—';
    } catch(e) {
        el('stTemplates').textContent = '—';
        el('stArticles').textContent = '—';
        el('stPublished').textContent = '—';
    }
}

// ─── Settings ───
function fillSettings() {
    const p = S.profile;
    if (!p) return;
    el('sName').value = p.name || '';
    el('sLang').value = p.language || 'ru';
    el('sTone').value = p.tone || 'professional';
    el('sNiche').value = p.niche || '';
    el('sDescription').value = p.description || '';
    el('sGptPersona').value = p.gpt_persona || '';
    el('sGptRules').value = p.gpt_rules || '';
}

async function saveSettings() {
    const spinner = el('saveSettingsSpinner');
    spinner.innerHTML = '<span class="spin spin-white"></span>';
    try {
        const updated = await api('profiles/' + S.profile.id, 'PUT', {
            name:        el('sName').value.trim(),
            language:    el('sLang').value,
            tone:        el('sTone').value,
            niche:       el('sNiche').value.trim() || null,
            description: el('sDescription').value.trim() || null,
            gpt_persona: el('sGptPersona').value.trim() || null,
            gpt_rules:   el('sGptRules').value.trim() || null,
        });
        S.profile = Object.assign(S.profile, updated);
        el('wsName').textContent = updated.name || S.profile.name;
        toast('Настройки сохранены', 'ok');
    } catch(e) {
        toast(e.message, 'err');
    }
    spinner.innerHTML = '';
}

async function regenGpt() {
    const desc = el('sDescription').value.trim() || S.profile.description || '';
    if (!desc) {
        toast('Заполните описание проекта для генерации', 'err');
        return;
    }
    el('btnRegenGpt').disabled = true;
    el('regenGptIcon').innerHTML = '<span class="spin"></span>';
    el('regenProgress').style.display = '';

    const steps = ['regenStep1','regenStep2','regenStep3'];
    steps.forEach(s => { el(s).className = 'ai-step'; });
    el('regenStep1').className = 'ai-step active';

    try {
        const result = await api('profiles/generate-from-description', 'POST', { description: desc });
        const profile = result.profile || result;

        el('regenStep1').className = 'ai-step done';
        el('regenStep2').className = 'ai-step active';
        await delay(300);
        el('regenStep2').className = 'ai-step done';
        el('regenStep3').className = 'ai-step active';
        await delay(300);
        el('regenStep3').className = 'ai-step done';

        if (profile.gpt_persona) el('sGptPersona').value = profile.gpt_persona;
        if (profile.gpt_rules)   el('sGptRules').value   = profile.gpt_rules;
        if (profile.tone)        el('sTone').value        = profile.tone;
        if (profile.language)    el('sLang').value        = profile.language;

        toast('GPT-настройки сгенерированы', 'ok');
    } catch(e) {
        steps.forEach(s => el(s).className = 'ai-step');
        toast(e.message, 'err');
    }
    el('btnRegenGpt').disabled = false;
    el('regenGptIcon').textContent = '✨';
    setTimeout(() => { el('regenProgress').style.display = 'none'; }, 2000);
}

function delay(ms) { return new Promise(r => setTimeout(r, ms)); }

// ─── Branding ───
function fillBranding() {
    const p = S.profile;
    if (!p) return;
    S.selectedTheme = p.theme || 'default';

    // Icon
    const drop = el('iconDrop');
    const preview = el('iconPreview');
    if (p.icon_path) {
        preview.src = iconUrl(p.id) + '&_=' + Date.now();
        preview.style.display = '';
        el('iconPlaceholder').style.display = 'none';
        drop.classList.add('has-img');
    } else {
        preview.style.display = 'none';
        el('iconPlaceholder').style.display = '';
        drop.classList.remove('has-img');
    }

    renderThemePicker();
}

function renderThemePicker() {
    el('themePicker').innerHTML = THEMES.map(t => `
    <div class="theme-tile ${S.selectedTheme === t.key ? 'selected' : ''}" onclick="selectTheme('${t.key}')">
        <div class="theme-tile-preview" style="background:${t.bg}">
            <div class="tp-bar" style="background:${t.accent}"></div>
            <div class="tp-line" style="background:${t.line2};width:80%"></div>
            <div class="tp-line-sm" style="background:${t.line2}"></div>
            <div class="tp-line" style="background:${t.accent};width:40%"></div>
        </div>
        <div class="theme-tile-name">${esc(t.name)}</div>
        <div class="theme-tile-desc">${esc(t.desc)}</div>
    </div>`).join('');
}

function selectTheme(key) {
    S.selectedTheme = key;
    renderThemePicker();
}

function iconDropClick() {
    el('iconFile').click();
}

function iconFileChange(input) {
    const file = input.files[0];
    if (!file) return;
    uploadIcon(file);
}

async function uploadIcon(file) {
    const form = new FormData();
    form.append('icon', file);
    try {
        const res = await fetch(API + 'profiles/' + S.profile.id + '/icon', {
            method: 'POST', body: form
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Ошибка загрузки');

        S.profile.icon_path = data.data.icon_path;
        const preview = el('iconPreview');
        preview.src = iconUrl(S.profile.id) + '&_=' + Date.now();
        preview.style.display = '';
        el('iconPlaceholder').style.display = 'none';
        el('iconDrop').classList.add('has-img');

        // Update workspace avatar
        el('wsAvatar').innerHTML = '<img src="' + iconUrl(S.profile.id) + '&_=' + Date.now() + '" alt="">';
        toast('Иконка загружена', 'ok');
    } catch(e) {
        toast(e.message, 'err');
    }
}

async function removeIcon(e) {
    e.stopPropagation();
    if (!S.profile.icon_path) return;
    try {
        await api('profiles/' + S.profile.id + '/icon', 'DELETE');
        S.profile.icon_path = null;
        el('iconPreview').style.display = 'none';
        el('iconPlaceholder').style.display = '';
        el('iconDrop').classList.remove('has-img');
        el('wsAvatar').innerHTML = profileInitial(S.profile.name);
        el('wsAvatar').style.background = (S.profile.color_scheme || '#5b5bd6') + '22';
        el('wsAvatar').style.color = S.profile.color_scheme || '#5b5bd6';
        toast('Иконка удалена', 'ok');
    } catch(e) {
        toast(e.message, 'err');
    }
}

async function saveBranding() {
    const spinner = el('saveBrandingSpinner');
    spinner.innerHTML = '<span class="spin spin-white"></span>';
    try {
        const updated = await api('profiles/' + S.profile.id, 'PUT', {
            theme: S.selectedTheme,
        });
        S.profile = Object.assign(S.profile, updated);
        toast('Брендинг сохранён', 'ok');
    } catch(e) {
        toast(e.message, 'err');
    }
    spinner.innerHTML = '';
}

// ─── Templates ───
async function loadTemplates() {
    if (!S.profile) return;
    el('tplContainer').innerHTML = '<div class="empty"><div class="spin"></div></div>';
    try {
        S.templates = await api('templates?profile_id=' + S.profile.id);
        el('tplCount').textContent = S.templates.length + ' шаблонов';
        renderTemplates();
    } catch(e) {
        el('tplContainer').innerHTML = '<div class="empty"><div class="empty-icon">⚠️</div><div class="empty-title">' + esc(e.message) + '</div></div>';
    }
}

function setTplView(mode) {
    S.tplView = mode;
    el('btnTileView').classList.toggle('active', mode === 'tiles');
    el('btnListView').classList.toggle('active', mode === 'list');
    renderTemplates();
}

function renderTemplates() {
    if (!S.templates.length) {
        el('tplContainer').innerHTML = '<div class="empty"><div class="empty-icon">📋</div><div class="empty-title">Нет шаблонов</div><div class="empty-sub">Шаблоны создаются в расширенной панели</div></div>';
        return;
    }

    if (S.tplView === 'tiles') {
        el('tplContainer').innerHTML = `<div class="tpl-grid">${S.templates.map(tplCard).join('')}</div>`;
    } else {
        el('tplContainer').innerHTML = `
        <div class="card">
            <table class="tpl-table">
                <thead><tr>
                    <th>Название</th><th>Блоков</th><th>Статей</th>
                </tr></thead>
                <tbody>${S.templates.map(t => `
                <tr onclick="openTemplate(${t.id})">
                    <td><span style="font-weight:600">${esc(t.name)}</span></td>
                    <td>${(t.blocks || []).length}</td>
                    <td>${t.articles_count || 0}</td>
                </tr>`).join('')}</tbody>
            </table>
        </div>`;
    }
}

function tplCard(t) {
    const blocks = t.blocks || [];
    const chips = blocks.slice(0, 6).map(b =>
        '<span class="tpl-block-chip">' + esc(b.type || b.name) + '</span>'
    ).join('');
    const more = blocks.length > 6 ? '<span class="tpl-block-chip">+' + (blocks.length-6) + '</span>' : '';
    return `
    <div class="tpl-card" onclick="openTemplate(${t.id})">
        <div class="tpl-card-name">${esc(t.name)}</div>
        <div class="tpl-card-meta">${blocks.length} блоков · ${t.articles_count || 0} статей</div>
        <div class="tpl-blocks">${chips}${more}</div>
    </div>`;
}

async function openTemplate(id) {
    try {
        const tpl = await api('templates/' + id);
        S.template = tpl;
        showTemplateDetail(tpl);
    } catch(e) {
        toast(e.message, 'err');
    }
}

function showTemplateDetail(tpl) {
    el('wsView').style.display = 'none';
    el('tplView').style.display = 'block';
    el('listView').style.display = 'none';
    el('topbarPage').textContent = tpl.name;

    el('tplDetailName').textContent = tpl.name;
    el('tplDetailMeta').textContent = (tpl.blocks || []).length + ' блоков · ' + (tpl.articles_count || 0) + ' статей';
    el('tplDescription').value = tpl.description || '';

    el('tplReviewResult').style.display = 'none';
    el('tplRegenProgress').style.display = 'none';

    renderBlocksList(tpl.blocks || []);
}

function renderBlocksList(blocks) {
    if (!blocks.length) {
        el('tplBlocksList').innerHTML = '<div class="empty" style="padding:24px"><div class="empty-sub">Блоки не добавлены</div></div>';
        return;
    }
    el('tplBlocksList').innerHTML = blocks.map((b, i) => `
    <div class="block-row">
        <div class="block-row-sort">${i+1}</div>
        <span class="block-row-type">${esc(b.type)}</span>
        <div class="block-row-name">${esc(b.name || '—')}</div>
    </div>`).join('');
}

function backToTemplates() {
    el('tplView').style.display = 'none';
    el('wsView').style.display = 'block';
    el('topbarPage').textContent = S.profile ? S.profile.name : 'Профили';
    S.template = null;
}

async function saveTplDescription() {
    if (!S.template) return;
    try {
        await api('templates/' + S.template.id, 'PUT', {
            description: el('tplDescription').value.trim() || null
        });
        toast('Описание сохранено', 'ok');
    } catch(e) {
        toast(e.message, 'err');
    }
}

async function tplAiReview() {
    if (!S.template) return;
    el('btnTplReview').disabled = true;
    el('btnTplReview').innerHTML = '<span class="spin"></span> Ревью...';
    el('tplReviewResult').style.display = 'none';
    try {
        const result = await api('templates/' + S.template.id + '/ai-review', 'POST', {});
        const score   = result.score ?? '';
        const suggestions = Array.isArray(result.suggestions)
            ? result.suggestions.map(s => '• ' + s).join('\n')
            : (result.summary || JSON.stringify(result));

        el('reviewScore').textContent = score ? 'Оценка: ' + score + '/10' : '';
        el('reviewSuggestions').textContent = suggestions;
        el('tplReviewResult').style.display = '';
    } catch(e) {
        toast(e.message, 'err');
    }
    el('btnTplReview').disabled = false;
    el('btnTplReview').innerHTML = '🔍 Ревью';
}

function tplAiRegen() {
    if (!S.template) return;
    const purpose = el('tplDescription').value.trim() || S.template.description || S.template.name;
    el('tplRegenProgress').style.display = '';
    el('tplRegenSteps').innerHTML = '<div class="ai-step active"><div class="ai-step-dot"></div>Перегенерация шаблона через AI...</div>';
    el('btnTplRegen').disabled = true;
    regenSse(purpose);
}

async function regenSse(purpose) {
    const steps = el('tplRegenSteps');
    const addStep = (msg, state='active') => {
        const prev = steps.querySelector('.ai-step.active');
        if (prev) prev.className = 'ai-step done';
        const d = document.createElement('div');
        d.className = 'ai-step ' + state;
        d.innerHTML = '<div class="ai-step-dot"></div>' + esc(msg);
        steps.appendChild(d);
    };

    try {
        const res = await fetch(API + 'templates/' + S.template.id + '/ai-regenerate-sse', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ purpose })
        });

        if (!res.body) throw new Error('Streaming not supported');
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
                        if (d.step) addStep(d.step);
                        if (d.result) {
                            // Update template blocks
                            const tpl = d.result;
                            S.template = tpl;
                            renderBlocksList(tpl.blocks || []);
                            el('tplDetailMeta').textContent = (tpl.blocks||[]).length + ' блоков · ' + (tpl.articles_count||0) + ' статей';
                        }
                    } catch(_) {}
                }
                if (line.startsWith('event: error')) {}
            }
        }
        const last = steps.querySelector('.ai-step.active');
        if (last) last.className = 'ai-step done';
        toast('Шаблон перегенерирован', 'ok');
    } catch(e) {
        addStep(e.message, 'error');
        toast(e.message, 'err');
    }
    el('btnTplRegen').disabled = false;
    el('btnTplRegen').innerHTML = '✨ Перегенерировать';
}

// ─── Telegram ───
function fillTelegram() {
    const p = S.profile;
    if (!p) return;

    el('tgBotToken').value = p.tg_bot_token || '';
    el('tgChannelId').value = p.tg_channel_id || '';

    S.selectedFormat = p.tg_post_format || 'auto';
    renderFormatPicker();

    const enabled = p.tg_render_blocks || Array.from(DEFAULT_BLOCKS);
    S.enabledBlocks = new Set(enabled);
    renderRenderBlocks();

    updateTgStatus(p);
}

function updateTgStatus(p) {
    const connected = !!(p && p.tg_bot_token && p.tg_channel_id);
    const st = el('tgStatus');
    st.className = 'tg-status ' + (connected ? 'connected' : 'disconnected');
    st.textContent = connected ? '● Подключено' : '● Не подключено';

    const ci = el('tgChannelInfo');
    if (connected && p.tg_channel_name) {
        ci.style.display = '';
        const av = el('tgChAvatar');
        if (p.tg_channel_avatar) {
            av.innerHTML = '<img src="' + esc(p.tg_channel_avatar) + '" alt="">';
        } else {
            av.textContent = (p.tg_channel_name || '?').charAt(0).toUpperCase();
        }
        el('tgChName').textContent = p.tg_channel_name;
        el('tgChId').textContent = p.tg_channel_id;
    } else {
        ci.style.display = 'none';
    }
}

function renderFormatPicker() {
    document.querySelectorAll('.format-tile').forEach(t => {
        t.classList.toggle('selected', t.dataset.format === S.selectedFormat);
    });
}

document.querySelectorAll('.format-tile').forEach(t => {
    t.addEventListener('click', () => {
        S.selectedFormat = t.dataset.format;
        renderFormatPicker();
    });
});

function renderRenderBlocks() {
    el('renderBlocksGrid').innerHTML = ALL_RENDER_BLOCKS.map(b => `
    <div class="rb-chip ${S.enabledBlocks.has(b.key) ? 'on' : ''}" onclick="toggleBlock('${b.key}')">
        <div class="rb-chip-dot"></div>${esc(b.label)}
    </div>`).join('');
}

function toggleBlock(key) {
    if (S.enabledBlocks.has(key)) S.enabledBlocks.delete(key);
    else S.enabledBlocks.add(key);
    renderRenderBlocks();
}

function toggleAllBlocks(on) {
    if (on) ALL_RENDER_BLOCKS.forEach(b => S.enabledBlocks.add(b.key));
    else S.enabledBlocks.clear();
    renderRenderBlocks();
}

async function testTgConnection() {
    const token = el('tgBotToken').value.trim();
    const channelId = el('tgChannelId').value.trim();
    if (!token || !channelId) { toast('Заполните Bot Token и Channel ID', 'err'); return; }

    el('tgTestSpinner').innerHTML = '<span class="spin"></span> ';
    el('btnTgTest').disabled = true;
    try {
        const result = await api('telegram/test-connection', 'POST', {
            bot_token: token, channel_id: channelId
        });
        toast('Подключение успешно: ' + (result.channel_name || result.title || 'OK'), 'ok');
        S.profile.tg_bot_token = token;
        S.profile.tg_channel_id = channelId;
        S.profile.tg_channel_name = result.channel_name || result.title || null;
        S.profile.tg_channel_avatar = result.channel_avatar || null;
        updateTgStatus(S.profile);
    } catch(e) {
        toast('Ошибка: ' + e.message, 'err');
    }
    el('tgTestSpinner').innerHTML = '';
    el('btnTgTest').disabled = false;
}

async function refreshTgChannel() {
    if (!S.profile) return;
    try {
        const result = await api('telegram/refresh-channel/' + S.profile.id, 'POST');
        S.profile.tg_channel_name = result.channel_name || result.title || S.profile.tg_channel_name;
        S.profile.tg_channel_avatar = result.channel_avatar || S.profile.tg_channel_avatar;
        updateTgStatus(S.profile);
        toast('Данные канала обновлены', 'ok');
    } catch(e) {
        toast(e.message, 'err');
    }
}

async function saveTelegram() {
    const spinner = el('saveTgSpinner');
    spinner.innerHTML = '<span class="spin spin-white"></span>';
    try {
        const blocks = Array.from(S.enabledBlocks);
        const updated = await api('profiles/' + S.profile.id, 'PUT', {
            tg_bot_token:    el('tgBotToken').value.trim() || null,
            tg_channel_id:   el('tgChannelId').value.trim() || null,
            tg_post_format:  S.selectedFormat,
            tg_render_blocks: blocks.length > 0 ? blocks : null,
        });
        S.profile = Object.assign(S.profile, updated);
        updateTgStatus(S.profile);
        toast('Telegram-настройки сохранены', 'ok');
    } catch(e) {
        toast(e.message, 'err');
    }
    spinner.innerHTML = '';
}

// ─── Escape HTML ───
function esc(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ─── Advanced mode ───
function toggleAdvanced() {
    const on = !document.body.classList.contains('advanced');
    document.body.classList.toggle('advanced', on);
    el('advToggle').classList.toggle('on', on);
    try { localStorage.setItem('seo_simple_adv', on ? '1' : '0'); } catch(e) {}
}

(function initAdvanced() {
    let on = false;
    try { on = localStorage.getItem('seo_simple_adv') === '1'; } catch(e) {}
    if (on) {
        document.body.classList.add('advanced');
        el('advToggle').classList.add('on');
    }
})();

// ═══════════════════ TEMPLATE PURPOSE SUGGESTIONS ═══════════════════

async function spLoadPurposes() {
    if (!S.profile) return;
    const btn = el('btnSpPurpose');
    const box = el('spPurposes');
    btn.disabled = true;
    btn.innerHTML = '<span class="spin"></span> Загрузка...';
    box.style.display = '';
    box.innerHTML = '<div style="color:var(--text-3);font-size:12px;padding:8px"><span class="spin"></span> AI подбирает варианты из брифа...</div>';
    try {
        const data = await api('profiles/' + S.profile.id + '/suggest-template-purposes', 'POST', {});
        const options = data.options || [];
        if (!options.length) { box.innerHTML = '<div style="color:var(--text-3);font-size:12px">Нет вариантов. Заполните бриф подробнее.</div>'; return; }
        box.innerHTML = options.map((o, i) => `
            <div class="profile-card" style="padding:12px;margin-bottom:6px;cursor:pointer" onclick="spPickPurpose(${i})" id="spCard_${i}">
                <div style="display:flex;gap:8px;align-items:flex-start">
                    <div style="font-size:10px;background:var(--accent-light);color:var(--accent);padding:2px 6px;border-radius:4px;margin-top:2px">${escHtml(o.format || '')}</div>
                    <div style="flex:1">
                        <div style="font-weight:600;font-size:13px">${escHtml(o.title || '')}</div>
                        <div style="color:var(--text-2);font-size:12px;margin-top:4px">${escHtml(o.purpose || '')}</div>
                        ${o.target_icp ? `<div style="color:var(--text-3);font-size:11px;margin-top:4px">Для: ${escHtml(o.target_icp)}</div>` : ''}
                        ${o.suggested_blocks_hint ? `<div style="color:var(--text-3);font-size:11px;margin-top:2px">Блоки: ${escHtml(o.suggested_blocks_hint)}</div>` : ''}
                    </div>
                </div>
            </div>`).join('');
        el('btnSpClear').style.display = '';
        window.__spOptions = options;
    } catch(e) { toast(e.message, 'error'); box.style.display = 'none'; }
    finally { btn.disabled = false; btn.innerHTML = '✨ AI: варианты из брифа'; }
}

function spPickPurpose(i) {
    const o = (window.__spOptions || [])[i];
    if (!o) return;
    el('tplDescription').value = o.purpose || o.title || '';
    document.querySelectorAll('[id^="spCard_"]').forEach((card, j) => {
        card.style.borderColor = j === i ? 'var(--accent)' : '';
        card.style.background = j === i ? 'var(--accent-light)' : '';
    });
}

function spClearPurposes() {
    el('spPurposes').style.display = 'none';
    el('spPurposes').innerHTML = '';
    el('btnSpClear').style.display = 'none';
    window.__spOptions = null;
}

// ═══════════════════ SIMPLE BRIEF WIZARD ═══════════════════
// Click-driven cards, minimal manual input. Shares endpoints with advanced wizard.

const SB_STEPS = [
    { key: 'classify',    title: 'Нишевые параметры' },
    { key: 'audience',    title: 'Кто аудитория?' },
    { key: 'usp',         title: 'Что в вас уникального?' },
    { key: 'competitors', title: 'Конкуренты и отстройка' },
    { key: 'voice',       title: 'Как звучит бренд?' },
    { key: 'rules',       title: 'Что можно и нельзя' },
    { key: 'compliance',  title: 'Compliance', regulatedOnly: true },
    { key: 'phrases',     title: 'Проба голоса' },
];

let sbState = null;
let sbIdx = 0;
let sbCur = {};

function sbInit() {
    sbState = (S.profile && S.profile.content_brief) ? JSON.parse(JSON.stringify(S.profile.content_brief)) : {};
    sbCur = {};
    sbIdx = 0;
    sbRender();
}

function sbReset() {
    if (!confirm('Сбросить бриф?')) return;
    sbState = {};
    sbCur = {};
    sbIdx = 0;
    sbRender();
}

function sbVisible() {
    const reg = !!(sbState && sbState.classify && sbState.classify.regulated);
    return SB_STEPS.filter(s => !s.regulatedOnly || reg);
}

async function sbRender() {
    const steps = sbVisible();
    const step = steps[sbIdx];
    el('sbProgress').textContent = `Шаг ${sbIdx + 1} из ${steps.length} — ${step.title}`;
    el('sbBtnBack').disabled = sbIdx === 0;
    el('sbBtnNext').textContent = sbIdx === steps.length - 1 ? 'Готово ✓' : 'Далее →';

    const body = el('sbStep');
    body.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-3)"><div class="spin"></div> AI подбирает варианты...</div>';

    // Auto-generate if not loaded yet
    if (!sbCur[step.key]) {
        try {
            const data = await api('profiles/brief', 'POST', {
                step: step.key,
                description: S.profile.description || '',
                brief: sbState || {},
            });
            sbCur[step.key] = data.data || {};
        } catch(e) {
            body.innerHTML = `<div class="empty"><div class="empty-icon">⚠️</div><div class="empty-title">Ошибка AI</div><div class="empty-sub">${escHtml(e.message)}</div><button class="btn btn-secondary btn-sm" style="margin-top:12px" onclick="sbRender()">Повторить</button></div>`;
            return;
        }
    }
    body.innerHTML = sbStepHtml(step, sbCur[step.key]);
}

function sbStepHtml(step, data) {
    if (step.key === 'classify') {
        return `<div style="display:grid;gap:12px">
            <div><div class="profile-stat" style="margin-bottom:4px">Ниша</div><input type="text" id="sb_niche" value="${escHtml(data.niche || '')}" class="input" style="width:100%"></div>
            <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" id="sb_reg" ${data.regulated ? 'checked' : ''}> Регулируемая ниша (финансы / медицина / юр / крипто)</label>
            <div><div class="profile-stat" style="margin-bottom:4px">Тип регулирования</div><input type="text" id="sb_regdom" value="${escHtml(data.regulatory_domain || 'none')}" class="input" style="width:100%"></div>
        </div>`;
    }
    if (step.key === 'rules') {
        const doL = data.do || [], dontL = data.dont || [];
        const rules = (list, cls) => list.map((r, i) => `
            <label style="display:flex;gap:8px;padding:10px;border:1px solid var(--border);border-radius:var(--radius-sm);margin-bottom:6px;cursor:pointer;background:var(--surface)">
                <input type="checkbox" class="${cls}" data-idx="${i}" checked>
                <span style="font-size:12.5px"><b>${escHtml(r.rule || '')}</b>${r.check ? '<div style="color:var(--text-3);font-size:11.5px;margin-top:2px">' + escHtml(r.check) + '</div>' : ''}</span>
            </label>`).join('');
        return `<div><b style="color:var(--success)">Делать:</b>${rules(doL, 'sb_do')}</div><div style="margin-top:12px"><b style="color:var(--danger)">НЕ делать:</b>${rules(dontL, 'sb_dont')}</div>`;
    }
    if (step.key === 'compliance') {
        return `<div style="font-size:12px;color:var(--text-2);margin-bottom:8px">AI подобрал compliance-ограничения. Проверьте и редактируйте JSON при необходимости (расширенный режим).</div>
            <pre id="sb_compliance_raw" style="background:var(--bg);padding:12px;border-radius:var(--radius-sm);font-size:11.5px;max-height:300px;overflow:auto;white-space:pre-wrap">${escHtml(JSON.stringify(data, null, 2))}</pre>`;
    }
    if (step.key === 'voice') {
        return (data.options || []).map((o, i) => `
            <label class="profile-card" style="display:block;padding:14px;cursor:pointer;margin-bottom:8px">
                <div style="display:flex;gap:10px;align-items:flex-start">
                    <input type="radio" name="sb_voice" value="${i}" ${i === 0 ? 'checked' : ''} style="margin-top:4px">
                    <div style="flex:1">
                        <div><b>${escHtml(o.label || o.archetype)}</b> <span style="color:var(--text-3);font-size:11px">[${escHtml(o.archetype)}]</span></div>
                        <div style="margin-top:6px;font-size:12.5px;color:var(--text-2)">«${escHtml(o.sample_explanation || '')}»</div>
                        <div style="margin-top:4px;font-size:12px;color:var(--accent)">CTA: «${escHtml(o.sample_cta || '')}»</div>
                    </div>
                </div>
            </label>`).join('');
    }
    if (step.key === 'phrases') {
        return (data.options || []).map((o, i) => `
            <label class="profile-card" style="display:block;padding:14px;cursor:pointer;margin-bottom:8px">
                <div style="display:flex;gap:10px;align-items:flex-start">
                    <input type="checkbox" class="sb_phrase" value="${i}" checked style="margin-top:4px">
                    <div style="flex:1;font-size:12.5px">
                        <div style="color:var(--text-3);font-size:11px">${escHtml(o.context || '')}</div>
                        <div style="margin-top:4px">${escHtml(o.text || '')}</div>
                    </div>
                </div>
            </label>`).join('');
    }
    return (data.options || []).map((o, i) => {
        const primary = o.label || o.headline || o.name || ('Вариант ' + (i + 1));
        const lines = [];
        if (o.demographics)   lines.push(`<div>👥 ${escHtml(o.demographics)}</div>`);
        if (o.pains)          lines.push(`<div>🔥 ${escHtml((o.pains || []).join('; '))}</div>`);
        if (o.goals)          lines.push(`<div>🎯 ${escHtml((o.goals || []).join('; '))}</div>`);
        if (o.proof)          lines.push(`<div>📊 ${escHtml(o.proof)}</div>`);
        if (o.differentiator) lines.push(`<div>✨ ${escHtml(o.differentiator)}</div>`);
        if (o.weaknesses)     lines.push(`<div>⚠️ ${escHtml((o.weaknesses || []).join(', '))}</div>`);
        if (o.angle)          lines.push(`<div>🎯 ${escHtml(o.angle)}</div>`);
        return `<label class="profile-card" style="display:block;padding:14px;cursor:pointer;margin-bottom:8px">
                <div style="display:flex;gap:10px;align-items:flex-start">
                    <input type="checkbox" class="sb_pick" value="${i}" ${i < 2 ? 'checked' : ''} style="margin-top:4px">
                    <div style="flex:1;font-size:12.5px">
                        <div><b>${escHtml(primary)}</b></div>
                        <div style="margin-top:6px;display:grid;gap:4px;color:var(--text-2)">${lines.join('')}</div>
                    </div>
                </div>
            </label>`;
    }).join('');
}

function escHtml(s) {
    if (s === null || s === undefined) return '';
    const d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
}

function sbCollect(stepKey) {
    const data = sbCur[stepKey];
    if (!data) return null;
    if (stepKey === 'classify') {
        return {
            niche: el('sb_niche').value,
            regulated: el('sb_reg').checked,
            regulatory_domain: el('sb_regdom').value,
            language: data.language || 'ru',
            detected_entities: data.detected_entities || {},
            clarifying_questions: data.clarifying_questions || [],
        };
    }
    if (stepKey === 'rules') {
        const doList = [...document.querySelectorAll('.sb_do')].filter(c => c.checked).map(c => data.do[+c.dataset.idx]);
        const dontList = [...document.querySelectorAll('.sb_dont')].filter(c => c.checked).map(c => data.dont[+c.dataset.idx]);
        return { do: doList, dont: dontList };
    }
    if (stepKey === 'compliance') {
        try { return JSON.parse(el('sb_compliance_raw').textContent); } catch(e) { return data; }
    }
    if (stepKey === 'voice') {
        const pick = document.querySelector('input[name="sb_voice"]:checked');
        return pick ? (data.options[+pick.value] || null) : (data.options && data.options[0]) || null;
    }
    if (stepKey === 'phrases') {
        return [...document.querySelectorAll('.sb_phrase')].filter(c => c.checked).map(c => data.options[+c.value]);
    }
    const picks = [...document.querySelectorAll('.sb_pick')].filter(c => c.checked).map(c => data.options[+c.value]);
    if (stepKey === 'audience') return picks[0] || null;
    if (stepKey === 'usp') return { usps: picks };
    if (stepKey === 'competitors') return picks;
    return picks;
}

function sbCommit() {
    const steps = sbVisible();
    const step = steps[sbIdx];
    if (!sbCur[step.key]) return true;
    const val = sbCollect(step.key);
    if (val === null) return false;
    if (step.key === 'usp') sbState.usps = val.usps;
    else sbState[step.key] = val;
    return true;
}

function sbPrev() {
    sbCommit();
    if (sbIdx > 0) { sbIdx--; sbRender(); }
}

function sbNext() {
    if (!sbCommit()) { toast('Выберите вариант', 'error'); return; }
    const steps = sbVisible();
    if (sbIdx < steps.length - 1) { sbIdx++; sbRender(); return; }
    sbSave();
}

async function sbSave() {
    try {
        sbCommit();
        const updated = await api('profiles/' + S.profile.id + '/brief', 'POST', { brief: sbState });
        S.profile = updated;
        toast('Бриф сохранён — persona и правила обновлены');
    } catch(e) {
        toast('Ошибка: ' + e.message, 'error');
    }
}

// ─── Init ───
loadProfiles();
</script>
</body>
</html>
