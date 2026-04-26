// ─── Editorial QA ───
async function refreshQaIssues() {
    if (!S.article) return;
    try {
        const res = await api('qa/' + S.article.id);
        renderQaIssues(res.data || []);
    } catch (e) {
        renderQaIssues([]);
    }
}

function renderQaIssues(issues) {
    const wrap = el('qaIssuesList');
    const summary = el('qaSummary');
    const forceWrap = el('qaForceWrap');
    if (!wrap) return;
    if (!issues.length) {
        wrap.innerHTML = '<div style="font-size:13px;color:var(--text-3)">Замечаний нет — либо проверки ещё не запускались.</div>';
        summary.textContent = '';
        forceWrap.style.display = 'none';
        return;
    }
    const c = { error: 0, warn: 0, info: 0 };
    for (const i of issues) c[i.severity] = (c[i.severity] || 0) + 1;
    summary.textContent = (c.error ? '⛔ ' + c.error + '  ' : '') + (c.warn ? '⚠️ ' + c.warn + '  ' : '') + (c.info ? 'ℹ️ ' + c.info : '');
    forceWrap.style.display = c.error > 0 ? '' : 'none';
    wrap.innerHTML = issues.map(i => {
        const color = i.severity === 'error' ? 'var(--danger)' : (i.severity === 'warn' ? '#a06200' : 'var(--text-3)');
        const bg = i.severity === 'error' ? 'var(--danger-light)' : (i.severity === 'warn' ? '#fff7e6' : 'var(--bg)');
        const icon = i.severity === 'error' ? '⛔' : (i.severity === 'warn' ? '⚠️' : 'ℹ️');
        return '<div style="display:flex;justify-content:space-between;gap:8px;padding:8px 10px;border-radius:8px;background:' + bg + ';color:' + color + ';font-size:13px">' +
               '<span><span style="font-weight:600;text-transform:uppercase;font-size:11px;margin-right:6px">' + icon + ' ' + esc(i.code) + '</span>' + esc(i.message) + '</span>' +
               '<button class="btn btn-ghost btn-sm" onclick="resolveQaIssue(' + i.id + ')" title="Отметить решённым">✓</button>' +
               '</div>';
    }).join('');
}

async function runQaChecks() {
    if (!S.article) return;
    const btn = el('btnRunQa');
    btn.disabled = true;
    const prev = btn.innerHTML;
    btn.innerHTML = 'Проверяю…';
    try {
        const aiReview = !!(el('qaAiReview') && el('qaAiReview').checked);
        const res = await api('qa/' + S.article.id + '/run', 'POST', { ai_review: aiReview });
        renderQaIssues(res.data.issues || []);
        toast(aiReview ? 'Проверки + AI-ревью выполнены' : 'Проверки выполнены', 'ok');
    } catch (e) {
        toast(e.message, 'err');
    } finally {
        btn.disabled = false;
        btn.innerHTML = prev;
    }
}

async function runQaFix() {
    if (!S.article) return;
    const btn = el('btnFixQa');
    btn.disabled = true;
    const prev = btn.innerHTML;
    btn.innerHTML = 'Исправляю…';
    try {
        const res = await api('qa/' + S.article.id + '/fix', 'POST', {
            codes: ['repetition', 'banned_phrase', 'empty_chart'],
        });
        const fixed = res.data?.report?.fixed_blocks ?? 0;
        renderQaIssues(res.data.issues || []);
        await openArticle(S.article.id);
        toast('Исправлено блоков: ' + fixed, fixed > 0 ? 'ok' : '');
    } catch (e) {
        toast(e.message, 'err');
    } finally {
        btn.disabled = false;
        btn.innerHTML = prev;
    }
}

async function resolveQaIssue(issueId) {
    try {
        await api('qa/' + S.article.id + '/resolve', 'POST', { issue_id: issueId });
        await refreshQaIssues();
    } catch (e) {
        toast(e.message, 'err');
    }
}

