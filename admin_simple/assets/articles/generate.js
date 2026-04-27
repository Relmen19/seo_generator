// Per-stage panel renderer. Driven by SSE events from generateAllBlocksSSE.
// Shows pending/running/done/failed for each pipeline stage with timing + retries.
const GenStages = (function () {
    const ALL = [
        { key: 'research', label: 'Research (досье)', advancedOnly: true },
        { key: 'outline',  label: 'Outline (план секций)', advancedOnly: true },
        { key: 'blocks',   label: 'Блоки контента' },
        { key: 'meta',     label: 'Meta-теги' },
        { key: 'hero',     label: 'Hero-картинка', optional: true },
        { key: 'og',       label: 'OG-картинка',   optional: true },
    ];

    let state = {};
    let mode = 'simple';

    function reset(modeName) {
        mode = modeName || 'simple';
        state = {};
        for (const s of ALL) {
            if (s.advancedOnly && mode !== 'advanced') continue;
            state[s.key] = {
                key: s.key, label: s.label, status: 'pending',
                startedAt: null, durationMs: null, retries: 0,
                blocksDone: 0, blocksTotal: 0, error: null, sub: '',
            };
        }
        const panel = document.getElementById('genStagesPanel');
        if (panel) { panel.style.display = ''; panel.open = true; }
        render();
    }

    function setMode(modeName) {
        if (mode === modeName) return;
        reset(modeName);
    }

    function get(key) { return state[key] || null; }

    function start(key) {
        const s = state[key]; if (!s) return;
        s.status = 'running'; s.startedAt = Date.now(); s.error = null;
        render();
    }
    function done(key, sub) {
        const s = state[key]; if (!s) return;
        s.status = 'done';
        s.durationMs = s.startedAt ? Date.now() - s.startedAt : s.durationMs;
        if (sub) s.sub = sub;
        render();
    }
    function fail(key, msg) {
        const s = state[key]; if (!s) return;
        s.status = 'failed';
        s.durationMs = s.startedAt ? Date.now() - s.startedAt : s.durationMs;
        s.error = msg || 'Ошибка';
        render();
    }
    function retry(key, reason) {
        const s = state[key]; if (!s) return;
        s.retries += 1;
        s.sub = (reason ? 'retry: ' + reason : 'retry');
        render();
    }
    function blocksProgress(done, total, sub) {
        const s = state.blocks; if (!s) return;
        s.blocksDone = done; s.blocksTotal = total;
        if (sub) s.sub = sub;
        render();
    }

    function badge(status) {
        const map = {
            pending:  ['—',  'gen-st-pending'],
            running:  ['…',  'gen-st-running'],
            done:     ['✓',  'gen-st-done'],
            failed:   ['✕',  'gen-st-failed'],
        };
        const [t, cls] = map[status] || map.pending;
        return `<span class="gen-st-badge ${cls}">${t}</span>`;
    }

    function fmtDuration(ms) {
        if (ms == null) return '';
        if (ms < 1000) return ms + ' мс';
        return (ms / 1000).toFixed(1) + ' с';
    }

    function render() {
        const body = document.getElementById('genStagesBody');
        if (!body) return;
        const rows = Object.values(state).map(s => {
            let extra = '';
            if (s.key === 'blocks' && s.blocksTotal > 0) {
                extra = ` · ${s.blocksDone}/${s.blocksTotal}`;
            }
            const dur = s.durationMs != null ? ' · ' + fmtDuration(s.durationMs) : '';
            const ret = s.retries > 0 ? ` · повторов: ${s.retries}` : '';
            const sub = s.sub ? `<div class="gen-st-sub">${esc(s.sub)}</div>` : '';
            const err = s.error ? `<div class="gen-st-err">${esc(s.error)}</div>` : '';
            return `
                <div class="gen-st-row st-${s.status}">
                    ${badge(s.status)}
                    <div class="gen-st-main">
                        <div class="gen-st-name">${esc(s.label)}${extra}${dur}${ret}</div>
                        ${sub}${err}
                    </div>
                </div>`;
        }).join('');
        body.innerHTML = rows;
    }

    function esc(s) {
        return String(s).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    return { reset, setMode, start, done, fail, retry, blocksProgress, get };
})();
window.GenStages = GenStages;

async function changeGenerationMode(mode) {
    if (!S.article) return;
    if (mode !== 'simple' && mode !== 'advanced') return;
    if (S.article.generation_mode === mode) return;
    try {
        await api('articles/' + S.article.id, 'PUT', { generation_mode: mode });
        S.article.generation_mode = mode;
        toast('Режим: ' + (mode === 'advanced' ? 'Расширенный' : 'Простой'), 'ok');
    } catch (e) {
        toast(e.message, 'err');
        const sel = el('genModeSelect');
        if (sel) sel.value = S.article.generation_mode || 'simple';
    }
}

async function generateAll() {
    if (!S.article) return;
    if (!confirm('Сгенерировать все блоки и SEO meta? Это перезапишет текущий контент.')) return;

    const btn = el('btnGenerate');
    btn.disabled = true;
    el('genSpin').innerHTML = '<span class="spin spin-white"></span>';
    const articleId = S.article.id;

    // Default to article's saved mode; pipeline_mode SSE event will confirm.
    const initialMode = (S.article && S.article.generation_mode) || 'simple';
    GenStages.reset(initialMode);

    const ac = new AbortController();
    let serverCancelled = false;
    const jobId = Progress.start({
        label: '🚀 Генерация статьи',
        articleId: articleId,
        cancellable: true,
        onCancel: async () => {
            try {
                await fetch(API + 'generate/' + articleId + '/cancel', { method: 'POST' });
                serverCancelled = true;
            } catch(_) {}
        },
    });

    let tokens = 0;
    let phase = '';
    let blocksDone = 0;
    let blocksTotal = 0;
    const phaseLabel = {
        research_start: 'research',
        outline_start:  'outline',
        meta_start:     'meta',
        start:          'blocks',
        block_start:    'blocks',
        hero_start:     'hero',
        og_start:       'og',
    };

    function bumpTokens(usage) {
        if (!usage) return;
        const t = +(usage.total_tokens || 0)
               || ((+usage.prompt_tokens || 0) + (+usage.completion_tokens || 0));
        if (t > 0) {
            tokens += t;
            Progress.update(jobId, { tokens });
        }
    }

    function handleEvent(name, data) {
        if (phaseLabel[name]) {
            phase = phaseLabel[name];
            Progress.update(jobId, { phase });
        }

        // Per-stage panel
        if (name === 'pipeline_mode') {
            GenStages.setMode(data.mode || 'simple');
            return;
        }
        if (name === 'research_start') GenStages.start('research');
        if (name === 'research_done')  GenStages.done('research');
        if (name === 'research_error') GenStages.fail('research', data.error);
        if (name === 'outline_start')  GenStages.start('outline');
        if (name === 'outline_done')   GenStages.done('outline', (data.sections != null ? data.sections + ' секций' : ''));
        if (name === 'outline_error')  GenStages.fail('outline', data.error);
        if (name === 'meta_start')     GenStages.start('meta');
        if (name === 'meta_done')      GenStages.done('meta');
        if (name === 'meta_error')     GenStages.fail('meta', data.error);
        if (name === 'hero_start')     GenStages.start('hero');
        if (name === 'hero_done')      GenStages.done('hero');
        if (name === 'hero_error')     GenStages.fail('hero', data.error);
        if (name === 'og_start')       GenStages.start('og');
        if (name === 'og_done')        GenStages.done('og');
        if (name === 'og_error')       GenStages.fail('og', data.error);

        if (name === 'start' && data.total_blocks) {
            blocksTotal = +data.total_blocks;
            GenStages.start('blocks');
            GenStages.blocksProgress(0, blocksTotal);
            Progress.update(jobId, { blocksDone: 0, blocksTotal });
        }
        if (name === 'block_done') {
            blocksDone = (+data.index || blocksDone) + 1;
            bumpTokens(data.usage);
            GenStages.blocksProgress(blocksDone, blocksTotal, data.name || data.type || '');
            if (blocksDone >= blocksTotal && blocksTotal > 0) GenStages.done('blocks');
            Progress.update(jobId, { blocksDone, blocksTotal });
        }
        if (name === 'block_start') {
            Progress.update(jobId, { sub: (data.name || data.type || '') });
            const s = GenStages.get('blocks');
            if (s) GenStages.blocksProgress(s.blocksDone, blocksTotal, data.name || data.type || '');
        }
        if (name === 'block_retry') {
            GenStages.retry('blocks', data.name || data.reason || 'empty');
        }
        if (name === 'block_failed') {
            GenStages.fail('blocks', 'Required-блок «' + (data.name || '?') + '» пуст после повтора');
        }
        if (name === 'block_error') {
            GenStages.fail('blocks', '[' + (data.name || data.type || '') + '] ' + (data.error || 'ошибка'));
        }
        if (name === 'research_done' || name === 'outline_done' || name === 'meta_done' || name === 'hero_done') {
            bumpTokens(data.usage);
        }
        if (name === 'cancelled') {
            serverCancelled = true;
            const blk = data.blocks_done != null ? (', блоков ' + data.blocks_done + '/' + (data.total_blocks || '?')) : '';
            const t = data.usage && data.usage.total_tokens ? (', токенов ' + data.usage.total_tokens) : '';
            Progress.cancelled(jobId, 'Отменено на фазе ' + (data.phase || phase) + blk + t);
            try { ac.abort(); } catch(_) {}
        }
        if (name === 'done') {
            const u = data.total_usage;
            if (u && u.total_tokens) tokens = u.total_tokens;
            Progress.update(jobId, { tokens });
            const s = GenStages.get('blocks');
            if (s && s.status === 'running') GenStages.done('blocks');
        }
        if (name === 'error') {
            Progress.error(jobId, data.message || 'Ошибка генерации');
            try { ac.abort(); } catch(_) {}
        }
    }

    try {
        const res = await fetch(API + 'generate/' + articleId + '/sse', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}',
            signal: ac.signal,
        });
        if (!res.body) throw new Error('Streaming не поддерживается');
        const reader = res.body.getReader();
        const dec = new TextDecoder();
        let buf = '';
        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            buf += dec.decode(value, { stream: true });
            const chunks = buf.split('\n\n');
            buf = chunks.pop();
            for (const chunk of chunks) {
                let evName = 'message';
                let payload = '';
                for (const line of chunk.split('\n')) {
                    if (line.startsWith('event:')) evName = line.slice(6).trim();
                    else if (line.startsWith('data:')) payload += line.slice(5).trim();
                }
                if (!payload) continue;
                let data;
                try { data = JSON.parse(payload); } catch(_) { continue; }
                handleEvent(evName, data);
            }
        }

        if (!serverCancelled) {
            try {
                GenStages.start('meta');
                const meta = await api('generate/' + articleId + '/meta', 'POST', {});
                bumpTokens(meta.data && meta.data.usage);
                GenStages.done('meta');
            } catch(e) {
                GenStages.fail('meta', e.message);
            }
            Progress.success(jobId, 'Готово · токенов ' + tokens);
        }

        const art = await api('articles/' + articleId);
        S.article = normalizeArticle(art.data);
        renderEditor();
        if (typeof loadImages === 'function') loadImages();
        if (!serverCancelled) toast('Генерация завершена', 'ok');
    } catch(e) {
        if (e.name === 'AbortError' || serverCancelled) {
            // already reported via Progress.cancelled
        } else {
            Progress.error(jobId, e.message);
            toast(e.message, 'err');
        }
    }

    btn.disabled = false;
    el('genSpin').innerHTML = '';
}
