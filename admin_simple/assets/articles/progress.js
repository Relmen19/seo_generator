// Sticky progress bar for long-running operations.
// Public API:
//   Progress.start({ label, articleId, cancellable, onCancel })  → jobId
//   Progress.update(jobId, { phase, sub, pct, tokens, blocksDone, blocksTotal })
//   Progress.success(jobId, msg)
//   Progress.error(jobId, msg)
//   Progress.cancelled(jobId, msg)
//
// Multiple jobs queue and stack vertically. Each job has its own row in the bar.

const Progress = (function () {
    let nextId = 1;
    const jobs = new Map();
    let containerEl = null;

    function ensureContainer() {
        if (containerEl) return containerEl;
        containerEl = document.createElement('div');
        containerEl.className = 'progress-bar';
        containerEl.id = 'globalProgressBar';
        const tb = document.querySelector('.topbar');
        if (tb && tb.parentNode) tb.parentNode.insertBefore(containerEl, tb.nextSibling);
        else document.body.insertBefore(containerEl, document.body.firstChild);
        return containerEl;
    }

    function renderJob(job) {
        const row = document.createElement('div');
        row.className = 'progress-row state-' + job.state;
        row.dataset.jobId = String(job.id);

        const label = document.createElement('div');
        label.className = 'progress-label';
        label.textContent = job.label;

        const phase = document.createElement('div');
        phase.className = 'progress-phase';
        phase.textContent = job.phase || '';

        const meter = document.createElement('div');
        meter.className = 'progress-meter';
        const fill = document.createElement('div');
        fill.className = 'progress-fill';
        if (typeof job.pct === 'number') fill.style.width = Math.max(0, Math.min(100, job.pct)) + '%';
        else { fill.classList.add('indeterminate'); }
        meter.appendChild(fill);

        const sub = document.createElement('div');
        sub.className = 'progress-sub';
        sub.textContent = job.sub || '';

        const tokens = document.createElement('div');
        tokens.className = 'progress-tokens';
        if (typeof job.tokens === 'number' && job.tokens > 0) {
            tokens.textContent = job.tokens.toLocaleString('ru-RU') + ' tok';
        }

        row.appendChild(label);
        row.appendChild(phase);
        row.appendChild(meter);
        row.appendChild(sub);
        row.appendChild(tokens);

        if (job.cancellable && job.state === 'running') {
            const cancel = document.createElement('button');
            cancel.className = 'progress-cancel';
            cancel.type = 'button';
            cancel.textContent = '✕ Отменить';
            cancel.onclick = () => {
                if (job.state !== 'running') return;
                cancel.disabled = true;
                cancel.textContent = 'Отменяю…';
                if (typeof job.onCancel === 'function') {
                    try { job.onCancel(); } catch (_) {}
                }
            };
            row.appendChild(cancel);
        }

        if (job.state !== 'running') {
            const close = document.createElement('button');
            close.className = 'progress-close';
            close.type = 'button';
            close.textContent = '✕';
            close.onclick = () => removeJob(job.id);
            row.appendChild(close);
        }

        return row;
    }

    function rerender(job) {
        const c = ensureContainer();
        const existing = c.querySelector('[data-job-id="' + job.id + '"]');
        const fresh = renderJob(job);
        if (existing) c.replaceChild(fresh, existing); else c.appendChild(fresh);
        c.classList.add('open');
    }

    function removeJob(id) {
        const c = ensureContainer();
        const node = c.querySelector('[data-job-id="' + id + '"]');
        if (node) node.remove();
        jobs.delete(id);
        if (!c.children.length) c.classList.remove('open');
    }

    function start(opts) {
        const id = nextId++;
        const job = {
            id,
            label: opts.label || 'Operation',
            articleId: opts.articleId || null,
            cancellable: !!opts.cancellable,
            onCancel: opts.onCancel || null,
            state: 'running',
            phase: '',
            sub: '',
            pct: null,
            tokens: 0,
        };
        jobs.set(id, job);
        rerender(job);
        return id;
    }

    function update(id, patch) {
        const job = jobs.get(id);
        if (!job) return;
        if ('phase' in patch)       job.phase = patch.phase;
        if ('sub' in patch)         job.sub = patch.sub;
        if ('pct' in patch)         job.pct = patch.pct;
        if ('tokens' in patch)      job.tokens = patch.tokens;
        if ('blocksDone' in patch && 'blocksTotal' in patch && patch.blocksTotal > 0) {
            job.pct = Math.round(patch.blocksDone / patch.blocksTotal * 100);
            job.sub = patch.blocksDone + ' / ' + patch.blocksTotal + ' блоков';
        }
        rerender(job);
    }

    function finish(id, state, msg) {
        const job = jobs.get(id);
        if (!job) return;
        job.state = state;
        if (msg) job.sub = msg;
        if (state === 'success') job.pct = 100;
        rerender(job);
        setTimeout(() => removeJob(id), state === 'success' ? 4000 : 8000);
    }

    return {
        start,
        update,
        success:   (id, msg) => finish(id, 'success', msg || 'Готово'),
        error:     (id, msg) => finish(id, 'error',   msg || 'Ошибка'),
        cancelled: (id, msg) => finish(id, 'cancelled', msg || 'Отменено'),
    };
})();

window.Progress = Progress;
