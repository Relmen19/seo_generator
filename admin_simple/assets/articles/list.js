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
        sel.innerHTML = '<option value="">— выберите профиль —</option>' +
            S.profiles.map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join('');

        // Pre-select: ?profile=<id> query → localStorage → first active profile
        const urlParam = new URLSearchParams(window.location.search).get('profile');
        let preselect = '';
        if (urlParam && S.profiles.some(p => String(p.id) === String(urlParam))) preselect = urlParam;
        if (!preselect) {
            try {
                const saved = localStorage.getItem('seo_simple_profile');
                if (saved && S.profiles.some(p => String(p.id) === String(saved))) preselect = saved;
            } catch(e) {}
        }
        if (!preselect && S.profiles.length === 1) preselect = String(S.profiles[0].id);
        if (preselect) sel.value = preselect;
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
    const pid = el('filterProfile').value;
    if (!pid) {
        el('listSubtitle').textContent = 'Выберите профиль';
        el('listContainer').innerHTML = '<div class="empty"><div class="empty-icon">📁</div><div class="empty-title">Выберите профиль</div><div class="empty-sub">Статьи показываются по одному профилю за раз</div></div>';
        S.articles = [];
        return;
    }
    el('listContainer').innerHTML = '<div class="empty"><div class="spin"></div></div>';
    const params = new URLSearchParams();
    const q = el('filterSearch').value.trim();
    const sort = el('filterSort').value;
    if (q) params.set('q', q);
    params.set('profile_id', pid);
    if (sort) params.set('sort', sort);
    params.set('per_page', '100');
    try {
        const res = await api('articles?' + params.toString());
        S.articles = res.data || [];
        renderList(res.meta && res.meta.total);
        try { localStorage.setItem('seo_simple_profile', pid); } catch(e) {}
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
        const updated = a.updated_at ? a.updated_at.substring(0, 10) : '—';
        return `
        <tr onclick="openArticle(${a.id})">
            <td><div class="a-title">${esc(a.title || '—')}</div><div class="a-slug">${esc(a.slug || '')}</div></td>
            <td>${esc(a.template_name || '—')}</td>
            <td>${statusBadge}</td>
            <td>${updated}</td>
        </tr>`;
    }).join('');
    el('listContainer').innerHTML = `
    <div class="articles-table-wrap">
        <table class="articles-table">
            <thead><tr>
                <th>Заголовок</th><th>Шаблон</th><th>Статус</th><th>Обновлено</th>
            </tr></thead>
            <tbody>${rows}</tbody>
        </table>
    </div>`;
}

el('filterSearch').addEventListener('input', debounce(loadArticles, 300));
el('filterProfile').addEventListener('change', loadArticles);
el('filterSort').addEventListener('change', loadArticles);

