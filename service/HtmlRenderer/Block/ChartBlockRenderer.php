<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class ChartBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $content, string $id): string
    {
        $c = $content;
        $title     = $this->e($c['title'] ?? 'График');
        $chartType = $c['chart_type'] ?? 'bar';
        $labels    = $c['labels'] ?? [];
        $datasets  = $c['datasets'] ?? [];
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';
        $useCSS    = in_array($chartType, ['bar', 'horizontalBar'])
            && count($datasets) <= 2
            && count($labels) <= 12;

        $h = '<section id="' . $id . '" class="block-chart reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="mac-window">'
            . '<div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $title . '</div></div>'
            . '<div class="mac-body">';

        /* Optional description (not for doughnut/pie) */
        $desc = trim($c['description'] ?? '');
        $isCircularType = in_array($chartType, ['doughnut', 'pie']);
        if ($desc !== '' && !$isCircularType) {
            $h .= '<p class="chart-desc">' . $this->e($desc) . '</p>';
        }

        if ($useCSS && !empty($labels)) {
            /* Premium CSS horizontal bars */
            $vals   = $datasets[0]['data'] ?? [];
            $maxVal = max(1, max(array_map('floatval', $vals)));
            $gradients = [
                ['#2563EB','#60A5FA'],['#0D9488','#2DD4BF'],['#8B5CF6','#C4B5FD'],
                ['#F59E0B','#FCD34D'],['#EF4444','#FCA5A5'],['#16A34A','#4ADE80'],
                ['#EC4899','#F9A8D4'],['#06B6D4','#67E8F9'],['#F97316','#FDBA74'],
                ['#6366F1','#A5B4FC'],['#14B8A6','#5EEAD4'],['#E11D48','#FDA4AF'],
            ];

            $h .= '<div class="css-chart">';
            foreach ($labels as $i => $label) {
                $val = floatval($vals[$i] ?? 0);
                $pct = round(($val / $maxVal) * 100);
                $g   = $gradients[$i % count($gradients)];
                $h .= '<div class="chart-row">'
                    . '<span class="chart-label">' . $this->e($label) . '</span>'
                    . '<div class="bar-track"><div class="bar-fill bar-fill--shimmer" style="background:linear-gradient(90deg,' . $g[0] . ',' . $g[1] . ')" data-width="' . $pct . '"></div></div>'
                    . '<span class="chart-val">' . $this->e((string)$val) . '</span>'
                    . '</div>';
            }
            $h .= '</div>';

        } elseif (in_array($chartType, ['doughnut', 'pie'])) {
            /* Interactive SVG Doughnut / Pie */
            $isDoughnut = ($chartType === 'doughnut');
            $ds0 = $datasets[0] ?? [];
            $data = $ds0['data'] ?? [];
            $colors = $ds0['colors'] ?? $ds0['backgroundColor'] ?? [
                '#2563EB','#0D9488','#8B5CF6','#F59E0B','#EF4444','#16A34A','#EC4899','#06B6D4'
            ];
            $descriptions = $ds0['descriptions'] ?? [];
            $total = array_sum(array_map('floatval', $data));

            if (count($data) <= 8 && count($data) > 0) {
                $radius = 70;
                $strokeW = $isDoughnut ? 36 : 70;
                $circum = 2 * M_PI * $radius;

                $h .= '<div class="donut-layout" data-donut>';

                /* Description */
                if ($desc !== '') {
                    $h .= '<div class="donut-aside-desc">' . $this->e($desc) . '</div>';
                }

                /* SVG ring */
                $h .= '<div class="donut-visual">'
                    . '<div class="donut-svg-wrap">'
                    . '<svg class="donut-svg" viewBox="0 0 200 200">';

                $h .= '<circle cx="100" cy="100" r="' . $radius . '" fill="none"'
                    . ' stroke="var(--border)" stroke-width="' . $strokeW . '" />';

                $cumulative = 0;
                foreach ($data as $i => $v) {
                    $val = floatval($v);
                    $segLen = $total > 0 ? ($val / $total) * $circum : 0;
                    $offset = -$cumulative;
                    $clr = $colors[$i % count($colors)];

                    $h .= '<circle class="donut-seg" data-seg="' . $i . '"'
                        . ' cx="100" cy="100" r="' . $radius . '"'
                        . ' fill="none" stroke="' . $clr . '"'
                        . ' stroke-width="' . $strokeW . '"'
                        . ' stroke-dasharray="' . round($segLen, 3) . ' ' . round($circum, 3) . '"'
                        . ' stroke-dashoffset="' . round($offset, 3) . '"'
                        . ' transform="rotate(-90 100 100)"'
                        . ' style="cursor:pointer;transition:opacity .3s,stroke-width .3s,filter .3s" />';

                    $cumulative += $segLen;
                }

                $h .= '</svg>';

                if ($isDoughnut) {
                    $h .= '<div class="donut-hole">'
                        . '<span class="donut-total">' . $this->e((string)round($total)) . '</span>'
                        . '<span class="donut-total-label">всего</span>'
                        . '</div>';
                }
                $h .= '</div></div>';

                /* Aside: legend + detail */
                $h .= '<div class="donut-aside">';

                /* Legend */
                $h .= '<div class="donut-legend">';
                foreach ($labels as $i => $label) {
                    $v = floatval($data[$i] ?? 0);
                    $pctLabel = $total > 0 ? round(($v / $total) * 100, 1) : 0;
                    $clr = $colors[$i % count($colors)];
                    $h .= '<button class="donut-legend-item" data-seg-btn="' . $i . '">'
                        . '<span class="donut-legend-dot" style="background:' . $clr . '"></span>'
                        . '<span class="donut-legend-text">' . $this->e($label) . '</span>'
                        . '<span class="donut-legend-val">' . $this->e((string)$v) . ' <small>(' . $pctLabel . '%)</small></span>'
                        . '</button>';
                }
                $h .= '</div>';

                /* Segment detail */
                $segDescsJson = json_encode(
                    array_map(fn($i) => [
                        'label' => $labels[$i] ?? '',
                        'desc'  => $descriptions[$i] ?? '',
                        'color' => $colors[$i % count($colors)],
                    ], array_keys($data)),
                    JSON_UNESCAPED_UNICODE
                );
                $h .= '<div class="donut-detail" data-donut-detail>'
                    . '<div class="donut-detail-wrap">'
                    . '<div class="donut-detail-inner">'
                    . '<span class="donut-detail-dot"></span>'
                    . '<span class="donut-detail-label"></span>'
                    . '</div>'
                    . '<div class="donut-detail-desc"></div>'
                    . '</div>'
                    . '</div>';
                $h .= '<script type="application/json" class="donut-seg-data">'
                    . str_replace('</', '<\\/', $segDescsJson) . '</script>';

                $h .= '</div>'; /* /donut-aside */
                $h .= '</div>'; /* /donut-layout */

            } else {
                /* Fallback to Chart.js for complex datasets */
                $cfg = $this->buildPremiumChartConfig($chartType, $labels, $datasets);
                $h .= '<div class="chartjs-wrap chartjs-wrap--donut"><canvas id="chart-' . $id . '"></canvas></div>';
                $h .= '<script>document.addEventListener("DOMContentLoaded",function(){'
                    . 'new Chart(document.getElementById("chart-' . $id . '"),'
                    . str_replace('</', '<\\/', $cfg) . ');});</script>';
            }

        } elseif ($chartType === 'line') {
            /* Premium Line Chart */
            $cfg = $this->buildPremiumChartConfig($chartType, $labels, $datasets);
            $h .= '<div class="chartjs-wrap chartjs-wrap--line"><canvas id="chart-' . $id . '"></canvas></div>';
            $h .= '<script>document.addEventListener("DOMContentLoaded",function(){'
                . 'var ctx=document.getElementById("chart-' . $id . '").getContext("2d");'
                . 'var grad=ctx.createLinearGradient(0,0,0,300);'
                . 'grad.addColorStop(0,"rgba(37,99,235,0.25)");grad.addColorStop(1,"rgba(37,99,235,0.01)");'
                . 'var cfg=' . str_replace('</', '<\\/', $cfg) . ';'
                . 'if(cfg.data&&cfg.data.datasets&&cfg.data.datasets[0]){'
                . 'cfg.data.datasets[0].backgroundColor=grad;cfg.data.datasets[0].fill=true;'
                . 'cfg.data.datasets[0].borderColor="#2563EB";cfg.data.datasets[0].borderWidth=2.5;'
                . 'cfg.data.datasets[0].pointBackgroundColor="#fff";cfg.data.datasets[0].pointBorderColor="#2563EB";'
                . 'cfg.data.datasets[0].pointBorderWidth=2;cfg.data.datasets[0].pointRadius=4;'
                . 'cfg.data.datasets[0].pointHoverRadius=6;cfg.data.datasets[0].tension=0.4;'
                . '}'
                . 'new Chart(ctx,cfg);});</script>';

        } else {
            /* Fallback: Chart.js with premium config */
            $cfg = $this->buildPremiumChartConfig($chartType, $labels, $datasets);
            $h .= '<div class="chartjs-wrap"><canvas id="chart-' . $id . '"></canvas></div>';
            $h .= '<script>document.addEventListener("DOMContentLoaded",function(){'
                . 'new Chart(document.getElementById("chart-' . $id . '"),'
                . str_replace('</', '<\\/', $cfg) . ');});</script>';
        }

        return $h . '</div></div><div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
    }

    private function buildPremiumChartConfig(string $type, array $labels, array $datasets): string
    {
        $isCircular = in_array($type, ['doughnut', 'pie']);
        $isLine     = ($type === 'line');
        $isHorizontal = ($type === 'horizontalBar');
        if ($isHorizontal) {
            $type = 'bar';
        }

        $options = [
            'responsive'          => true,
            'maintainAspectRatio' => true,
            'animation'           => ['duration' => 800, 'easing' => 'easeOutQuart'],
            'plugins'             => [
                'legend' => [
                    'position' => $isCircular ? 'bottom' : 'top',
                    'labels'   => [
                        'padding'       => 20,
                        'usePointStyle' => true,
                        'pointStyle'    => 'circle',
                        'font'          => ['size' => 13, 'family' => '"Onest",sans-serif'],
                    ],
                ],
                'tooltip' => [
                    'backgroundColor' => 'rgba(15,23,42,0.92)',
                    'titleFont'       => ['size' => 13, 'family' => '"Geologica",sans-serif', 'weight' => 700],
                    'bodyFont'        => ['size' => 12, 'family' => '"Onest",sans-serif'],
                    'padding'         => 14,
                    'cornerRadius'    => 10,
                    'displayColors'   => true,
                    'boxPadding'      => 6,
                ],
            ],
        ];

        if ($isHorizontal) {
            $options['indexAxis'] = 'y';
        }

        if ($isCircular) {
            $options['cutout'] = ($type === 'doughnut') ? '62%' : '0%';
            $options['elements'] = ['arc' => ['borderWidth' => 2, 'borderColor' => 'rgba(255,255,255,0.9)']];
        } else {
            $options['scales'] = [
                'x' => [
                    'grid'   => ['display' => false],
                    'ticks'  => ['font' => ['size' => 12, 'family' => '"Onest",sans-serif'], 'color' => '#64748B'],
                    'border' => ['display' => false],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'grid'        => ['color' => 'rgba(0,0,0,0.04)', 'drawBorder' => false],
                    'ticks'       => ['font' => ['size' => 12, 'family' => '"Onest",sans-serif'], 'color' => '#64748B', 'padding' => 8],
                    'border'      => ['display' => false, 'dash' => [4, 4]],
                ],
            ];
            if ($isLine) {
                $options['elements'] = ['line' => ['tension' => 0.4]];
            } else {
                $options['elements'] = ['bar' => ['borderRadius' => 8, 'borderSkipped' => false]];
                $options['barPercentage'] = 0.65;
            }
        }

        return json_encode([
            'type'    => $type,
            'data'    => ['labels' => $labels, 'datasets' => $datasets],
            'options' => $options,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function getCss(): string
    {
        return '.block-chart { }'
            . "\n" . '.chart-desc { font-size:14px; color:var(--muted); line-height:1.6; margin-bottom:20px; max-width:560px }'
            . "\n" . '.chartjs-wrap { max-width:600px; margin:0 auto; padding:8px }'
            . "\n" . '.chartjs-wrap--donut { max-width:420px }'
            . "\n" . '.chartjs-wrap--line { max-width:680px }'
            . "\n" . '.css-chart { display:flex; flex-direction:column; gap:10px }'
            . "\n" . '.chart-row { display:grid; grid-template-columns:120px 1fr 55px; gap:12px; align-items:center; padding:6px 0 }'
            . "\n" . '.chart-label { color:var(--slate); font-weight:500; font-size:13px; text-align:right; white-space:normal; line-height:1.3 }'
            . "\n" . '.chart-row .bar-track { height:32px; border-radius:10px; background:rgba(226,232,240,.35) }'
            . "\n" . '[data-theme="dark"] .chart-row .bar-track { background:rgba(255,255,255,.06) }'
            . "\n" . '.chart-row .bar-fill { border-radius:10px; position:relative; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08) }'
            . "\n" . '.chart-val { font-family:var(--fh); font-weight:700; color:var(--dark); font-size:14px }'
            . "\n" . '.donut-layout { display:grid; grid-template-columns:auto 1fr; grid-template-rows:auto 1fr; gap:8px 36px; align-items:start }'
            . "\n" . '.donut-aside-desc { grid-column:2; grid-row:1; font-size:14px; color:var(--muted); line-height:1.65; margin:0; padding-top:4px }'
            . "\n" . '.donut-visual { grid-column:1; grid-row:1/3; align-self:center }'
            . "\n" . '.donut-aside { grid-column:2; grid-row:2; display:flex; flex-direction:column; gap:12px; min-width:0 }'
            . "\n" . '.donut-svg-wrap { position:relative; width:220px; height:220px }'
            . "\n" . '.donut-svg { width:100%; height:100%; filter:drop-shadow(0 4px 16px rgba(0,0,0,.08)) }'
            . "\n" . '.donut-seg { transition:opacity .35s ease, stroke-width .35s ease, filter .35s ease }'
            . "\n" . '.donut-seg:hover { filter:brightness(1.12) }'
            . "\n" . '[data-donut].has-active .donut-seg { opacity:.25 }'
            . "\n" . '[data-donut].has-active .donut-seg.is-active { opacity:1; filter:drop-shadow(0 0 8px rgba(0,0,0,.2)) }'
            . "\n" . '.donut-svg-wrap .donut-hole { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:56%; height:56%; border-radius:50%; background:var(--white); display:flex; flex-direction:column; align-items:center; justify-content:center; box-shadow:inset 0 2px 12px rgba(0,0,0,.04); pointer-events:none }'
            . "\n" . '[data-theme="dark"] .donut-svg-wrap .donut-hole { background:var(--dark2) }'
            . "\n" . '.donut-total { font-family:var(--fh); font-size:1.8rem; font-weight:900; color:var(--dark); letter-spacing:-1px; line-height:1 }'
            . "\n" . '.donut-total-label { font-size:.72rem; color:var(--muted); text-transform:uppercase; letter-spacing:1.5px; margin-top:4px }'
            . "\n" . '.donut-legend { display:flex; flex-direction:column; gap:6px }'
            . "\n" . '.donut-legend-item { display:flex; align-items:center; gap:8px; padding:8px 14px; border-radius:10px; background:rgba(255,255,255,.5); border:1px solid var(--border); transition:all .25s; min-width:0; cursor:pointer; font-family:var(--fb); font-size:inherit; color:inherit }'
            . "\n" . '[data-theme="dark"] .donut-legend-item { background:rgba(255,255,255,.04) }'
            . "\n" . '.donut-legend-item:hover { border-color:rgba(37,99,235,.3); box-shadow:0 2px 12px rgba(37,99,235,.06) }'
            . "\n" . '.donut-legend-item.is-active { border-color:var(--blue); box-shadow:0 2px 12px rgba(37,99,235,.12); background:var(--blue-light) }'
            . "\n" . '[data-donut].has-active .donut-legend-item:not(.is-active) { opacity:.45 }'
            . "\n" . '.donut-legend-dot { width:10px; height:10px; border-radius:3px; flex-shrink:0; transition:transform .25s }'
            . "\n" . '.donut-legend-item.is-active .donut-legend-dot { transform:scale(1.3) }'
            . "\n" . '.donut-legend-text { font-size:13px; color:var(--slate); font-weight:500; white-space:normal; min-width:0; overflow:hidden }'
            . "\n" . '.donut-legend-val { font-family:var(--fh); font-size:13px; font-weight:700; color:var(--dark); margin-left:auto; white-space:nowrap }'
            . "\n" . '.donut-legend-val small { font-weight:400; color:var(--muted); font-size:11px }'
            . "\n" . '.donut-detail { display:grid; grid-template-rows:0fr; transition:grid-template-rows .4s ease, opacity .3s ease; opacity:0 }'
            . "\n" . '.donut-detail.is-open { grid-template-rows:1fr; opacity:1 }'
            . "\n" . '.donut-detail > * { overflow:hidden }'
            . "\n" . '.donut-detail-wrap { padding:14px 16px; border-radius:12px; background:rgba(255,255,255,.45); border:1px solid var(--border); backdrop-filter:blur(6px) }'
            . "\n" . '[data-theme="dark"] .donut-detail-wrap { background:rgba(255,255,255,.03) }'
            . "\n" . '.donut-detail-inner { display:flex; align-items:center; gap:8px; margin-bottom:6px }'
            . "\n" . '.donut-detail-dot { width:8px; height:8px; border-radius:2px; flex-shrink:0 }'
            . "\n" . '.donut-detail-label { font-family:var(--fh); font-size:14px; font-weight:700; color:var(--dark) }'
            . "\n" . '.donut-detail-desc { font-size:13px; color:var(--muted); line-height:1.55 }'
            . "\n" . '@media(max-width:768px) {'
            .     '.chart-row { grid-template-columns:80px 1fr 45px }'
            .     '.donut-layout { display:flex; flex-direction:column; align-items:center; gap:20px }'
            .     '.donut-aside-desc { text-align:center; order:-1 }'
            .     '.donut-visual { order:0 }'
            .     '.donut-aside { order:1; width:100% }'
            .     '.donut-svg-wrap { width:180px; height:180px }'
            .     '.donut-total { font-size:1.4rem }'
            .     '.donut-legend { align-items:stretch }'
            .     '.donut-legend-item { justify-content:space-between }'
            . '}'
            . "\n" . '@media(max-width:414px) {'
            .     '.donut-svg-wrap { width:160px; height:160px }'
            .     '.chart-row { grid-template-columns:60px 1fr 40px; gap:8px }'
            .     '.chart-row .bar-track { height:24px }'
            .     '.chart-label { font-size:11px }'
            .     '.chart-val { font-size:12px }'
            . '}';
    }

    public function getJs(): string
    {
        return '(function(){'
            . 'document.querySelectorAll("[data-donut]").forEach(function(wrap){'
            . 'var segs=wrap.querySelectorAll(".donut-seg");'
            . 'var btns=wrap.querySelectorAll("[data-seg-btn]");'
            . 'var detail=wrap.querySelector("[data-donut-detail]");'
            . 'var jsonEl=wrap.querySelector(".donut-seg-data");'
            . 'var segData=[];'
            . 'try{segData=JSON.parse(jsonEl?jsonEl.textContent:"[]")}catch(e){}'
            . 'var active=-1;'
            . 'var holeTotal=wrap.querySelector(".donut-total");'
            . 'var holeLabel=wrap.querySelector(".donut-total-label");'
            . 'var originalTotal=holeTotal?holeTotal.textContent:"100";'
            . 'var originalLabel=holeLabel?holeLabel.textContent:"всего";'
            . 'var style = document.createElement("style");'
            . 'style.textContent = `'
            . '.donut-hole.has-selected .donut-total-label { display: none; }'
            . '.donut-hole.has-selected .donut-total { '
            . '    font-size: 2.5em;'
            . '    line-height: 1.2;'
            . '    display: flex;'
            . '    align-items: center;'
            . '    justify-content: center;'
            . '    height: 100%;'
            . '}`;'
            . 'document.head.appendChild(style);'
            . 'var hole = wrap.querySelector(".donut-hole");'
            . 'function select(i){'
            . 'if(i===active){deselect();return}'
            . 'active=i;'
            . 'wrap.classList.add("has-active");'
            . 'segs.forEach(function(s,si){s.classList.toggle("is-active",si===i)});'
            . 'btns.forEach(function(b,bi){b.classList.toggle("is-active",bi===i)});'
            . 'if(detail&&segData[i]){'
            . 'var d=segData[i];'
            . 'var dot=detail.querySelector(".donut-detail-dot");'
            . 'var lbl=detail.querySelector(".donut-detail-label");'
            . 'var desc=detail.querySelector(".donut-detail-desc");'
            . 'if(dot)dot.style.background=d.color;'
            . 'if(lbl)lbl.textContent=d.label;'
            . 'if(desc)desc.textContent=d.desc;'
            . 'detail.classList.add("is-open");'
            . 'if(holeTotal&&btns[i]){'
            . 'var val=btns[i].querySelector(".donut-legend-val");'
            . 'if(val){'
            . 'var valText=val.textContent.trim();'
            . 'var numMatch=valText.match(/^(\\d+(\\.\\d+)?)/);'
            . 'if(numMatch){'
            . 'holeTotal.textContent=numMatch[1];'
            . '}'
            . '}'
            . '}'
            . '}'
            . '}'
            . 'function deselect(){'
            . 'active=-1;'
            . 'wrap.classList.remove("has-active");'
            . 'if(hole) hole.classList.remove("has-selected");'
            . 'segs.forEach(function(s){s.classList.remove("is-active")});'
            . 'btns.forEach(function(b){b.classList.remove("is-active")});'
            . 'if(detail)detail.classList.remove("is-open");'
            . 'if(holeTotal)holeTotal.textContent=originalTotal;'
            . 'if(holeLabel)holeLabel.textContent=originalLabel;'
            . '}'
            . 'segs.forEach(function(seg){seg.addEventListener("click",function(){'
            . 'select(parseInt(seg.dataset.seg,10))'
            . '})});'
            . 'btns.forEach(function(btn){btn.addEventListener("click",function(){'
            . 'select(parseInt(btn.dataset.segBtn,10))'
            . '})});'
            . 'document.addEventListener("click",function(e){'
            . 'if(!wrap.contains(e.target))deselect()'
            . '});'
            . '})})();';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'График';
    }
}
