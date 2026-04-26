<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class StackedAreaBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $c, string $id): string
    {
        $title = $this->e($c['title'] ?? 'Динамика');
        $jsonData = json_encode([
            'labels' => $c['labels'] ?? [],
            'series' => $c['series'] ?? [],
        ], JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-stacked reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $title . '</div></div>'
            . '<div class="mac-body">'
            . '<div class="sa-layout" data-stacked="' . $id . '">'
            . '<div>'
            . '<div class="sa-chart"><svg class="sa-svg" viewBox="0 0 600 200" preserveAspectRatio="none"></svg>'
            . '<div class="sa-x-labels"></div></div>'
            . '<div class="sa-detail"><div><div class="sa-detail-inner"></div></div></div>'
            . '</div>'
            . '<div class="sa-legend"></div>'
            . '</div>'
            . '<script type="application/json" class="sa-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '</div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";

        return $h;
    }

    public function getCss(): string
    {
        return '.sa-layout{display:grid;grid-template-columns:1fr 200px;gap:24px;align-items:start}'
            . "\n" . '.sa-chart{position:relative;border-radius:14px;overflow:hidden;background:rgba(255,255,255,.3);border:1px solid var(--color-border);padding:16px}'
            . "\n" . '[data-theme="dark"] .sa-chart{background:rgba(255,255,255,.02)}'
            . "\n" . '.sa-svg{width:100%;height:200px;display:block}'
            . "\n" . '.sa-area{transition:opacity .4s ease;cursor:pointer}'
            . "\n" . '.sa-area:hover{filter:brightness(1.1)}'
            . "\n" . '[data-stacked].has-active .sa-area{opacity:.15}'
            . "\n" . '[data-stacked].has-active .sa-area.is-active{opacity:1;filter:drop-shadow(0 0 6px rgba(0,0,0,.15))}'
            . "\n" . '.sa-x-labels{display:flex;justify-content:space-between;padding:6px 16px 0;font-family:var(--type-font-heading);font-size:10px;color:var(--color-text-3);font-weight:600}'
            . "\n" . '.sa-legend{display:flex;flex-direction:column;gap:6px}'
            . "\n" . '.sa-legend-item{display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:10px;background:rgba(255,255,255,.5);border:1px solid var(--color-border);transition:all .25s;cursor:pointer;font-size:13px;color:var(--color-text-2)}'
            . "\n" . '[data-theme="dark"] .sa-legend-item{background:rgba(255,255,255,.04)}'
            . "\n" . '.sa-legend-item.is-active{border-color:var(--color-accent);box-shadow:0 2px 10px rgba(37,99,235,.1);background:var(--color-accent-soft)}'
            . "\n" . '[data-stacked].has-active .sa-legend-item:not(.is-active){opacity:.35}'
            . "\n" . '.sa-legend-dot{width:10px;height:10px;border-radius:3px;flex-shrink:0}'
            . "\n" . '.sa-legend-val{font-family:var(--type-font-heading);font-weight:700;color:var(--color-text);margin-left:auto;font-size:12px}'
            . "\n" . '.sa-detail{display:grid;grid-template-rows:0fr;transition:grid-template-rows .4s ease,opacity .3s;opacity:0;margin-top:12px}'
            . "\n" . '.sa-detail.is-open{grid-template-rows:1fr;opacity:1}'
            . "\n" . '.sa-detail>div{overflow:hidden}'
            . "\n" . '.sa-detail-inner{padding:12px 16px;border-radius:12px;background:rgba(255,255,255,.45);border:1px solid var(--color-border);font-size:13px;color:var(--color-text-3);line-height:1.55}'
            . "\n" . '[data-theme="dark"] .sa-detail-inner{background:rgba(255,255,255,.03)}'
            . "\n" . '.sa-detail-inner b{color:var(--color-text);font-family:var(--type-font-heading)}'
            . "\n" . '@media(max-width:700px){.sa-layout{grid-template-columns:1fr}}'
            ;
    }

    public function getJs(): string
    {
        return '(function(){'
            . 'document.querySelectorAll("[data-stacked]").forEach(function(wrap){'
            . 'var jsonEl=wrap.parentElement.querySelector(".sa-data");if(!jsonEl)return;'
            . 'var cfg={};try{cfg=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'var labels=cfg.labels||[];var series=cfg.series||[];'
            . 'var svg=wrap.querySelector(".sa-svg");var legend=wrap.querySelector(".sa-legend");var xlEl=wrap.querySelector(".sa-x-labels");'
            . 'var detailEl=wrap.querySelector(".sa-detail");var detailInner=detailEl?detailEl.querySelector(".sa-detail-inner"):null;'
            . 'var W=600,H=200,n=labels.length,active=-1;if(!n||!series.length)return;'
            . 'xlEl.innerHTML=labels.map(function(l){return"<span>"+l+"</span>"}).join("");'
            . 'var totals=labels.map(function(_,i){return series.reduce(function(s,sr){return s+(sr.data[i]||0)},0)});var maxT=Math.max.apply(null,totals)||1;'
            . 'var areas=[];for(var si=series.length-1;si>=0;si--){var topP=[],botP=[];'
            . 'for(var xi=0;xi<n;xi++){var x=xi/(n-1)*W;var below=0;for(var k=0;k<si;k++)below+=(series[k].data[xi]||0);var top=below+(series[si].data[xi]||0);topP.push(x+","+(H-top/maxT*H));botP.push(x+","+(H-below/maxT*H))}'
            . 'var d="M"+topP.join(" L")+" L"+botP.reverse().join(" L")+" Z";var path=document.createElementNS("http://www.w3.org/2000/svg","path");path.setAttribute("d",d);path.setAttribute("fill",series[si].color||"#2563EB");path.setAttribute("class","sa-area");path.dataset.idx=si;'
            . 'path.addEventListener("click",function(){selectSA(+this.dataset.idx)});svg.appendChild(path);areas.push({el:path,idx:si})}'
            . 'series.forEach(function(s,i){var item=document.createElement("button");item.className="sa-legend-item";item.dataset.idx=i;'
            . 'item.innerHTML=\'<span class="sa-legend-dot" style="background:\'+(s.color||"#2563EB")+\'"></span><span>\'+(s.name||"")+\'</span><span class="sa-legend-val">\'+((s.data[s.data.length-1])||0)+"%</span>";'
            . 'item.addEventListener("click",function(){selectSA(i)});legend.appendChild(item)});'
            . 'function selectSA(i){if(i===active){desel();return}active=i;wrap.classList.add("has-active");'
            . 'areas.forEach(function(a){a.el.classList.toggle("is-active",a.idx===i)});'
            . 'legend.querySelectorAll(".sa-legend-item").forEach(function(el,ei){el.classList.toggle("is-active",ei===i)});'
            . 'if(detailInner){detailInner.innerHTML="<b style=\\"color:"+(series[i].color||"#2563EB")+"\\">"+(series[i].name||"")+"</b><br>"+(series[i].description||"");detailEl.classList.add("is-open")}}'
            . 'function desel(){active=-1;wrap.classList.remove("has-active");areas.forEach(function(a){a.el.classList.remove("is-active")});legend.querySelectorAll(".sa-legend-item").forEach(function(el){el.classList.remove("is-active")});if(detailEl)detailEl.classList.remove("is-open")}'
            . 'document.addEventListener("click",function(e){if(!wrap.closest("section").contains(e.target))desel()});'
            . '});'
            . '})();';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Динамика';
    }
}
