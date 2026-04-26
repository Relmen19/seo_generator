async function generateAll() {
    if (!S.article) return;
    if (!confirm('Сгенерировать все блоки и SEO meta? Это перезапишет текущий контент.')) return;

    const btn = el('btnGenerate');
    btn.disabled = true;
    el('genSpin').innerHTML = '<span class="spin spin-white"></span>';
    el('genProgress').style.display = '';
    const steps = el('genSteps');
    steps.innerHTML = '<div class="ai-step active"><div class="ai-step-dot"></div>Запуск генерации...</div>';

    const addStep = (msg, state='active') => {
        const prev = steps.querySelector('.ai-step.active');
        if (prev) prev.className = 'ai-step done';
        const d = document.createElement('div');
        d.className = 'ai-step ' + state;
        d.innerHTML = '<div class="ai-step-dot"></div>' + esc(msg);
        steps.appendChild(d);
        steps.scrollTop = steps.scrollHeight;
    };

    try {
        // 1) Блоки через SSE
        const res = await fetch(API + 'generate/' + S.article.id + '/sse', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}'
        });
        if (!res.body) throw new Error('Streaming не поддерживается');
        const reader = res.body.getReader();
        const dec = new TextDecoder();
        let buf = '';
        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            buf += dec.decode(value, { stream: true });
            const lines = buf.split('\n');
            buf = lines.pop();
            for (const line of lines) {
                if (line.startsWith('data:')) {
                    try {
                        const d = JSON.parse(line.slice(5).trim());
                        if (d.step || d.message) addStep(d.step || d.message);
                    } catch(_) {}
                }
            }
        }

        // 2) Meta
        addStep('Генерация SEO meta...');
        try {
            await api('generate/' + S.article.id + '/meta', 'POST', {});
        } catch(e) { /* non-fatal */ }

        const last = steps.querySelector('.ai-step.active');
        if (last) last.className = 'ai-step done';
        addStep('Готово', 'done');

        // Reload
        const art = await api('articles/' + S.article.id);
        S.article = normalizeArticle(art.data);
        renderEditor();
        toast('Генерация завершена', 'ok');
    } catch(e) {
        addStep(e.message, 'error');
        toast(e.message, 'err');
    }

    btn.disabled = false;
    el('genSpin').innerHTML = '';
    setTimeout(() => { el('genProgress').style.display = 'none'; }, 3000);
}

