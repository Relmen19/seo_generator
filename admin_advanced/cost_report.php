<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
requireAuth();

require_once __DIR__ . '/../config.php';

use Seo\Service\CostReportService;
use Seo\Database;

$days      = isset($_GET['days']) ? max(1, min(365, (int)$_GET['days'])) : 30;
$profileId = isset($_GET['profile_id']) && $_GET['profile_id'] !== '' ? (int)$_GET['profile_id'] : null;

$profileName = '';
if ($profileId !== null) {
    $row = Database::getInstance()->fetchOne(
        "SELECT name FROM seo_site_profiles WHERE id = ?", [$profileId]
    );
    $profileName = $row ? (string)$row['name'] : '';
}

$report     = (new CostReportService())->build($days, $profileId);
$totals     = $report['totals'];
$byDay      = $report['by_day'];
$byCategory = $report['by_category'];
$strategies = $report['by_strategy'];
$operations = $report['by_operation'];
$comparison = $report['comparison'];
$generated  = (string)($report['generated_at'] ?? '');

$dayLabels = array_column($byDay, 'day');
$dayCost   = array_map(static fn($r) => round((float)$r['cost_usd'], 4), $byDay);
$dayCalls  = array_column($byDay, 'calls');
$dayTokens = array_column($byDay, 'total_tokens');

$catLabels = array_column($byCategory, 'category');
$catCost   = array_map(static fn($r) => round((float)$r['cost_usd'], 4), $byCategory);

$strategyLabels = array_keys($strategies);
$strategyCost   = array_map(static fn($r) => $r['avg_cost'],   $strategies);
$strategyTokens = array_map(static fn($r) => $r['avg_tokens'], $strategies);

$totalArticles = array_sum(array_map(static fn($r) => (int)$r['articles'], $strategies));
$totalCost     = (float)($totals['cost_usd'] ?? 0.0);
$avgPerArticle = $totalArticles > 0 ? array_sum(array_map(static fn($r) => (float)$r['total_cost'], $strategies)) / $totalArticles : 0.0;
$totalCalls    = (int)($totals['calls'] ?? 0);
$totalTokens   = (int)($totals['total_tokens'] ?? 0);

$pageTitle      = 'Расходы — SEO admin';
$activeNav      = 'cost';
$pageHeading    = 'Расходы';
$pageSubheading = $profileName !== ''
    ? 'Профиль: ' . $profileName . ' · период ' . (int)$days . ' дн.'
    : ($profileId !== null ? 'Профиль #' . (int)$profileId : 'Все профили (профиль не выбран)');

ob_start();?>
<form method="GET" class="flex items-center gap-2 bg-sand-50 rounded-full pl-4 pr-1 h-12 shadow-rail">
  <?php if ($profileId !== null): ?>
    <input type="hidden" name="profile_id" value="<?= (int)$profileId ?>">
  <?php endif; ?>
  <span class="text-ink-500 text-sm">Период</span>
  <input type="number" name="days" min="1" max="365" value="<?= (int)$days ?>"
         class="w-20 h-9 px-3 rounded-full bg-sand-100 text-sm text-ink-900 outline-none focus:bg-sand-50">
  <span class="text-ink-500 text-sm pr-1">дней</span>
  <button type="submit" class="btn-primary h-10 px-5">Обновить</button>
</form>
<?php
$topbarRight = ob_get_clean();

$autoRedirect = $profileId === null
    ? '<script>(function(){var id=localStorage.getItem("seo_profile_id");if(id){var u=new URL(location.href);u.searchParams.set("profile_id",id);location.replace(u.toString());}})();</script>'
    : '';
$extraHead = $autoRedirect . '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>';

include __DIR__ . '/_layout/header.php';
?>

<section class="grid grid-cols-2 md:grid-cols-5 gap-4 md:gap-6">
  <div class="card p-5">
    <div class="text-ink-500 text-xs uppercase tracking-wide font-semibold">Всего</div>
    <div class="text-3xl font-bold mt-2">$<?= number_format($totalCost, 2, '.', ' ') ?></div>
    <div class="text-ink-300 text-xs mt-1">за <?= (int)$days ?> дн.</div>
  </div>
  <div class="card p-5">
    <div class="text-ink-500 text-xs uppercase tracking-wide font-semibold">Вызовов</div>
    <div class="text-3xl font-bold mt-2"><?= number_format($totalCalls, 0, '.', ' ') ?></div>
    <div class="text-ink-300 text-xs mt-1">API-запросов</div>
  </div>
  <div class="card p-5">
    <div class="text-ink-500 text-xs uppercase tracking-wide font-semibold">Токенов</div>
    <div class="text-3xl font-bold mt-2"><?= number_format($totalTokens / 1000, 1, '.', ' ') ?>k</div>
    <div class="text-ink-300 text-xs mt-1">всего</div>
  </div>
  <div class="card p-5">
    <div class="text-ink-500 text-xs uppercase tracking-wide font-semibold">Статей</div>
    <div class="text-3xl font-bold mt-2"><?= number_format($totalArticles, 0, '.', ' ') ?></div>
    <div class="text-ink-300 text-xs mt-1">research</div>
  </div>
  <div class="card-dark p-5">
    <div class="text-sand-300 text-xs uppercase tracking-wide font-semibold">На статью</div>
    <div class="text-3xl font-bold mt-2">$<?= number_format($avgPerArticle, 4, '.', '') ?></div>
    <div class="text-sand-300/80 text-xs mt-1">avg research</div>
  </div>
