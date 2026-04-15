<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class RangeComparisonBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $c, string $id): string
    {
        $title = $this->e($c['title'] ?? 'Референсные диапазоны');
        $jsonData = json_encode([
            'groups' => $c['groups'] ?? [],
            'rows' => $c['rows'] ?? [],
        ], JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-ranges reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $title . '</div></div>'
            . '<div class="mac-body">'
            . '<div data-ranges="' . $id . '">'
            . '<div class="rc-toggle"></div>'
            . '<div class="rc-wrap"></div>'
            . '<div class="rc-detail"><div><div class="rc-detail-inner"></div></div></div>'
            . '</div>'
            . '<script type="application/json" class="rc-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '</div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";

        return $h;
    }

    public function getCss(): string
    {
        return '.rc-wrap{display:flex;flex-direction:column;gap:8px}'
            . "\n" . '.rc-row{display:grid;grid-template-columns:100px 1fr;gap:14px;align-items:center;padding:12px 16px;border-radius:12px;background:rgba(255,255,255,.4);border:1px solid var(--border);cursor:pointer;transition:all .3s}'
            . "\n" . '[data-theme="dark"] .rc-row{background:rgba(255,255,255,.02)}'
            . "\n" . '.rc-row:hover{border-color:rgba(37,99,235,.2)}'
            . "\n" . '.rc-row.is-active{border-color:var(--blue);box-shadow:0 0 0 2px var(--blue),0 4px 20px rgba(37,99,235,.08)}'
            . "\n" . '[data-ranges].has-active .rc-row:not(.is-active){opacity:.35}'
            . "\n" . '.rc-label{font-family:var(--fh);font-size:13px;font-weight:700;color:var(--dark)}'
            . "\n" . '.rc-bars{display:flex;flex-direction:column;gap:6px}'
            . "\n" . '.rc-bar-row{display:flex;align-items:center;gap:8px}'
            . "\n" . '.rc-bar-name{font-size:10px;font-family:var(--fh);font-weight:600;color:var(--muted);width:30px;text-align:right;flex-shrink:0}'
            . "\n" . '.rc-bar-track{flex:1;height:12px;border-radius:100px;background:var(--border);position:relative;overflow:hidden}'
            . "\n" . '.rc-bar-range{position:absolute;top:0;bottom:0;border-radius:100px;transition:left .8s ease,width .8s ease}'
            . "\n" . '.rc-bar-marker{position:absolute;top:-3px;bottom:-3px;width:3px;border-radius:2px;background:var(--dark);transition:left .8s ease;z-index:2}'
            . "\n" . '.rc-bar-val{font-size:10px;font-family:var(--fh);font-weight:700;color:var(--dark);width:38px;flex-shrink:0}'
            . "\n" . '.rc-toggle{display:flex;gap:4px;margin-bottom:14px}'
            . "\n" . '.rc-toggle-btn{padding:8px 16px;border:1px solid var(--border);background:transparent;border-radius:10px;font-family:var(--fh);font-size:12px;font-weight:600;color:var(--muted);cursor:pointer;transition:all .25s}'
            . "\n" . '.rc-toggle-btn:hover{color:var(--dark);background:rgba(37,99,235,.06)}'
            . "\n" . '.rc-toggle-btn.is-active{color:#fff;background:var(--blue);border-color:var(--blue)}'
            . "\n" . '.rc-detail{display:grid;grid-template-rows:0fr;transition:grid-template-rows .4s ease,opacity .3s;opacity:0;margin-top:10px}'
            . "\n" . '.rc-detail.is-open{grid-template-rows:1fr;opacity:1}'
            . "\n" . '.rc-detail>div{overflow:hidden}'
            . "\n" . '.rc-detail-inner{padding:14px 18px;border-radius:12px;background:rgba(255,255,255,.45);border:1px solid var(--border);font-size:13px;color:var(--muted);line-height:1.55}'
            . "\n" . '[data-theme="dark"] .rc-detail-inner{background:rgba(255,255,255,.03)}'
            . "\n" . '@media(max-width:700px){.rc-row{grid-template-columns:80px 1fr}}'
            ;
    }

    public function getJs(): string
    {
        return '(function(){'
            . 'document.querySelectorAll("[data-ranges]").forEach(function(wrap){'
            . 'var jsonEl=wrap.parentElement.querySelector(".rc-data");if(!jsonEl)return;'
            . 'var cfg={};try{cfg=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'var groups=cfg.groups||[];var rows=cfg.rows||[];'
            . 'var toggle=wrap.querySelector(".rc-toggle");var rowsEl=wrap.querySelector(".rc-wrap");'
            . 'var detail=wrap.querySelector(".rc-detail");var detailInner=detail?detail.querySelector(".rc-detail-inner"):null;'
            . 'var colors=["#2563EB","#EC4899","#0D9488","#F59E0B"];var activeGroup=0;var activeRow=-1;'
            . 'groups.forEach(function(g,gi){var btn=document.createElement("button");btn.className="rc-toggle-btn"+(gi===0?" is-active":"");btn.textContent=g.key||"";btn.addEventListener("click",function(){activeGroup=gi;toggle.querySelectorAll(".rc-toggle-btn").forEach(function(b,bi){b.classList.toggle("is-active",bi===gi)});renderRows();if(activeRow>=0)showDetail(activeRow)});toggle.appendChild(btn)});'
            . 'function renderRows(){rowsEl.innerHTML="";rows.forEach(function(r,ri){'
            . 'var row=document.createElement("div");row.className="rc-row"+(ri===activeRow?" is-active":"");'
            . 'var barsHtml=\'<div class="rc-bars">\';'
            . 'groups.forEach(function(g,gi){var lo=r.ranges[gi][0],hi=r.ranges[gi][1],val=r.values[gi],mn=parseFloat(r.min||0),mx=parseFloat(r.max||200);'
            . 'var leftPct=((lo-mn)/(mx-mn)*100).toFixed(1);var widthPct=(((hi-lo)/(mx-mn))*100).toFixed(1);var markerPct=(((val-mn)/(mx-mn))*100).toFixed(1);var op=gi===activeGroup?"1":".25";'
            . 'barsHtml+=\'<div class="rc-bar-row"><span class="rc-bar-name" style="color:\'+colors[gi%colors.length]+\'">\'+((g.tag||""))+\'</span><div class="rc-bar-track"><div class="rc-bar-range" style="left:\'+leftPct+\'%;width:\'+widthPct+\'%;background:\'+colors[gi%colors.length]+\';opacity:\'+op+\'"></div><div class="rc-bar-marker" style="left:calc(\'+markerPct+\'% - 1px);background:\'+colors[gi%colors.length]+\';opacity:\'+Math.max(.5,+op)+\'"></div></div><span class="rc-bar-val">\'+val+" "+(r.unit||"")+"</span></div>"});'
            . 'barsHtml+="</div>";row.innerHTML=\'<div class="rc-label">\'+(r.name||"")+\'</div>\'+barsHtml;'
            . 'row.addEventListener("click",function(){if(activeRow===ri){deselRow();return}activeRow=ri;wrap.classList.add("has-active");rowsEl.querySelectorAll(".rc-row").forEach(function(x,xi){x.classList.toggle("is-active",xi===ri)});showDetail(ri)});'
            . 'rowsEl.appendChild(row)})}'
            . 'function showDetail(ri){var r=rows[ri],g=groups[activeGroup],val=r.values[activeGroup],lo=r.ranges[activeGroup][0],hi=r.ranges[activeGroup][1];'
            . 'var status=val>=lo&&val<=hi?"<span style=\\"color:var(--green)\\">✓ В норме</span>":"<span style=\\"color:var(--red)\\">⚠ Отклонение</span>";'
            . 'if(detailInner){detailInner.innerHTML="<b>"+(r.name||"")+" ("+(g.key||"")+")</b> — "+val+" "+(r.unit||"")+" "+status+"<br>Норма: "+lo+"–"+hi+" "+(r.unit||"")+"<br>"+(r.description||"");detail.classList.add("is-open")}}'
            . 'function deselRow(){activeRow=-1;wrap.classList.remove("has-active");rowsEl.querySelectorAll(".rc-row").forEach(function(x){x.classList.remove("is-active")});if(detail)detail.classList.remove("is-open")}'
            . 'document.addEventListener("click",function(e){if(!wrap.contains(e.target))deselRow()});'
            . 'renderRows()'
            . '});'
            . '})();';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Диапазоны';
    }
}
