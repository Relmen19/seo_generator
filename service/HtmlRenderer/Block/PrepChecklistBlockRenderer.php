<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class PrepChecklistBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $c, string $id): string
    {
        $title = $this->e($c['title'] ?? 'Подготовка');
        $sub   = $this->e($c['subtitle'] ?? '');
        $sections = $c['sections'] ?? [];
        $totalItems = 0;
        foreach ($sections as $s) $totalItems += count($s['items'] ?? []);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-prepcheck reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<h2 class="sec-title">' . $title . '</h2>'
            . ($sub ? '<p class="sec-desc">' . $sub . '</p>' : '')
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">Чек-лист</div></div>'
            . '<div class="mac-body">'
            . '<div class="pc-wrap" data-prepcheck="' . $id . '" data-total="' . $totalItems . '">'
            . '<div class="pc-progress-row"><span class="pc-progress-text">Готово <b class="pc-done">0</b> из <b>' . $totalItems . '</b></span>'
            . '<div class="bar-track"><div class="pc-bar bar-fill" style="background:var(--green);width:0"></div></div></div>'
            . '<div class="pc-milestone" style="display:none"></div>';
        foreach ($sections as $si => $sec) {
            $icon = $this->e($sec['icon'] ?? '📋');
            $name = $this->e($sec['name'] ?? '');
            $h .= '<div class="pc-section">'
                . '<div class="pc-section-header"><span class="pc-section-icon">' . $icon . '</span><span class="pc-section-name">' . $name . '</span></div>'
                . '<div class="pc-section-items">';
            foreach ($sec['items'] ?? [] as $it) {
                $imp = !empty($it['important']);
                $h .= '<label class="pc-item' . ($imp ? ' pc-item--important' : '') . '">'
                    . '<input type="checkbox" class="pc-check">'
                    . '<span class="pc-checkmark"></span>'
                    . '<span class="pc-text">' . $this->e($it['text'] ?? '') . '</span>'
                    . '</label>';
            }
            $h .= '</div></div>';
        }
        $h .= '<div class="pc-confetti" style="display:none">🎉 Вы готовы!</div>'
            . '</div></div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    public function getCss(): string
    {
        return '.pc-wrap{max-width:560px;margin:0 auto}'
            . "\n" . '.pc-progress-row{display:flex;align-items:center;gap:12px;margin-bottom:20px}'
            . "\n" . '.pc-progress-text{font-size:13px;color:var(--muted);font-weight:500;white-space:nowrap}'
            . "\n" . '.pc-progress-text b{color:var(--dark);font-family:var(--fh)}'
            . "\n" . '.pc-bar{transition:width .5s ease}'
            . "\n" . '.pc-section{margin-bottom:20px}'
            . "\n" . '.pc-section-header{display:flex;align-items:center;gap:10px;padding:10px 0;font-family:var(--fh);font-size:14px;font-weight:700;color:var(--dark)}'
            . "\n" . '.pc-section-icon{font-size:1.3em}'
            . "\n" . '.pc-item{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:10px;cursor:pointer;transition:all .2s;margin-bottom:3px}'
            . "\n" . '.pc-item:hover{background:var(--blue-light)}'
            . "\n" . '.pc-item--important{border-left:3px solid var(--warn)}'
            . "\n" . '.pc-check{display:none}'
            . "\n" . '.pc-checkmark{width:20px;height:20px;border-radius:6px;border:2px solid var(--border);flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:all .25s;font-size:11px;color:transparent}'
            . "\n" . '.pc-check:checked~.pc-checkmark{background:var(--green);border-color:var(--green);color:#fff;transform:scale(1.15)}'
            . "\n" . '.pc-check:checked~.pc-checkmark::after{content:"✓"}'
            . "\n" . '.pc-check:checked~.pc-text{text-decoration:line-through;opacity:.5}'
            . "\n" . '.pc-text{font-size:14px;color:var(--slate);line-height:1.4}'
            . "\n" . '.pc-confetti{text-align:center;font-size:1.5rem;padding:16px;border-radius:14px;background:var(--green-light);color:var(--green);font-family:var(--fh);font-weight:700}'
            . "\n" . '.pc-milestone{text-align:center;font-size:14px;padding:10px 16px;border-radius:10px;background:var(--blue-light);color:var(--blue);font-family:var(--fh);font-weight:600;margin-top:12px}'
            ;
    }

    public function getJs(): string
    {
        return '(function(){'
            . 'document.querySelectorAll("[data-prepcheck]").forEach(function(wrap){'
            . 'var total=parseInt(wrap.dataset.total)||1;var checks=wrap.querySelectorAll(".pc-check");'
            . 'var doneEl=wrap.querySelector(".pc-done");var bar=wrap.querySelector(".pc-bar");'
            . 'var confetti=wrap.querySelector(".pc-confetti");var milestone=wrap.querySelector(".pc-milestone");'
            . 'var msgs=["","Хорошее начало! 💪","Уже половина! 👏","Почти готово! 🔥",""];'
            . 'function update(){'
            . 'var done=0;checks.forEach(function(ch){if(ch.checked)done++});'
            . 'var pct=Math.round(done/total*100);'
            . 'doneEl.textContent=done;bar.style.width=pct+"%";'
            . 'if(done===total&&confetti){confetti.style.display="block";confetti.style.animation="fadeInUp .5s ease";'
            . 'if(milestone)milestone.style.display="none"}'
            . 'else if(confetti){confetti.style.display="none"}'
            . 'if(milestone&&done<total){'
            . 'var mi=pct<25?0:pct<50?1:pct<75?2:3;'
            . 'if(msgs[mi]){milestone.textContent=msgs[mi];milestone.style.display="block";milestone.style.animation="fadeInUp .3s ease"}'
            . 'else{milestone.style.display="none"}}}'
            . 'checks.forEach(function(ch){ch.addEventListener("change",function(){'
            . 'var label=ch.closest(".pc-item");if(label&&ch.checked){label.style.transition="background .3s";label.style.background="rgba(22,163,74,.06)";'
            . 'setTimeout(function(){label.style.background=""},600)}'
            . 'update()})})});'
            . '})();';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Подготовка';
    }
}
