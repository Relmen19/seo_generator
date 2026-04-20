<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class MiniCalculatorBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $c, string $id): string
    {
        $title = $this->e($c['title'] ?? 'Калькулятор');
        $desc  = $this->e($c['description'] ?? '');
        $inputs  = $c['inputs'] ?? [];
        $results = $c['results'] ?? [];
        $fDesc   = $this->e($c['formula_description'] ?? '');
        $disclaimer = $this->e($c['disclaimer'] ?? '');
        $jsonData = json_encode(['inputs' => $inputs, 'results' => $results], JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-mcalc reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<h2 class="sec-title">' . $title . '</h2>'
            . ($desc ? '<p class="sec-desc">' . $desc . '</p>' : '')
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $this->e($c['mac_title'] ?? 'Расчёт') . '</div></div>'
            . '<div class="mac-body">'
            . '<div class="mc-wrap" data-mcalc="' . $id . '">'
            . '<div class="mc-inputs">';
        foreach ($inputs as $inp) {
            $key  = $this->e($inp['key'] ?? '');
            $lbl  = $this->e($inp['label'] ?? '');
            $type = $inp['type'] ?? 'number';
            $showIf = !empty($inp['show_if']) ? ' data-show-if-key="' . $this->e($inp['show_if']['key'] ?? '') . '" data-show-if-val="' . $this->e($inp['show_if']['value'] ?? '') . '"' : '';

            $h .= '<div class="mc-field"' . $showIf . '><label class="mc-label">' . $lbl . '</label>';
            if ($type === 'select') {
                $h .= '<select class="mc-select" data-key="' . $key . '">';
                foreach ($inp['options'] ?? [] as $opt) {
                    $h .= '<option value="' . $this->e($opt['value'] ?? '') . '">' . $this->e($opt['label'] ?? '') . '</option>';
                }
                $h .= '</select>';
            } else {
                $unit = $this->e($inp['unit'] ?? '');
                $mn = $inp['min'] ?? '';
                $mx = $inp['max'] ?? '';
                $h .= '<div class="mc-input-wrap"><input type="number" class="mc-input" data-key="' . $key . '"'
                    . ' placeholder="' . $this->e($inp['placeholder'] ?? '') . '"'
                    . ($mn !== '' ? ' min="' . (int)$mn . '"' : '')
                    . ($mx !== '' ? ' max="' . (int)$mx . '"' : '')
                    . '>'
                    . ($unit ? '<span class="mc-unit">' . $unit . '</span>' : '')
                    . '</div>';
            }
            $h .= '</div>';
        }
        $h .= '</div>'
            . '<button class="mc-btn">Рассчитать</button>'
            . '<div class="mc-result" style="display:none">'
            . '<div class="mc-result-value"></div>'
            . '<div class="mc-result-text"></div>'
            . '</div>'
            . ($fDesc ? '<div class="mc-formula-desc">' . $fDesc . '</div>' : '')
            . ($disclaimer ? '<div class="mc-disclaimer">' . $disclaimer . '</div>' : '')
            . '<script type="application/json" class="mc-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '</div></div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    public function getCss(): string
    {
        return '.mc-wrap{max-width:520px;margin:0 auto}'
            . "\n" . '.mc-inputs{display:grid;gap:14px;margin-bottom:18px}'
            . "\n" . '.mc-field{display:grid;gap:4px}'
            . "\n" . '.mc-label{font-family:var(--fh);font-size:13px;font-weight:600;color:var(--dark)}'
            . "\n" . '.mc-select,.mc-input{padding:12px 16px;border:2px solid var(--border);border-radius:10px;font-size:15px;color:var(--dark);background:var(--white);font-family:var(--fb);transition:border-color .2s;outline:none;width:100%;box-sizing:border-box}'
            . "\n" . '[data-theme="dark"] .mc-select,[data-theme="dark"] .mc-input{background:rgba(255,255,255,.05);color:var(--dark)}'
            . "\n" . '.mc-select:focus,.mc-input:focus{border-color:var(--blue)}'
            . "\n" . '.mc-input-wrap{position:relative}'
            . "\n" . '.mc-unit{position:absolute;right:14px;top:50%;transform:translateY(-50%);font-size:13px;color:var(--muted);font-weight:500}'
            . "\n" . '.mc-btn{width:100%;padding:14px;border:none;border-radius:12px;background:var(--blue);color:#fff;font-family:var(--fh);font-size:15px;font-weight:700;cursor:pointer;transition:all .2s}'
            . "\n" . '.mc-btn:hover{background:var(--blue-dark);transform:translateY(-1px);box-shadow:0 4px 16px rgba(37,99,235,.25)}'
            . "\n" . '.mc-result{padding:20px;border-radius:16px;background:var(--blue-light);text-align:center;margin-top:18px;animation:fadeInUp .5s ease}'
            . "\n" . '.mc-result-value{font-family:var(--fh);font-size:2rem;font-weight:900;color:var(--blue)}'
            . "\n" . '.mc-result-text{font-size:14px;color:var(--slate);margin-top:4px}'
            . "\n" . '.mc-formula-desc{font-size:11.5px;color:var(--muted);text-align:center;margin-top:12px}'
            . "\n" . '.mc-disclaimer{font-size:11.5px;color:var(--muted);text-align:center;margin-top:8px;font-style:italic}'
            ;
    }

    public function getJs(): string
    {
        return '(function(){'
            . 'document.querySelectorAll("[data-mcalc]").forEach(function(wrap){'
            . 'var jsonEl=wrap.querySelector(".mc-data");if(!jsonEl)return;'
            . 'var cfg={};try{cfg=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'var results=cfg.results||[];var inputs=cfg.inputs||[];'
            . 'var btn=wrap.querySelector(".mc-btn");var resultEl=wrap.querySelector(".mc-result");'
            . 'var rVal=resultEl.querySelector(".mc-result-value");var rText=resultEl.querySelector(".mc-result-text");'
            . 'var fields=wrap.querySelectorAll("[data-key]");'
            . 'var showIfFields=wrap.querySelectorAll("[data-show-if-key]");'
            . 'function updateVisibility(){'
            . 'showIfFields.forEach(function(f){'
            . 'var key=f.dataset.showIfKey;var val=f.dataset.showIfVal;'
            . 'var src=wrap.querySelector("[data-key=\\""+key+"\\"]");'
            . 'if(src){f.style.display=src.value===val?"grid":"none"}})}'
            . 'fields.forEach(function(f){f.addEventListener("change",updateVisibility)});updateVisibility();'
            . 'btn.addEventListener("click",function(){'
            . 'var vals={};fields.forEach(function(f){vals[f.dataset.key]=f.value});'
            . 'var match=results.find(function(r){'
            . 'var cond=r.condition||"";var parts=cond.split("&&");'
            . 'return parts.every(function(p){p=p.trim();var m=p.match(/^(\\w+)=(.+)$/);if(!m)return false;return vals[m[1]]===m[2]})});'
            . 'if(match){rVal.textContent=match.value||"";rText.textContent=match.text||"";resultEl.style.display="block"}else{rVal.textContent="—";rText.textContent="Заполните все поля";resultEl.style.display="block"}})});'
            . '})();';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Калькулятор';
    }
}
