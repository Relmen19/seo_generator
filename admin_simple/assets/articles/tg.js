// ─── Telegram sidebar ───
// Push-content panel: shows the latest draft/scheduled post, lets the user
// build / recompose / save / schedule / send.

const TG = {
    open: false,
    post: null,        // current loaded post (or null)
    loaded: false,     // whether tg state has been fetched for current article
};

function toggleTgSidebar() {
    TG.open = !TG.open;
    document.body.classList.toggle('tg-open', TG.open);
    if (TG.open && S.article && !TG.loaded) tgLoad();
}

async function tgLoad() {
    if (!S.article) return;
    const body = el('tgSidebarBody');
    body.innerHTML = '<div class="empty"><div class="spin"></div></div>';
    try {
        const res = await api('telegram/' + S.article.id + '/posts');
        const posts = (res.data || []).slice().sort((a, b) => (b.id || 0) - (a.id || 0));
        if (posts.length) {
            const full = await api('telegram/post/' + posts[0].id);
            TG.post = full.data || posts[0];
        } else {
            TG.post = null;
        }
        TG.loaded = true;
        tgRender();
    } catch (e) {
        body.innerHTML = '<div class="tg-empty" style="color:var(--danger)">Ошибка: ' + esc(e.message) + '</div>';
    }
}

function tgRender() {
    const body = el('tgSidebarBody');
    if (!TG.post) {
        body.innerHTML = `
            <div class="tg-empty">Черновика нет.</div>
            <button class="btn btn-primary" onclick="tgBuildDraft()" id="tgBuildBtn">
                <span id="tgBuildSpin"></span> ✨ Создать черновик
            </button>`;
        return;
    }
    const p = TG.post;
    const status = (p.status || 'draft');
    const sched = p.scheduled_at ? new Date(p.scheduled_at).toISOString().slice(0, 16) : '';
    const imgs = (p.images || []).map(img => `
        <div class="tg-post-img">
            <img src="${API}telegram/rendered-image/${img.id}&_=${Date.now()}" alt="">
            <button class="tg-img-del" onclick="tgDeleteImage(${img.id})" title="Удалить">×</button>
        </div>`).join('');
    body.innerHTML = `
        <div class="tg-meta">
            <span class="tg-status-pill tg-status-${esc(status)}">${esc(status)}</span>
            ${p.scheduled_at ? ' · запланирован ' + esc(p.scheduled_at) : ''}
            ${p.sent_at ? ' · отправлен ' + esc(p.sent_at) : ''}
        </div>
        <textarea class="tg-post-text" id="tgPostText" placeholder="Текст поста...">${esc(p.text || '')}</textarea>
        <div class="tg-post-imgs">${imgs || '<div class="tg-meta" style="grid-column:1/-1">Картинок нет</div>'}</div>
        <div class="tg-meta">
            <input type="datetime-local" id="tgSchedAt" value="${esc(sched)}" style="width:100%;font-size:12px;padding:4px 6px">
        </div>
        <div class="tg-actions">
            <button class="btn btn-secondary btn-sm" onclick="tgRecompose()" id="tgRecompBtn">
                <span id="tgRecompSpin"></span> ✨ Перегенерировать текст
            </button>
            <button class="btn btn-secondary btn-sm" onclick="tgSave()" id="tgSaveBtn">
                <span id="tgSaveSpin"></span> 💾 Сохранить
            </button>
            <button class="btn btn-secondary btn-sm" onclick="tgSchedule()" id="tgSchedBtn">
                <span id="tgSchedSpin"></span> ⏰ Запланировать
            </button>
            <button class="btn btn-primary btn-sm" onclick="tgSend()" id="tgSendBtn" ${status === 'sent' ? 'disabled' : ''}>
                <span id="tgSendSpin"></span> 📤 Отправить сейчас
            </button>
            <button class="btn btn-ghost btn-sm" onclick="tgDeletePost()" style="color:var(--danger)">🗑 Удалить пост</button>
        </div>`;
}

