function blkArrAdd(blockId, path) {
    blkCollectIntoDraft(blockId);
    const b = (S.article.blocks || []).find(x => x.id === blockId);
    if (!b || !b._draft) return;
    const arr = getByPath(b._draft, path);
    if (!Array.isArray(arr)) return;
    let proto;
    const arrSpec = resolveSchemaAtPath(b.type, path);
    if (arrSpec && arrSpec.kind === 'arrayOfObjects') {
        proto = protoFromSchema({ kind: 'object', fields: arrSpec.fields || {} });
    } else if (arrSpec && (arrSpec.kind === 'arrayOfStrings' || arrSpec.kind === 'arrayOfPrimitives')) {
        proto = protoFromSchema(arrSpec.itemSchema || { kind: 'string' });
    } else if (arr.length) {
        const sample = arr[0];
        if (sample && typeof sample === 'object' && !Array.isArray(sample)) {
            proto = {};
            Object.keys(sample).forEach(k => {
                const sv = sample[k];
                if (typeof sv === 'string') proto[k] = '';
                else if (typeof sv === 'number') proto[k] = 0;
                else if (typeof sv === 'boolean') proto[k] = false;
                else if (Array.isArray(sv)) proto[k] = [];
                else proto[k] = null;
            });
        } else if (typeof sample === 'string') proto = '';
        else if (typeof sample === 'number') proto = 0;
        else if (typeof sample === 'boolean') proto = false;
        else proto = null;
    } else {
        proto = '';
    }
    arr.push(proto);
    blkRerenderPane(blockId);
}

function blkArrDel(blockId, path, idx) {
    blkCollectIntoDraft(blockId);
    const b = (S.article.blocks || []).find(x => x.id === blockId);
    if (!b || !b._draft) return;
    const arr = getByPath(b._draft, path);
    if (!Array.isArray(arr)) return;
    arr.splice(idx, 1);
    blkRerenderPane(blockId);
}

async function saveBlockFields(blockId) {
    const b = (S.article.blocks || []).find(x => x.id === blockId);
    if (!b) return;
    const sp = el('saveBlkSpin-' + blockId);
    if (sp) sp.innerHTML = '<span class="spin spin-white"></span>';

    blkCollectIntoDraft(blockId);
    // Preserve non-editable technical fields from original content
    const base = Object.assign({}, b.content || {});
    const draft = b._draft || {};
    const preserved = ['image_id', 'image_layout', 'gpt_prompt'];
    const content = Object.assign({}, draft);
    preserved.forEach(k => { if (base[k] !== undefined) content[k] = base[k]; });
    const name = el('blkName-' + blockId).value;

    try {
        await api('articles/' + S.article.id + '/blocks', 'PUT', {
            block_id: blockId, content, name
        });
        // Refresh article
        const res = await api('articles/' + S.article.id);
        S.article = normalizeArticle(res.data);
        const nb = (S.article.blocks || []).find(x => x.id === blockId);
        if (nb) {
            nb._draft = null; // reseed from fresh content on next render
            const card = el('block-' + blockId);
            const nameEl = card.querySelector('.block-name');
            if (nameEl) nameEl.textContent = nb.name || blockTypeMeta(nb.type)?.display_name || nb.type;
            blkRerenderPane(blockId);
        }
        toast('Блок сохранён', 'ok');
    } catch(e) { toast(e.message, 'err'); }
    if (sp) sp.innerHTML = '';
}

// ─── Chat regenerate ───
function renderChatRegen(b) {
    const prompt = b.gpt_prompt || '';
    if (!S.advanced) {
        return `
        <div style="display:flex;justify-content:flex-end;margin-top:10px">
            <button class="btn btn-secondary btn-sm" onclick="regenBlock(${b.id})" id="blkRegenBtn-${b.id}">
                <span id="blkRegenSpin-${b.id}"></span> ✨ Перегенерировать блок
            </button>
            <input type="hidden" id="blkPrompt-${b.id}" value="${esc(prompt)}">
        </div>`;
    }
    return `
    <div class="regen-chat">
        <div class="regen-chat-label">GPT-промпт · перегенерация</div>
        <div class="regen-chat-row">
            <textarea id="blkPrompt-${b.id}" placeholder="Опишите, как нужно изменить блок...">${esc(prompt)}</textarea>
            <button class="regen-btn" onclick="regenBlock(${b.id})" id="blkRegenBtn-${b.id}" title="Перегенерировать">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13"/>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
            </button>
        </div>
    </div>`;
}

