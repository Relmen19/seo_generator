function renderBlocks(blocks) {
    el('blocksCount').textContent = blocks.length + ' блоков';
    if (!blocks.length) {
        el('blocksList').innerHTML = '<div class="empty"><div class="empty-icon">🧩</div><div class="empty-title">Блоков пока нет</div><div class="empty-sub">Запустите генерацию</div></div>';
        return;
    }
    el('blocksList').innerHTML = blocks.map((b, i) => renderBlockCard(b, i)).join('');
}

function renderBlockCard(b, idx) {
    const tab = S.blockTabs[b.id] || 'form';
    const meta = blockTypeMeta(b.type);
    const displayName = (meta && meta.display_name) || b.type;
    const name = b.name || displayName;
    const isImageBlock = b.type === 'image' || b.type === 'hero' || (b.content && (b.content.image_id || b.content.image_layout));
    const imgId = b.content && b.content.image_id;
    const thumb = imgId
        ? `<img class="block-thumb" src="${API}images/${imgId}/raw&_=${Date.now()}" alt="" onclick="event.stopPropagation(); openImageModal(${b.id})">`
        : '';

    return `
    <div class="block-card ${S.openBlockId === b.id ? 'open' : ''}" id="block-${b.id}">
        <div class="block-card-head" onclick="toggleBlock(${b.id})">
            <div class="block-sort">${idx + 1}</div>
            <span class="block-type">${esc(displayName)}</span>
            <span class="block-name">${esc(name)}</span>
            ${thumb}
            <span class="block-arrow">▼</span>
        </div>
        <div class="block-body">
            <div class="block-tabs">
                <div class="block-tab ${tab === 'form' ? 'active' : ''}" onclick="setBlockTab(${b.id}, 'form')">Форма</div>
                <div class="block-tab ${tab === 'preview' ? 'active' : ''}" onclick="setBlockTab(${b.id}, 'preview')">Превью</div>
            </div>
            <div id="blockPane-${b.id}">${renderBlockPane(b, tab)}</div>
            ${renderChatRegen(b)}
            ${isImageBlock ? renderImageBox(b) : ''}
        </div>
    </div>`;
}

function toggleBlock(blockId) {
    const card = el('block-' + blockId);
    if (!card) return;
    if (S.openBlockId === blockId) {
        S.openBlockId = null;
        card.classList.remove('open');
    } else {
        if (S.openBlockId) {
            const prev = el('block-' + S.openBlockId);
            if (prev) prev.classList.remove('open');
        }
        S.openBlockId = blockId;
        card.classList.add('open');
        // Load preview lazily if it's the default tab
        const tab = S.blockTabs[blockId] || 'form';
        if (tab === 'preview') loadPreview(blockId);
    }
}

function setBlockTab(blockId, tab) {
    // Preserve in-progress edits before swapping tabs
    if ((S.blockTabs[blockId] || 'form') === 'form') blkCollectIntoDraft(blockId);
    S.blockTabs[blockId] = tab;
    const b = (S.article.blocks || []).find(x => x.id === blockId);
    if (!b) return;
    el('blockPane-' + blockId).innerHTML = renderBlockPane(b, tab);
    if (tab === 'preview') loadPreview(blockId);
    // Refresh tab highlights
    const card = el('block-' + blockId);
    card.querySelectorAll('.block-tab').forEach((t, i) => {
        t.classList.toggle('active', (i === 0 && tab === 'form') || (i === 1 && tab === 'preview'));
    });
}

function renderBlockPane(b, tab) {
    if (tab === 'preview') {
        return `<div class="preview-frame" id="preview-${b.id}"><div class="preview-empty"><span class="spin"></span></div></div>`;
    }
    return renderFormFields(b);
}

function renderFormFields(b) {
    if (!b._draft) b._draft = JSON.parse(JSON.stringify(b.content || {}));
    const draft = b._draft;
    const skip = new Set(['image_id', 'image_layout', 'gpt_prompt']);
    const schemaFields = getSchemaFields(b) || {};
    const schemaKeys = Object.keys(schemaFields).filter(k => !skip.has(k));
    const extraKeys = Object.keys(draft).filter(k => !skip.has(k) && !schemaFields.hasOwnProperty(k));

    let html = renderNameField(b);
    const parts = [];
    schemaKeys.forEach(k => parts.push(renderSchemaField(b.id, [k], draft[k], labelFor(k, schemaFields[k]), schemaFields[k])));
    extraKeys.forEach(k => parts.push(renderInferredField(b.id, [k], draft[k], labelFor(k, null))));
    if (!parts.length) html += '<div class="field-hint">Блок не содержит полей для редактирования.</div>';
    else html += '<div class="blk-fields">' + parts.join('') + '</div>';
    html += '<div style="display:flex;justify-content:flex-end;gap:8px;margin-top:8px">' +
            '<button class="btn btn-primary btn-sm" onclick="saveBlockFields(' + b.id + ')"><span id="saveBlkSpin-' + b.id + '"></span> Сохранить блок</button>' +
            '</div>';
    return html;
}

function renderNameField(b) {
    return `
    <div class="field blk-wide">
        <label>Название блока</label>
        <input type="text" id="blkName-${b.id}" value="${esc(b.name || '')}" placeholder="Внутреннее название">
    </div>`;
}

function getSchemaFields(b) {
    const meta = blockTypeMeta(b.type);
    if (!meta || !meta.json_schema) return null;
    const s = meta.json_schema;
    return (s && typeof s === 'object' && s.fields && typeof s.fields === 'object') ? s.fields : null;
}
