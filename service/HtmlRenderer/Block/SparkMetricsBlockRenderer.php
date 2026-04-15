<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class SparkMetricsBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $content, string $id): string
    {
        $c = $content;
        $title = $this->e($c['title'] ?? 'Метрики');
        $items = $c['items'] ?? [];
        $jsonData = json_encode($items, JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-spark reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<h2 class="sec-title">' . $title . '</h2>'
            . '<div class="sp-grid" data-sparks="' . $id . '"></div>'
            . '<div class="sp-detail" data-sp-detail="' . $id . '"><div><div class="sp-detail-inner"></div></div></div>'
            . '<script type="application/json" class="sp-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";

        return $h;
    }

    public function getCss(): string
    {
        return '@keyframes spPulse{0%,100%{r:3;opacity:.3}50%{r:7;opacity:0}}'
            . "\n" . '.sp-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px}'
            . "\n" . '.sp-card{padding:20px 18px 16px;border-radius:16px;background:rgba(255,255,255,.55);backdrop-filter:blur(12px);border:1px solid var(--border);transition:all .35s;cursor:pointer;overflow:hidden}'
            . "\n" . '[data-theme="dark"] .sp-card{background:rgba(255,255,255,.03)}'
            . "\n" . '.sp-card:hover,.sp-card.is-active{transform:translateY(-3px);box-shadow:0 10px 36px rgba(37,99,235,.08);border-color:rgba(37,99,235,.18)}'
            . "\n" . '.sp-card.is-active{border-color:var(--sp-c,var(--blue));box-shadow:0 0 0 2px var(--sp-c,var(--blue)),0 10px 36px rgba(37,99,235,.1)}'
            . "\n" . '[data-sparks].has-active .sp-card:not(.is-active){opacity:.4;transform:scale(.97)}'
            . "\n" . '.sp-head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px}'
            . "\n" . '.sp-icon{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}'
            . "\n" . '.sp-trend{display:inline-flex;align-items:center;gap:2px;padding:3px 9px;border-radius:100px;font-family:var(--fh);font-size:10.5px;font-weight:700}'
            . "\n" . '.sp-trend--up{background:#DCFCE7;color:#16A34A}[data-theme="dark"] .sp-trend--up{background:rgba(22,163,74,.15);color:#4ADE80}'
            . "\n" . '.sp-trend--down{background:#FEE2E2;color:#EF4444}[data-theme="dark"] .sp-trend--down{background:rgba(239,68,68,.15);color:#FCA5A5}'
            . "\n" . '.sp-val{font-family:var(--fh);font-size:1.6rem;font-weight:900;color:var(--dark);letter-spacing:-1px;line-height:1}'
            . "\n" . '.sp-val small{font-size:.45em;font-weight:500;color:var(--muted);margin-left:2px}'
            . "\n" . '.sp-name{font-size:12.5px;color:var(--muted);margin-top:2px;font-weight:500}'
            . "\n" . '.sp-chart{margin-top:10px;height:40px}'
            . "\n" . '.sp-svg{width:100%;height:100%;display:block}'
            . "\n" . '.sp-line{fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:var(--sp-len,300);stroke-dashoffset:var(--sp-len,300);transition:stroke-dashoffset 2s ease}'
            . "\n" . '.sp-line.is-drawn{stroke-dashoffset:0}'
            . "\n" . '.sp-area{opacity:.12}'
            . "\n" . '.sp-dot{fill:var(--bg);stroke-width:2;filter:drop-shadow(0 1px 3px rgba(0,0,0,.15))}'
            . "\n" . '.sp-dot-pulse{opacity:.3;animation:spPulse 2s ease-in-out infinite}'
            . "\n" . '.sp-detail{display:grid;grid-template-rows:0fr;transition:grid-template-rows .4s ease,opacity .3s;opacity:0;margin-top:14px}'
            . "\n" . '.sp-detail.is-open{grid-template-rows:1fr;opacity:1}'
            . "\n" . '.sp-detail>div{overflow:hidden}'
            . "\n" . '.sp-detail-inner{padding:14px 18px;border-radius:12px;background:rgba(255,255,255,.45);border:1px solid var(--border);backdrop-filter:blur(6px)}'
            . "\n" . '[data-theme="dark"] .sp-detail-inner{background:rgba(255,255,255,.03)}'
            . "\n" . '.sp-detail-pair{display:flex;justify-content:space-between;align-items:center;padding:4px 0;font-size:13px;color:var(--slate)}'
            . "\n" . '.sp-detail-pair+.sp-detail-pair{border-top:1px solid var(--border)}'
            . "\n" . '.sp-detail-pair span:last-child{font-family:var(--fh);font-weight:700;color:var(--dark)}'
            . "\n" . '@media(max-width:700px){'
            .     '.sp-grid{grid-template-columns:1fr 1fr}'
            . '}';
    }

    public function getJs(): string
    {
        return '(function(){'
            . 'document.querySelectorAll("[data-sparks]").forEach(function(grid){'
            . 'var jsonEl=grid.parentElement.querySelector(".sp-data");'
            . 'var detail=grid.parentElement.querySelector("[data-sp-detail]");'
            . 'if(!jsonEl)return;'
            . 'var cards=[];'
            . 'try{cards=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'var active=-1;'
            . 'var colors=["#2563EB","#0D9488","#8B5CF6","#16A34A","#F59E0B","#EF4444","#EC4899","#06B6D4"];'
            . 'cards.forEach(function(d,i){'
            . 'var card=document.createElement("div");'
            . 'card.className="sp-card";'
            . 'var clr=d.color||colors[i%colors.length];'
            . 'card.style.setProperty("--sp-c",clr);'
            . 'var w=180,h=40,pad=4;'
            . 'var pts=(d.points&&d.points.length>=2)?d.points:[50,50];'
            . 'var mn=Math.min.apply(null,pts);'
            . 'var mx=Math.max.apply(null,pts);'
            . 'var rng=mx-mn||1;'
            . 'var coords=pts.map(function(v,j){'
            . 'var x=pad+(w-2*pad)/(pts.length-1)*j;'
            . 'var y=h-pad-(v-mn)/rng*(h-2*pad);'
            . 'return x+","+y'
            . '});'
            . 'var poly=coords.join(" ");'
            . 'var last=coords[coords.length-1].split(",");'
            . 'var area="M "+coords[0]'
            . '+" L "+coords.slice(1).join(" ")'
            . '+" L "+(w-pad)+","+h'
            . '+" L "+pad+","+h+" Z";'
            . 'var lineLen=0;'
            . 'for(var k=1;k<coords.length;k++){'
            . 'var a=coords[k-1].split(","),b=coords[k].split(",");'
            . 'lineLen+=Math.sqrt(Math.pow(b[0]-a[0],2)+Math.pow(b[1]-a[1],2))'
            . '}'
            . 'var tUp=d.trend_up!==false&&d.trend_up!==0;'
            . 'var tCls=tUp?"sp-trend--up":"sp-trend--down";'
            . 'var tArr=tUp?"↑":"↓";'
            . 'card.innerHTML='
            . '"<div class=\"sp-head\">"'
            . '+"<div class=\"sp-icon\" style=\"background:"+(d.icon_bg||"#EFF6FF")+"\">"'
            . '+(d.icon||"📊")'
            . '+"</div>"'
            . '+"<span class=\"sp-trend "+tCls+"\">"'
            . '+tArr+" "+(d.trend||"")'
            . '+"</span>"'
            . '+"</div>"'
            . '+"<div class=\"sp-val\">"+(d.value||"0")+"<small>"+(d.unit||"")+"</small></div>"'
            . '+"<div class=\"sp-name\">"+(d.name||"")+"</div>"'
            . '+"<div class=\"sp-chart\">"'
            . '+"<svg class=\"sp-svg\" viewBox=\"0 0 "+w+" "+h+"\" preserveAspectRatio=\"none\">"'
            . '+"<path class=\"sp-area\" d=\""+area+"\" fill=\""+clr+"\"/>"'
            . '+"<polyline class=\"sp-line\" points=\""+poly+"\" stroke=\""+clr+"\" style=\"--sp-len:"+Math.ceil(lineLen)+"\"/>"'
            . '+"<circle class=\"sp-dot-pulse\" cx=\""+last[0]+"\" cy=\""+last[1]+"\" r=\"3\" stroke=\""+clr+"\" fill=\""+clr+"\"/>"'
            . '+"<circle class=\"sp-dot\" cx=\""+last[0]+"\" cy=\""+last[1]+"\" r=\"3\" stroke=\""+clr+"\"/>"'
            . '+"</svg>"'
            . '+"</div>";'
            . 'card.addEventListener("click",function(){selectSp(i)});'
            . 'grid.appendChild(card)'
            . '});'
            . 'function selectSp(idx){'
            . 'if(idx===active){deselSp();return}'
            . 'active=idx;'
            . 'grid.classList.add("has-active");'
            . 'grid.querySelectorAll(".sp-card").forEach(function(c,ci){'
            . 'c.classList.toggle("is-active",ci===idx)'
            . '});'
            . 'if(detail){'
            . 'var d=cards[idx];'
            . 'var inner=detail.querySelector(".sp-detail-inner");'
            . 'if(inner){'
            . 'inner.innerHTML=(d.details||[]).map(function(p){'
            . 'return"<div class=\"sp-detail-pair\"><span>"+p[0]+"</span><span>"+p[1]+"</span></div>"'
            . '}).join("")'
            . '}'
            . 'detail.classList.add("is-open")'
            . '}}'
            . 'function deselSp(){'
            . 'active=-1;'
            . 'grid.classList.remove("has-active");'
            . 'grid.querySelectorAll(".sp-card").forEach(function(c){c.classList.remove("is-active")});'
            . 'if(detail)detail.classList.remove("is-open")'
            . '}'
            . 'document.addEventListener("click",function(e){'
            . 'var section=grid.closest("section");'
            . 'if(!section||!section.contains(e.target))deselSp()'
            . '});'
            . 'new IntersectionObserver(function(entries){'
            . 'entries.forEach(function(x){'
            . 'if(x.isIntersecting){'
            . 'x.target.querySelectorAll(".sp-line").forEach(function(l){l.classList.add("is-drawn")})'
            . '}'
            . '});'
            . '},{threshold:0.2}).observe(grid)'
            . '});'
            . '})();';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Метрики';
    }
}