async function regenBlock(blockId) {
    const b = (S.article.blocks || []).find(x => x.id === blockId);
    if (!b) return;
    const prompt = el('blkPrompt-' + blockId).value.trim();
    const btn = el('blkRegenBtn-' + blockId);
    const prevHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spin spin-white"></span>';

    try {
        // Save prompt first
        if (prompt !== (b.gpt_prompt || '')) {
            await api('articles/' + S.article.id + '/blocks', 'PUT', {
                block_id: blockId, gpt_prompt: prompt
            });
        }
        // Regenerate
        const res = await api('generate/' + S.article.id + '/block', 'POST', {
            block_id: blockId
        });
        // Refresh article
        const art = await api('articles/' + S.article.id);
        S.article = normalizeArticle(art.data);
        renderEditor();
        // Keep this block open
        S.openBlockId = blockId;
        const card = el('block-' + blockId);
        if (card) card.classList.add('open');
        toast('Блок перегенерирован', 'ok');
    } catch(e) { toast(e.message, 'err'); }

    btn.disabled = false;
    btn.innerHTML = prevHtml;
}

// ─── Preview ───
async function loadPreview(blockId) {
    const b = (S.article.blocks || []).find(x => x.id === blockId);
    if (!b) return;
    const pane = el('preview-' + blockId);
    if (!pane) return;
    pane.innerHTML = '<div class="preview-empty"><span class="spin"></span></div>';
    try {
        const res = await fetch(API + 'articles/render-block', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: b.type, content: b.content || {} })
        });
        const html = await res.text();
        if (!html) {
            pane.innerHTML = '<div class="preview-empty">Нет контента</div>';
            return;
        }
        const iframe = document.createElement('iframe');
        iframe.sandbox = 'allow-scripts allow-same-origin';
        iframe.onload = () => {
            try {
                const doc = iframe.contentDocument;
                if (doc) {
                    doc.querySelectorAll('.reveal').forEach(e => e.classList.add('vis'));
                    // Auto-resize to content
                    const h = Math.max(doc.body.scrollHeight, doc.documentElement.scrollHeight, 320);
                    iframe.style.height = (h + 20) + 'px';
                }
            } catch(_) {}
        };
        iframe.srcdoc = html;
        pane.innerHTML = '';
        pane.appendChild(iframe);
    } catch(e) {
        pane.innerHTML = '<div class="preview-empty" style="color:var(--danger)">Ошибка: ' + esc(e.message) + '</div>';
    }
}

// ─── Image box (Nano Banana only) ───
function renderImageBox(b) {
    const imgId = b.content && b.content.image_id;
    const imgSrc = imgId ? (API + 'images/' + imgId + '/raw&_=' + Date.now()) : '';
    const hasImage = !!imgId;
    return `
    <div class="img-box">
        <div class="img-preview">
            ${hasImage ? '<img src="' + imgSrc + '" alt="">' : 'Нет изображения'}
        </div>
        <div class="img-ctrl">
            <div class="img-model-pill">🍌 ${IMAGE_MODEL_LABEL}</div>
            <div class="img-ctrl-title">Изображение блока</div>
            <div class="field" style="margin-bottom:10px">
                <textarea id="imgPrompt-${b.id}" rows="2" placeholder="Опишите изображение...">${esc((b.content && b.content.image_prompt) || '')}</textarea>
            </div>
            <button class="btn btn-primary btn-sm" onclick="genImage(${b.id})" id="imgGenBtn-${b.id}">
                <span id="imgGenSpin-${b.id}"></span> ${hasImage ? 'Перегенерировать' : 'Сгенерировать'}
            </button>
        </div>
    </div>`;
}

