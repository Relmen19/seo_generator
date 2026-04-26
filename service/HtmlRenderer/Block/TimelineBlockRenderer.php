<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class TimelineBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $content, string $id): string
    {
        $c = $content;
        $title = $this->e($c['title'] ?? 'Этапы');
        $items = $c['items'] ?? [];
        $colors = ['var(--color-chart-1)', 'var(--color-chart-2)', 'var(--color-chart-3)', 'var(--color-chart-4)',
            'var(--color-chart-5)', 'var(--color-chart-6)', 'var(--color-chart-7)', 'var(--color-chart-8)'];

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-timeline reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<h2 class="sec-title">' . $title . '</h2>'
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $this->e($c['mac_title'] ?? 'Процесс') . '</div></div>'
            . '<div class="mac-body">'
            . '<div class="tl-wrap" data-timeline="' . $id . '">'
            . '<div class="tl-line"></div>'
            . '<div class="tl-line-fill"></div>';

        foreach ($items as $i => $it) {
            $color = $this->e($it['color'] ?? $colors[$i % count($colors)]);
            $stepRaw = isset($it['step']) && $it['step'] !== '' ? (string)$it['step'] : (string)($i + 1);
            $step = $this->e('Шаг ' . $stepRaw);
            $itTitle = $this->e($it['title'] ?? '');
            $summary = $this->e($it['summary'] ?? '');
            $detail = $this->e($it['detail'] ?? '');
            $meta = $this->e($it['meta'] ?? '');

            $h .= '<div class="tl-item" style="--tl-c:' . $color . '">'
                . '<div class="tl-dot"></div>'
                . '<div class="tl-card">'
                . '<div class="tl-step" style="color:' . $color . '">' . $step . '</div>'
                . '<div class="tl-title">' . $itTitle . '</div>'
                . '<p class="tl-summary">' . $summary . '</p>'
                . '<div class="tl-expand"><div>'
                . '<div class="tl-detail">' . $detail . '</div>'
                . ($meta ? '<div class="tl-meta">⏱ ' . $meta . '</div>' : '')
                . '</div></div>'
                . '</div></div>';
        }

        $h .= '</div></div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    public function getCss(): string
    {
        return '.tl-wrap{position:relative;padding:8px 0 8px 44px;margin-top:12px}'
            . "\n" . '.tl-line{position:absolute;left:16px;top:0;bottom:0;width:2px;background:var(--color-border)}'
            . "\n" . '.tl-line-fill{position:absolute;left:16px;top:0;width:2px;height:0;background:linear-gradient(180deg,var(--color-chart-1,var(--color-accent)),var(--color-chart-2,var(--color-accent)),var(--color-chart-3,var(--color-accent)),var(--color-chart-4,var(--color-accent)));border-radius:2px;transition:height 2s cubic-bezier(.4,0,.2,1)}'
            . "\n" . '.tl-item{position:relative;padding:0 0 14px;opacity:0;transform:translateX(-16px);transition:all .5s ease;cursor:pointer}'
            . "\n" . '.tl-item.is-shown{opacity:1;transform:translateX(0)}'
            . "\n" . '.tl-dot{position:absolute;left:-36px;top:8px;width:18px;height:18px;border-radius:50%;border:3px solid var(--tl-c,var(--color-accent));background:var(--color-bg);transition:all .4s;z-index:2}'
            . "\n" . '.tl-item.is-shown .tl-dot{background:var(--tl-c,var(--color-accent));box-shadow:0 0 0 4px var(--color-accent-soft)}'
            . "\n" . '.tl-item.is-active .tl-dot{animation:pulseRing 1.5s infinite;--pulse-c:var(--tl-c,var(--color-accent))}'
            . "\n" . '.tl-card{padding:16px 20px;border-radius:var(--radius-md);background:var(--color-surface);border:1px solid var(--color-border);transition:all .3s}'
            . "\n" . '.tl-item.is-active .tl-card{border-color:var(--tl-c,var(--color-accent));box-shadow:0 4px 20px rgba(0,0,0,.06)}'
            . "\n" . '.tl-step{font-family:var(--type-font-heading);font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:3px}'
            . "\n" . '.tl-title{font-family:var(--type-font-heading);font-size:15px;font-weight:700;color:var(--color-text);margin-bottom:2px}'
            . "\n" . '.tl-summary{font-size:13px;color:var(--color-text-3);line-height:1.5;margin:0}'
            . "\n" . '.tl-expand{display:grid;grid-template-rows:0fr;transition:grid-template-rows .4s ease;overflow:hidden}'
            . "\n" . '.tl-item.is-active .tl-expand{grid-template-rows:1fr}'
            . "\n" . '.tl-expand>div{overflow:hidden}'
            . "\n" . '.tl-detail{padding-top:10px;font-size:13px;color:var(--color-text-2);line-height:1.6;border-top:1px solid var(--color-border);margin-top:10px}'
            . "\n" . '.tl-meta{font-size:11px;color:var(--color-text-3);font-family:var(--type-font-heading);font-weight:600;margin-top:6px;opacity:.6}'
            . "\n" . '@media(max-width:700px){'
            .     '.tl-wrap{padding-left:32px}'
            . '}';
    }

    public function getJs(): string
    {
        return '(function(){'
            . 'document.querySelectorAll("[data-timeline]").forEach(function(wrap){'
            . 'var items=wrap.querySelectorAll(".tl-item");var fill=wrap.querySelector(".tl-line-fill");var active=-1;'
            . 'items.forEach(function(it,i){it.addEventListener("click",function(){'
            . 'if(active===i){it.classList.remove("is-active");active=-1}else{'
            . 'items.forEach(function(x){x.classList.remove("is-active")});it.classList.add("is-active");active=i}})});'
            . 'new IntersectionObserver(function(e){e.forEach(function(x){if(x.isIntersecting){items.forEach(function(it,i){'
            . 'setTimeout(function(){it.classList.add("is-shown");if(fill)fill.style.height=((i+1)/items.length*100)+"%"},i*220)})}})},{threshold:.12}).observe(wrap)'
            . '});'
            . '})();';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Этапы';
    }
}
