<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Component;

class NavSearchComponent implements ComponentInterface
{
    public function renderHtml(array $context): string
    {
        $articleId = (int)($context['articleId'] ?? 0);
        $profileId = (int)($context['profileId'] ?? 0);
        $apiUrl    = json_encode(SEO_SEARCH_SCRIPT);
        $excludeId = $articleId;

        return
            '<div class="nav-search-wrap" id="navSearchWrap">' .
            '<button class="nav-search-toggle" id="navSearchToggle" aria-label="Поиск статей" title="Поиск статей">' .
            '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"' .
            ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' .
            '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>' .
            '</svg>' .
            '</button>' .
            '<div class="nav-search-box" id="navSearchBox">' .
            '<input type="search" id="navSearchInput" class="nav-search-input"' .
            ' placeholder="Поиск статей…" autocomplete="off" aria-label="Поиск">' .
            '<div class="nav-search-results" id="navSearchResults" role="listbox" aria-live="polite"></div>' .
            '</div>' .
            '</div>' .
            '<script>' .
            '(function(){' .
            'var API=' . $apiUrl . ', EXCL=' . $excludeId . ', PID=' . $profileId . ', DEB=280;' .
            'var toggle=document.getElementById(\'navSearchToggle\');' .
            'var box=document.getElementById(\'navSearchBox\');' .
            'var input=document.getElementById(\'navSearchInput\');' .
            'var results=document.getElementById(\'navSearchResults\');' .
            'var timer=null, last=\'\';' .

            'toggle.addEventListener(\'click\',function(){' .
            'var open=box.classList.toggle(\'is-open\');' .
            'toggle.classList.toggle(\'is-active\',open);' .
            'if(open){input.focus();}else{clear();}' .
            '});' .
            'document.addEventListener(\'click\',function(e){' .
            'if(!document.getElementById(\'navSearchWrap\').contains(e.target)){' .
            'box.classList.remove(\'is-open\');' .
            'toggle.classList.remove(\'is-active\');' .
            'clear();' .
            '}' .
            '});' .
            'input.addEventListener(\'input\',function(){' .
            'var q=input.value.trim();' .
            'clearTimeout(timer);' .
            'if(q.length<2){clear();return;}' .
            'if(q===last)return;' .
            'timer=setTimeout(function(){fetch_results(q);},DEB);' .
            '});' .
            'input.addEventListener(\'keydown\',function(e){' .
            'var items=results.querySelectorAll(\'.ns-item\');' .
            'if(!items.length)return;' .
            'var active=results.querySelector(\'.ns-item.is-focused\');' .
            'if(e.key===\'ArrowDown\'){' .
            'e.preventDefault();' .
            'var next=active?active.nextElementSibling:items[0];' .
            'if(next){if(active)active.classList.remove(\'is-focused\');next.classList.add(\'is-focused\');next.focus();}' .
            '}else if(e.key===\'ArrowUp\'){' .
            'e.preventDefault();' .
            'var prev=active?active.previousElementSibling:items[items.length-1];' .
            'if(prev){if(active)active.classList.remove(\'is-focused\');prev.classList.add(\'is-focused\');prev.focus();}' .
            '}else if(e.key===\'Escape\'){' .
            'box.classList.remove(\'is-open\');' .
            'toggle.classList.remove(\'is-active\');' .
            'clear();' .
            '}' .
            '});' .

            'function fetch_results(q){' .
            'last=q;' .
            'results.innerHTML=\'<div class="ns-loading">Поиск…</div>\';' .
            'fetch(API+\'?q=\'+encodeURIComponent(q)+\'&exclude=\'+EXCL+\'&limit=8\'+(PID?\'&profile_id=\'+PID:\'\'))' .
            '.then(function(r){return r.json();})' .
            '.then(function(d){render(d.results||[]);})' .
            '.catch(function(){results.innerHTML=\'<div class="ns-error">Ошибка поиска</div>\';});' .
            '}' .
            'function render(items){' .
            'if(!items.length){results.innerHTML=\'<div class="ns-empty">Ничего не найдено</div>\';return;}' .
            'results.innerHTML=items.map(function(it){' .
            'var d=it.description?\'<span class="ns-desc">\'+esc(it.description)+\'</span>\':\'\';' .
            'var spl=it.url.split("articles/");' .
            'var href="' . SEO_BASE_ART_URL . '"+spl[1];' .
            'return \'<a class="ns-item" href="\'+esc(href)+\'" tabindex="0"><span class="ns-title">\'+esc(it.title)+\'</span>\'+d+\'</a>\';' .
            '}).join(\'\');' .
            '}' .
            'function clear(){results.innerHTML=\'\';last=\'\';}' .
            'function esc(s){return String(s).replace(/&/g,\'&amp;\').replace(/</g,\'&lt;\').replace(/>/g,\'&gt;\').replace(/"/g,\'&quot;\');}' .
            '})();' .
            '</script>';
    }

