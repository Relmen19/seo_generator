// ─── Images section ───
// List article images with thumbnail / status / per-image delete.
// Under S.advanced: model picker + batch generate + delete buttons.

async function loadImages() {
    if (!S.article) return;
    const list = el('imagesList');
    if (!list) return;
    try {
        const res = await api('images?article_id=' + S.article.id + '&per_page=100');
        const items = (res.data && res.data.items) || res.data || [];
        S.images = items;
        renderImages(items);
    } catch (e) {
        list.innerHTML = '<div class="empty" style="color:var(--danger)">Ошибка: ' + esc(e.message) + '</div>';
    }
}

function renderImages(items) {
    el('imagesCount').textContent = '· ' + items.length;
    const list = el('imagesList');
    if (!items.length) {
        list.innerHTML = '<div class="empty"><div class="empty-icon">📷</div><div class="empty-title">Изображений нет</div><div class="empty-sub">Сгенерируйте через блоки или batch</div></div>';
        return;
    }
    list.innerHTML = items.map(renderImageCard).join('');
}

function renderImageCard(img) {
    const blockRef = img.block_id ? ('Блок #' + img.block_id) : 'Без привязки';
    const dim = (img.width && img.height) ? (img.width + '×' + img.height) : '';
    const sizeKb = img.size_bytes ? (Math.round(img.size_bytes / 1024) + ' KB') : '';
    const src = img.has_data !== false ? (API + 'images/' + img.id + '/raw&_=' + Date.now()) : '';
    return `
    <div class="img-card" data-img-id="${img.id}">
        <div class="img-card-thumb">
            ${src
                ? '<img src="' + src + '" alt="' + esc(img.alt_text || '') + '" onclick="openImagePreview(' + img.id + ')">'
                : '<span class="img-card-thumb-empty">нет данных</span>'}
        </div>
        <div class="img-card-meta">
            <div class="img-card-name">${esc(img.name || ('image #' + img.id))}</div>
            <div>${esc(blockRef)}</div>
            <div>${esc(dim)} · ${esc(sizeKb)} · ${esc(img.source || '')}</div>
        </div>
        <div class="img-card-actions adv-only">
            <button class="img-card-del" onclick="deleteImage(${img.id})">Удалить</button>
        </div>
    </div>`;
}

function openImagePreview(imgId) {
    const img = (S.images || []).find(x => x.id === imgId);
    if (!img) return;
    // Reuse the block image modal if image is linked to a block.
    if (img.block_id && typeof openImageModal === 'function') {
        openImageModal(img.block_id);
        return;
    }
    // Fallback: simple lightbox via modal
    const prev = el('imgModalPreview');
    el('imgModalPrompt').value = img.gpt_prompt || '';
    prev.innerHTML = '<img src="' + API + 'images/' + img.id + '/raw&_=' + Date.now() + '" alt="" style="max-width:100%;max-height:60vh;border-radius:6px">';
    _imgModalBlockId = null;
    el('imgModal').classList.add('show');
}

async function deleteImage(imgId) {
    if (!confirm('Удалить изображение #' + imgId + '?')) return;
    try {
        await api('images/' + imgId, 'DELETE');
        await loadImages();
        toast('Удалено', 'ok');
    } catch (e) { toast(e.message, 'err'); }
}

async function generateAllImages() {
    if (!S.article) return;
    if (!confirm('Сгенерировать изображения для всех блоков с image_prompt? Существующие не перезаписываются.')) return;
    const btn = el('btnGenAllImg');
    const sp  = el('genAllImgSpin');
    btn.disabled = true;
    sp.innerHTML = '<span class="spin spin-white"></span>';

    const jobId = Progress.start({ label: '🖼 Batch images', articleId: S.article.id, cancellable: false });
    Progress.update(jobId, { phase: 'images', sub: 'pending…' });
    try {
        const model = el('imgModelPick').value || null;
        const body = { article_id: S.article.id, overwrite: false };
        if (model) body.model = model;
        const res = await api('images/generate-all', 'POST', body);
        const generated = (res.data && (res.data.generated_count || res.data.count)) || 0;
        Progress.success(jobId, 'Готово · сгенерировано ' + generated);
        await loadImages();
    } catch (e) {
        Progress.error(jobId, e.message);
        toast(e.message, 'err');
    }
    btn.disabled = false;
    sp.innerHTML = '';
}
