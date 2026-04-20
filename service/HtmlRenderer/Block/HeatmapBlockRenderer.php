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
        $c     = $content;
        $title = $this->e($c['title'] ?? 'Карта активности');
        $desc  = $this->e($c['description'] ?? '');

        $jsonData = json_encode([
            'rows'    => $c['rows']    ?? [],
            'columns' => $c['columns'] ?? [],
            'data'    => $c['data']    ?? [],
            'unit'    => $c['unit']    ?? '',
        ], JSON_UNESCAPED_UNICODE);

        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-heatmap reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop . $imgH
            . '<div class="mac-window">'
            . '<div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $title . '</div></div>'
            . '<div class="mac-body">'
            . '<div class="hm-wrap" data-heatmap="' . $id . '">'
            // Header row: legend + stats
            . '<div class="hm-header">'
            . '<div class="hm-legend">'
            . '<span class="hm-leg-min"></span>'
            . '<div class="hm-legend-track"><div class="hm-legend-bar"></div></div>'
            . '<span class="hm-leg-max"></span>'
            . '</div>'
            . '<div class="hm-stats">'
            . '<div class="hm-stat"><span class="hm-stat-lbl">Мин</span><span class="hm-stat-val hm-stat-min">—</span></div>'
            . '<div class="hm-stat"><span class="hm-stat-lbl">Среднее</span><span class="hm-stat-val hm-stat-avg">—</span></div>'
            . '<div class="hm-stat"><span class="hm-stat-lbl">Макс</span><span class="hm-stat-val hm-stat-max">—</span></div>'
            . '</div>'
            . '</div>'
            // Scrollable grid
            . '<div class="hm-outer"><div class="hm-grid"></div></div>'
            // Optional description caption
            . ($desc ? '<p class="hm-desc">' . $desc . '</p>' : '')
            // Click-details panel
            . '<div class="hm-info"><div><div class="hm-info-inner">'
            . '<div class="hm-info-swatch"></div>'
            . '<div class="hm-info-body">'
            . '<b class="hm-info-val"></b>'
            . '<span class="hm-info-lbl"></span>'
            . '<div class="hm-info-avgs"><span class="hm-info-rowavg"></span><span class="hm-info-colavg"></span></div>'
            . '</div>'
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
        return implode("\n", [
            /* wrap */
            '.hm-wrap{margin-top:12px}',
            /* header */
            '.hm-header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px}',
            /* legend */
            '.hm-legend{display:flex;align-items:center;gap:8px;font-size:11px;color:var(--muted);font-family:var(--fh);font-weight:600}',
            '.hm-legend-track{display:flex;flex-direction:column;gap:3px}',
            '.hm-legend-bar{width:120px;height:10px;border-radius:6px;background:linear-gradient(90deg,#EFF6FF,#BFDBFE,#60A5FA,#2563EB,#1E3A8A)}',
            '[data-theme="dark"] .hm-legend-bar{background:linear-gradient(90deg,#1e293b,#1e3a8a,#2563eb,#818cf8)}',
            /* stats pills */
            '.hm-stats{display:flex;gap:6px;flex-wrap:wrap}',
            '.hm-stat{background:rgba(255,255,255,.55);border:1px solid var(--border);border-radius:8px;padding:4px 10px;text-align:center;font-family:var(--fh)}',
            '[data-theme="dark"] .hm-stat{background:rgba(255,255,255,.04)}',
            '.hm-stat-lbl{display:block;font-size:9px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.05em}',
            '.hm-stat-val{display:block;font-size:13px;font-weight:700;color:var(--dark)}',
            /* scrollable outer */
            '.hm-outer{overflow-x:auto;-webkit-overflow-scrolling:touch;padding-bottom:6px}',
            '.hm-outer::-webkit-scrollbar{height:4px}',
            '.hm-outer::-webkit-scrollbar-track{background:transparent}',
            '.hm-outer::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}',
            /* grid */
            '.hm-grid{display:grid;gap:4px;width:max-content}',
            '.hm-corner{min-width:72px;width:72px}',
            '.hm-row-label{min-width:72px;width:72px;font-family:var(--fh);font-size:11px;font-weight:600;color:var(--muted);display:flex;align-items:center;justify-content:flex-end;padding-right:8px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}',
            '.hm-col-label{min-width:38px;width:38px;font-family:var(--fh);font-size:10px;font-weight:600;color:var(--muted);text-align:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding-bottom:4px}',
            /* cells */
            '.hm-cell{min-width:38px;width:38px;height:38px;border-radius:6px;cursor:pointer;position:relative;outline:2px solid transparent;outline-offset:2px;transition:transform .2s ease,box-shadow .2s ease,opacity .2s ease}',
            '@keyframes hmFadeIn{from{opacity:0;transform:scale(.5)}to{opacity:1;transform:scale(1)}}',
            '.hm-cell{animation:hmFadeIn .3s ease both}',
            '.hm-cell:hover{transform:scale(1.22);z-index:10;box-shadow:0 4px 18px rgba(0,0,0,.26);border-radius:8px}',
            '.hm-cell.is-active{outline-color:#3B82F6;transform:scale(1.22);z-index:10;border-radius:8px}',
            '[data-heatmap].has-active .hm-cell:not(.is-active):not(.is-row-hl):not(.is-col-hl){opacity:.2}',
            '.hm-cell.is-row-hl,.hm-cell.is-col-hl{opacity:.6}',
            /* value label inside cell (shown on hover) */
            '.hm-cell-val{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;font-family:var(--fh);pointer-events:none;opacity:0;transition:opacity .2s}',
            '.hm-cell:hover .hm-cell-val{opacity:1}',
            '.hm-cell-val.cl{color:rgba(0,0,0,.72)}',
            '.hm-cell-val.cd{color:rgba(255,255,255,.9)}',
            /* description */
            '.hm-desc{font-size:12px;color:var(--muted);margin-top:10px;font-style:italic}',
            /* info panel */
            '.hm-info{display:grid;grid-template-rows:0fr;transition:grid-template-rows .35s ease,opacity .3s;opacity:0;margin-top:14px}',
            '.hm-info.is-open{grid-template-rows:1fr;opacity:1}',
            '.hm-info>div{overflow:hidden}',
            '.hm-info-inner{padding:12px 16px;border-radius:12px;background:rgba(255,255,255,.5);border:1px solid var(--border);backdrop-filter:blur(6px);display:flex;align-items:center;gap:14px;flex-wrap:wrap}',
            '[data-theme="dark"] .hm-info-inner{background:rgba(255,255,255,.04)}',
            '.hm-info-swatch{width:34px;height:34px;border-radius:8px;flex-shrink:0;box-shadow:0 2px 8px rgba(0,0,0,.15)}',
            '.hm-info-body{font-size:13px;color:var(--slate)}',
            '.hm-info-val{font-family:var(--fh);color:var(--dark);font-size:20px;font-weight:800;display:block;margin-bottom:1px}',
            '.hm-info-lbl{display:block;margin-bottom:6px;font-weight:500}',
            '.hm-info-avgs{display:flex;gap:12px;font-size:11px;color:var(--muted);font-family:var(--fh);font-weight:600}',
            /* floating tooltip */
            '.hm-tooltip{position:fixed;pointer-events:none;background:rgba(15,23,42,.93);color:#fff;padding:7px 12px;border-radius:8px;font-size:12px;font-family:var(--fh);font-weight:600;opacity:0;transition:opacity .15s;z-index:9999;white-space:nowrap;box-shadow:0 4px 20px rgba(0,0,0,.3);transform:translate(-50%,-115%);line-height:1.6}',
            '.hm-tooltip.vis{opacity:1}',
            '.hm-tooltip small{display:block;font-size:10px;font-weight:400;opacity:.75;margin-top:1px}',
        ]);
    }

    public function getJs(): string
    {
        return '(function(){'
            /* color stops: light-theme white→deep-blue, dark-theme slate→indigo */
            . 'var LS=[[239,246,255],[191,219,254],[96,165,250],[37,99,235],[30,58,138]];'
            . 'var DS=[[15,23,42],[30,58,138],[37,99,235],[96,165,250],[129,140,248]];'
            . 'function lerp(a,b,t){return[Math.round(a[0]+(b[0]-a[0])*t),Math.round(a[1]+(b[1]-a[1])*t),Math.round(a[2]+(b[2]-a[2])*t)]}'
            . 'function hcol(t,s){t=Math.max(0,Math.min(1,t));var n=s.length-1,i=Math.min(n-1,Math.floor(t*n)),f=t*n-i,c=lerp(s[i],s[i+1],f);'
            . 'return{rgb:"rgb("+c[0]+","+c[1]+","+c[2]+")",light:0.299*c[0]+0.587*c[1]+0.114*c[2]>145}}'
            . 'function fmtV(v,u,dec){return(dec?Math.round(v*10)/10:Math.round(v))+u}'
            /* singleton tooltip */
            . 'var tip=document.getElementById("hm-tip-g");'
            . 'if(!tip){tip=document.createElement("div");tip.id="hm-tip-g";tip.className="hm-tooltip";document.body.appendChild(tip)}'
            . 'function showTip(x,y,h){tip.innerHTML=h;tip.style.left=x+"px";tip.style.top=(y-10)+"px";tip.className="hm-tooltip vis"}'
            . 'function hideTip(){tip.className="hm-tooltip"}'
            . 'document.querySelectorAll("[data-heatmap]").forEach(function(wrap){'
            . 'var jEl=wrap.parentElement.querySelector(".hm-data");if(!jEl)return;'
            . 'var cfg={};try{cfg=JSON.parse(jEl.textContent)}catch(e){}'
            . 'var rows=cfg.rows||[],cols=cfg.columns||[],matrix=cfg.data||[],unit=cfg.unit||"";'
            . 'var dark=document.documentElement.getAttribute("data-theme")==="dark";'
            . 'var stops=dark?DS:LS;'
            /* stats */
            . 'var flat=[];rows.forEach(function(r,ri){cols.forEach(function(c,ci){flat.push(matrix[ri]&&matrix[ri][ci]!=null?+matrix[ri][ci]:0)})});'
            . 'var minV=flat.length?Math.min.apply(null,flat):0;'
            . 'var maxV=flat.length?Math.max.apply(null,flat):100;'
            . 'var avgV=flat.length?flat.reduce(function(a,b){return a+b},0)/flat.length:0;'
            . 'var range=maxV-minV||1;'
            . 'var sm=wrap.querySelector(".hm-stat-min"),sa=wrap.querySelector(".hm-stat-avg"),sx=wrap.querySelector(".hm-stat-max");'
            . 'if(sm)sm.textContent=fmtV(minV,unit,false);if(sa)sa.textContent=fmtV(avgV,unit,true);if(sx)sx.textContent=fmtV(maxV,unit,false);'
            . 'var lm=wrap.querySelector(".hm-leg-min"),lx=wrap.querySelector(".hm-leg-max");'
            . 'if(lm)lm.textContent=fmtV(minV,unit,false);if(lx)lx.textContent=fmtV(maxV,unit,false);'
            /* build grid */
            . 'var grid=wrap.querySelector(".hm-grid");'
            . 'var info=wrap.querySelector(".hm-info");'
            . 'var swatch=wrap.querySelector(".hm-info-swatch"),valEl=wrap.querySelector(".hm-info-val"),lblEl=wrap.querySelector(".hm-info-lbl");'
            . 'var ravgEl=wrap.querySelector(".hm-info-rowavg"),cavgEl=wrap.querySelector(".hm-info-colavg");'
            . 'grid.style.gridTemplateColumns="72px repeat("+cols.length+",38px)";'
            . 'var cor=document.createElement("div");cor.className="hm-corner";grid.appendChild(cor);'
            . 'cols.forEach(function(m){var l=document.createElement("div");l.className="hm-col-label";l.textContent=m;l.title=m;grid.appendChild(l)});'
            /* per-row/col averages */
            . 'var rowAvg=rows.map(function(r,ri){var s=0;cols.forEach(function(c,ci){s+=matrix[ri]&&matrix[ri][ci]!=null?+matrix[ri][ci]:0});return s/cols.length});'
            . 'var colAvg=cols.map(function(c,ci){var s=0;rows.forEach(function(r,ri){s+=matrix[ri]&&matrix[ri][ci]!=null?+matrix[ri][ci]:0});return s/rows.length});'
            . 'var allCells=[],active=null;'
            . 'rows.forEach(function(w,ri){'
            . 'var rl=document.createElement("div");rl.className="hm-row-label";rl.textContent=w;rl.title=w;grid.appendChild(rl);'
            . 'cols.forEach(function(m,ci){'
            . 'var val=matrix[ri]&&matrix[ri][ci]!=null?+matrix[ri][ci]:0;'
            . 'var t=(val-minV)/range;var col=hcol(t,stops);'
            . 'var c=document.createElement("div");c.className="hm-cell";'
            . 'c.style.background=col.rgb;'
            . 'c.style.animationDelay=Math.min(ri*cols.length+ci,28)*20+"ms";'
            . 'c.dataset.ri=ri;c.dataset.ci=ci;'
            /* value overlay shown on hover */
            . 'var vl=document.createElement("span");vl.className="hm-cell-val "+(col.light?"cl":"cd");'
            . 'vl.textContent=val+(unit.length<=2?unit:"");c.appendChild(vl);'
            /* tooltip */
            . 'c.addEventListener("mouseenter",function(e){showTip(e.clientX,e.clientY,"<span>"+val+unit+"</span><small>"+w+" \u2022 "+m+"</small>")});'
            . 'c.addEventListener("mousemove",function(e){tip.style.left=e.clientX+"px";tip.style.top=(e.clientY-10)+"px"});'
            . 'c.addEventListener("mouseleave",hideTip);'
            /* click */
            . 'c.addEventListener("click",function(){selCell(c,ri,ci,val,m,w,col.rgb)});'
            . 'grid.appendChild(c);allCells.push(c)'
            . '})});'
            . 'function selCell(el,ri,ci,val,m,w,clr){'
            . 'if(active===el){desel();return}active=el;wrap.classList.add("has-active");'
            . 'allCells.forEach(function(c){c.classList.remove("is-active","is-row-hl","is-col-hl");'
            . 'if(+c.dataset.ri===ri)c.classList.add("is-row-hl");if(+c.dataset.ci===ci)c.classList.add("is-col-hl")});'
            . 'el.classList.add("is-active");swatch.style.background=clr;'
            . 'valEl.textContent=val+unit;lblEl.textContent=w+"\u00a0\u2022\u00a0"+m;'
            . 'ravgEl.textContent="Ряд avg: "+fmtV(rowAvg[ri],unit,true);'
            . 'cavgEl.textContent="Столбец avg: "+fmtV(colAvg[ci],unit,true);'
            . 'info.classList.add("is-open")}'
            . 'function desel(){active=null;wrap.classList.remove("has-active");'
            . 'allCells.forEach(function(c){c.classList.remove("is-active","is-row-hl","is-col-hl")});'
            . 'info.classList.remove("is-open")}'
            . 'document.addEventListener("click",function(e){if(!wrap.contains(e.target))desel()});'
            . '});'
            . '})();';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Карта активности';
    }
}
