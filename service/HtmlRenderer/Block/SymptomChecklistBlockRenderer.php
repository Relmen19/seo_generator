<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class SymptomChecklistBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $c, string $id): string
    {
        $title = $this->e($c['title'] ?? 'Проверьте симптомы');
        $sub   = $this->e($c['subtitle'] ?? '');
        $items = $c['items'] ?? [];
        $thresholds = $c['thresholds'] ?? [];
        $ctaText = $c['cta_text'] ?? '';
        $ctaKey  = $c['cta_link_key'] ?? '';
        $jsonData = json_encode(['items' => $items, 'thresholds' => $thresholds], JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-symcheck reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<h2 class="sec-title">' . $title . '</h2>'
            . ($sub ? '<p class="sec-desc">' . $sub . '</p>' : '')
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">Тест</div></div>'
            . '<div class="mac-body">'
            . '<div class="sc-wrap" data-symcheck="' . $id . '">'
            . '<div class="sc-progress"><div class="sc-progress-text">Отмечено: <span class="sc-count">0</span></div>'
            . '<div class="bar-track"><div class="sc-bar bar-fill" style="background:var(--blue);width:0"></div></div></div>'
            . '<div class="sc-items">';
        $groups = [];
        foreach ($items as $i => $it) {
            $g = $it['group'] ?? 'другие';
            $groups[$g][] = ['idx' => $i, 'item' => $it];
        }
        foreach ($groups as $gName => $gItems) {
            $h .= '<div class="sc-group"><div class="sc-group-label">' . $this->e(mb_convert_case($gName, MB_CASE_TITLE, 'UTF-8')) . '</div>';
            foreach ($gItems as $gi) {
                $w = (int)($gi['item']['weight'] ?? 1);
                $h .= '<label class="sc-item" data-weight="' . $w . '">'
                    . '<input type="checkbox" class="sc-check">'
                    . '<span class="sc-checkmark"></span>'
                    . '<span class="sc-text">' . $this->e($gi['item']['text'] ?? '') . '</span>'
                    . ($w >= 3 ? '<span class="sc-badge-important">!</span>' : '')
                    . '</label>';
            }
            $h .= '</div>';
        }
        $h .= '</div>'
            . '<div class="sc-result" style="display:none">'
            . '<div class="sc-result-icon"></div>'
            . '<div class="sc-result-label"></div>'
            . '<div class="sc-result-text"></div>'
            . '</div>';
        if ($ctaText) {
            $h .= '<div class="sc-cta" style="display:none"><a class="btn-primary" href="{{link:' . $this->e($ctaKey) . '}}">' . $this->e($ctaText) . '</a></div>';
        }
        $h .= '<script type="application/json" class="sc-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '</div></div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    public function getCss(): string
    {
        return '.sc-wrap{max-width:560px;margin:0 auto}'
            . "\n" . '.sc-progress{margin-bottom:20px}'
            . "\n" . '.sc-progress-text{font-size:13px;color:var(--muted);font-weight:600;margin-bottom:6px}'
            . "\n" . '.sc-count{color:var(--blue);font-family:var(--fh);font-weight:900}'
            . "\n" . '.sc-bar{transition:width .5s ease}'
            . "\n" . '.sc-group{margin-bottom:16px}'
            . "\n" . '.sc-group-label{font-family:var(--fh);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:var(--muted);margin-bottom:8px}'
            . "\n" . '.sc-item{display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:12px;cursor:pointer;transition:all .2s;border:1px solid transparent;margin-bottom:4px}'
            . "\n" . '.sc-item:hover{background:var(--blue-light);border-color:rgba(37,99,235,.12)}'
            . "\n" . '.sc-check{display:none}'
            . "\n" . '.sc-checkmark{width:22px;height:22px;border-radius:7px;border:2px solid var(--border);flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:all .25s;font-size:12px;color:transparent}'
            . "\n" . '.sc-check:checked~.sc-checkmark{background:var(--blue);border-color:var(--blue);color:#fff}'
            . "\n" . '.sc-check:checked~.sc-checkmark::after{content:"✓"}'
            . "\n" . '.sc-text{font-size:14px;color:var(--slate);line-height:1.4}'
            . "\n" . '.sc-badge-important{background:var(--red);color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:100px}'
            . "\n" . '.sc-result{padding:20px;border-radius:16px;text-align:center;animation:fadeInUp .5s ease;margin-top:20px}'
            . "\n" . '.sc-result-icon{font-size:2.5rem;margin-bottom:6px}'
            . "\n" . '.sc-result-label{font-family:var(--fh);font-size:1.3rem;font-weight:900;margin-bottom:4px}'
            . "\n" . '.sc-result-text{font-size:14px;color:var(--slate);line-height:1.6}'
            . "\n" . '.sc-cta{text-align:center;margin-top:16px}'
            ;
    }

    public function getJs(): string
    {
        return '(function(){'
            . 'document.querySelectorAll("[data-symcheck]").forEach(function(wrap){'
            . 'var jsonEl=wrap.querySelector(".sc-data");if(!jsonEl)return;'
            . 'var cfg={};try{cfg=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'var thresholds=cfg.thresholds||[];var items=cfg.items||[];'
            . 'var checks=wrap.querySelectorAll(".sc-check");var countEl=wrap.querySelector(".sc-count");'
            . 'var bar=wrap.querySelector(".sc-bar");var result=wrap.querySelector(".sc-result");'
            . 'var rIcon=result.querySelector(".sc-result-icon");var rLabel=result.querySelector(".sc-result-label");var rText=result.querySelector(".sc-result-text");'
            . 'var cta=wrap.querySelector(".sc-cta");'
            . 'var maxScore=items.reduce(function(s,it){return s+(it.weight||1)},0);'
            . 'function update(){'
            . 'var score=0,count=0;checks.forEach(function(ch,i){if(ch.checked){count++;score+=items[i]?items[i].weight||1:1}});'
            . 'countEl.textContent=count;bar.style.width=Math.round(score/maxScore*100)+"%";'
            . 'var th=thresholds.filter(function(t){return score>=t.min&&score<=t.max});'
            . 'if(th.length&&count>0){var t=th[0];rLabel.textContent=t.label||"";rLabel.style.color=t.color||"var(--dark)";'
            . 'rText.textContent=t.text||"";rIcon.textContent=score>=7?"🚨":score>=3?"⚠️":"✅";'
            . 'result.style.display="block";result.style.borderColor=t.color||"var(--border)";'
            . 'if(cta&&score>=3)cta.style.display="block"}else{result.style.display="none";if(cta)cta.style.display="none"}}'
            . 'checks.forEach(function(ch){ch.addEventListener("change",update)})});'
            . '})();';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Чеклист симптомов';
    }
}