async function tgWith(btnId, spinId, fn) {
    const btn = el(btnId), sp = el(spinId);
    if (btn) btn.disabled = true;
    if (sp)  sp.innerHTML = '<span class="spin spin-white"></span>';
    try { await fn(); }
    catch (e) { toast(e.message, 'err'); }
    if (btn) btn.disabled = false;
    if (sp)  sp.innerHTML = '';
}

async function tgBuildDraft() {
    await tgWith('tgBuildBtn', 'tgBuildSpin', async () => {
        const res = await api('telegram/' + S.article.id + '/build-preview', 'POST', {});
        TG.post = (res.data && (res.data.post || res.data)) || null;
        if (TG.post && TG.post.id) {
            const full = await api('telegram/post/' + TG.post.id);
            TG.post = full.data || TG.post;
        }
        tgRender();
        toast('Черновик создан', 'ok');
    });
}

async function tgRecompose() {
    if (!TG.post) return;
    await tgWith('tgRecompBtn', 'tgRecompSpin', async () => {
        await api('telegram/recompose/' + TG.post.id, 'POST', {});
        const full = await api('telegram/post/' + TG.post.id);
        TG.post = full.data || TG.post;
        tgRender();
        toast('Текст обновлён', 'ok');
    });
}

async function tgSave() {
    if (!TG.post) return;
    await tgWith('tgSaveBtn', 'tgSaveSpin', async () => {
        const text = el('tgPostText').value;
        await api('telegram/post/' + TG.post.id, 'PUT', { text });
        TG.post.text = text;
        toast('Сохранено', 'ok');
    });
}

async function tgSchedule() {
    if (!TG.post) return;
    const v = el('tgSchedAt').value;
    if (!v) { toast('Укажите дату/время', 'err'); return; }
    await tgWith('tgSchedBtn', 'tgSchedSpin', async () => {
        // Persist text edits first.
        const text = el('tgPostText').value;
        await api('telegram/post/' + TG.post.id, 'PUT', { text });
        await api('telegram/' + S.article.id + '/schedule', 'POST', {
            post_id: TG.post.id,
            scheduled_at: v.replace('T', ' ') + ':00',
        });
        await tgLoad();
        toast('Запланировано', 'ok');
    });
}

async function tgSend() {
    if (!TG.post) return;
    if (!confirm('Отправить пост в канал прямо сейчас?')) return;
    await tgWith('tgSendBtn', 'tgSendSpin', async () => {
        const text = el('tgPostText').value;
        await api('telegram/post/' + TG.post.id, 'PUT', { text });
        await api('telegram/' + S.article.id + '/send', 'POST', { post_id: TG.post.id });
        await tgLoad();
        toast('Отправлено', 'ok');
    });
}

async function tgDeletePost() {
    if (!TG.post) return;
    if (!confirm('Удалить пост?')) return;
    try {
        await api('telegram/post/' + TG.post.id, 'DELETE');
        TG.post = null;
        tgRender();
        toast('Удалено', 'ok');
    } catch (e) { toast(e.message, 'err'); }
}

async function tgDeleteImage(imgId) {
    if (!confirm('Удалить изображение?')) return;
    try {
        await api('telegram/image/' + imgId, 'DELETE');
        await tgLoad();
    } catch (e) { toast(e.message, 'err'); }
}

// Reset cached state when switching articles.
(function patchOpenArticle() {
    const _orig = window.openArticle;
    if (typeof _orig !== 'function') return;
    window.openArticle = async function(id) {
        TG.loaded = false;
        TG.post = null;
        if (TG.open) {
            const body = el('tgSidebarBody');
            if (body) body.innerHTML = '<div class="empty"><div class="spin"></div></div>';
        }
        await _orig(id);
        if (TG.open) tgLoad();
    };
})();