    public function getCss(): string
    {
        return '.nav-search-wrap{position:relative;display:flex;align-items:center;margin-left:auto;margin-right:.5rem}'
            . "\n" . '.nav-search-toggle{background:none;border:none;cursor:pointer;color:var(--text-muted,#8b949e);padding:.4rem;border-radius:6px;display:flex;align-items:center;transition:color .2s,background .2s}'
            . "\n" . '.nav-search-toggle:hover,.nav-search-toggle.is-active{color:var(--accent,#58a6ff);background:rgba(88,166,255,.08)}'
            . "\n" . '.nav-search-box{position:absolute;top:calc(100% + 8px);right:0;width:320px;background:var(--surface,#161b22);border:1px solid var(--color-border,#30363d);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.5);overflow:hidden;display:none;flex-direction:column;z-index:9999}'
            . "\n" . '.nav-search-box.is-open{display:flex}'
            . "\n" . '.nav-search-input{width:100%;box-sizing:border-box;padding:.7rem 1rem;background:transparent;border:none;border-bottom:1px solid var(--color-border,#30363d);color:var(--text,#e6edf3);font-size:.95rem;outline:none}'
            . "\n" . '.nav-search-input::placeholder{color:var(--text-muted,#8b949e)}'
            . "\n" . '.nav-search-results{max-height:360px;overflow-y:auto}'
            . "\n" . '.ns-item{display:flex;flex-direction:column;gap:.2rem;padding:.65rem 1rem;color:var(--text,#e6edf3);text-decoration:none;border-bottom:1px solid var(--color-border,#30363d);transition:background .15s;cursor:pointer;outline:none}'
            . "\n" . '.ns-item:last-child{border-bottom:none}'
            . "\n" . '.ns-item:hover,.ns-item.is-focused{background:rgba(88,166,255,.08);color:var(--accent,#58a6ff)}'
            . "\n" . '.ns-title{font-size:.9rem;font-weight:500}'
            . "\n" . '.ns-desc{font-size:.78rem;color:var(--text-muted,#8b949e);line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}'
            . "\n" . '.ns-loading,.ns-empty,.ns-error{padding:.8rem 1rem;color:var(--text-muted,#8b949e);font-size:.85rem;text-align:center}'
            . "\n" . '.ns-error{color:#f85149}'
            . "\n" . '.related-articles{padding:3rem 0 4rem;background:var(--bg-alt,#0d1117)}'
            . "\n" . '.ra-heading{font-size:1.6rem;font-weight:700;margin-bottom:1.5rem;color:var(--text,#e6edf3)}'
            . "\n" . '.ra-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem}'
            . "\n" . '.ra-card{display:flex;flex-direction:column;gap:.5rem;padding:1.2rem 1.25rem;background:var(--surface,#161b22);border:1px solid var(--color-border,#30363d);border-radius:12px;text-decoration:none;color:var(--text,#e6edf3);transition:border-color .2s,transform .2s,box-shadow .2s}'
            . "\n" . '.ra-card:hover{border-color:var(--accent,#58a6ff);transform:translateY(-3px);box-shadow:0 8px 24px rgba(88,166,255,.12)}'
            . "\n" . '.ra-card-title{font-size:.95rem;font-weight:600;line-height:1.35;color:var(--text,#e6edf3)}'
            . "\n" . '.ra-card-desc{font-size:.8rem;color:var(--text-muted,#8b949e);line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin:0}'
            . "\n" . '.ra-card-arrow{margin-top:auto;color:var(--accent,#58a6ff);font-size:.9rem;font-weight:700;align-self:flex-end}'
            . "\n" . '.ra-skeleton{height:140px;border-radius:12px;background:linear-gradient(90deg,var(--color-border,rgba(255,255,255,.06)) 25%,rgba(255,255,255,.04) 50%,var(--color-border,rgba(255,255,255,.06)) 75%);background-size:200% 100%;animation:ra-shimmer 1.4s infinite}';
    }

    public function getJs(): string
    {
        // Search JS is inline within the HTML returned by renderHtml()
        return '';
    }
}