</section>

<section class="grid grid-cols-1 lg:grid-cols-3 gap-5 md:gap-8 mt-8">
  <div class="card lg:col-span-2 p-6 md:p-8">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h2 class="text-lg font-semibold">Расходы по дням</h2>
        <p class="text-ink-500 text-xs">Дневная стоимость + количество вызовов</p>
      </div>
      <span class="badge-soft"><?= count($byDay) ?> дн. с активностью</span>
    </div>
    <?php if (empty($byDay)): ?>
      <div class="bg-sand-100 rounded-2xl py-12 text-center text-ink-500">Нет данных за период</div>
    <?php else: ?>
      <canvas id="chartDay" style="max-height:340px"></canvas>
    <?php endif; ?>
  </div>

  <div class="card p-6 md:p-8">
    <h2 class="text-lg font-semibold mb-4">По категориям</h2>
    <?php if (empty($byCategory)): ?>
      <p class="text-ink-500 text-sm">Нет данных за период.</p>
    <?php else: ?>
      <canvas id="chartCat" style="max-height:240px" class="mb-4"></canvas>
      <ul class="space-y-2 text-sm">
        <?php foreach ($byCategory as $r):
          $share = $totalCost > 0 ? ($r['cost_usd'] / $totalCost * 100) : 0;
        ?>
          <li class="flex items-center justify-between gap-3 py-2 border-b border-sand-200 last:border-0">
            <div class="min-w-0">
              <div class="font-semibold truncate"><?= htmlspecialchars($r['category'], ENT_QUOTES, 'UTF-8') ?></div>
              <div class="text-ink-500 text-xs"><?= number_format($r['calls'], 0, '.', ' ') ?> выз. · <?= number_format($r['total_tokens'] / 1000, 1, '.', '') ?>k ток.</div>
            </div>
            <div class="text-right">
              <div class="font-semibold tabular-nums">$<?= number_format($r['cost_usd'], 4, '.', '') ?></div>
              <div class="text-xs text-ink-500"><?= number_format($share, 1, '.', '') ?>%</div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>

