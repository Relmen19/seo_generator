<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class ValueCheckerBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $c, string $id): string
    {
        $title = $this->e($c['title'] ?? 'Проверьте свой результат');
        $desc  = $this->e($c['description'] ?? '');
        $label = $this->e($c['input_label'] ?? 'Значение');
        $ph    = $this->e($c['input_placeholder'] ?? '');
        $disclaimer = $this->e($c['disclaimer'] ?? '');
        $zones = $c['zones'] ?? [];
        $jsonData = json_encode($zones, JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        /* Calculate proportional flex weights for zones */
        $totalRange = 0;
        foreach ($zones as $z) {
            $totalRange += max(1, ($z['to'] ?? 0) - ($z['from'] ?? 0));
        }

        $h = '<section id="' . $id . '" class="block-vcheck reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<h2 class="sec-title">' . $title . '</h2>'
            . ($desc ? '<p class="sec-desc">' . $desc . '</p>' : '')
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $label . '</div></div>'
            . '<div class="mac-body">'
            . '<div class="vc-wrap" data-vcheck="' . $id . '">'
            . '<div class="vc-input-row">'
            . '<input type="number" class="vc-input" placeholder="' . $ph . '" step="any">'
            . '<button class="vc-btn">Проверить</button>'
            . '</div>'
            . '<div class="vc-scale"><div class="vc-scale-track">';
        foreach ($zones as $z) {
            $range = max(1, ($z['to'] ?? 0) - ($z['from'] ?? 0));
            $flex = $totalRange > 0 ? round($range / $totalRange * 100, 2) : 1;
            $h .= '<div class="vc-zone" style="flex:' . $flex . ';background:' . $this->e($z['color'] ?? '#ccc') . '" title="' . $this->e($z['label'] ?? '') . '"></div>';
        }
        $h .= '</div><div class="vc-marker" style="display:none"><div class="vc-marker-dot"></div><div class="vc-marker-line"></div></div></div>'
            . '<div class="vc-result" style="display:none">'
            . '<div class="vc-result-icon"></div>'
            . '<div class="vc-result-label"></div>'
            . '<div class="vc-result-text"></div>'
            . '</div>'
            . ($disclaimer ? '<div class="vc-disclaimer">' . $disclaimer . '</div>' : '')
            . '<script type="application/json" class="vc-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '</div></div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    public function getCss(): string
    {
        return '.vc-wrap{max-width:520px;margin:0 auto}'
            . "\n" . '.vc-input-row{display:flex;gap:10px;margin-bottom:20px}'
            . "\n" . '.vc-input{flex:1;padding:14px 18px;border:2px solid var(--color-border);border-radius:12px;font-size:18px;font-family:var(--type-font-heading);font-weight:700;color:var(--color-text);background:var(--color-surface);transition:border-color .3s;outline:none}'
            . "\n" . '.vc-input:focus{border-color:var(--color-accent)}'
            . "\n" . '[data-theme="dark"] .vc-input{background:rgba(255,255,255,.05);color:var(--color-text)}'
            . "\n" . '.vc-btn{padding:14px 28px;border:none;border-radius:12px;background:var(--color-accent);color:#fff;font-family:var(--type-font-heading);font-size:15px;font-weight:700;cursor:pointer;transition:all .2s;white-space:nowrap}'
            . "\n" . '.vc-btn:hover{background:var(--color-accent);transform:translateY(-1px);box-shadow:0 4px 16px rgba(37,99,235,.25)}'
            . "\n" . '.vc-scale{position:relative;margin:24px 0}'
            . "\n" . '.vc-scale-track{display:flex;height:12px;border-radius:100px;overflow:hidden;gap:2px}'
            . "\n" . '.vc-zone{flex:1;transition:opacity .4s}'
            . "\n" . '.vc-marker{position:absolute;top:-6px;transition:left .6s cubic-bezier(.4,0,.2,1);z-index:2}'
            . "\n" . '.vc-marker-dot{width:24px;height:24px;border-radius:50%;background:var(--color-text);border:3px solid var(--color-bg);box-shadow:0 2px 8px rgba(0,0,0,.2);margin-left:-12px}'
            . "\n" . '.vc-marker-line{width:2px;height:18px;background:var(--color-text);margin:2px auto 0;opacity:.4}'
            . "\n" . '.vc-result{padding:20px;border-radius:16px;background:rgba(255,255,255,.5);backdrop-filter:blur(8px);border:1px solid var(--color-border);text-align:center;animation:fadeInUp .5s ease}'
            . "\n" . '[data-theme="dark"] .vc-result{background:rgba(255,255,255,.03)}'
            . "\n" . '.vc-result-icon{font-size:2.5rem;margin-bottom:6px}'
            . "\n" . '.vc-result-label{font-family:var(--type-font-heading);font-size:1.3rem;font-weight:900;margin-bottom:4px}'
            . "\n" . '.vc-result-text{font-size:14px;color:var(--color-text-2);line-height:1.6}'
            . "\n" . '.vc-disclaimer{font-size:11.5px;color:var(--color-text-3);text-align:center;margin-top:16px;font-style:italic}'
            . "\n" . '@keyframes fadeInUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}'
            . "\n" . '@media(max-width:700px){.vc-input-row{flex-direction:column}}'
            ;
    }

    public function getJs(): string
    {
        return '(function(){'
            . 'document.querySelectorAll("[data-vcheck]").forEach(function(wrap){'
            . 'var jsonEl=wrap.querySelector(".vc-data");if(!jsonEl)return;'
            . 'var zones=[];try{zones=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'var input=wrap.querySelector(".vc-input");var btn=wrap.querySelector(".vc-btn");'
            . 'var marker=wrap.querySelector(".vc-marker");var result=wrap.querySelector(".vc-result");'
            . 'var rIcon=result.querySelector(".vc-result-icon");var rLabel=result.querySelector(".vc-result-label");var rText=result.querySelector(".vc-result-text");'
            . 'var track=wrap.querySelector(".vc-scale-track");'
            . 'var globalMin=Infinity,globalMax=-Infinity;'
            . 'zones.forEach(function(z){if(z.from<globalMin)globalMin=z.from;if(z.to>globalMax)globalMax=z.to});'
            . 'function check(){'
            . 'var val=parseFloat(input.value);if(isNaN(val))return;'
            . 'var pct=Math.max(0,Math.min(100,(val-globalMin)/(globalMax-globalMin)*100));'
            . 'marker.style.display="block";marker.style.left=pct+"%";'
            . 'var zone=zones.find(function(z){return val>=z.from&&val<z.to})||zones[zones.length-1];'
            . 'rIcon.textContent=zone.icon||"";rLabel.textContent=(zone.label||"");rLabel.style.color=zone.color||"var(--color-text)";'
            . 'rText.textContent=zone.text||"";result.style.display="block";result.style.borderColor=zone.color||"var(--color-border)"}'
            . 'btn.addEventListener("click",check);input.addEventListener("keydown",function(e){if(e.key==="Enter")check()})});'
            . '})();';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Проверьте результат';
    }
}
