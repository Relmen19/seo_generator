<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class ScoreRingsBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $c, string $id): string
    {
        $title = $this->e($c['title'] ?? 'Индекс здоровья');
        $rawLabel = trim((string)($c['total_label'] ?? 'общий балл'));
        if (mb_strlen($rawLabel) > 24) {
            $rawLabel = mb_substr($rawLabel, 0, 22) . '…';
        }
        $totalLabel = $this->e($rawLabel !== '' ? $rawLabel : 'общий балл');
        $rings = $c['rings'] ?? [];
        $jsonData = json_encode([
            'rings' => $rings,
            'total_label' => $c['total_label'] ?? 'общий балл',
        ], JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-rings reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $title . '</div></div>'
            . '<div class="mac-body">'
            . '<div class="ring-layout" data-rings="' . $id . '">'
            . '<div class="ring-svg-wrap">'
            . '<svg class="ring-svg" viewBox="0 0 220 220"></svg>'
            . '<div class="ring-center"><span class="ring-center-val"></span><span class="ring-center-label">' . $totalLabel . '</span></div>'
            . '</div>'
            . '<div>'
            . '<div class="ring-aside"></div>'
            . '<div class="ring-detail"><div><div class="ring-detail-inner"></div></div></div>'
            . '</div>'
            . '</div>'
            . '<script type="application/json" class="ring-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '</div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";

        return $h;
    }

    public function getCss(): string
    {
        return '.ring-layout{display:grid;grid-template-columns:220px 1fr;gap:32px;align-items:center}'
            . "\n" . '.ring-svg-wrap{position:relative;width:220px;height:220px}'
            . "\n" . '.ring-svg{width:100%;height:100%}'
            . "\n" . '.ring-track{fill:none;stroke-width:16;stroke-linecap:round;opacity:.12}'
            . "\n" . '.ring-fill{fill:none;stroke-width:16;stroke-linecap:round;transition:stroke-dashoffset 1.8s cubic-bezier(.4,0,.2,1);filter:drop-shadow(0 0 8px var(--ring-glow,rgba(37,99,235,.3)))}'
            . "\n" . '.ring-center{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;pointer-events:none;max-width:120px}'
            . "\n" . '.ring-center-val{font-family:var(--type-font-heading);font-size:2.2rem;font-weight:900;color:var(--color-text);letter-spacing:-1px;line-height:1;transition:all .3s}'
            . "\n" . '.ring-center-label{font-size:10px;color:var(--color-text-3);text-transform:uppercase;letter-spacing:1px;margin-top:4px;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;line-clamp:2;overflow:hidden;text-overflow:ellipsis;max-width:110px;line-height:1.25;word-break:break-word}'
            . "\n" . '.ring-aside{display:flex;flex-direction:column;gap:8px}'
            . "\n" . '.ring-item{display:flex;align-items:center;gap:10px;padding:14px 16px;border-radius:12px;background:rgba(255,255,255,.5);border:1px solid var(--color-border);transition:all .3s;cursor:pointer}'
            . "\n" . '[data-theme="dark"] .ring-item{background:rgba(255,255,255,.04)}'
            . "\n" . '.ring-item:hover{border-color:rgba(37,99,235,.25)}'
            . "\n" . '.ring-item.is-active{border-color:var(--ring-c,var(--color-accent));box-shadow:0 0 0 2px var(--ring-c,var(--color-accent)),0 4px 16px rgba(37,99,235,.08)}'
            . "\n" . '[data-rings].has-active .ring-item:not(.is-active){opacity:.35}'
            . "\n" . '.ring-item-dot{width:14px;height:14px;border-radius:50%;flex-shrink:0}'
            . "\n" . '.ring-item-info{flex:1;min-width:0}'
            . "\n" . '.ring-item-name{font-family:var(--type-font-heading);font-size:14px;font-weight:700;color:var(--color-text)}'
            . "\n" . '.ring-item-sub{font-size:12px;color:var(--color-text-3)}'
            . "\n" . '.ring-item-val{font-family:var(--type-font-heading);font-size:18px;font-weight:900;color:var(--color-text);letter-spacing:-1px}'
            . "\n" . '.ring-item-pct{font-size:10px;color:var(--color-text-3)}'
            . "\n" . '.ring-detail{display:grid;grid-template-rows:0fr;transition:grid-template-rows .4s ease,opacity .3s;opacity:0;margin-top:10px}'
            . "\n" . '.ring-detail.is-open{grid-template-rows:1fr;opacity:1}'
            . "\n" . '.ring-detail>div{overflow:hidden}'
            . "\n" . '.ring-detail-inner{padding:12px 16px;border-radius:12px;background:rgba(255,255,255,.45);border:1px solid var(--color-border);font-size:13px;color:var(--color-text-3);line-height:1.55}'
            . "\n" . '[data-theme="dark"] .ring-detail-inner{background:rgba(255,255,255,.03)}'
            . "\n" . '@media(max-width:700px){.ring-layout{grid-template-columns:1fr}.ring-svg-wrap{margin:0 auto}}'
            ;
    }

    public function getJs(): string
    {
        return '(function(){'
            . 'document.querySelectorAll("[data-rings]").forEach(function(wrap){'
            . 'var jsonEl=wrap.parentElement.querySelector(".ring-data");if(!jsonEl)return;'
            . 'var cfg={};try{cfg=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'var rings=cfg.rings||[];var svg=wrap.querySelector(".ring-svg");var aside=wrap.querySelector(".ring-aside");'
            . 'var centerVal=wrap.querySelector(".ring-center-val");'
            . 'var detailEl=wrap.querySelector(".ring-detail");var detailInner=detailEl?detailEl.querySelector(".ring-detail-inner"):null;'
            . 'var active=-1;var radii=[90,72,54,36];'
            . 'var avgScore=Math.round(rings.reduce(function(s,r){return s+(parseFloat(r.value)||0)},0)/Math.max(1,rings.length));'
            . 'if(centerVal)centerVal.textContent=avgScore;'
            . 'rings.forEach(function(ring,i){'
            . 'var r=radii[i%radii.length];var circum=2*Math.PI*r;var pct=(parseFloat(ring.value)||0)/(parseFloat(ring.max)||100);var offset=circum*(1-pct);var clr=ring.color||"#2563EB";'
            . 'var track=document.createElementNS("http://www.w3.org/2000/svg","circle");track.setAttribute("cx","110");track.setAttribute("cy","110");track.setAttribute("r",r);track.setAttribute("class","ring-track");track.setAttribute("stroke",clr);track.setAttribute("stroke-width","16");svg.appendChild(track);'
            . 'var fill=document.createElementNS("http://www.w3.org/2000/svg","circle");fill.setAttribute("cx","110");fill.setAttribute("cy","110");fill.setAttribute("r",r);fill.setAttribute("class","ring-fill");fill.setAttribute("stroke",clr);fill.setAttribute("stroke-dasharray",circum);fill.setAttribute("stroke-dashoffset",circum);fill.setAttribute("transform","rotate(-90 110 110)");fill.style.setProperty("--ring-glow",clr+"55");fill.dataset.target=offset;fill.dataset.idx=i;fill.style.cursor="pointer";fill.addEventListener("click",function(){selectRing(+this.dataset.idx)});svg.appendChild(fill);'
            . 'var item=document.createElement("div");item.className="ring-item";item.style.setProperty("--ring-c",clr);item.dataset.idx=i;'
            . 'item.innerHTML=\'<div class="ring-item-dot" style="background:\'+clr+\'"></div><div class="ring-item-info"><div class="ring-item-name">\'+(ring.name||"")+\'</div><div class="ring-item-sub">\'+(ring.subtitle||"")+\'</div></div><div style="text-align:right"><div class="ring-item-val">\'+(ring.value||0)+\'</div><div class="ring-item-pct">из \'+(ring.max||100)+"</div></div>";'
            . 'item.addEventListener("click",function(){selectRing(i)});aside.appendChild(item)});'
            . 'function selectRing(i){if(i===active){desel();return}active=i;wrap.classList.add("has-active");'
            . 'aside.querySelectorAll(".ring-item").forEach(function(el,ei){el.classList.toggle("is-active",ei===i)});'
            . 'if(centerVal)centerVal.textContent=rings[i].value||0;'
            . 'if(detailInner){detailInner.innerHTML="<b style=\\"color:"+(rings[i].color||"#2563EB")+"\\">"+(rings[i].name||"")+" — "+(rings[i].value||0)+"/"+(rings[i].max||100)+"</b><br>"+(rings[i].description||"");detailEl.classList.add("is-open")}}'
            . 'function desel(){active=-1;wrap.classList.remove("has-active");aside.querySelectorAll(".ring-item").forEach(function(el){el.classList.remove("is-active")});if(centerVal)centerVal.textContent=avgScore;if(detailEl)detailEl.classList.remove("is-open")}'
            . 'document.addEventListener("click",function(e){if(!wrap.closest("section").contains(e.target))desel()});'
            . 'new IntersectionObserver(function(e){e.forEach(function(x){if(x.isIntersecting){svg.querySelectorAll(".ring-fill").forEach(function(f){f.style.strokeDashoffset=f.dataset.target})}})},{threshold:.25}).observe(svg)'
            . '});'
            . '})();';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Индекс';
    }
}
