<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class HeatmapBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $content, string $id): string
    {
        $c = $content;
        $title = $this->e($c['title'] ?? 'Карта активности');
        $jsonData = json_encode([
            'rows' => $c['rows'] ?? [],
            'columns' => $c['columns'] ?? [],
            'data' => $c['data'] ?? [],
            'description' => $c['description'] ?? '',
        ], JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-heatmap reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $title . '</div></div>'
            . '<div class="mac-body">'
            . '<div class="hm-wrap" data-heatmap="' . $id . '">'
            . '<div class="hm-legend"><span>Мин</span><div class="hm-legend-bar"></div><span>Макс</span></div>'
            . '<div class="hm-grid"></div>'
            . '<div class="hm-info"><div><div class="hm-info-inner">'
            . '<div class="hm-info-swatch"></div>'
            . '<div class="hm-info-text"><b></b><span></span></div>'
            . '</div></div></div>'
            . '</div>'
            . '<script type="application/json" class="hm-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '</div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";

        return $h;
    }

    public function getCss(): string
    {
        return '.hm-wrap{margin-top:12px}'
            . "\n" . '.hm-legend{display:flex;align-items:center;gap:8px;margin-bottom:14px;font-size:11px;color:var(--muted);font-family:var(--fh);font-weight:600}'
            . "\n" . '.hm-legend-bar{width:120px;height:10px;border-radius:6px;background:linear-gradient(90deg,rgba(37,99,235,.06),#93C5FD,#3B82F6,#1D4ED8,#1E3A8A)}'
            . "\n" . '[data-theme="dark"] .hm-legend-bar{background:linear-gradient(90deg,rgba(255,255,255,.04),#60A5FA,#3B82F6,#2563EB,#7C3AED)}'
            . "\n" . '.hm-grid{display:grid;gap:3px}'
            . "\n" . '.hm-row-label{font-family:var(--fh);font-size:11px;font-weight:600;color:var(--muted);display:flex;align-items:center;padding-right:6px;justify-content:flex-end}'
            . "\n" . '.hm-col-label{font-family:var(--fh);font-size:10px;font-weight:600;color:var(--muted);text-align:center;padding-bottom:4px}'
            . "\n" . '.hm-cell{aspect-ratio:1;border-radius:4px;transition:all .3s ease;cursor:pointer;position:relative;min-width:0}'
            . "\n" . '.hm-cell:hover{transform:scale(1.3);z-index:10;box-shadow:0 4px 16px rgba(0,0,0,.2);border-radius:6px}'
            . "\n" . '.hm-cell.is-active{outline:2px solid var(--blue);outline-offset:1px;transform:scale(1.3);z-index:10;border-radius:6px}'
            . "\n" . '[data-heatmap].has-active .hm-cell:not(.is-active):not(.is-row-hl):not(.is-col-hl){opacity:.3}'
            . "\n" . '.hm-cell.is-row-hl,.hm-cell.is-col-hl{opacity:.7}'
            . "\n" . '.hm-info{display:grid;grid-template-rows:0fr;transition:grid-template-rows .35s ease,opacity .3s;opacity:0;margin-top:14px}'
            . "\n" . '.hm-info.is-open{grid-template-rows:1fr;opacity:1}'
            . "\n" . '.hm-info>div{overflow:hidden}'
            . "\n" . '.hm-info-inner{padding:14px 18px;border-radius:12px;background:rgba(255,255,255,.45);border:1px solid var(--border);backdrop-filter:blur(6px);display:flex;align-items:center;gap:16px;flex-wrap:wrap}'
            . "\n" . '[data-theme="dark"] .hm-info-inner{background:rgba(255,255,255,.03)}'
            . "\n" . '.hm-info-swatch{width:36px;height:36px;border-radius:8px;flex-shrink:0}'
            . "\n" . '.hm-info-text{font-size:13px;color:var(--slate)}'
            . "\n" . '.hm-info-text b{font-family:var(--fh);color:var(--dark);font-size:18px;display:block;margin-bottom:2px}';
    }

    public function getJs(): string
    {
        return '(function(){'
            . 'function hColor(t){return "rgba(37,99,235,"+(0.06+t*0.8)+")"}'
            . 'document.querySelectorAll("[data-heatmap]").forEach(function(wrap){'
            . 'var jsonEl=wrap.parentElement.querySelector(".hm-data");if(!jsonEl)return;'
            . 'var cfg={};try{cfg=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'var rows=cfg.rows||[],cols=cfg.columns||[],matrix=cfg.data||[];'
            . 'var grid=wrap.querySelector(".hm-grid");var info=wrap.querySelector(".hm-info");'
            . 'var swatch=wrap.querySelector(".hm-info-swatch"),valEl=wrap.querySelector(".hm-info-text b"),lblEl=wrap.querySelector(".hm-info-text span");'
            . 'grid.style.gridTemplateColumns="36px repeat("+cols.length+",1fr)";'
            . 'var corner=document.createElement("div");grid.appendChild(corner);'
            . 'cols.forEach(function(m){var l=document.createElement("div");l.className="hm-col-label";l.textContent=m;grid.appendChild(l)});'
            . 'var allCells=[],active=null;'
            . 'rows.forEach(function(w,ri){'
            . 'var rl=document.createElement("div");rl.className="hm-row-label";rl.textContent=w;grid.appendChild(rl);'
            . 'cols.forEach(function(m,ci){'
            . 'var val=(matrix[ri]&&matrix[ri][ci]!=null)?matrix[ri][ci]:Math.round(Math.abs(Math.sin(ri*127.1+ci*311.7)*43758.5453)%1*100);'
            . 'var c=document.createElement("div");c.className="hm-cell";c.style.background=hColor(val/100);'
            . 'c.dataset.val=val;c.dataset.row=ri;c.dataset.col=ci;c.dataset.label=w+", "+m;'
            . 'c.addEventListener("click",function(){selectCell(c,ri,ci,val,m,w)});'
            . 'grid.appendChild(c);allCells.push(c)})});'
            . 'function selectCell(el,ri,ci,val,m,w){'
            . 'if(active===el){desel();return}active=el;wrap.classList.add("has-active");'
            . 'allCells.forEach(function(c){c.classList.remove("is-active","is-row-hl","is-col-hl");'
            . 'if(+c.dataset.row===ri)c.classList.add("is-row-hl");if(+c.dataset.col===ci)c.classList.add("is-col-hl")});'
            . 'el.classList.add("is-active");swatch.style.background=hColor(val/100);valEl.textContent=val+"%";lblEl.textContent=" — "+w+", "+m;info.classList.add("is-open")}'
            . 'function desel(){active=null;wrap.classList.remove("has-active");allCells.forEach(function(c){c.classList.remove("is-active","is-row-hl","is-col-hl")});info.classList.remove("is-open")}'
            . 'document.addEventListener("click",function(e){if(!wrap.contains(e.target))desel()});'
            . '});'
            . '})();';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Карта активности';
    }
}
