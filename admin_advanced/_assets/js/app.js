/* admin_advanced shared client library
 * Exposes: window.SEO = { api, sse, toast, $, esc, escAttr, debounce, fmtNum, fmtCost, iconUrl, profile, modal, copy, on, ready, SearchSelect }
 *
 * No build step — plain ES2020 + IIFE. Tailwind utility classes used directly in HTML.
 */
(function () {
  'use strict';

  // ---------- DOM helpers ----------
  const $ = (id) => (typeof id === 'string' ? document.getElementById(id) : id);
  const ready = (fn) => (document.readyState !== 'loading' ? fn() : document.addEventListener('DOMContentLoaded', fn, { once: true }));

  function on(el, ev, sel, handler) {
    if (typeof el === 'string') el = document.querySelector(el);
    if (!el) return;
    if (typeof sel === 'function') { el.addEventListener(ev, sel); return; }
    el.addEventListener(ev, (e) => {
      const t = e.target.closest(sel);
      if (t && el.contains(t)) handler(e, t);
    });
  }

  function debounce(fn, ms = 250) {
    let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
  }

  // ---------- Escape ----------
  const _entities = { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' };
  const esc      = (s) => (s == null ? '' : String(s).replace(/[&<>"']/g, (c) => _entities[c]));
  const escAttr  = (s) => esc(s);

  // ---------- Numbers ----------
  function fmtNum(n) {
    if (n == null || isNaN(n)) return '—';
    return Number(n).toLocaleString('ru-RU');
  }
  function fmtCost(c, digits = 4) {
    if (c == null || isNaN(c)) return '—';
    return '$' + Number(c).toFixed(digits);
  }

  // ---------- Toast ----------
  function toast(msg, type) {
    const host = $('seo-toast-host'); if (!host) return console.warn(msg);
    const el = document.createElement('div');
    el.className = 'toast ' + (type === 'err' ? 'toast-err' : type === 'ok' ? 'toast-ok' : '');
    el.textContent = msg;
    host.appendChild(el);
    setTimeout(() => { el.style.transition = 'opacity .25s'; el.style.opacity = '0'; }, 3000);
    setTimeout(() => el.remove(), 3300);
  }

  // ---------- API wrapper ----------
  const API_BASE = '/controllers/router.php';

  async function api(resource, opts = {}) {
    const method = (opts.method || 'GET').toUpperCase();
    const url    = API_BASE + '?r=' + resource;
    const init   = { method, headers: {} };

    if (opts.body !== undefined) {
      if (opts.body instanceof FormData) {
        init.body = opts.body;
      } else if (typeof opts.body === 'string') {
        init.headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=utf-8';
        init.body = opts.body;
      } else {
        init.headers['Content-Type'] = 'application/json; charset=utf-8';
        init.body = JSON.stringify(opts.body);
      }
    }
    if (opts.headers) Object.assign(init.headers, opts.headers);
    if (opts.signal) init.signal = opts.signal;

    let res;
    try { res = await fetch(url, init); }
    catch (e) { toast('Сеть: ' + e.message, 'err'); throw e; }

    const ct = res.headers.get('content-type') || '';
    let data = null;
    if (ct.includes('application/json')) {
      try { data = await res.json(); } catch (_) { data = null; }
    } else {
      data = await res.text();
    }

    if (!res.ok || (data && data.success === false)) {
      const msg = (data && (data.error || data.message)) || ('HTTP ' + res.status);
      if (!opts.silent) toast(msg, 'err');
      const err = new Error(msg); err.status = res.status; err.data = data; throw err;
    }
    // Unwrap {success:true, data:...} envelope used by all SEO controllers.
    if (data && typeof data === 'object' && data.success === true && Object.prototype.hasOwnProperty.call(data, 'data')) {
      return data.data;
    }
    return data;
  }

  // ---------- SSE wrapper ----------
  function sse(resource, { onEvent, onError, onOpen } = {}) {
    const url = API_BASE + '?r=' + resource;
    const es  = new EventSource(url);
    if (onOpen)  es.addEventListener('open', onOpen);
    if (onError) es.addEventListener('error', onError);
    if (onEvent) {
      es.onmessage = (ev) => { try { onEvent('message', JSON.parse(ev.data)); } catch (_) { onEvent('message', ev.data); } };
      ['progress','log','done','error','step'].forEach((name) => {
        es.addEventListener(name, (ev) => { try { onEvent(name, JSON.parse(ev.data)); } catch (_) { onEvent(name, ev.data); } });
      });
    }
    return es;
  }

  // ---------- Profile helpers ----------
  const PROFILE_KEY = 'seo_profile_id';
  const profile = {
    get id() {
      const v = localStorage.getItem(PROFILE_KEY);
      return v ? Number(v) : null;
    },
    set id(v) {
      v ? localStorage.setItem(PROFILE_KEY, String(v)) : localStorage.removeItem(PROFILE_KEY);
      window.dispatchEvent(new CustomEvent('seo-profile-changed', { detail: { id: v ? Number(v) : null } }));
    },
    require() {
      const id = this.id;
      if (!id) { toast('Профиль не выбран', 'err'); throw new Error('no profile'); }
      return id;
    },
    iconUrl(p) {
      if (!p || !p.id) return '';
      return '/controllers/router.php?r=profiles/' + p.id + '/icon&v=' + (p.icon_version || 0);
    },
    iconHtml(p, size = 36) {
      const s = size + 'px';
      if (p && p.has_icon) {
        return '<img src="' + escAttr(this.iconUrl(p)) + '" alt="" style="width:'+s+';height:'+s+'" class="rounded-full object-cover bg-sand-100">';
      }
      const initials = (p && p.name ? p.name : '?').slice(0, 2).toUpperCase();
      return '<span class="rounded-full bg-ink-900 text-sand-50 grid place-items-center font-semibold" style="width:'+s+';height:'+s+';font-size:'+(size*0.4)+'px">'+esc(initials)+'</span>';
    },
  };

  // ---------- Modal ----------
  const modal = {
    open(html, { onClose } = {}) {
      const host = document.createElement('div');
      host.className = 'modal-backdrop';
      host.innerHTML = '<div class="modal-card">'+html+'</div>';
      host.addEventListener('click', (e) => { if (e.target === host) close(); });
      document.body.appendChild(host);
      const close = () => { host.remove(); if (onClose) onClose(); };
      host._close = close;
      return { el: host, close };
    },
  };

  // ---------- Copy ----------
  async function copy(text) {
    try { await navigator.clipboard.writeText(text); toast('Скопировано', 'ok'); }
    catch { toast('Не получилось скопировать', 'err'); }
  }

  // ---------- SearchSelect ----------
  // Drop-in replacement for the per-page SearchSelect from the old codebase.
  // Usage: new SEO.SearchSelect(hostEl, { items, value, onChange, placeholder, render })
  class SearchSelect {
    constructor(host, opts = {}) {
      this.host    = typeof host === 'string' ? document.querySelector(host) : host;
      this.items   = opts.items || [];
      this.value   = opts.value ?? null;
      this.onChange= opts.onChange || (()=>{});
      this.placeholder = opts.placeholder || 'Выбрать…';
      this.render  = opts.render || ((it)=>esc(it.label || it.name || it.id));
      this._build();
    }
    setItems(items) { this.items = items || []; this._renderList(this._lastQ || ''); this._renderTrigger(); }
    setValue(v)     { this.value = v; this._renderTrigger(); }
    getValue()      { return this.value; }

    _build() {
      this.host.innerHTML = '';
      this.host.classList.add('relative');

      this.trigger = document.createElement('button');
      this.trigger.type = 'button';
      this.trigger.className = 'input text-left flex items-center justify-between';
      this.trigger.addEventListener('click', () => this.toggle());
      this.host.appendChild(this.trigger);

      this.pop = document.createElement('div');
      this.pop.className = 'hidden absolute z-50 mt-1 w-full bg-sand-50 rounded-2xl shadow-card p-2';
      this.pop.innerHTML = '<input class="input mb-2" placeholder="Поиск…"><div class="ss-list max-h-60 overflow-auto"></div>';
      this.host.appendChild(this.pop);

      this.search = this.pop.querySelector('input');
      this.list   = this.pop.querySelector('.ss-list');
      this.search.addEventListener('input', () => this._renderList(this.search.value));
      document.addEventListener('click', (e) => { if (!this.host.contains(e.target)) this.close(); });

      this._renderTrigger();
    }
    _renderTrigger() {
      const sel = this.items.find((i) => String(i.id) === String(this.value));
      this.trigger.innerHTML = sel
        ? '<span>'+this.render(sel)+'</span><svg width="14" height="14" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 8l5 5 5-5"/></svg>'
        : '<span class="text-ink-300">'+esc(this.placeholder)+'</span><svg width="14" height="14" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 8l5 5 5-5"/></svg>';
    }
    _renderList(q) {
      this._lastQ = q;
      const ql = (q || '').toLowerCase();
      const filtered = ql
        ? this.items.filter((i) => (i.label || i.name || '').toLowerCase().includes(ql))
        : this.items;
      this.list.innerHTML = filtered.length
        ? filtered.map((i) => '<div class="ss-option px-3 py-2 rounded-xl cursor-pointer hover:bg-sand-100" data-id="'+escAttr(i.id)+'">'+this.render(i)+'</div>').join('')
        : '<div class="px-3 py-2 text-ink-300 text-sm">Ничего не найдено</div>';
      this.list.querySelectorAll('.ss-option').forEach((el) => {
        el.addEventListener('click', () => {
          this.value = el.dataset.id;
          this._renderTrigger();
          this.close();
          this.onChange(this.value, this.items.find((i) => String(i.id) === String(this.value)));
        });
      });
    }
    open()   { this.pop.classList.remove('hidden'); this._renderList(''); this.search.focus(); }
    close()  { this.pop.classList.add('hidden'); }
    toggle() { this.pop.classList.contains('hidden') ? this.open() : this.close(); }
  }

  // ---------- Lazy loaders ----------
  const _loaders = {};
  function loadScript(src) {
    if (_loaders[src]) return _loaders[src];
    return _loaders[src] = new Promise((resolve, reject) => {
      const s = document.createElement('script');
      s.src = src; s.async = true;
      s.onload = resolve; s.onerror = () => reject(new Error('script load failed: '+src));
      document.head.appendChild(s);
    });
  }
  const loadChartJs    = () => loadScript('https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js');
  const loadCodeMirror = async () => {
    await loadScript('https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js');
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css';
    document.head.appendChild(link);
    await loadScript('https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js');
  };

  // ---------- Alpine stores ----------
  document.addEventListener('alpine:init', () => {
    if (!window.Alpine) return;
    Alpine.store('layout', { hideTopbar: false });
  });

  /**
   * iOS-style "magic move" highlight for any list of buttons.
   *
   * Markup contract (CSS classes defined in admin.css):
   *   <div class="drawer-list" x-ref="myList">
   *     <div class="drawer-list-pill" :class="{'is-visible': activeId}"
   *          :style="SEO.pillStyle(pill.myList)"></div>
   *     <button class="drawer-list-item" :data-row-id="row.id"
   *             :class="{'is-active': activeId === row.id}">…</button>
   *   </div>
   *
   * In the Alpine component:
   *   pill: { myList: null },
   *   $watch('activeId', v => $nextTick(() =>
   *     SEO.morphPill($refs.myList, pill, 'myList', v && v.id)
   *   ))
   *
   * The pill element animates between rows via CSS transition on
   * transform/height (vars --pill-top / --pill-h), so there's no
   * per-row repaint and no JS animation loop.
   */
  function morphPill(listEl, pillState, key, rowId) {
    if (!listEl || !pillState) return;
    if (!rowId) { pillState[key] = null; return; }
    const row = listEl.querySelector('[data-row-id="' + rowId + '"]');
    pillState[key] = row ? { top: row.offsetTop, height: row.offsetHeight } : null;
  }
  function pillStyle(p) {
    return p
      ? `--pill-top: ${p.top}px; --pill-h: ${p.height}px;`
      : '--pill-top: 0px; --pill-h: 0px;';
  }

  /**
   * Workspace focus mode — flip body[data-focus] so any descendant
   * with `.focus-collapse` smoothly collapses and the page chrome
   * (#seo-topbar) shrinks/dims. Pure CSS-driven; see admin.css.
   * Use from Alpine via x-effect: `SEO.setFocus(!!editorOpen)`.
   */
  function setFocus(on) {
    document.body.dataset.focus = on ? 'on' : 'off';
  }

  /**
   * FLIP reorder: capture child positions, run the mutation, then animate
   * each child from its old top to its new top. Use it to make Alpine
   * x-for reorders feel like a continuous move instead of a snap.
   *
   *   SEO.flipReorder($refs.list, () => arr.splice(...))
   */
  function flipReorder(container, mutate) {
    if (!container || typeof mutate !== 'function') { if (mutate) mutate(); return; }
    const isItem = (el) => el && el.nodeType === 1 && el.tagName !== 'TEMPLATE';
    const before = new Map();
    Array.from(container.children).forEach((el) => {
      if (!isItem(el)) return;
      before.set(el, el.getBoundingClientRect().top);
    });
    mutate();
    // Alpine flushes reactive DOM updates as microtasks. Wait for that
    // microtask, then double-rAF so layout is settled before we measure.
    Promise.resolve().then(() => requestAnimationFrame(() => requestAnimationFrame(() => {
      Array.from(container.children).forEach((el) => {
        if (!isItem(el)) return;
        const oldTop = before.get(el);
        if (oldTop == null) return;
        const dy = oldTop - el.getBoundingClientRect().top;
        if (!dy) return;
        el.style.transition = 'none';
        el.style.transform = 'translateY(' + dy + 'px)';
        // Force reflow then animate to identity.
        el.offsetHeight; // eslint-disable-line no-unused-expressions
        el.style.transition = 'transform var(--motion-base) var(--motion-glide)';
        el.style.transform = '';
        const cleanup = () => {
          el.style.transition = '';
          el.style.transform = '';
          el.removeEventListener('transitionend', cleanup);
        };
        el.addEventListener('transitionend', cleanup);
      });
    })));
  }

  // ---------- Public ----------
  window.SEO = {
    $, on, ready, esc, escAttr, debounce, fmtNum, fmtCost,
    api, sse, toast, profile, modal, copy,
    SearchSelect,
    loadChartJs, loadCodeMirror,
    morphPill, pillStyle,
    setFocus, flipReorder,
  };
})();
