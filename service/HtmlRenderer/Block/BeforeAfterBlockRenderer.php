<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class BeforeAfterBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $c, string $id): string
    {
        $title = $this->e($c['title'] ?? 'До и после');
        $metrics = $c['metrics'] ?? [];
        $jsonData = json_encode($metrics, JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-beforeafter reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $title . '</div></div>'
            . '<div class="mac-body">'
            . '<div class="ba-container" data-beforeafter="' . $id . '"></div>'
            . '<script type="application/json" class="ba-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '</div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";

        return $h;
    }

    public function getCss(): string
    {
        return '.ba-container{max-width:680px;margin:0 auto}'
            . "\n" . '.ba-cards{display:grid;grid-template-columns:1fr 1fr;gap:0;border-radius:16px;overflow:hidden;border:1px solid var(--border);background:rgba(255,255,255,.5)}'
            . "\n" . '[data-theme="dark"] .ba-cards{background:rgba(255,255,255,.03)}'
            . "\n" . '.ba-side{padding:28px 24px}'
            . "\n" . '.ba-side--before{border-right:1px solid var(--border)}'
            . "\n" . '.ba-tag{font-family:var(--fh);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:2px;padding:4px 12px;border-radius:100px;display:inline-block;margin-bottom:14px}'
            . "\n" . '.ba-tag--before{background:#FEE2E2;color:#991B1B}'
            . "\n" . '.ba-tag--after{background:#DCFCE7;color:#16A34A}'
            . "\n" . '[data-theme="dark"] .ba-tag--before{background:rgba(239,68,68,.15);color:#FCA5A5}'
            . "\n" . '[data-theme="dark"] .ba-tag--after{background:rgba(22,163,74,.15);color:#4ADE80}'
            . "\n" . '.ba-metric{margin-bottom:12px}'
            . "\n" . '.ba-metric-val{font-family:var(--fh);font-size:1.8rem;font-weight:900;color:var(--dark);letter-spacing:-1px;line-height:1}'
            . "\n" . '.ba-metric-name{font-size:12px;color:var(--muted);font-weight:500;margin-top:2px}'
            . "\n" . '.ba-bar-track{height:8px;border-radius:100px;background:var(--border);overflow:hidden}'
            . "\n" . '.ba-bar-fill{height:100%;border-radius:100px;transition:width 1.2s cubic-bezier(.4,0,.2,1)}'
            . "\n" . '.ba-slider-row{display:flex;align-items:center;gap:12px;margin-top:20px}'
            . "\n" . '.ba-slider-label{font-family:var(--fh);font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;white-space:nowrap}'
            . "\n" . '.ba-slider{flex:1;-webkit-appearance:none;appearance:none;height:6px;border-radius:100px;background:linear-gradient(90deg,var(--red),var(--warn),var(--green));outline:none;cursor:pointer}'
            . "\n" . '.ba-slider::-webkit-slider-thumb{-webkit-appearance:none;width:22px;height:22px;border-radius:50%;background:var(--white);border:3px solid var(--blue);box-shadow:0 2px 10px rgba(0,0,0,.15);cursor:pointer}'
            . "\n" . '.ba-delta{text-align:center;margin-top:16px;padding:12px;border-radius:12px;background:rgba(255,255,255,.4);border:1px solid var(--border)}'
            . "\n" . '[data-theme="dark"] .ba-delta{background:rgba(255,255,255,.03)}'
            . "\n" . '.ba-delta-val{font-family:var(--fh);font-size:1.3rem;font-weight:900;letter-spacing:-1px}'
            . "\n" . '.ba-delta-label{font-size:11px;color:var(--muted)}'
            . "\n" . '@media(max-width:700px){.ba-cards{grid-template-columns:1fr}.ba-side--before{border-right:none;border-bottom:1px solid var(--border)}}'
            ;
    }

    public function getJs(): string
    {
        return '(function(){'
            . 'document.querySelectorAll("[data-beforeafter]").forEach(function(wrap){'
            . 'var jsonEl=wrap.parentElement.querySelector(".ba-data");if(!jsonEl)return;'
            . 'var metrics=[];try{metrics=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'if(!metrics.length)return;'
            . 'metrics.forEach(function(m){var mx=parseFloat(m.max);if(!isFinite(mx)||mx<=0){var b=parseFloat(m.before)||0,a=parseFloat(m.after)||0;m.max=Math.max(b,a,1)}});'
            . 'var h=\'<div class="ba-cards"><div class="ba-side ba-side--before"><div class="ba-tag ba-tag--before">До</div>\';'
            . 'metrics.forEach(function(m){h+=\'<div class="ba-metric"><div class="ba-metric-val">\'+m.before+\'<small style="font-size:.5em;color:var(--muted);margin-left:2px">\'+((m.unit||""))+\'</small></div><div class="ba-metric-name">\'+(m.name||"")+\'</div><div class="ba-bar-track"><div class="ba-bar-fill" style="background:var(--red);width:0" data-w="\'+Math.round(m.before/m.max*100)+\'"></div></div></div>\'});'
            . 'h+=\'</div><div class="ba-side ba-side--after"><div class="ba-tag ba-tag--after">После</div>\';'
            . 'metrics.forEach(function(m){h+=\'<div class="ba-metric"><div class="ba-metric-val">\'+m.after+\'<small style="font-size:.5em;color:var(--muted);margin-left:2px">\'+((m.unit||""))+\'</small></div><div class="ba-metric-name">\'+(m.name||"")+\'</div><div class="ba-bar-track"><div class="ba-bar-fill" style="background:var(--green);width:0" data-w="\'+Math.round(m.after/m.max*100)+\'"></div></div></div>\'});'
            . 'h+=\'</div></div><div class="ba-slider-row"><span class="ba-slider-label">До</span><input type="range" min="0" max="100" value="100" class="ba-slider"><span class="ba-slider-label">После</span></div><div class="ba-delta"><div class="ba-delta-val" style="color:var(--green)">↑ Результат</div><div class="ba-delta-label">Перетяните слайдер</div></div>\';'
            . 'wrap.innerHTML=h;'
            . 'wrap.querySelector(".ba-slider").addEventListener("input",function(){var t=+this.value/100;'
            . 'wrap.querySelector(".ba-delta-val").textContent=t>=.5?"↑ Улучшение: "+Math.round(t*100)+"%":"⏳ Прогресс: "+Math.round(t*100)+"%";'
            . 'wrap.querySelector(".ba-delta-val").style.color=t>=.5?"var(--green)":"var(--warn)"});'
            . 'new IntersectionObserver(function(e){e.forEach(function(x){if(x.isIntersecting){x.target.querySelectorAll(".ba-bar-fill").forEach(function(b){b.style.width=b.dataset.w+"%"})}})},{threshold:.2}).observe(wrap)'
            . '});'
            . '})();';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'До и после';
    }
}
