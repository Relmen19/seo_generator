<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class GaugeChartBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $content, string $id): string
    {
        $c = $content;
        $title = $this->e($c['title'] ?? 'Показатели');
        $items = $c['items'] ?? [];
        $jsonData = json_encode($items, JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-gauge reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="mac-window">'
            . '<div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $title . '</div></div>'
            . '<div class="mac-body">'
            . '<div class="gauge-grid" data-gauges="' . $id . '"></div>'
            . '<div class="gauge-detail" data-gauge-detail="' . $id . '"><div><div class="gauge-detail-inner">'
            . '<div class="gauge-detail-header"><span class="gauge-detail-dot"></span><span class="gauge-detail-label"></span><span class="gauge-detail-val"></span></div>'
            . '<div class="gauge-detail-desc"></div>'
            . '<div class="gauge-detail-bar-track"><div class="gauge-detail-bar"></div></div>'
            . '</div></div></div>'
            . '<script type="application/json" class="gauge-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '</div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";

        return $h;
    }

    public function getCss(): string
    {
        return '@keyframes pulseRing{0%,100%{box-shadow:0 0 0 0 var(--pulse-c,rgba(37,99,235,.3))}50%{box-shadow:0 0 0 8px transparent}}'
            . "\n" . '.gauge-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px}'
            . "\n" . '.gauge-card{padding:24px 18px 18px;border-radius:18px;background:rgba(255,255,255,.55);backdrop-filter:blur(12px);border:1px solid var(--border);text-align:center;transition:all .35s;cursor:pointer}'
            . "\n" . '[data-theme="dark"] .gauge-card{background:rgba(255,255,255,.03)}'
            . "\n" . '.gauge-card:hover,.gauge-card.is-active{transform:translateY(-4px);box-shadow:0 12px 40px rgba(37,99,235,.1);border-color:rgba(37,99,235,.25)}'
            . "\n" . '.gauge-card.is-active{border-color:var(--gauge-c,var(--blue));box-shadow:0 0 0 2px var(--gauge-c,var(--blue)),0 12px 40px rgba(37,99,235,.12)}'
            . "\n" . '[data-gauges].has-active .gauge-card:not(.is-active){opacity:.4;transform:scale(.97)}'
            . "\n" . '.gauge-svg{width:130px;height:75px;display:block;margin:0 auto 10px}'
            . "\n" . '.gauge-track{fill:none;stroke:var(--border);stroke-width:10;stroke-linecap:round}'
            . "\n" . '.gauge-fill{fill:none;stroke-width:10;stroke-linecap:round;transition:stroke-dashoffset 1.6s cubic-bezier(.4,0,.2,1);filter:drop-shadow(0 0 6px var(--gauge-glow,rgba(37,99,235,.25)))}'
            . "\n" . '.gauge-val{font-family:var(--fh);font-size:1.5rem;font-weight:900;color:var(--dark);letter-spacing:-1px;line-height:1}'
            . "\n" . '.gauge-val small{font-size:.5em;font-weight:500;color:var(--muted);margin-left:2px}'
            . "\n" . '.gauge-name{font-size:12.5px;color:var(--muted);margin-top:3px;font-weight:500}'
            . "\n" . '.gauge-range{display:flex;justify-content:space-between;font-size:9.5px;color:var(--muted);margin-top:6px;padding:0 8px;font-family:var(--fh);font-weight:600;opacity:.4}'
            . "\n" . '.gauge-detail{display:grid;grid-template-rows:0fr;transition:grid-template-rows .4s ease,opacity .35s ease;opacity:0;margin-top:16px}'
            . "\n" . '.gauge-detail.is-open{grid-template-rows:1fr;opacity:1}'
            . "\n" . '.gauge-detail>*{overflow:hidden}'
            . "\n" . '.gauge-detail-inner{padding:16px 18px;border-radius:14px;background:rgba(255,255,255,.45);border:1px solid var(--border);backdrop-filter:blur(6px)}'
            . "\n" . '[data-theme="dark"] .gauge-detail-inner{background:rgba(255,255,255,.03)}'
            . "\n" . '.gauge-detail-header{display:flex;align-items:center;gap:8px;margin-bottom:6px}'
            . "\n" . '.gauge-detail-dot{width:10px;height:10px;border-radius:3px;flex-shrink:0}'
            . "\n" . '.gauge-detail-label{font-family:var(--fh);font-size:14px;font-weight:700;color:var(--dark)}'
            . "\n" . '.gauge-detail-val{font-family:var(--fh);font-size:24px;font-weight:900;color:var(--dark);margin-left:auto}'
            . "\n" . '.gauge-detail-desc{font-size:13px;color:var(--muted);line-height:1.55}'
            . "\n" . '.gauge-detail-bar-track{height:6px;border-radius:100px;background:var(--border);overflow:hidden;margin-top:10px}'
            . "\n" . '.gauge-detail-bar{height:100%;border-radius:100px;transition:width .8s ease,background .4s ease}'
            . "\n" . '@media(max-width:700px){'
            .     '.gauge-grid{grid-template-columns:repeat(2,1fr)}'
            . '}';
    }

    public function getJs(): string
    {
        return '(function(){'
            . 'document.querySelectorAll("[data-gauges]").forEach(function(grid){'
            . 'var jsonEl=grid.parentElement.querySelector(".gauge-data");'
            . 'var detail=grid.parentElement.querySelector("[data-gauge-detail]");'
            . 'if(!jsonEl)return;var data=[];try{data=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'var r=50,arcLen=Math.PI,circum=arcLen*r,active=-1;'
            . 'var colors=["#2563EB","#0D9488","#8B5CF6","#F59E0B","#EF4444","#16A34A","#EC4899","#06B6D4"];'
            . 'data.forEach(function(d,i){'
            . 'var mn=parseFloat(d.min||0),mx=parseFloat(d.max||100),val=parseFloat(d.value||0);'
            . 'var pct=Math.max(0,Math.min(1,(val-mn)/(mx-mn)));'
            . 'var off=circum-circum*pct;var clr=d.color||colors[i%colors.length];'
            . 'var card=document.createElement("div");card.className="gauge-card";card.style.setProperty("--gauge-c",clr);card.dataset.idx=i;'
            . 'card.innerHTML=\'<svg class="gauge-svg" viewBox="0 0 140 80"><path class="gauge-track" d="M10,70 A50,50 0 0,1 130,70"/><path class="gauge-fill" d="M10,70 A50,50 0 0,1 130,70" stroke="\'+clr+\'" style="--gauge-glow:\'+clr+\'33;stroke-dasharray:\'+circum+\';stroke-dashoffset:\'+circum+\'" data-off="\'+off+\'"/></svg><div class="gauge-val">\'+val+\'<small>\'+((d.unit||""))+"</small></div>"+'
            . '\'<div class="gauge-name">\'+((d.name||""))+\'</div><div class="gauge-range"><span>\'+mn+\'</span><span>\'+mx+"</span></div>";'
            . 'card.addEventListener("click",function(){select(i)});grid.appendChild(card)});'
            . 'function select(i){if(i===active){desel();return}active=i;grid.classList.add("has-active");'
            . 'grid.querySelectorAll(".gauge-card").forEach(function(c,ci){c.classList.toggle("is-active",ci===i)});'
            . 'var d=data[i],mn=parseFloat(d.min||0),mx=parseFloat(d.max||100),val=parseFloat(d.value||0);'
            . 'var pct=Math.max(0,Math.min(1,(val-mn)/(mx-mn)));var clr=d.color||colors[i%colors.length];'
            . 'if(detail){detail.querySelector(".gauge-detail-dot").style.background=clr;'
            . 'detail.querySelector(".gauge-detail-label").textContent=d.name||"";'
            . 'detail.querySelector(".gauge-detail-val").textContent=val+" "+(d.unit||"");'
            . 'detail.querySelector(".gauge-detail-desc").textContent=d.description||"";'
            . 'var bar=detail.querySelector(".gauge-detail-bar");bar.style.background=clr;bar.style.width=Math.round(pct*100)+"%";'
            . 'detail.classList.add("is-open")}}'
            . 'function desel(){active=-1;grid.classList.remove("has-active");grid.querySelectorAll(".gauge-card").forEach(function(c){c.classList.remove("is-active")});if(detail)detail.classList.remove("is-open")}'
            . 'document.addEventListener("click",function(e){if(!grid.closest("section").contains(e.target))desel()});'
            . 'new IntersectionObserver(function(e){e.forEach(function(x){if(x.isIntersecting){x.target.querySelectorAll(".gauge-fill").forEach(function(p){p.style.strokeDashoffset=p.dataset.off})}})},{threshold:.25}).observe(grid)'
            . '});'
            . '})();';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Показатели';
    }
}
