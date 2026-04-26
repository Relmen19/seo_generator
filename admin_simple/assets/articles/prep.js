// ─── Research dossier ───
function renderResearchStatus(status, at) {
    const badge = el('researchStatusBadge');
    const map = {
        none:  ['Нет',     'badge-muted'],
        draft: ['Draft',   'badge-info'],
        ready: ['Готово',  'badge-success'],
        stale: ['Устарел', 'badge-muted'],
    };
    const [label, cls] = map[status] || ['—', 'badge-muted'];
    badge.className = 'badge ' + cls;
    badge.textContent = label;
    el('researchAt').textContent = at ? ('обновлено ' + new Date(at.replace(' ', 'T')).toLocaleString('ru-RU')) : '';
}

async function buildResearch(force) {
    if (!S.article) return;
    const cur = (el('fResearch').value || '').trim();
    if (cur && !force && !confirm('Досье непустое. Перезаписать через GPT?')) return;
    const btn = el('btnResearchBuild');
    btn.disabled = true;
    const oldText = btn.innerHTML;
    btn.innerHTML = '⏳ Собираю…';
    try {
        const res = await fetch(API + 'generate/' + S.article.id + '/research', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ force: !!cur || force })
        });
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.error || ('HTTP ' + res.status));
        const d = json.data || {};
        el('fResearch').value = d.dossier || '';
        S.article.research_dossier = d.dossier || '';
        S.article.research_status  = 'ready';
        S.article.research_at      = d.at || new Date().toISOString().replace('T', ' ').slice(0, 19);
        renderResearchStatus('ready', S.article.research_at);
        toast('Research собран', 'ok');
    } catch (e) {
        toast('Ошибка research: ' + e.message, 'err');
    } finally {
        btn.disabled = false;
        btn.innerHTML = oldText;
    }
}

async function saveResearchManual() {
    if (!S.article) return;
    const dossier = el('fResearch').value;
    const btn = el('btnResearchSave');
    btn.disabled = true;
    try {
        const res = await fetch(API + 'generate/' + S.article.id + '/research', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ dossier: dossier })
        });
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.error || ('HTTP ' + res.status));
        const d = json.data || {};
        S.article.research_dossier = d.dossier || '';
        S.article.research_status  = d.status || 'none';
        S.article.research_at      = d.at || null;
        renderResearchStatus(S.article.research_status, S.article.research_at);
        toast('Research сохранён', 'ok');
    } catch (e) {
        toast('Ошибка сохранения: ' + e.message, 'err');
    } finally {
        btn.disabled = false;
    }
}

// ─── Outline ───
function renderOutline(outlineJson, status) {
    const badge = el('outlineStatusBadge');
    if (badge) {
        badge.className = 'badge ' + ({
            ready: 'badge-success', stale: 'badge-warn', draft: 'badge-muted', none: 'badge-muted',
        }[status] || 'badge-muted');
        badge.textContent = status || 'none';
    }
    const list = el('outlineSections');
    list.innerHTML = '';
    let sections = [];
    try {
        const parsed = outlineJson ? JSON.parse(outlineJson) : null;
        if (parsed && Array.isArray(parsed.sections)) sections = parsed.sections;
    } catch (e) { /* invalid json — show nothing */ }

    if (!sections.length) {
        list.innerHTML = '<div style="font-size:12px;color:var(--text-3)">Outline ещё не построен.</div>';
        return;
    }
    sections.forEach((s, i) => {
        const row = document.createElement('div');
        row.style.cssText = 'border:1px solid var(--border);border-radius:6px;padding:8px 10px;background:var(--bg-2)';
        const facts = Array.isArray(s.source_facts) ? s.source_facts : [];
        row.innerHTML =
            '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">'
            + '<b>' + (i+1) + '. ' + escapeHtml(s.h2_title || '—') + '</b>'
            + '<span class="badge badge-muted" style="font-size:10px">' + escapeHtml(s.narrative_role || '?') + '</span>'
            + '<span class="badge" style="font-size:10px;background:var(--accent-soft)">' + escapeHtml(s.block_type || '?') + '</span>'
            + '</div>'
            + (s.content_brief ? '<div style="font-size:12px;color:var(--text-2);margin-top:4px">' + escapeHtml(s.content_brief) + '</div>' : '')
            + (facts.length ? '<div style="font-size:11px;color:var(--text-3);margin-top:4px">факты: ' + facts.map(escapeHtml).join('; ') + '</div>' : '');
        list.appendChild(row);
    });
}

function escapeHtml(s) {
    return String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

async function buildOutline(force) {
    if (!S.article) return;
    const btn = el('btnOutlineBuild');
    const oldText = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '⏳ Строю outline…';
    try {
        const res = await fetch(API + 'generate/' + S.article.id + '/outline', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ force: !!force })
        });
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.error || ('HTTP ' + res.status));
        const d = json.data || {};
        const outlineStr = d.outline || (d.sections ? JSON.stringify({sections: d.sections}) : '');
        el('fOutline').value = outlineStr;
        S.article.article_outline = outlineStr;
        S.article.outline_status  = 'ready';
        renderOutline(outlineStr, 'ready');
        toast('Outline построен', 'ok');
    } catch (e) {
        toast('Ошибка outline: ' + e.message, 'err');
    } finally {
        btn.disabled = false; btn.innerHTML = oldText;
    }
}

async function saveOutlineManual() {
    if (!S.article) return;
    const outline = el('fOutline').value;
    const btn = el('btnOutlineSave');
    btn.disabled = true;
    try {
        const res = await fetch(API + 'generate/' + S.article.id + '/outline', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ outline: outline })
        });
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.error || ('HTTP ' + res.status));
        const d = json.data || {};
        S.article.article_outline = d.outline || '';
        S.article.outline_status  = d.status || 'none';
        renderOutline(S.article.article_outline, S.article.outline_status);
        toast('Outline сохранён', 'ok');
    } catch (e) {
        toast('Ошибка сохранения: ' + e.message, 'err');
    } finally {
        btn.disabled = false;
    }
}

