// ─── Publish modal ───
function openPublishModal() {
    if (!S.article) return;
    el('pubUrl').value = S.article.published_url || '';
    el('btnUnpublish').style.display = (S.article.status === 'published') ? '' : 'none';
    el('pubModal').classList.add('show');
}

function closePublishModal() {
    el('pubModal').classList.remove('show');
}

async function doPublish() {
    if (!S.article) return;
    const url = el('pubUrl').value.trim();
    if (!url) { toast('Укажите URL', 'err'); return; }
    const sp = el('pubSpin');
    sp.innerHTML = '<span class="spin spin-white"></span>';
    try {
        const gate = await api('qa/' + S.article.id + '/has-errors');
        if (gate.data.has_errors && !(el('qaForcePublish') && el('qaForcePublish').checked)) {
            sp.innerHTML = '';
            toast('Есть блокирующие ошибки в проверках. Отметьте force-override для публикации.', 'err');
            return;
        }
        await api('articles/' + S.article.id, 'PUT', { published_url: url });
        // Set status published
        await api('articles/' + S.article.id + '/status', 'PUT', { status: 'published' });
        const art = await api('articles/' + S.article.id);
        S.article = normalizeArticle(art.data);
        renderEditor();
        closePublishModal();
        toast('Опубликовано', 'ok');
    } catch(e) { toast(e.message, 'err'); }
    sp.innerHTML = '';
}

async function doUnpublish() {
    if (!S.article) return;
    if (!confirm('Снять статью с публикации?')) return;
    try {
        await api('publish/' + S.article.id + '/unpublish', 'POST', {});
        const art = await api('articles/' + S.article.id);
        S.article = normalizeArticle(art.data);
        renderEditor();
        closePublishModal();
        toast('Снято с публикации', 'ok');
    } catch(e) { toast(e.message, 'err'); }
}

async function openFullPreview() {
    if (!S.article) return;
    const modal = el('fullPreviewModal');
    const frame = el('fullPreviewFrame');
    frame.srcdoc = '<!doctype html><html><body style="font-family:sans-serif;padding:40px;text-align:center;color:#666">Загрузка…</body></html>';
    modal.classList.add('show');
    try {
        for (const g of Object.keys(S.autosaveTimers)) {
            if (S.autosaveTimers[g]) { clearTimeout(S.autosaveTimers[g]); S.autosaveTimers[g] = null; await doAutosave(g); }
        }
        const res = await fetch(API + 'publish/' + S.article.id + '/preview', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}'
        });
        const html = await res.text();
        if (!res.ok) throw new Error('HTTP ' + res.status);
        frame.srcdoc = html;
    } catch(e) {
        frame.srcdoc = '<!doctype html><html><body style="font-family:sans-serif;padding:40px;color:#c00">Ошибка: ' + esc(e.message) + '</body></html>';
        toast(e.message, 'err');
    }
}

function closeFullPreview() {
    el('fullPreviewModal').classList.remove('show');
    el('fullPreviewFrame').srcdoc = '';
}
