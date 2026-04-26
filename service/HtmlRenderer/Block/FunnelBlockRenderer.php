<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class FunnelBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $content, string $id): string
    {
        $c = $content;
        $title = $this->e($c['title'] ?? 'Воронка');
        $items = $c['items'] ?? [];
        $jsonData = json_encode($items, JSON_UNESCAPED_UNICODE);
        $desc = trim($c['description'] ?? '');

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-funnel reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $title . '</div></div>'
            . '<div class="mac-body">';

        if ($desc !== '') {
            $h .= '<p class="chart-desc">' . $this->e($desc) . '</p>';
        }

        $h .= '<div class="fn-wrap" data-funnel="' . $id . '"></div>'
            . '<div class="fn-detail" data-fn-detail="' . $id . '"><div><div class="fn-detail-inner">'
            . '<div class="fn-detail-head"><span class="fn-detail-dot"></span><span class="fn-detail-name"></span><span class="fn-detail-big"></span></div>'
            . '<div class="fn-detail-desc"></div>'
            . '</div></div></div>'
            . '<script type="application/json" class="fn-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '</div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";

        return $h;
    }

    public function getCss(): string
    {
        return '.fn-wrap{display:flex;flex-direction:column;gap:4px}'
            . "\n" . '.fn-stage{cursor:pointer;transition:all .3s}'
            . "\n" . '.fn-stage:hover .fn-bar-track{box-shadow:0 0 0 2px rgba(37,99,235,.15)}'
            . "\n" . '.fn-stage.is-active .fn-bar-track{box-shadow:0 0 0 2px var(--fn-c,var(--color-accent))}'
            . "\n" . '[data-funnel].has-active .fn-stage:not(.is-active){opacity:.35}'
            . "\n" . '.fn-row{display:grid;grid-template-columns:120px 1fr 54px;gap:12px;align-items:center}'
            . "\n" . '.fn-label{font-family:var(--type-font-heading);font-size:13px;font-weight:600;color:var(--color-text);text-align:right;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}'
            . "\n" . '.fn-bar-track{height:38px;border-radius:12px;background:var(--color-border);overflow:hidden;position:relative;transition:box-shadow .3s}'
            . "\n" . '.fn-bar-fill{height:100%;border-radius:12px;width:0;transition:width 1.4s cubic-bezier(.4,0,.2,1);position:relative;display:flex;align-items:center;justify-content:flex-end;padding-right:12px;overflow:hidden}'
            . "\n" . '.fn-bar-fill::after{content:"";position:absolute;top:0;left:0;right:0;bottom:0;background:linear-gradient(90deg,rgba(255,255,255,0) 0%,rgba(255,255,255,.2) 50%,rgba(255,255,255,0) 100%);animation:shimmer 3s ease-in-out infinite}'
            . "\n" . '.fn-inner-val{font-family:var(--type-font-heading);font-size:12px;font-weight:700;color:rgba(255,255,255,.9);position:relative;z-index:1;text-shadow:0 1px 3px rgba(0,0,0,.2)}'
            . "\n" . '.fn-pct{font-family:var(--type-font-heading);font-size:13px;font-weight:900;color:var(--color-text);text-align:left}'
            . "\n" . '.fn-conn{display:grid;grid-template-columns:120px 1fr 54px;gap:12px;align-items:center;height:16px}'
            . "\n" . '.fn-conn-line{display:flex;align-items:center;justify-content:center}'
            . "\n" . '.fn-conn-arrow{width:0;height:0;border-left:5px solid transparent;border-right:5px solid transparent;border-top:7px solid var(--color-text-3);opacity:.2}'
            . "\n" . '.fn-drop{font-size:10px;font-family:var(--type-font-heading);font-weight:700;color:var(--color-danger);text-align:center;opacity:.5}'
            . "\n" . '.fn-detail{display:grid;grid-template-rows:0fr;transition:grid-template-rows .4s ease,opacity .3s;opacity:0;margin-top:12px}'
            . "\n" . '.fn-detail.is-open{grid-template-rows:1fr;opacity:1}'
            . "\n" . '.fn-detail>div{overflow:hidden}'
            . "\n" . '.fn-detail-inner{padding:16px 18px;border-radius:14px;background:rgba(255,255,255,.45);border:1px solid var(--color-border);backdrop-filter:blur(6px)}'
            . "\n" . '[data-theme="dark"] .fn-detail-inner{background:rgba(255,255,255,.03)}'
            . "\n" . '.fn-detail-head{display:flex;align-items:center;gap:8px;margin-bottom:6px}'
            . "\n" . '.fn-detail-dot{width:10px;height:10px;border-radius:3px;flex-shrink:0}'
            . "\n" . '.fn-detail-name{font-family:var(--type-font-heading);font-size:15px;font-weight:700;color:var(--color-text)}'
            . "\n" . '.fn-detail-big{font-family:var(--type-font-heading);font-size:28px;font-weight:900;color:var(--color-text);margin-left:auto;letter-spacing:-1px}'
            . "\n" . '.fn-detail-desc{font-size:13px;color:var(--color-text-3);line-height:1.55}'
            . "\n" . '@media(max-width:700px){'
            .     '.fn-row,.fn-conn{grid-template-columns:80px 1fr 44px;gap:8px}'
            . '}';
    }

    public function getJs(): string
    {
        return '(function(){'
            . 'document.querySelectorAll("[data-funnel]").forEach(function(wrap){'
            . 'var jsonEl=wrap.parentElement.querySelector(".fn-data");var detail=wrap.parentElement.querySelector("[data-fn-detail]");'
            . 'if(!jsonEl)return;var data=[];try{data=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'if(!data.length)return;var maxVal=parseFloat(data[0].value||1);var active=-1;var stages=[];'
            . 'var gradients=["linear-gradient(90deg,#2563EB,#60A5FA)","linear-gradient(90deg,#0D9488,#2DD4BF)","linear-gradient(90deg,#8B5CF6,#C4B5FD)","linear-gradient(90deg,#F59E0B,#FCD34D)","linear-gradient(90deg,#16A34A,#4ADE80)","linear-gradient(90deg,#EC4899,#F9A8D4)","linear-gradient(90deg,#06B6D4,#67E8F9)"];'
            . 'var dotColors=["#2563EB","#0D9488","#8B5CF6","#F59E0B","#16A34A","#EC4899","#06B6D4"];'
            . 'data.forEach(function(d,i){'
            . 'var val=parseFloat(d.value||0);var pct=Math.round(val/maxVal*100);'
            . 'var color=d.color||gradients[i%gradients.length];var dotC=dotColors[i%dotColors.length];'
            . 'var stage=document.createElement("div");stage.className="fn-stage";stage.style.setProperty("--fn-c",dotC);'
            . 'stage.innerHTML=\'<div class="fn-row"><div class="fn-label">\'+((d.label||""))+\'</div><div class="fn-bar-track"><div class="fn-bar-fill" style="background:\'+color+\'" data-width="\'+pct+\'"><span class="fn-inner-val">\'+val.toLocaleString("ru-RU")+\'</span></div></div><div class="fn-pct">\'+pct+"%</div></div>";'
            . 'stage.addEventListener("click",function(){selectStage(i)});wrap.appendChild(stage);stages.push(stage);'
            . 'if(i<data.length-1){var drop=Math.round((parseFloat(data[i].value)-parseFloat(data[i+1].value))/parseFloat(data[i].value)*100);'
            . 'var cn=document.createElement("div");cn.className="fn-conn";cn.innerHTML=\'<div></div><div class="fn-conn-line"><div class="fn-conn-arrow"></div></div><div class="fn-drop">-\'+drop+"%</div>";wrap.appendChild(cn)}});'
            . 'function selectStage(i){if(i===active){desel();return}active=i;wrap.classList.add("has-active");'
            . 'stages.forEach(function(s,si){s.classList.toggle("is-active",si===i)});'
            . 'if(detail){var d=data[i];detail.querySelector(".fn-detail-dot").style.background=dotColors[i%dotColors.length];'
            . 'detail.querySelector(".fn-detail-name").textContent=d.label||"";'
            . 'detail.querySelector(".fn-detail-big").textContent=parseFloat(d.value||0).toLocaleString("ru-RU");'
            . 'detail.querySelector(".fn-detail-desc").textContent=d.description||"";detail.classList.add("is-open")}}'
            . 'function desel(){active=-1;wrap.classList.remove("has-active");stages.forEach(function(s){s.classList.remove("is-active")});if(detail)detail.classList.remove("is-open")}'
            . 'document.addEventListener("click",function(e){if(!wrap.closest("section").contains(e.target))desel()});'
            . 'new IntersectionObserver(function(e){e.forEach(function(x){if(x.isIntersecting){x.target.querySelectorAll(".fn-bar-fill").forEach(function(b,i){setTimeout(function(){b.style.width=b.dataset.width+"%"},i*100)})}})},{threshold:.2}).observe(wrap)'
            . '});'
            . '})();';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Воронка';
    }
}