async function genImage(blockId) {
    const b = (S.article.blocks || []).find(x => x.id === blockId);
    if (!b) return;
    const prompt = el('imgPrompt-' + blockId).value.trim();
    const btn = el('imgGenBtn-' + blockId);
    const sp = el('imgGenSpin-' + blockId);
    btn.disabled = true;
    sp.innerHTML = '<span class="spin spin-white"></span>';

    try {
        const existing = b.content && b.content.image_id;
        let res;
        if (existing) {
            res = await api('images/' + existing + '/regenerate', 'POST', {
                custom_prompt: prompt || null,
                model: IMAGE_MODEL,
            });
        } else {
            res = await api('images/generate', 'POST', {
                article_id: S.article.id,
                block_id: blockId,
                model: IMAGE_MODEL,
                custom_prompt: prompt || null,
            });
        }
        // Refresh article
        const art = await api('articles/' + S.article.id);
        S.article = normalizeArticle(art.data);
        // re-render block
        const card = el('block-' + blockId);
        if (card) {
            const newB = (S.article.blocks || []).find(x => x.id === blockId);
            if (newB) {
                const body = card.querySelector('.block-body');
                const tab = S.blockTabs[blockId] || 'form';
                body.innerHTML = `
                <div class="block-tabs">
                    <div class="block-tab ${tab === 'form' ? 'active' : ''}" onclick="setBlockTab(${blockId}, 'form')">Форма</div>
                    <div class="block-tab ${tab === 'preview' ? 'active' : ''}" onclick="setBlockTab(${blockId}, 'preview')">Превью</div>
                </div>
                <div id="blockPane-${blockId}">${renderBlockPane(newB, tab)}</div>
                ${renderChatRegen(newB)}
                ${renderImageBox(newB)}`;
                if (tab === 'preview') loadPreview(blockId);
            }
        }
        toast('Изображение сгенерировано', 'ok');
    } catch(e) { toast(e.message, 'err'); }

    btn.disabled = false;
    sp.innerHTML = '';
}

// ─── Inline thumbnail modal ───
let _imgModalBlockId = null;

function openImageModal(blockId) {
    const b = (S.article.blocks || []).find(x => x.id === blockId);
    if (!b) return;
    const imgId = b.content && b.content.image_id;
    _imgModalBlockId = blockId;
    const prev = el('imgModalPreview');
    if (imgId) {
        prev.innerHTML = '<img src="' + API + 'images/' + imgId + '/raw&_=' + Date.now() + '" alt="" style="max-width:100%;max-height:60vh;border-radius:6px">';
    } else {
        prev.innerHTML = '<div style="color:var(--text-3);padding:40px 0">Нет изображения</div>';
    }
    el('imgModalPrompt').value = (b.content && b.content.image_prompt) || '';
    el('imgModal').classList.add('show');
}

function closeImageModal() {
    el('imgModal').classList.remove('show');
    _imgModalBlockId = null;
}

async function regenImageFromModal() {
    if (_imgModalBlockId == null) return;
    const blockId = _imgModalBlockId;
    const b = (S.article.blocks || []).find(x => x.id === blockId);
    if (!b) return;
    const prompt = el('imgModalPrompt').value.trim();
    const btn = el('imgModalRegen');
    const sp  = el('imgModalSpin');
    btn.disabled = true;
    sp.innerHTML = '<span class="spin spin-white"></span>';
    try {
        const existing = b.content && b.content.image_id;
        if (existing) {
            await api('images/' + existing + '/regenerate', 'POST', { custom_prompt: prompt || null, model: IMAGE_MODEL });
        } else {
            await api('images/generate', 'POST', { article_id: S.article.id, block_id: blockId, model: IMAGE_MODEL, custom_prompt: prompt || null });
        }
        const art = await api('articles/' + S.article.id);
        S.article = normalizeArticle(art.data);
        renderBlocks(S.article.blocks || []);
        // refresh preview in modal
        const newB = (S.article.blocks || []).find(x => x.id === blockId);
        const newId = newB && newB.content && newB.content.image_id;
        if (newId) {
            el('imgModalPreview').innerHTML = '<img src="' + API + 'images/' + newId + '/raw&_=' + Date.now() + '" alt="" style="max-width:100%;max-height:60vh;border-radius:6px">';
        }
        toast('Изображение обновлено', 'ok');
    } catch(e) { toast(e.message, 'err'); }
    btn.disabled = false;
    sp.innerHTML = '';
}
