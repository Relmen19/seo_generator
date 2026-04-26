async function generateAll() {
    if (!S.article) return;
    if (!confirm('Сгенерировать все блоки и SEO meta? Это перезапишет текущий контент.')) return;

    const btn = el('btnGenerate');
    btn.disabled = true;
    el('genSpin').innerHTML = '<span class="spin spin-white"></span>';
    const articleId = S.article.id;

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
        if (name === 'start' && data.total_blocks) {
            blocksTotal = +data.total_blocks;
            Progress.update(jobId, { blocksDone: 0, blocksTotal });
        }
        if (name === 'block_done') {
            blocksDone = (+data.index || blocksDone) + 1;
            bumpTokens(data.usage);
            Progress.update(jobId, { blocksDone, blocksTotal });
        }
        if (name === 'block_start') {
            Progress.update(jobId, { sub: (data.name || data.type || '') });
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
                const meta = await api('generate/' + articleId + '/meta', 'POST', {});
                bumpTokens(meta.data && meta.data.usage);
            } catch(_) {}
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
