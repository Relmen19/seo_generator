<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class ComparisonTableBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $content, string $id): string
    {
        $c = $content;
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';
        $headers = $c['headers'] ?? [];
        $rows    = $c['rows'] ?? [];
        $title   = $this->e($c['title'] ?? 'Сравнение');

        /* Detect: if >= 3 columns and first column is a label -> tabbed card view */
        $useTabs = (count($headers) >= 3 && count($rows) >= 2);

        $h = '<section id="' . $id . '" class="block-comparison reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<h2 class="sec-title">' . $title . '</h2>';

        /* Optional description */
        $desc = trim($c['description'] ?? '');
        if ($desc !== '') {
            $h .= '<p class="cmp-desc">' . $this->e($desc) . '</p>';
        }

        if ($useTabs) {
            /* Tabbed Cards */
            $tabHeaders = array_slice($headers, 1);
            $rowLabel   = $headers[0] ?? '';

            /* Tab navigation */
            $h .= '<div class="cmp-tabs" data-tabs="' . $id . '">'
                . '<div class="cmp-tabs-nav">';
            foreach ($tabHeaders as $ti => $th) {
                $label = $this->e(is_string($th) ? $th : ($th['label'] ?? ''));
                $h .= '<button class="cmp-tab-btn' . ($ti === 0 ? ' is-active' : '') . '" data-tab="' . $ti . '">' . $label . '</button>';
            }
            $h .= '</div>';

            /* Tab panels */
            foreach ($tabHeaders as $ti => $th) {
                $h .= '<div class="cmp-tab-panel' . ($ti === 0 ? ' is-active' : '') . '" data-panel="' . $ti . '">';
                $h .= '<div class="cmp-cards-grid">';
                foreach ($rows as $row) {
                    $cells = is_array($row) ? (isset($row[0]) ? $row : array_values($row)) : [$row];
                    $feature = $this->e((string)($cells[0] ?? ''));
                    $value   = (string)($cells[$ti + 1] ?? '');
                    $isYes   = in_array($value, ['✓', 'true', '1', 'да', 'Да'], true);
                    $isNo    = in_array($value, ['✗', 'false', '0', 'нет', 'Нет'], true);
                    $valClass = $isYes ? ' cmp-card-val--yes' : ($isNo ? ' cmp-card-val--no' : '');

                    $displayVal = $isYes ? '✓' : ($isNo ? '✗' : $this->e($value));

                    $h .= '<div class="cmp-card">'
                        . '<div class="cmp-card-feature">' . $feature . '</div>'
                        . '<div class="cmp-card-val' . $valClass . '">' . $displayVal . '</div>'
                        . '</div>';
                }
                $h .= '</div></div>';
            }
            $h .= '</div>';

            /* Desktop carousel below tabs */
            $h .= '<div class="cmp-carousel" data-carousel="' . $id . '">'
                . '<div class="cmp-carousel-track">';
            foreach ($tabHeaders as $ti => $th) {
                $label = $this->e(is_string($th) ? $th : ($th['label'] ?? ''));
                $yesCount = 0;
                $totalRows = count($rows);
                foreach ($rows as $row) {
                    $cells = is_array($row) ? (isset($row[0]) ? $row : array_values($row)) : [$row];
                    $v = (string)($cells[$ti + 1] ?? '');
                    if (in_array($v, ['✓', 'true', '1', 'да', 'Да'], true)) $yesCount++;
                }
                $h .= '<div class="cmp-carousel-card">'
                    . '<div class="cmp-carousel-card-title">' . $label . '</div>'
                    . '<div class="cmp-carousel-card-stat">' . $yesCount . '/' . $totalRows . '</div>'
                    . '<div class="cmp-carousel-card-label">совпадений</div>'
                    . '</div>';
            }
            $h .= '</div>';
            if (count($tabHeaders) > 3) {
                $h .= '<div class="cmp-carousel-nav">'
                    . '<button class="cmp-carousel-btn cmp-carousel-btn--prev" aria-label="Назад">←</button>'
                    . '<div class="cmp-carousel-dots"></div>'
                    . '<button class="cmp-carousel-btn cmp-carousel-btn--next" aria-label="Вперед">→</button>'
                    . '</div>';
            }
            $h .= '</div>';

        } else {
            /* Fallback: Premium responsive table */
            $h .= '<div class="mac-window">'
                . '<div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
                . '<div class="mac-title">' . $title . '</div></div>'
                . '<div class="mac-body">'
                . '<div class="table-wrap"><table class="cmp-table premium-table">';

            $h .= '<thead><tr>';
            foreach ($headers as $hd) {
                $h .= '<th>' . $this->e(is_string($hd) ? $hd : ($hd['label'] ?? '')) . '</th>';
            }
            $h .= '</tr></thead><tbody>';
            foreach ($rows as $row) {
                $h .= '<tr>';
                $cells = is_array($row) ? (isset($row[0]) ? $row : array_values($row)) : [$row];
                foreach ($cells as $ci => $cell) {
                    $v   = (string)$cell;
                    $cls = '';
                    $dlabel = isset($headers[$ci]) ? ' data-label="' . $this->e(is_string($headers[$ci]) ? $headers[$ci] : ($headers[$ci]['label'] ?? '')) . '"' : '';
                    if (in_array($v, ['✓', 'true', '1'], true)) { $v = '✓'; $cls = ' class="cell-yes"'; }
                    elseif (in_array($v, ['✗', 'false', '0'], true)) { $v = '✗'; $cls = ' class="cell-no"'; }
                    $h .= '<td' . $cls . $dlabel . '>' . $this->e($v) . '</td>';
                }
                $h .= '</tr>';
            }
            $h .= '</tbody></table></div></div></div>';
        }

        return $h . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
    }

    public function getCss(): string
    {
        return '.block-comparison { }'
            . "\n" . '.block-comparison .sec-title { margin-bottom:24px }'
            . "\n" . '.cmp-desc { font-size:14px; color:var(--color-text-3); line-height:1.6; margin-bottom:24px; max-width:680px }'
            . "\n" . '.cmp-tabs { margin-bottom:32px }'
            . "\n" . '.cmp-tabs-nav { display:flex; gap:4px; padding:4px; background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-md); margin-bottom:20px; overflow-x:auto; -webkit-overflow-scrolling:touch }'
            . "\n" . '.cmp-tab-btn { flex:1 1 auto; min-width:120px; max-width:260px; padding:10px 16px; border:none; background:transparent; font-family:var(--type-font-heading); font-size:13px; font-weight:600; color:var(--color-text-3); cursor:pointer; border-radius:var(--radius-md); transition:all .25s; white-space:normal; line-height:1.3; text-align:center; word-break:break-word; hyphens:auto }'
            . "\n" . '.cmp-tab-btn:hover { color:var(--color-text); background:var(--color-accent-soft) }'
            . "\n" . '.cmp-tab-btn.is-active { color:var(--color-accent); background:var(--color-bg); box-shadow:0 2px 8px rgba(0,0,0,.06); border:1px solid var(--color-border) }'
            . "\n" . '.cmp-tab-panel { display:none; animation:fadeUp .35s ease both }'
            . "\n" . '.cmp-tab-panel.is-active { display:block }'
            . "\n" . '.cmp-cards-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:12px }'
            . "\n" . '.cmp-card { padding:18px 20px; border-radius:var(--radius-md); background:var(--color-surface); border:1px solid var(--color-border); transition:all .25s; display:flex; justify-content:space-between; align-items:center; gap:12px; min-width:0 }'
            . "\n" . '.cmp-card:hover { border-color:var(--color-accent); box-shadow:0 4px 16px rgba(0,0,0,.06); transform:translateY(-2px) }'
            . "\n" . '.cmp-card-feature { font-size:14px; color:var(--color-text); font-weight:500; min-width:0; word-break:break-word }'
            . "\n" . '.cmp-card-val { font-family:var(--type-font-heading); font-weight:700; font-size:15px; color:var(--color-text-2); flex-shrink:0; max-width:55%; text-align:right; word-break:break-word }'
            . "\n" . '.cmp-card-val--yes { color:var(--color-success); font-size:1.3em }'
            . "\n" . '.cmp-card-val--no { color:var(--color-danger); font-size:1.3em }'
            . "\n" . '.cmp-carousel { margin-top:12px; overflow:hidden; position:relative }'
            . "\n" . '.cmp-carousel-track { display:flex; gap:12px; overflow-x:auto; scroll-snap-type:x mandatory; -webkit-overflow-scrolling:touch; scrollbar-width:none; padding:4px 0 12px }'
            . "\n" . '.cmp-carousel-track::-webkit-scrollbar { display:none }'
            . "\n" . '.cmp-carousel-card { flex:0 0 auto; width:180px; scroll-snap-align:start; padding:22px 20px; border-radius:var(--radius-lg); background:var(--color-surface); border:1px solid var(--color-border); text-align:center; transition:all .3s; cursor:pointer }'
            . "\n" . '.cmp-carousel-card:hover { border-color:var(--color-accent); box-shadow:0 8px 28px rgba(0,0,0,.06); transform:translateY(-3px) }'
            . "\n" . '.cmp-carousel-card-title { font-family:var(--type-font-heading); font-size:15px; font-weight:700; color:var(--color-text); margin-bottom:12px }'
            . "\n" . '.cmp-carousel-card-stat { font-family:var(--type-font-heading); font-size:2rem; font-weight:900; color:var(--color-accent); letter-spacing:-1px; line-height:1 }'
            . "\n" . '.cmp-carousel-card-label { font-size:12px; color:var(--color-text-3); margin-top:6px }'
            . "\n" . '.cmp-carousel-nav { display:flex; align-items:center; justify-content:center; gap:12px; margin-top:12px }'
            . "\n" . '.cmp-carousel-btn { width:36px; height:36px; border:1px solid var(--color-border); background:var(--color-surface); border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:16px; color:var(--color-text-3); transition:all .2s }'
            . "\n" . '.cmp-carousel-btn:hover { border-color:var(--color-accent); color:var(--color-accent); background:var(--color-accent-soft) }'
            . "\n" . '.cmp-carousel-dots { display:flex; gap:6px }'
            . "\n" . '.cmp-carousel-dot { width:8px; height:8px; border-radius:50%; background:var(--color-border); transition:all .2s }'
            . "\n" . '.cmp-carousel-dot.is-active { background:var(--color-accent); transform:scale(1.2) }'
            . "\n" . '.cmp-table td.cell-yes { color:var(--color-success); font-weight:700; font-size:1.2em; text-align:center }'
            . "\n" . '.cmp-table td.cell-no { color:var(--color-danger); font-weight:700; font-size:1.2em; text-align:center }'
            . "\n" . '@media(max-width:768px) {'
            .     '.cmp-tabs-nav { flex-wrap:nowrap; overflow-x:auto; -webkit-overflow-scrolling:touch }'
            .     '.cmp-tab-btn { flex:0 0 auto; min-width:100px; max-width:200px; padding:8px 12px; font-size:12px }'
            .     '.cmp-cards-grid { grid-template-columns:1fr }'
            .     '.cmp-card { flex-wrap:wrap }'
            .     '.cmp-card-feature { width:100%; max-width:100% }'
            .     '.cmp-card-val { max-width:100%; text-align:left }'
            .     '.cmp-carousel-card { width:150px }'
            .     '.cmp-carousel-card-stat { font-size:1.6rem }'
            . '}';
    }

    public function getJs(): string
    {
        return '(function(){'
            . 'document.querySelectorAll("[data-tabs]").forEach(function(wrap){'
            . 'var btns=wrap.querySelectorAll(".cmp-tab-btn");'
            . 'var panels=wrap.querySelectorAll(".cmp-tab-panel");'
            . 'btns.forEach(function(btn){'
            . 'btn.addEventListener("click",function(){'
            . 'var idx=btn.dataset.tab;'
            . 'btns.forEach(function(b){b.classList.remove("is-active")});'
            . 'panels.forEach(function(p){p.classList.remove("is-active")});'
            . 'btn.classList.add("is-active");'
            . 'var panel=wrap.querySelector("[data-panel=\""+idx+"\"]");'
            . 'if(panel)panel.classList.add("is-active")'
            . '})})});'
            . '})();'
            . '(function(){'
            . 'document.querySelectorAll("[data-carousel]").forEach(function(wrap){'
            . 'var track=wrap.querySelector(".cmp-carousel-track");'
            . 'if(!track)return;'
            . 'var prev=wrap.querySelector(".cmp-carousel-btn--prev");'
            . 'var next=wrap.querySelector(".cmp-carousel-btn--next");'
            . 'var step=200;'
            . 'if(prev)prev.addEventListener("click",function(){track.scrollBy({left:-step,behavior:"smooth"})});'
            . 'if(next)next.addEventListener("click",function(){track.scrollBy({left:step,behavior:"smooth"})});'
            . 'var cards=track.querySelectorAll(".cmp-carousel-card");'
            . 'cards.forEach(function(card,i){'
            . 'card.addEventListener("click",function(){'
            . 'var tabsWrap=wrap.closest("section").querySelector("[data-tabs]");'
            . 'if(!tabsWrap)return;'
            . 'var btn=tabsWrap.querySelector(".cmp-tab-btn[data-tab=\""+i+"\"]");'
            . 'if(btn)btn.click();'
            . 'tabsWrap.scrollIntoView({behavior:"smooth",block:"nearest"})'
            . '})'
            . '});'
            . '})})();';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Сравнение';
    }
}
