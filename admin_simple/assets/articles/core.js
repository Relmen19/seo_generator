const API = '../controllers/router.php?r=';

const S = {
    articles: [],
    article: null,
    profiles: [],
    blockTypes: [],
    openBlockId: null,
    blockTabs: {},
    advanced: false,
    // wizard
    wizStep: 1,
    wizProfile: null,
    wizTemplate: null,
    wizTemplates: [],
    // autosave
    autosaveTimers: {},
    saveInFlight: 0,
};

const IMAGE_MODEL = 'gemini-2.5-flash-image';
const IMAGE_MODEL_LABEL = 'Nano Banana';

function el(id) { return document.getElementById(id); }

function esc(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function toast(msg, type='') {
    const wrap = el('toastWrap');
    const t = document.createElement('div');
    t.className = 'toast' + (type ? ' ' + type : '');
    t.textContent = msg;
    wrap.appendChild(t);
    setTimeout(() => t.classList.add('show'), 10);
    setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 3000);
}

async function api(path, method='GET', body=null) {
    const opts = { method, headers: {} };
    if (body !== null && body !== undefined) {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(body);
    }
    const res = await fetch(API + path, opts);
    const data = await res.json();
    if (!data.success) throw new Error(data.error || 'Ошибка API');
    return data;
}