<section class="grid grid-cols-1 lg:grid-cols-3 gap-5 md:gap-8 mt-8">
  <div class="card lg:col-span-2 p-6 md:p-8">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h2 class="text-lg font-semibold">Стоимость по стратегиям research</h2>
        <p class="text-ink-500 text-xs">Среднее на статью + средние токены</p>
      </div>
      <span class="badge-soft">Сгенерирован: <?= htmlspecialchars($generated, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <?php if (empty($strategies)): ?>
      <div class="bg-sand-100 rounded-2xl py-12 text-center text-ink-500">Нет данных по стратегиям</div>
    <?php else: ?>
      <canvas id="chartStrategy" style="max-height:300px"></canvas>
    <?php endif; ?>
  </div>

  <div class="card-dark p-6 md:p-8">
    <h2 class="text-lg font-semibold mb-4">Сводка стратегий</h2>
    <ul class="space-y-3 text-sm">
      <?php foreach ($strategies as $name => $row):
        $cmpKey    = $name . '_vs_single_pct';
        $delta     = $comparison[$cmpKey] ?? null;
        $deltaText = $delta !== null ? sprintf('%+.1f%%', $delta) : '—';
        $deltaCls  = 'text-sand-300';
        if ($delta !== null) {
          $deltaCls = $delta > 0 ? 'text-ember-400' : ($delta < 0 ? 'text-emerald-400' : 'text-sand-300');
        }
      ?>
        <li class="flex items-center justify-between gap-3 border-b border-white/10 pb-3 last:border-0 last:pb-0">
          <div class="min-w-0">
            <div class="font-semibold truncate"><?= htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="text-sand-300 text-xs"><?= (int)$row['articles'] ?> статей · <?= number_format($row['avg_tokens'], 0, '.', ' ') ?> ток.</div>
          </div>
          <div class="text-right">
            <div class="font-semibold tabular-nums">$<?= number_format($row['avg_cost'], 4, '.', '') ?></div>
            <div class="text-xs <?= $deltaCls ?>"><?= $deltaText ?></div>
          </div>
        </li>
      <?php endforeach; ?>
      <?php if (empty($strategies)): ?>
        <li class="text-sand-300">Нет данных</li>
      <?php endif; ?>
    </ul>
    <p class="text-xs text-sand-300/70 mt-4">Цель: split не дороже single больше 30%.</p>
  </div>
</section>

<section class="card p-6 md:p-8 mt-8">
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-lg font-semibold">По операциям</h2>
    <span class="badge-soft"><?= count($operations) ?> операций</span>
  </div>
  <?php if (empty($operations)): ?>
    <p class="text-ink-500 text-sm">Нет данных за период.</p>
  <?php else: ?>
    <div class="overflow-auto">
    <table class="tbl">
      <thead><tr>
        <th>Категория</th><th>Операция</th>
        <th class="text-right">Вызовов</th><th class="text-right">Prompt</th>
        <th class="text-right">Completion</th><th class="text-right">Total ток.</th><th class="text-right">Cost $</th>
      </tr></thead>
      <tbody>
      <?php foreach ($operations as $r): ?>
        <tr>
          <td class="text-ink-500 text-xs"><?= htmlspecialchars($r['category'], ENT_QUOTES, 'UTF-8') ?></td>
          <td class="font-semibold"><?= htmlspecialchars($r['operation'], ENT_QUOTES, 'UTF-8') ?></td>
          <td class="text-right tabular-nums"><?= (int)$r['calls'] ?></td>
          <td class="text-right tabular-nums"><?= number_format($r['prompt_tokens'], 0, '.', ' ') ?></td>
          <td class="text-right tabular-nums"><?= number_format($r['completion_tokens'], 0, '.', ' ') ?></td>
          <td class="text-right tabular-nums"><?= number_format($r['total_tokens'], 0, '.', ' ') ?></td>
          <td class="text-right tabular-nums">$<?= number_format($r['cost_usd'], 4, '.', '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</section>

<script>
(function () {
  const ink900 = '#171511', ink500 = '#6F665B', ink300 = '#9A9285';
  const sun400 = '#F5C842', sand200 = '#ECE6DC', ember = '#D97706';
  const palette = ['#171511', '#D97706', '#F5C842', '#10b981', '#3b82f6', '#a855f7', '#ef4444', '#94a3b8'];

  <?php if (!empty($byDay)): ?>
  new Chart(document.getElementById('chartDay'), {
    data: {
      labels: <?= json_encode($dayLabels) ?>,
      datasets: [
        { type: 'bar',  label: 'Cost ($)',  data: <?= json_encode($dayCost) ?>,
          backgroundColor: ink900, borderRadius: 6, yAxisID: 'y',  maxBarThickness: 28 },
        { type: 'line', label: 'Вызовов',   data: <?= json_encode($dayCalls) ?>,
          borderColor: ember, backgroundColor: ember, tension: .3, yAxisID: 'y1',
          pointRadius: 3, pointBackgroundColor: ember },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      scales: {
        y:  { beginAtZero: true, position: 'left',  ticks: { color: ink500 }, grid: { color: sand200 }, title: { display: true, text: 'cost ($)', color: ink300 } },
        y1: { beginAtZero: true, position: 'right', ticks: { color: ink500 }, grid: { drawOnChartArea: false }, title: { display: true, text: 'calls', color: ink300 } },
        x:  { ticks: { color: ink900, maxRotation: 0, autoSkip: true }, grid: { display: false } },
      },
      plugins: { legend: { labels: { color: ink900, font: { weight: '600' } } } },
    },
  });
  <?php endif; ?>

  <?php if (!empty($byCategory)): ?>
  new Chart(document.getElementById('chartCat'), {
    type: 'doughnut',
    data: {
      labels: <?= json_encode($catLabels) ?>,
      datasets: [{ data: <?= json_encode($catCost) ?>, backgroundColor: palette, borderColor: '#FBF8F3', borderWidth: 2 }],
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '62%',
      plugins: { legend: { position: 'bottom', labels: { color: ink900, boxWidth: 10, font: { size: 11 } } } },
    },
  });
  <?php endif; ?>

  <?php if (!empty($strategies)): ?>
  new Chart(document.getElementById('chartStrategy'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($strategyLabels, JSON_UNESCAPED_UNICODE) ?>,
      datasets: [
        { label: 'avg cost ($)', data: <?= json_encode($strategyCost) ?>,   backgroundColor: ink900, borderRadius: 8, yAxisID: 'y',  maxBarThickness: 36 },
        { label: 'avg tokens',   data: <?= json_encode($strategyTokens) ?>, borderColor: sun400, backgroundColor: sun400, type: 'line', tension: .3, yAxisID: 'y1', pointRadius: 4, pointBackgroundColor: sun400 },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      scales: {
        y:  { beginAtZero: true, position: 'left',  ticks: { color: ink500 }, grid: { color: sand200 }, title: { display: true, text: 'cost ($)', color: ink300 } },
        y1: { beginAtZero: true, position: 'right', ticks: { color: ink500 }, grid: { drawOnChartArea: false }, title: { display: true, text: 'tokens', color: ink300 } },
        x:  { ticks: { color: ink900 }, grid: { display: false } },
      },
      plugins: { legend: { labels: { color: ink900, font: { weight: '600' } } } },
    },
  });
  <?php endif; ?>
})();
</script>

<?php include __DIR__ . '/_layout/footer.php'; ?>
