<?php

require_once __DIR__ . '/../auth.php';
requireAuth();

require_once __DIR__ . '/../config.php';

use Seo\Service\CostReportService;

$days = isset($_GET['days']) ? max(1, (int)$_GET['days']) : 30;
$report = (new CostReportService())->build($days);
$strategies = $report['by_strategy'];
$operations = $report['by_operation'];
$comparison = $report['comparison'];

$chartLabels = array_keys($strategies);
$chartCost   = array_map(fn($r) => $r['avg_cost'], $strategies);
$chartTokens = array_map(fn($r) => $r['avg_tokens'], $strategies);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cost Report — Research</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
               background: #0f172a; color: #e2e8f0; min-height: 100vh; }
        .topbar { background: #1e293b; border-bottom: 1px solid #334155; padding: 14px 24px;
                  display: flex; align-items: center; justify-content: space-between; }
        .topbar h1 { font-size: 1.1rem; color: #f1f5f9; }
        .topbar a { color: #94a3b8; text-decoration: none; font-size: .85rem; }
        .topbar a:hover { color: #e2e8f0; }
        .container { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 8px;
                padding: 18px; margin-bottom: 18px; }
        .card h2 { font-size: 1rem; color: #f1f5f9; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: .82rem; }
        th, td { text-align: right; padding: 8px 10px; border-bottom: 1px solid #334155; }
        th:first-child, td:first-child { text-align: left; }
        th { color: #94a3b8; font-weight: 600; }
        td.strategy { color: #f1f5f9; font-weight: 500; }
        .filter { display: flex; gap: 8px; align-items: center; font-size: .85rem; }
        .filter input { background: #0f172a; border: 1px solid #475569; color: #e2e8f0;
                        padding: 5px 8px; border-radius: 4px; width: 70px; }
        .filter button { background: #0e7490; border: 0; color: white; padding: 6px 14px;
                         border-radius: 4px; cursor: pointer; font-size: .82rem; }
        .pct-pos { color: #f87171; }
        .pct-neg { color: #4ade80; }
        .pct-neutral { color: #94a3b8; }
        canvas { max-height: 320px; }
        .meta { font-size: .7rem; color: #64748b; margin-top: 6px; }
    </style>
</head>
<body>

<div class="topbar">
    <h1>Cost Report — Research</h1>
    <a href="../admin_simple/articles.php">← Articles</a>
</div>

<div class="container">
    <div class="card">
        <form class="filter" method="GET">
            <label>Период (дней): <input type="number" name="days" value="<?= (int)$days ?>" min="1" max="365"></label>
            <button type="submit">Обновить</button>
            <span style="margin-left:auto;font-size:.7rem;color:#64748b">
                Сгенерировано: <?= htmlspecialchars($report['generated_at']) ?>
            </span>
        </form>
    </div>

    <div class="card">
        <h2>По стратегии (avg cost / article)</h2>
        <canvas id="chartCost"></canvas>
    </div>

    <div class="card">
        <h2>По стратегии — таблица</h2>
        <?php if (empty($strategies)): ?>
            <p style="color:#64748b">Нет данных за период.</p>
        <?php else: ?>
        <table>
            <thead><tr>
                <th>Strategy</th><th>Articles</th><th>Avg tokens</th>
                <th>Avg cost ($)</th><th>Total cost ($)</th><th>Δ vs single</th>
            </tr></thead>
            <tbody>
            <?php foreach ($strategies as $s => $row): ?>
                <?php
                $cmpKey = $s . '_vs_single_pct';
                $delta = $comparison[$cmpKey] ?? null;
                $cls = 'pct-neutral';
                $deltaText = '—';
                if ($delta !== null) {
                    $cls = $delta > 0 ? 'pct-pos' : ($delta < 0 ? 'pct-neg' : 'pct-neutral');
                    $deltaText = sprintf('%+.1f%%', $delta);
                }
                ?>
                <tr>
                    <td class="strategy"><?= htmlspecialchars((string)$s) ?></td>
                    <td><?= (int)$row['articles'] ?></td>
                    <td><?= number_format($row['avg_tokens'], 0, '.', ' ') ?></td>
                    <td><?= number_format($row['avg_cost'], 6, '.', '') ?></td>
                    <td><?= number_format($row['total_cost'], 6, '.', '') ?></td>
                    <td class="<?= $cls ?>"><?= $deltaText ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="meta">Тезис плана 6: split-вариант не должен быть дороже single больше чем на 30%.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>По операции</h2>
        <?php if (empty($operations)): ?>
            <p style="color:#64748b">Нет данных за период.</p>
        <?php else: ?>
        <table>
            <thead><tr>
                <th>Operation</th><th>Calls</th><th>Prompt</th>
                <th>Completion</th><th>Total tokens</th><th>Cost ($)</th>
            </tr></thead>
            <tbody>
            <?php foreach ($operations as $r): ?>
                <tr>
                    <td class="strategy"><?= htmlspecialchars((string)$r['operation']) ?></td>
                    <td><?= (int)$r['calls'] ?></td>
                    <td><?= number_format($r['prompt_tokens'], 0, '.', ' ') ?></td>
                    <td><?= number_format($r['completion_tokens'], 0, '.', ' ') ?></td>
                    <td><?= number_format($r['total_tokens'], 0, '.', ' ') ?></td>
                    <td><?= number_format($r['cost_usd'], 6, '.', '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
const labels  = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
const cost    = <?= json_encode($chartCost) ?>;
const tokens  = <?= json_encode($chartTokens) ?>;

new Chart(document.getElementById('chartCost'), {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [
            { label: 'avg cost ($)', data: cost, backgroundColor: '#22d3ee', yAxisID: 'y' },
            { label: 'avg tokens',   data: tokens, backgroundColor: '#a78bfa', yAxisID: 'y1', type: 'line' },
        ],
    },
    options: {
        responsive: true,
        scales: {
            y:  { beginAtZero: true, position: 'left',  title: { display: true, text: 'cost ($)', color: '#94a3b8' }, ticks: { color: '#94a3b8' } },
            y1: { beginAtZero: true, position: 'right', title: { display: true, text: 'tokens',  color: '#94a3b8' }, ticks: { color: '#94a3b8' }, grid: { drawOnChartArea: false } },
            x:  { ticks: { color: '#e2e8f0' } },
        },
        plugins: { legend: { labels: { color: '#e2e8f0' } } },
    },
});
</script>

</body>
</html>
