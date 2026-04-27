<?php

declare(strict_types=1);

/**
 * CLI: research cost breakdown by strategy.
 *   php cron/cost_report.php [--days=30] [--json]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'CLI only';
    exit(1);
}

require_once __DIR__ . '/../config.php';

use Seo\Service\CostReportService;
use Seo\Service\Logger;

Logger::info(Logger::CHANNEL_CRON, 'cost_report start', ['argv' => array_slice($argv, 1)]);

$days = 30;
$json = false;
foreach ($argv as $a) {
    if (preg_match('/^--days=(\d+)$/', $a, $m)) $days = (int)$m[1];
    if ($a === '--json') $json = true;
}

$report = (new CostReportService())->build($days);
Logger::debug(Logger::CHANNEL_CRON, 'cost_report built', ['days' => $days, 'strategies' => array_keys($report['by_strategy'] ?? [])]);

if ($json) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
}

echo "Research cost report — last {$report['days']} days\n";
echo str_repeat('=', 60) . "\n\n";

echo "By strategy:\n";
printf("%-14s %8s %14s %14s %14s\n",
    'strategy', 'articles', 'avg_tokens', 'avg_cost($)', 'total_cost($)');
echo str_repeat('-', 60) . "\n";
foreach ($report['by_strategy'] as $s => $row) {
    printf("%-14s %8d %14d %14.6f %14.6f\n",
        $s, $row['articles'], $row['avg_tokens'], $row['avg_cost'], $row['total_cost']);
}
echo "\n";

echo "Δ vs single (avg cost):\n";
foreach ($report['comparison'] as $k => $v) {
    echo "  {$k}: " . ($v === null ? 'n/a' : sprintf('%+.1f%%', $v)) . "\n";
}
echo "\n";

echo "By operation:\n";
printf("%-30s %6s %12s %12s\n", 'operation', 'calls', 'tokens', 'cost($)');
echo str_repeat('-', 64) . "\n";
foreach ($report['by_operation'] as $r) {
    printf("%-30s %6d %12d %12.6f\n",
        (string)$r['operation'], $r['calls'], $r['total_tokens'], $r['cost_usd']);
}
exit(0);
