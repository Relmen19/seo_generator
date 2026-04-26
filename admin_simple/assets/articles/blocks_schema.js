function humanKey(k) {
    return String(k).replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function labelFor(key, spec) {
    return humanKey(key);
}

function pathAttr(path) { return esc(JSON.stringify(path)); }

function getByPath(root, path) {
    let cur = root;
    for (const p of path) { if (cur == null) return undefined; cur = cur[p]; }
    return cur;
}
function setByPath(root, path, value) {
    let cur = root;
    for (let i = 0; i < path.length - 1; i++) cur = cur[path[i]];
    cur[path[path.length - 1]] = value;
}

// Normalize schema spec → {kind, ...}
function normSchema(spec) {
    if (spec == null) return { kind: 'any' };
    if (typeof spec === 'string') {
        const s = spec.toLowerCase();
        if (s.includes('array of string')) return { kind: 'arrayOfStrings', hint: spec };
        if (s.startsWith('array')) return { kind: 'arrayOfAny', hint: spec };
        if (s.includes('boolean')) return { kind: 'boolean' };
        if (s.includes('integer') || s.includes('number')) return { kind: 'number' };
        if (s.includes('hex')) return { kind: 'color' };
        const em = spec.match(/(?::|^)\s*([a-z][a-z0-9_-]*(\|[a-z][a-z0-9_-]*)+)/i);
        if (em) return { kind: 'enum', enum: em[1].split('|').map(x => x.trim()) };
        return { kind: 'string', hint: spec };
    }
    if (typeof spec !== 'object') return { kind: 'any' };
    if (Array.isArray(spec.enum) && spec.enum.length) {
        return { kind: 'enum', enum: spec.enum.slice(), required: !!spec.required };
    }
    const t = spec.type ? String(spec.type).toLowerCase() : '';
    if (t === 'array' || t.startsWith('array')) {
        if (typeof spec.items === 'string') return { kind: 'arrayOfPrimitives', itemSchema: normSchema(spec.items), required: !!spec.required };
        if (spec.items && typeof spec.items === 'object') {
            const ikeys = Object.keys(spec.items);
            const scalarOnly = ['type', 'required', 'enum', 'note'];
            const hasOther = ikeys.some(k => !scalarOnly.includes(k));
            if (hasOther) return { kind: 'arrayOfObjects', fields: spec.items, required: !!spec.required };
            return { kind: 'arrayOfPrimitives', itemSchema: normSchema(spec.items), required: !!spec.required };
        }
        return { kind: 'arrayOfAny', required: !!spec.required };
    }
    if (t === 'object' || spec.fields) return { kind: 'object', fields: spec.fields || {}, required: !!spec.required };
    if (t === 'integer' || t === 'number') return { kind: 'number', required: !!spec.required };
    if (t === 'boolean') return { kind: 'boolean', required: !!spec.required };
    if (t.includes(':') && t.includes('|')) {
        const parts = t.split(':')[1].split('|').map(x => x.trim()).filter(Boolean);
        if (parts.length) return { kind: 'enum', enum: parts, required: !!spec.required };
    }
    if (t.includes('hex')) return { kind: 'color', required: !!spec.required };
    return { kind: 'string', required: !!spec.required, hint: spec.note || '' };
}

function resolveSchemaAtPath(blockCode, path) {
    const meta = blockTypeMeta(blockCode);
    if (!meta || !meta.json_schema || !meta.json_schema.fields) return null;
    let cur = meta.json_schema.fields;
    let n = null;
    for (let i = 0; i < path.length; i++) {
        const p = path[i];
        if (typeof p === 'number') {
            // should have been handled by prior array spec; unreachable with proper traversal
            return null;
        }
        const spec = (cur && typeof cur === 'object') ? cur[p] : null;
        if (spec == null) return null;
        n = normSchema(spec);
        if (i === path.length - 1) return n;
        const next = path[i + 1];
        if (n.kind === 'object') { cur = n.fields; continue; }
        if (n.kind === 'arrayOfObjects' && typeof next === 'number') { cur = n.fields; i++; continue; }
        if ((n.kind === 'arrayOfStrings' || n.kind === 'arrayOfPrimitives') && typeof next === 'number') {
            // leaf scalar under array
            return n.itemSchema || { kind: 'string' };
        }
        return n;
    }
    return n;
}

function protoFromSchema(n) {
    if (!n) return '';
    if (n.kind === 'object') {
        const o = {};
        Object.keys(n.fields || {}).forEach(k => { o[k] = protoFromSchema(normSchema(n.fields[k])); });
        return o;
    }
    if (n.kind === 'arrayOfObjects' || n.kind === 'arrayOfStrings' || n.kind === 'arrayOfPrimitives' || n.kind === 'arrayOfAny') return [];
    if (n.kind === 'number') return 0;
    if (n.kind === 'boolean') return false;
    if (n.kind === 'enum' && n.enum && n.enum.length) return n.enum[0];
    return '';
}

// Keys commonly containing long text → use textarea even when schema says plain string
const LONG_TEXT_KEYS = new Set(['text','content','answer','subtitle','description','caption','verdict','reason','quote','note','footer','explanation','question']);

function isLongTextKey(path) {
    const last = path[path.length - 1];
    if (typeof last !== 'string') return false;
    return LONG_TEXT_KEYS.has(last.toLowerCase());
}

function renderSchemaField(blockId, path, value, label, spec) {
    const n = normSchema(spec);
    if (n.kind === 'object') {
        const obj = (value && typeof value === 'object' && !Array.isArray(value)) ? value : {};
        const subSpecs = n.fields || {};
        const subKeys = Object.keys(subSpecs);
        const extra = Object.keys(obj).filter(k => !subSpecs.hasOwnProperty(k));
        const parts = [];
        subKeys.forEach(k => parts.push(renderSchemaField(blockId, path.concat([k]), obj[k], labelFor(k, subSpecs[k]), subSpecs[k])));
        extra.forEach(k => parts.push(renderInferredField(blockId, path.concat([k]), obj[k], labelFor(k, null))));
        return `<div class="blk-nested">
            <div class="blk-nested-label">${esc(label)}</div>
            <div class="blk-nested-body">${parts.join('')}</div>
        </div>`;
    }
    if (n.kind === 'arrayOfObjects') {
        return renderArrayOfObjects(blockId, path, Array.isArray(value) ? value : [], label, n.fields || {});
    }
    if (n.kind === 'arrayOfStrings' || n.kind === 'arrayOfPrimitives' || n.kind === 'arrayOfAny') {
        return renderArrayOfPrimitives(blockId, path, Array.isArray(value) ? value : [], label, n.itemSchema);
    }
    if (n.kind === 'enum') return renderEnum(path, value, label, n.enum);
    if (n.kind === 'boolean') return renderBool(path, value, label);
    if (n.kind === 'number') return renderNumber(path, value, label);
    if (n.kind === 'color') return renderColor(path, value, label);
    if (n.kind === 'string') return renderString(path, value, label, isLongTextKey(path));
    return renderInferredField(blockId, path, value, label);
}

function renderInferredField(blockId, path, value, label) {
    if (Array.isArray(value)) {
        const allPrim = value.every(v => v === null || typeof v !== 'object');
        if (allPrim) return renderArrayOfPrimitives(blockId, path, value, label, { kind: 'string' });
        return renderArrayOfObjects(blockId, path, value, label, inferObjectSchema(value[0]));
    }
    if (value && typeof value === 'object') {
        const fields = {};
        Object.keys(value).forEach(k => fields[k] = 'string');
        const spec = { type: 'object', fields };
        return renderSchemaField(blockId, path, value, label, spec);
    }
    if (typeof value === 'number') return renderNumber(path, value, label);
    if (typeof value === 'boolean') return renderBool(path, value, label);
    return renderString(path, value == null ? '' : String(value), label, isLongTextKey(path) || (typeof value === 'string' && (value.length > 80 || value.includes('\n'))));
}

function inferObjectSchema(sample) {
    if (!sample || typeof sample !== 'object') return { text: 'string' };
    const fields = {};
    Object.keys(sample).forEach(k => {
        const v = sample[k];
        if (Array.isArray(v)) fields[k] = 'array of strings';
        else if (typeof v === 'number') fields[k] = { type: 'number' };
        else if (typeof v === 'boolean') fields[k] = { type: 'boolean' };
        else fields[k] = 'string';
    });
    return fields;
}

/* ── Field renderers ── */

function renderString(path, value, label, long) {
    const pa = pathAttr(path);
    const v = value == null ? '' : String(value);
    if (long || v.length > 80 || v.includes('\n')) {
        return `<div class="field blk-wide"><label>${esc(label)}</label><textarea data-path='${pa}' rows="3">${esc(v)}</textarea></div>`;
    }
    return `<div class="field"><label>${esc(label)}</label><input type="text" data-path='${pa}' value="${esc(v)}"></div>`;
}

function renderNumber(path, value, label) {
    const pa = pathAttr(path);
    const v = (value === null || value === undefined || value === '') ? '' : String(value);
    return `<div class="field"><label>${esc(label)}</label><input type="number" data-path='${pa}' data-type="number" value="${esc(v)}"></div>`;
}

function renderBool(path, value, label) {
    const pa = pathAttr(path);
    const checked = !!value;
    return `<div class="field blk-bool">
        <label class="blk-switch">
            <input type="checkbox" data-path='${pa}' data-type="boolean" ${checked ? 'checked' : ''}>
            <span class="blk-switch-track"><span class="blk-switch-dot"></span></span>
            <span class="blk-switch-text">${esc(label)}</span>
        </label>
    </div>`;
}

function renderEnum(path, value, label, options) {
    const pa = pathAttr(path);
    const cur = value == null ? '' : String(value);
    const useChips = options.length <= 5;
    if (useChips) {
        const gname = 'enum-' + esc(JSON.stringify(path));
        const chips = options.map(o => {
            const on = cur === o;
            return `<label class="blk-chip ${on ? 'on' : ''}"><input type="radio" name="${gname}" data-path='${pa}' value="${esc(o)}" ${on ? 'checked' : ''}> ${esc(o)}</label>`;
        }).join('');
        return `<div class="field"><label>${esc(label)}</label><div class="blk-chips">${chips}</div></div>`;
    }
    const opts = options.map(o => `<option value="${esc(o)}" ${cur === o ? 'selected' : ''}>${esc(o)}</option>`).join('');
    return `<div class="field"><label>${esc(label)}</label><select data-path='${pa}'><option value="">—</option>${opts}</select></div>`;
}

function renderColor(path, value, label) {
    const pa = pathAttr(path);
    const hex = (typeof value === 'string' && /^#[0-9A-Fa-f]{3,8}$/.test(value)) ? value : '';
    return `<div class="field"><label>${esc(label)}</label>
        <div class="blk-color">
            <input type="color" class="blk-color-swatch" value="${hex || '#3b82f6'}" oninput="blkColorSync(this)">
            <input type="text" class="blk-color-hex" data-path='${pa}' value="${esc(value == null ? '' : String(value))}" oninput="blkColorSync(this)" placeholder="#RRGGBB">
        </div>
    </div>`;
}

function renderArrayOfPrimitives(blockId, path, arr, label, itemSchema) {
    const pa = pathAttr(path);
    const kind = (itemSchema && itemSchema.kind) || 'string';
    const chips = arr.map((v, i) => {
        const rpa = pathAttr(path.concat([i]));
        const val = v == null ? '' : String(v);
        if (kind === 'number') {
            return `<div class="blk-chip-input"><input type="number" data-path='${rpa}' data-type="number" value="${esc(val)}"><button type="button" class="blk-chip-del" onclick="blkArrDel(${blockId}, ${pa}, ${i})">✕</button></div>`;
        }
        return `<div class="blk-chip-input"><input type="text" data-path='${rpa}' value="${esc(val)}"><button type="button" class="blk-chip-del" onclick="blkArrDel(${blockId}, ${pa}, ${i})">✕</button></div>`;
    }).join('');
    return `<div class="blk-nested blk-arr blk-wide">
        <div class="blk-nested-label">${esc(label)} <span class="blk-arr-count">${arr.length}</span></div>
        <div class="blk-chip-list">${chips || '<div class="field-hint">Пусто</div>'}</div>
        <button type="button" class="btn btn-secondary btn-sm blk-add" onclick="blkArrAdd(${blockId}, ${pa})">+ Добавить</button>
    </div>`;
}

function renderArrayOfObjects(blockId, path, arr, label, fieldsSpec) {
    const pa = pathAttr(path);
    const items = arr.map((v, i) => {
        const obj = (v && typeof v === 'object' && !Array.isArray(v)) ? v : {};
        const preview = arrObjPreview(obj, fieldsSpec);
        const subKeys = Object.keys(fieldsSpec || {});
        const extra = Object.keys(obj).filter(k => !subKeys.includes(k));
        const parts = [];
        subKeys.forEach(k => parts.push(renderSchemaField(blockId, path.concat([i, k]), obj[k], labelFor(k, fieldsSpec[k]), fieldsSpec[k])));
        extra.forEach(k => parts.push(renderInferredField(blockId, path.concat([i, k]), obj[k], labelFor(k, null))));
        return `<details class="blk-arr-card" ${i === 0 ? 'open' : ''}>
            <summary class="blk-arr-card-sum">
                <span class="blk-arr-idx">#${i + 1}</span>
                <span class="blk-arr-preview">${esc(preview || '—')}</span>
                <button type="button" class="blk-del" onclick="event.preventDefault();event.stopPropagation();blkArrDel(${blockId}, ${pa}, ${i})">✕</button>
                <span class="blk-arr-caret">▾</span>
            </summary>
            <div class="blk-arr-card-body">
                <div class="blk-nested-body">${parts.join('')}</div>
            </div>
        </details>`;
    }).join('');
    return `<div class="blk-nested blk-arr blk-wide">
        <div class="blk-nested-label">${esc(label)} <span class="blk-arr-count">${arr.length}</span></div>
        <div class="blk-arr-cards">${items || '<div class="field-hint">Пусто</div>'}</div>
        <button type="button" class="btn btn-secondary btn-sm blk-add" onclick="blkArrAdd(${blockId}, ${pa})">+ Добавить</button>
    </div>`;
}

function arrObjPreview(obj, fieldsSpec) {
    const priority = ['title','name','question','label','headline','text','subtitle','content','rule'];
    for (const k of priority) {
        if (obj[k] && typeof obj[k] === 'string') return obj[k].slice(0, 80);
    }
    const keys = Object.keys(fieldsSpec || obj);
    for (const k of keys) {
        const v = obj[k];
        if (typeof v === 'string' && v.trim()) return v.slice(0, 80);
    }
    return '';
}

function blkColorSync(source) {
    const row = source.closest('.blk-color');
    if (!row) return;
    const swatch = row.querySelector('.blk-color-swatch');
    const hex = row.querySelector('.blk-color-hex');
    if (source === swatch) hex.value = swatch.value;
    else {
        const v = hex.value.trim();
        if (/^#[0-9A-Fa-f]{6}$/.test(v)) swatch.value = v;
    }
}

function blkCollectIntoDraft(blockId) {
    const b = (S.article.blocks || []).find(x => x.id === blockId);
    if (!b || !b._draft) return;
    const pane = el('blockPane-' + blockId);
    if (!pane) return;
    pane.querySelectorAll('[data-path]').forEach(input => {
        const path = JSON.parse(input.getAttribute('data-path'));
        const t = input.getAttribute('data-type');
        const tag = input.tagName;
        let v;
        if (input.type === 'radio') {
            if (!input.checked) return; // only winning radio writes
            v = input.value;
        } else if (input.type === 'checkbox') {
            v = !!input.checked;
        } else {
            v = input.value;
        }
        if (t === 'number') {
            if (v === '' || v == null) v = null;
            else { const n = parseFloat(v); v = isNaN(n) ? v : n; }
        } else if (t === 'boolean') {
            v = !!input.checked;
        }
        try { setByPath(b._draft, path, v); } catch(e) {}
    });
}

function blkRerenderPane(blockId) {
    const b = (S.article.blocks || []).find(x => x.id === blockId);
    if (!b) return;
    const pane = el('blockPane-' + blockId);
    if (!pane) return;
    const tab = S.blockTabs[blockId] || 'form';
    pane.innerHTML = renderBlockPane(b, tab);
}
