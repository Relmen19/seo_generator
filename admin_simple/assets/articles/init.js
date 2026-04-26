// ─── Init ───
(function initAdvanced() {
    let on = false;
    try { on = localStorage.getItem('seo_simple_adv') === '1'; } catch(e) {}
    S.advanced = on;
    if (on) {
        document.body.classList.add('advanced');
        el('advToggle').classList.add('on');
    }
})();

(async function init() {
    await Promise.all([loadProfiles(), loadBlockTypes()]);
    loadArticles();
})();
