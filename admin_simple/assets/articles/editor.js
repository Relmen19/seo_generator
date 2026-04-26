function debounce(fn, ms) {
    let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
}

// ─── Open article ───
function normalizeArticle(a) {
    if (a && Array.isArray(a.blocks)) {
        a.blocks.forEach(b => {
            if (typeof b.content === 'string') {
                try { b.content = JSON.parse(b.content); } catch(_) { b.content = {}; }
            }
            if (!b.content || typeof b.content !== 'object') b.content = {};
        });
    }
    return a;
}

async function openArticle(id) {
    try {
        const res = await api('articles/' + id);
        S.article = normalizeArticle(res.data);
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

    // Research/Outline UI — advanced-only; markup removed in simple mode.

    el('metaPubUrl').textContent = a.published_url || '—';
    el('metaVersion').textContent = a.version || 1;
    el('metaModel').textContent = a.gpt_model || '—';
    el('metaTokens').textContent = sumTokens(a.generation_log);

    // Status pill + publish button
    const published = a.status === 'published' && a.published_url;
    const pill = el('statusPill');
    pill.className = 'status-pill ' + (published ? 'published' : 'draft');
    el('statusPillText').textContent = published ? 'Опубликовано' : 'Черновик';
    el('btnPublish').textContent = published ? '🔄 Изменить публикацию' : '📤 Опубликовать';

    const btn = el('btnViewPage');
    if (a.published_url) {
        btn.style.display = '';
        btn.onclick = () => window.open(a.published_url, '_blank');
    } else {
        btn.style.display = 'none';
    }

    setSaveState('saved');
    renderBlocks(a.blocks || []);
    refreshQaIssues();
    const wf = el('wfStatus');
    if (wf) wf.value = a.status || 'draft';
    populateThemeSelect(a.theme_code || '');
}

async function populateThemeSelect(currentCode) {
    const sel = el('artTheme');
    if (!sel) return;
    if (!S.themes) {
        try {
            const res = await api('themes');
            S.themes = (res.data || []).filter(t => t.is_active != 0);
        } catch (e) { S.themes = []; }
    }
    sel.innerHTML = '<option value="">— наследовать от профиля —</option>'
        + S.themes.map(t => '<option value="' + esc(t.code) + '">' + esc(t.name || t.code) + '</option>').join('');
    sel.value = currentCode || '';
}

async function changeArticleTheme(code) {
    if (!S.article) return;
    try {
        await api('articles/' + S.article.id, 'PUT', { theme_code: code === '' ? null : code });
        S.article.theme_code = code === '' ? null : code;
        toast('Тема обновлена', 'ok');
    } catch (e) {
        toast(e.message, 'err');
        el('artTheme').value = S.article.theme_code || '';
    }
}

async function changeWorkflowStatus(newStatus) {
    if (!S.article) return;
    if (newStatus === 'published' || newStatus === 'unpublished') {
        toast('Публикация — через кнопку «Опубликовать»', 'err');
        el('wfStatus').value = S.article.status || 'draft';
        return;
    }
    try {
        await api('articles/' + S.article.id + '/status', 'PUT', { status: newStatus });
        S.article.status = newStatus;
        toast('Статус изменён: ' + newStatus, 'ok');
    } catch (e) {
        toast(e.message, 'err');
        el('wfStatus').value = S.article.status || 'draft';
    }
}


function setSaveState(state, msg) {
    const el_ = el('saveState');
    const txt = el('saveStateText');
    el_.className = 'save-state ' + state;
    if (state === 'saving') txt.textContent = 'Сохранение…';
    else if (state === 'saved') txt.textContent = 'Сохранено';
    else if (state === 'err') txt.textContent = msg || 'Ошибка';
}

function scheduleAutosave(group) {
    if (!S.article) return;
    clearTimeout(S.autosaveTimers[group]);
    setSaveState('saving');
    S.autosaveTimers[group] = setTimeout(() => doAutosave(group), 800);
}

async function doAutosave(group) {
    if (!S.article) return;
    const payload = {};
    if (group === 'main') {
        payload.title = el('fTitle').value.trim();
        payload.slug = el('fSlug').value.trim();
    } else if (group === 'meta') {
        payload.meta_title = el('fMetaTitle').value.trim() || null;
        payload.meta_description = el('fMetaDesc').value.trim() || null;
    }
    S.saveInFlight++;
    try {
        const res = await api('articles/' + S.article.id, 'PUT', payload);
        Object.assign(S.article, res.data);
        if (group === 'main') {
            el('edTitle').textContent = S.article.title || '—';
            el('topbarPage').textContent = S.article.title || 'Статья';
        }
        S.saveInFlight--;
        if (S.saveInFlight === 0) setSaveState('saved');
    } catch(e) {
        S.saveInFlight--;
        setSaveState('err', e.message);
        toast(e.message, 'err');
    }
}

document.addEventListener('input', (ev) => {
    const t = ev.target;
    if (t && t.dataset && t.dataset.autosave) scheduleAutosave(t.dataset.autosave);
});

// ─── Advanced mode ───
function toggleAdvanced() {
    S.advanced = !S.advanced;
    document.body.classList.toggle('advanced', S.advanced);
    el('advToggle').classList.toggle('on', S.advanced);
    try { localStorage.setItem('seo_simple_adv', S.advanced ? '1' : '0'); } catch(e) {}
    // Re-render blocks (simple/advanced field visibility)
    if (S.article) renderBlocks(S.article.blocks || []);
    if (S.openBlockId) {
        const card = el('block-' + S.openBlockId);
        if (card) card.classList.add('open');
    }
}

// ─── Wizard ───

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

function openPublicPage() {
    if (S.article && S.article.published_url) {
        window.open(S.article.published_url, '_blank');
    }
}
