async function openCreateModal() {
    S.wizStep = 1;
    // Prefill from current filter if set
    const currentPid = el('filterProfile').value;
    S.wizProfile = currentPid ? parseInt(currentPid, 10) : null;
    S.wizTemplate = null;
    S.wizTemplates = [];
    el('wizTitleInput').value = '';
    el('wizSlugInput').value = '';
    el('wizSlugInput').dataset.touched = '';
    el('wizModal').classList.add('show');
    // Skip step 1 if profile already selected
    if (S.wizProfile) {
        S.wizStep = 2;
        renderWizStep();
        await loadWizTemplates();
    } else {
        renderWizStep();
        renderWizProfiles();
    }
}

function closeCreateModal() {
    el('wizModal').classList.remove('show');
}

function renderWizStep() {
    ['wizStep1','wizStep2','wizStep3'].forEach((id, i) => {
        el(id).style.display = (i + 1 === S.wizStep) ? '' : 'none';
    });
    document.querySelectorAll('.wiz-step-pill').forEach(p => {
        const n = parseInt(p.dataset.step, 10);
        p.className = 'wiz-step-pill' + (n < S.wizStep ? ' done' : (n === S.wizStep ? ' active' : ''));
    });
    el('wizBack').style.display = S.wizStep > 1 ? '' : 'none';
    el('wizNext').textContent = S.wizStep === 3 ? 'Создать' : 'Далее';
    updateWizNext();
}

function updateWizNext() {
    let ok = false;
    if (S.wizStep === 1) ok = !!S.wizProfile;
    else if (S.wizStep === 2) ok = !!S.wizTemplate;
    else if (S.wizStep === 3) ok = el('wizTitleInput').value.trim().length > 0 && el('wizSlugInput').value.trim().length > 0;
    el('wizNext').disabled = !ok;
}

function renderWizProfiles() {
    const grid = el('wizProfiles');
    if (!S.profiles.length) {
        grid.innerHTML = '<div class="empty" style="padding:20px"><div class="empty-sub">Нет активных профилей</div></div>';
        return;
    }
    grid.innerHTML = S.profiles.map(p => `
        <div class="tpl-pick-card ${S.wizProfile === p.id ? 'selected' : ''}" onclick="pickWizProfile(${p.id})">
            <div class="tpl-pick-name">${esc(p.name)}</div>
            <div class="tpl-pick-meta">${esc(p.niche || p.slug || '')}</div>
        </div>`).join('');
}

function pickWizProfile(id) {
    S.wizProfile = id;
    renderWizProfiles();
    updateWizNext();
}

async function loadWizTemplates() {
    const grid = el('wizTemplates');
    grid.innerHTML = '<div class="empty" style="padding:20px"><div class="spin"></div></div>';
    try {
        const res = await api('templates?profile_id=' + S.wizProfile);
        S.wizTemplates = res.data || [];
        if (!S.wizTemplates.length) {
            grid.innerHTML = '<div class="empty" style="padding:20px"><div class="empty-sub">У профиля нет шаблонов. Создайте в расширенной панели.</div></div>';
            return;
        }
        grid.innerHTML = S.wizTemplates.map(t => `
            <div class="tpl-pick-card ${S.wizTemplate === t.id ? 'selected' : ''}" onclick="pickWizTemplate(${t.id})">
                <div class="tpl-pick-name">${esc(t.name)}</div>
                <div class="tpl-pick-meta">${(t.blocks || []).length} блоков</div>
            </div>`).join('');
    } catch(e) {
        grid.innerHTML = '<div class="empty" style="padding:20px"><div class="empty-sub">' + esc(e.message) + '</div></div>';
    }
}

function pickWizTemplate(id) {
    S.wizTemplate = id;
    loadWizTemplates();
    updateWizNext();
}

function wizBack() {
    if (S.wizStep > 1) { S.wizStep--; renderWizStep(); }
}

async function wizNext() {
    if (S.wizStep === 1) {
        S.wizStep = 2;
        renderWizStep();
        await loadWizTemplates();
    } else if (S.wizStep === 2) {
        S.wizStep = 3;
        renderWizStep();
        el('wizTitleInput').focus();
    } else {
        await createArticleFromWiz();
    }
}

function slugify(s) {
    const map = {'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'e','ж':'zh','з':'z','и':'i','й':'y','к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r','с':'s','т':'t','у':'u','ф':'f','х':'h','ц':'ts','ч':'ch','ш':'sh','щ':'sch','ъ':'','ы':'y','ь':'','э':'e','ю':'yu','я':'ya'};
    return s.toLowerCase().split('').map(c => map[c] != null ? map[c] : c).join('')
        .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80);
}

function regenWizSlug() {
    const title = el('wizTitleInput').value.trim();
    if (!title) { toast('Сначала введите заголовок', 'err'); return; }
    const slug = el('wizSlugInput');
    slug.value = slugify(title);
    slug.dataset.touched = '';
    updateWizNext();
}

async function createArticleFromWiz() {
    const btn = el('wizNext');
    btn.disabled = true;
    btn.innerHTML = '<span class="spin spin-white"></span> Создание...';
    try {
        const res = await api('articles', 'POST', {
            title: el('wizTitleInput').value.trim(),
            slug: el('wizSlugInput').value.trim(),
            template_id: S.wizTemplate,
            profile_id: S.wizProfile,
        });
        closeCreateModal();
        toast('Статья создана', 'ok');
        await openArticle(res.data.id);
    } catch(e) {
        toast(e.message, 'err');
    }
    btn.disabled = false;
    btn.textContent = 'Создать';
}

// Auto-slug on title input
document.addEventListener('input', (ev) => {
    if (ev.target && ev.target.id === 'wizTitleInput') {
        const slug = el('wizSlugInput');
        if (!slug.dataset.touched) slug.value = slugify(ev.target.value);
        updateWizNext();
    } else if (ev.target && ev.target.id === 'wizSlugInput') {
        ev.target.dataset.touched = '1';
        updateWizNext();
    }
});

