<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class ProgressTrackerBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $c, string $id): string
    {
        $title = $this->e($c['title'] ?? 'Ожидаемый прогресс');
        $unit  = $this->e($c['timeline_unit'] ?? 'месяц');
        $milestones = $c['milestones'] ?? [];
        $note  = $this->e($c['note'] ?? '');
        $jsonData = json_encode($milestones, JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-ptrack reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<h2 class="sec-title">' . $title . '</h2>'
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $this->e($c['mac_title'] ?? 'Прогресс') . '</div></div>'
            . '<div class="mac-body">'
            . '<div class="pt-wrap" data-ptrack="' . $id . '">'
            . '<div class="pt-track"><div class="pt-track-fill"></div>';
        foreach ($milestones as $i => $m) {
            $left = (int)($m['marker'] ?? 0);
            $h .= '<div class="pt-dot" data-idx="' . $i . '" style="left:' . $left . '%"><div class="pt-dot-inner"></div>'
                . '<div class="pt-label">' . $this->e($m['period'] ?? '') . '</div>'
                . '</div>';
        }
        $h .= '</div>'
            . '<div class="pt-detail" style="display:none">'
            . '<div class="pt-detail-period"></div>'
            . '<div class="pt-detail-text"></div>'
            . '<div class="pt-detail-metric"></div>'
            . '</div>'
            . ($note ? '<div class="pt-note">' . $note . '</div>' : '')
            . '<script type="application/json" class="pt-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '</div></div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    public function getCss(): string
    {
        return '.pt-wrap{padding:10px 0}'
            . "\n" . '.pt-track{position:relative;height:8px;background:var(--border);border-radius:100px;margin:40px 20px 50px}'
            . "\n" . '.pt-track-fill{position:absolute;top:0;left:0;height:100%;border-radius:100px;background:linear-gradient(90deg,var(--blue),var(--teal),var(--green));width:0;transition:width 1.5s cubic-bezier(.4,0,.2,1)}'
            . "\n" . '.pt-dot{position:absolute;top:50%;transform:translateY(-50%);cursor:pointer;z-index:2}'
            . "\n" . '.pt-dot-inner{width:20px;height:20px;border-radius:50%;background:var(--bg);border:3px solid var(--blue);margin-left:-10px;transition:all .3s;box-shadow:0 2px 8px rgba(37,99,235,.15)}'
            . "\n" . '.pt-dot:hover .pt-dot-inner,.pt-dot.is-active .pt-dot-inner{background:var(--blue);transform:scale(1.3)}'
            . "\n" . '.pt-label{position:absolute;top:28px;left:50%;transform:translateX(-50%);font-family:var(--fh);font-size:11px;font-weight:600;color:var(--muted);white-space:normal;text-align:center;max-width:110px;line-height:1.3;word-break:break-word}'
            . "\n" . '.pt-detail{padding:18px 20px;border-radius:14px;background:rgba(255,255,255,.5);border:1px solid var(--border);backdrop-filter:blur(8px);animation:fadeInUp .4s ease}'
            . "\n" . '[data-theme="dark"] .pt-detail{background:rgba(255,255,255,.03)}'
            . "\n" . '.pt-detail-period{font-family:var(--fh);font-size:14px;font-weight:700;color:var(--blue);margin-bottom:4px}'
            . "\n" . '.pt-detail-text{font-size:14px;color:var(--slate);line-height:1.6}'
            . "\n" . '.pt-detail-metric{margin-top:8px;padding:8px 12px;border-radius:8px;background:var(--blue-light);font-family:var(--fh);font-size:13px;font-weight:600;color:var(--dark)}'
            . "\n" . '.pt-note{font-size:11.5px;color:var(--muted);text-align:center;margin-top:16px;font-style:italic}'
            ;
    }

    public function getJs(): string
    {
        return '(function(){'
            . 'document.querySelectorAll("[data-ptrack]").forEach(function(wrap){'
            . 'var jsonEl=wrap.querySelector(".pt-data");if(!jsonEl)return;'
            . 'var milestones=[];try{milestones=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'var dots=wrap.querySelectorAll(".pt-dot");var fill=wrap.querySelector(".pt-track-fill");'
            . 'var detail=wrap.querySelector(".pt-detail");var dPeriod=detail.querySelector(".pt-detail-period");'
            . 'var dText=detail.querySelector(".pt-detail-text");var dMetric=detail.querySelector(".pt-detail-metric");'
            . 'var active=-1;'
            . 'dots.forEach(function(dot,i){dot.addEventListener("click",function(e){'
            . 'e.stopPropagation();if(active===i){desel();return}active=i;'
            . 'dots.forEach(function(d){d.classList.remove("is-active")});dot.classList.add("is-active");'
            . 'var m=milestones[i]||{};dPeriod.textContent=m.period||"";dText.textContent=m.text||"";'
            . 'dMetric.textContent=m.metric||"";detail.style.display="block";'
            . 'fill.style.width=(m.marker||0)+"%"})});'
            . 'function desel(){active=-1;dots.forEach(function(d){d.classList.remove("is-active")});detail.style.display="none"}'
            . 'document.addEventListener("click",function(e){if(!wrap.contains(e.target))desel()});'
            . 'new IntersectionObserver(function(e){e.forEach(function(x){if(x.isIntersecting){var last=milestones[milestones.length-1];if(last)fill.style.width=(last.marker||100)+"%"}})},{threshold:.3}).observe(wrap)'
            . '});'
            . '})();';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Прогресс';
    }
}
