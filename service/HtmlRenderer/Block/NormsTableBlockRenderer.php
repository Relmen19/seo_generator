<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class NormsTableBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $content, string $id): string
    {
        $c = $content;
        $cap  = $this->e($c['caption'] ?? 'Показатели и нормы');
        $rows = $c['rows'] ?? [];
        /* New format: rows[].states is an array of 3-5 state objects */
        $hasStates = !empty($rows) && is_array($rows[0] ?? null) && !empty($rows[0]['states']);
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-norms reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $cap . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="mac-window">'
            . '<div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $cap . '</div></div>'
            . '<div class="mac-body">';

        if ($hasStates) {
            $stateStyles = [
                'very_low'     => ['bar' => 'linear-gradient(90deg,#991B1B,#EF4444)', 'badge' => '#FEE2E2', 'bc' => '#991B1B', 'icon' => '↓↓'],
                'critical_low' => ['bar' => 'linear-gradient(90deg,#991B1B,#EF4444)', 'badge' => '#FEE2E2', 'bc' => '#991B1B', 'icon' => '↓↓'],
                'low'          => ['bar' => 'linear-gradient(90deg,#F59E0B,#FCD34D)', 'badge' => '#FEF3C7', 'bc' => '#B45309', 'icon' => '↓'],
                'ok'           => ['bar' => 'linear-gradient(90deg,#16A34A,#4ADE80)', 'badge' => '#DCFCE7', 'bc' => '#16A34A', 'icon' => '✓'],
                'normal'       => ['bar' => 'linear-gradient(90deg,#16A34A,#4ADE80)', 'badge' => '#DCFCE7', 'bc' => '#16A34A', 'icon' => '✓'],
                'optimal'      => ['bar' => 'linear-gradient(90deg,#16A34A,#4ADE80)', 'badge' => '#DCFCE7', 'bc' => '#16A34A', 'icon' => '✓'],
                'high'         => ['bar' => 'linear-gradient(90deg,#F59E0B,#FCD34D)', 'badge' => '#FEF3C7', 'bc' => '#B45309', 'icon' => '↑'],
                'elevated'     => ['bar' => 'linear-gradient(90deg,#F59E0B,#FCD34D)', 'badge' => '#FEF3C7', 'bc' => '#B45309', 'icon' => '↑'],
                'very_high'    => ['bar' => 'linear-gradient(90deg,#991B1B,#EF4444)', 'badge' => '#FEE2E2', 'bc' => '#991B1B', 'icon' => '↑↑'],
            ];

            $h .= '<div class="norms-status-list">';
            foreach ($rows as $ri => $row) {
                if (!is_array($row)) continue;
                $name   = $this->e($row['name'] ?? '');
                $unit   = $this->e($row['unit'] ?? '');
                $states = $row['states'] ?? [];
                $active = max(0, min(count($states) - 1, (int)($row['active'] ?? 0)));

                /* Encode states JSON for JS */
                $statesJson = [];
                foreach ($states as $si => $st) {
                    $key   = $st['key'] ?? 'ok';
                    $style = $stateStyles[$key] ?? $stateStyles['ok'];
                    $statesJson[] = [
                        'key'   => $key,
                        'label' => $st['label'] ?? $key,
                        'range' => ($st['range'] ?? '') . ($unit ? ' ' . $unit : ''),
                        'pct'   => max(4, min(100, (int)($st['pct'] ?? 50))),
                        'desc'  => $st['description'] ?? '',
                        'bar'   => $style['bar'],
                        'badge' => $style['badge'],
                        'bc'    => $style['bc'],
                        'icon'  => $style['icon'],
                    ];
                }
                $jsonAttr = $this->e(json_encode($statesJson, JSON_UNESCAPED_UNICODE));
                $initState = $statesJson[$active] ?? $statesJson[0];

                /* Pills navigation (state selector) */
                $h .= '<div class="norm-card" data-norm-card data-states="' . $jsonAttr . '" data-active="' . $active . '">'
                    . '<div class="norm-card-header">'
                    . '<span class="norm-name">' . $name . '</span>'
                    . '<span class="norm-badge" style="background:' . $initState['badge'] . ';color:' . $initState['bc'] . '">'
                    . '<span class="norm-badge-icon">' . $this->e($initState['icon']) . '</span> '
                    . $this->e($initState['range'])
                    . '</span>'
                    . '</div>'
                    . '<div class="norm-pills">';
                foreach ($states as $si => $st) {
                    $key = $st['key'] ?? 'ok';
                    $stStyle = $stateStyles[$key] ?? $stateStyles['ok'];
                    $h .= '<button class="norm-pill' . ($si === $active ? ' is-active' : '') . '"'
                        . ' data-idx="' . $si . '"'
                        . ' style="--pill-c:' . $stStyle['bc'] . '"'
                        . '>' . $this->e($st['label'] ?? $key) . '</button>';
                }
                $h .= '</div>'
                    . '<div class="bar-track norm-bar-track"><div class="bar-fill norm-bar" style="background:' . $initState['bar'] . '" data-width="' . $initState['pct'] . '"></div></div>'
                    . '<div class="norm-desc">' . $this->e($initState['desc']) . '</div>'
                    . '</div>';
            }
            $h .= '</div>';

        } else {
            /* Plain table - premium responsive card/table hybrid */
            $columns = $c['columns'] ?? [];
            $h .= '<div class="table-wrap premium-table-wrap">';
            $h .= '<table class="premium-table">';
            $h .= '<thead><tr>';
            foreach ($columns as $col) {
                $h .= '<th>' . $this->e(is_array($col) ? ($col['label'] ?? $col['name'] ?? '') : $col) . '</th>';
            }
            $h .= '</tr></thead><tbody>';
            foreach ($rows as $row) {
                $h .= '<tr>';
                $cells = is_array($row) ? (isset($row[0]) ? $row : array_values($row)) : [$row];
                foreach ($cells as $ci => $cell) {
                    $dlabel = isset($columns[$ci])
                        ? ' data-label="' . $this->e(is_array($columns[$ci]) ? ($columns[$ci]['label'] ?? $columns[$ci]['name'] ?? '') : $columns[$ci]) . '"'
                        : '';
                    $h .= '<td' . $dlabel . '>' . $this->e((string)$cell) . '</td>';
                }
                $h .= '</tr>';
            }
            $h .= '</tbody></table></div>';
        }

        return $h . '</div></div><div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
    }

    public function getCss(): string
    {
        return '.block-norms { }'
            . "\n" . '.norms-status-list { display:flex; flex-direction:column; gap:10px }'
            . "\n" . '.norm-card { padding:20px 22px; border-radius:16px; background:rgba(255,255,255,.5); backdrop-filter:blur(10px); border:1px solid var(--color-border); transition:all .3s }'
            . "\n" . '[data-theme="dark"] .norm-card { background:rgba(255,255,255,.03) }'
            . "\n" . '.norm-card:hover { border-color:rgba(37,99,235,.18); box-shadow:0 4px 20px rgba(37,99,235,.05) }'
            . "\n" . '.norm-card-header { display:flex; justify-content:space-between; align-items:flex-start; gap:8px; margin-bottom:12px }'
            . "\n" . '.norm-name { color:var(--color-text); font-weight:700; font-size:15px; font-family:var(--type-font-heading); min-width:0; flex:1; line-height:1.3 }'
            . "\n" . '.norm-badge { font-size:12px; font-weight:700; padding:5px 14px; border-radius:100px; font-family:var(--type-font-heading); display:inline-flex; align-items:center; gap:5px; transition:all .3s; white-space:nowrap }'
            . "\n" . '.norm-badge-icon { font-size:11px; line-height:1 }'
            . "\n" . '.norm-pills { display:flex; gap:4px; margin-bottom:14px; flex-wrap:wrap }'
            . "\n" . '.norm-pill { padding:6px 14px; border:1px solid var(--color-border); background:transparent; border-radius:100px; font-family:var(--type-font-heading); font-size:11.5px; font-weight:600; color:var(--color-text-3); cursor:pointer; transition:all .25s; white-space:nowrap }'
            . "\n" . '.norm-pill:hover { border-color:var(--pill-c,var(--color-accent)); color:var(--pill-c,var(--color-accent)); background:rgba(37,99,235,.04) }'
            . "\n" . '.norm-pill.is-active { background:var(--pill-c,var(--color-accent)); color:#fff; border-color:var(--pill-c,var(--color-accent)); box-shadow:0 2px 8px rgba(0,0,0,.1) }'
            . "\n" . '.norm-bar-track { height:10px; border-radius:100px; background:var(--color-border); overflow:hidden; margin-bottom:10px }'
            . "\n" . '.norm-bar { height:100%; border-radius:100px; transition:width .6s cubic-bezier(.4,0,.2,1), background .4s ease }'
            . "\n" . '.norm-desc { font-size:13px; color:var(--color-text-3); line-height:1.5; min-height:20px; transition:opacity .3s ease; font-style:italic }'
            . "\n" . '.norm-desc.is-fading { opacity:0 }'
            . "\n" . '.premium-table-wrap { overflow-x:auto; border-radius:var(--radius-md) }'
            . "\n" . '.premium-table { width:100%; border-collapse:separate; border-spacing:0; font-size:.9rem }'
            . "\n" . '.premium-table thead { background:linear-gradient(135deg,var(--color-surface),#1a2744) }'
            . "\n" . '[data-theme="dark"] .premium-table thead { background:linear-gradient(135deg,#0a1628,#152040) }'
            . "\n" . '.premium-table th { padding:14px 18px; text-align:left; font-family:var(--type-font-heading); font-weight:600; font-size:.78rem; text-transform:uppercase; letter-spacing:.8px; color:rgba(255,255,255,.85); white-space:nowrap }'
            . "\n" . '.premium-table th:first-child { border-radius:var(--radius-md) 0 0 0 }'
            . "\n" . '.premium-table th:last-child { border-radius:0 var(--radius-md) 0 0 }'
            . "\n" . '.premium-table td { padding:13px 18px; border-bottom:1px solid var(--color-border); color:var(--color-text-2); font-size:.88rem; transition:background .15s }'
            . "\n" . '.premium-table tr:last-child td { border-bottom:none }'
            . "\n" . '.premium-table tr:nth-child(even) td { background:rgba(37,99,235,.02) }'
            . "\n" . '.premium-table tr:hover td { background:rgba(37,99,235,.06) }'
            . "\n" . '.premium-table td:first-child { font-weight:600; color:var(--color-text); font-family:var(--type-font-heading) }'
            . "\n" . '@media(max-width:768px) {'
            .     '.premium-table { border:0 }'
            .     '.premium-table thead { display:none }'
            .     '.premium-table tbody tr { display:block; margin-bottom:12px; padding:16px; border-radius:14px; background:rgba(255,255,255,.6); border:1px solid var(--color-border); backdrop-filter:blur(8px) }'
            .     '[data-theme="dark"] .premium-table tbody tr { background:rgba(255,255,255,.04) }'
            .     '.premium-table tbody td { display:flex; justify-content:space-between; align-items:center; padding:8px 4px; border-bottom:1px solid rgba(0,0,0,.04); font-size:.88rem }'
            .     '[data-theme="dark"] .premium-table tbody td { border-bottom-color:rgba(255,255,255,.04) }'
            .     '.premium-table tbody td:last-child { border-bottom:none }'
            .     '.premium-table tbody td:first-child { font-size:.95rem; font-weight:700; color:var(--color-text); padding-bottom:10px; border-bottom:1px solid var(--color-border) }'
            .     '.premium-table tbody td::before { content:attr(data-label); font-size:.78rem; font-weight:600; color:var(--color-text-3); text-transform:uppercase; letter-spacing:.5px; flex-shrink:0; margin-right:12px }'
            .     '.premium-table tbody td:first-child::before { display:none }'
            .     '.norm-pills { gap:3px }'
            .     '.norm-pill { padding:5px 10px; font-size:10.5px }'
            .     '.norm-card { padding:16px }'
            . '}'
            . "\n" . '@media(max-width:414px) {'
            .     '.norm-pill { padding:4px 8px; font-size:10px }'
            . '}';
    }

    public function getJs(): string
    {
        return '(function(){'
            . 'document.querySelectorAll("[data-norm-card]").forEach(function(card){'
            . 'var states=JSON.parse(card.dataset.states||"[]");'
            . 'if(!states.length)return;'
            . 'var idx=parseInt(card.dataset.active||"0",10);'
            . 'var pills=card.querySelectorAll(".norm-pill");'
            . 'var badge=card.querySelector(".norm-badge");'
            . 'var bar=card.querySelector(".norm-bar");'
            . 'var desc=card.querySelector(".norm-desc");'
            . 'var icon=card.querySelector(".norm-badge-icon");'
            . 'function apply(i){'
            . 'idx=i;'
            . 'var s=states[i];if(!s)return;'
            . 'desc.classList.add("is-fading");'
            . 'pills.forEach(function(p,pi){p.classList.toggle("is-active",pi===i)});'
            . 'badge.style.background=s.badge;badge.style.color=s.bc;'
            . 'icon.textContent=s.icon;'
            . 'badge.childNodes[badge.childNodes.length-1].textContent=" "+s.range;'
            . 'bar.style.background=s.bar;bar.style.width=s.pct+"%";'
            . 'setTimeout(function(){desc.textContent=s.desc;desc.classList.remove("is-fading")},200);'
            . '}'
            . 'pills.forEach(function(pill){'
            . 'pill.addEventListener("click",function(){apply(parseInt(pill.dataset.idx,10))});'
            . '});'
            . 'card.querySelector(".norm-card-header").addEventListener("click",function(){'
            . 'apply((idx+1)%states.length);'
            . '});'
            . '});'
            . '})();';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['caption'] ?? 'Нормы';
    }
}
