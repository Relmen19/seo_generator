<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class RadarChartBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $c, string $id): string
    {
        $title = $this->e($c['title'] ?? 'Профиль');
        $jsonData = json_encode([
            'axes' => $c['axes'] ?? [],
            'color' => $c['color'] ?? '#2563EB',
        ], JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-radar reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $title . '</div></div>'
            . '<div class="mac-body">'
            . '<div class="radar-layout" data-radar="' . $id . '">'
            . '<div class="radar-svg-wrap"><svg class="radar-svg" viewBox="0 0 320 300" preserveAspectRatio="xMidYMid meet"></svg></div>'
            . '<div class="radar-aside"></div>'
            . '</div>'
            . '<div class="radar-detail" data-radar-detail="' . $id . '"><div><div class="radar-detail-inner"></div></div></div>'
            . '<script type="application/json" class="radar-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '</div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";

        return $h;
    }

    public function getCss(): string
    {
        return '.radar-layout{display:grid;grid-template-columns:320px 1fr;gap:28px;align-items:start}'
            . "\n" . '.radar-svg-wrap{position:relative;width:320px;max-width:100%;aspect-ratio:320/300}'
            . "\n" . '.radar-svg{width:100%;height:100%}'
            . "\n" . '.radar-grid-line{fill:none;stroke:var(--color-border);stroke-width:1}'
            . "\n" . '.radar-axis{stroke:var(--color-border);stroke-width:1}'
            . "\n" . '.radar-shape{transition:d .6s cubic-bezier(.4,0,.2,1),opacity .3s;cursor:pointer}'
            . "\n" . '.radar-axis-label{font-family:var(--type-font-heading);font-size:11px;font-weight:600;fill:var(--color-text-3);cursor:pointer;transition:fill .3s}'
            . "\n" . '.radar-axis-label:hover,.radar-axis-label.is-active{fill:var(--color-text);font-weight:700}'
            . "\n" . '.radar-dot{transition:r .3s,opacity .3s;cursor:pointer}'
            . "\n" . '.radar-aside{display:flex;flex-direction:column;gap:8px}'
            . "\n" . '.radar-legend-item{display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:10px;background:rgba(255,255,255,.5);border:1px solid var(--color-border);transition:all .25s;cursor:pointer;font-size:13px;color:var(--color-text-2)}'
            . "\n" . '[data-theme="dark"] .radar-legend-item{background:rgba(255,255,255,.04)}'
            . "\n" . '.radar-legend-item:hover{border-color:rgba(37,99,235,.3)}'
            . "\n" . '.radar-legend-item.is-active{border-color:var(--color-accent);box-shadow:0 2px 12px rgba(37,99,235,.12);background:var(--color-accent-soft)}'
            . "\n" . '[data-radar].has-active .radar-legend-item:not(.is-active){opacity:.35}'
            . "\n" . '.radar-legend-dot{width:10px;height:10px;border-radius:3px;flex-shrink:0}'
            . "\n" . '.radar-legend-val{font-family:var(--type-font-heading);font-weight:700;color:var(--color-text);margin-left:auto}'
            . "\n" . '.radar-detail{display:grid;grid-template-rows:0fr;transition:grid-template-rows .4s ease,opacity .3s;opacity:0;margin-top:10px}'
            . "\n" . '.radar-detail.is-open{grid-template-rows:1fr;opacity:1}'
            . "\n" . '.radar-detail>div{overflow:hidden}'
            . "\n" . '.radar-detail-inner{padding:12px 16px;border-radius:12px;background:rgba(255,255,255,.45);border:1px solid var(--color-border);font-size:13px;color:var(--color-text-3);line-height:1.55}'
            . "\n" . '[data-theme="dark"] .radar-detail-inner{background:rgba(255,255,255,.03)}'
            . "\n" . '@media(max-width:700px){.radar-layout{grid-template-columns:1fr}.radar-svg-wrap{margin:0 auto}}'
            ;
    }

    public function getJs(): string
    {
        return '(function(){'
            . 'document.querySelectorAll("[data-radar]").forEach(function(wrap){'
            . 'var jsonEl=wrap.parentElement.querySelector(".radar-data");if(!jsonEl)return;'
            . 'var cfg={};try{cfg=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'var axes=cfg.axes||[];var svg=wrap.querySelector(".radar-svg");var aside=wrap.querySelector(".radar-aside");'
            . 'var detailEl=wrap.parentElement.querySelector("[data-radar-detail]")||wrap.parentElement.querySelector(".radar-detail");'
            . 'var detailInner=detailEl?detailEl.querySelector(".radar-detail-inner"):null;'
            . 'var cx=160,cy=150,R=100,n=axes.length,active=-1;'
            . 'var colors=["#2563EB","#0D9488","#8B5CF6","#F59E0B","#EF4444","#16A34A","#EC4899","#06B6D4"];'
            . 'if(!n)return;'
            . '[.25,.5,.75,1].forEach(function(s){var pts=[];for(var i=0;i<n;i++){var a=-Math.PI/2+2*Math.PI/n*i;pts.push((cx+R*s*Math.cos(a))+","+(cy+R*s*Math.sin(a)))}var poly=document.createElementNS("http://www.w3.org/2000/svg","polygon");poly.setAttribute("points",pts.join(" "));poly.setAttribute("class","radar-grid-line");svg.appendChild(poly)});'
            . 'for(var i=0;i<n;i++){var a=-Math.PI/2+2*Math.PI/n*i;var line=document.createElementNS("http://www.w3.org/2000/svg","line");line.setAttribute("x1",cx);line.setAttribute("y1",cy);line.setAttribute("x2",cx+R*Math.cos(a));line.setAttribute("y2",cy+R*Math.sin(a));line.setAttribute("class","radar-axis");svg.appendChild(line);'
            . 'var lbl=document.createElementNS("http://www.w3.org/2000/svg","text");lbl.setAttribute("x",cx+(R+18)*Math.cos(a));lbl.setAttribute("y",cy+(R+18)*Math.sin(a));lbl.setAttribute("text-anchor","middle");lbl.setAttribute("dominant-baseline","middle");lbl.setAttribute("class","radar-axis-label");lbl.setAttribute("data-idx",i);lbl.textContent=axes[i].name||"";lbl.addEventListener("click",function(){selectAxis(+this.dataset.idx)});svg.appendChild(lbl)}'
            . 'var shapePts=axes.map(function(ax,i){var a=-Math.PI/2+2*Math.PI/n*i;var r=R*(parseFloat(ax.value||50))/100;return(cx+r*Math.cos(a))+","+(cy+r*Math.sin(a))});'
            . 'var shape=document.createElementNS("http://www.w3.org/2000/svg","polygon");shape.setAttribute("points",shapePts.join(" "));shape.setAttribute("fill","rgba(37,99,235,.15)");shape.setAttribute("stroke","#2563EB");shape.setAttribute("stroke-width","2");shape.setAttribute("class","radar-shape");svg.appendChild(shape);'
            . 'var dots=[];axes.forEach(function(ax,i){var a=-Math.PI/2+2*Math.PI/n*i;var r=R*(parseFloat(ax.value||50))/100;var dot=document.createElementNS("http://www.w3.org/2000/svg","circle");dot.setAttribute("cx",cx+r*Math.cos(a));dot.setAttribute("cy",cy+r*Math.sin(a));dot.setAttribute("r","4");dot.setAttribute("fill",colors[i%colors.length]);dot.setAttribute("stroke","#fff");dot.setAttribute("stroke-width","2");dot.setAttribute("class","radar-dot");dot.dataset.idx=i;dot.addEventListener("click",function(){selectAxis(+this.dataset.idx)});svg.appendChild(dot);dots.push(dot)});'
            . 'axes.forEach(function(ax,i){var item=document.createElement("button");item.className="radar-legend-item";item.dataset.idx=i;item.innerHTML=\'<span class="radar-legend-dot" style="background:\'+colors[i%colors.length]+\'"></span><span>\'+(ax.name||"")+\'</span><span class="radar-legend-val">\'+(ax.value||0)+"%</span>";item.addEventListener("click",function(){selectAxis(i)});aside.appendChild(item)});'
            . 'function selectAxis(i){if(i===active){desel();return}active=i;wrap.classList.add("has-active");'
            . 'aside.querySelectorAll(".radar-legend-item").forEach(function(el,ei){el.classList.toggle("is-active",ei===i)});'
            . 'svg.querySelectorAll(".radar-axis-label").forEach(function(el){el.classList.toggle("is-active",+el.dataset.idx===i)});'
            . 'dots.forEach(function(d,di){d.setAttribute("r",di===i?"7":"4")});'
            . 'if(detailInner){detailInner.innerHTML="<b style=\\"color:"+colors[i%colors.length]+"\\">"+(axes[i].name||"")+" — "+(axes[i].value||0)+"%</b><br>"+(axes[i].description||"");detailEl.classList.add("is-open")}}'
            . 'function desel(){active=-1;wrap.classList.remove("has-active");aside.querySelectorAll(".radar-legend-item").forEach(function(el){el.classList.remove("is-active")});svg.querySelectorAll(".radar-axis-label").forEach(function(el){el.classList.remove("is-active")});dots.forEach(function(d){d.setAttribute("r","4")});if(detailEl)detailEl.classList.remove("is-open")}'
            . 'document.addEventListener("click",function(e){if(!wrap.closest("section").contains(e.target))desel()});'
            . '});'
            . '})();';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Профиль';
    }
}
