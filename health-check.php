<?php
/**
 * CARU CARS — Inventory Health Check
 * Verifies inventory-data.js is fresh and alerts if stale.
 * Returns JSON status. Called by external scheduled task.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$key = $_GET['key'] ?? '';
if ($key !== 'carucars-health-2026') {
    http_response_code(403);
    die(json_encode(['error' => 'Forbidden']));
}

$jsFile = __DIR__ . '/inventory-data.js';
$csvFiles = glob(__DIR__ . '/DealerCenter_*.csv');

$status = [
    'timestamp' => date('c'),
    'inventory_js_exists' => file_exists($jsFile),
    'inventory_js_age_hours' => null,
    'inventory_js_modified' => null,
    'latest_csv' => null,
    'latest_csv_modified' => null,
    'csv_count' => count($csvFiles),
    'vehicle_count' => 0,
    'healthy' => false,
    'issues' => [],
];

if (!$csvFiles) {
    $status['issues'][] = 'NO CSV FILES FOUND - DealerCenter FTP may be broken';
}

if (file_exists($jsFile)) {
    $ageSeconds = time() - filemtime($jsFile);
    $status['inventory_js_age_hours'] = round($ageSeconds / 3600, 1);
    $status['inventory_js_modified'] = date('c', filemtime($jsFile));

    // Count vehicles in inventory-data.js
    $js = file_get_contents($jsFile);
    if (preg_match('/(\d+) vehicles/', $js, $m)) {
        $status['vehicle_count'] = (int)$m[1];
    }

    // Stale if older than 24 hours
    if ($ageSeconds > 86400) {
        $status['issues'][] = sprintf('INVENTORY STALE - inventory-data.js is %.1f hours old', $ageSeconds / 3600);
    }

    // If latest CSV is newer than JS by more than 12 hours, something is broken
    if ($csvFiles) {
        usort($csvFiles, function($a, $b) { return filemtime($b) - filemtime($a); });
        $latestCsv = $csvFiles[0];
        $status['latest_csv'] = basename($latestCsv);
        $status['latest_csv_modified'] = date('c', filemtime($latestCsv));

        if (filemtime($latestCsv) - filemtime($jsFile) > 43200) {
            $status['issues'][] = sprintf(
                'SYNC BROKEN - CSV (%s) is newer than inventory-data.js by %.1f hours',
                basename($latestCsv),
                (filemtime($latestCsv) - filemtime($jsFile)) / 3600
            );
        }
    }
} else {
    $status['issues'][] = 'CRITICAL - inventory-data.js does not exist';
}

$status['healthy'] = empty($status['issues']);

// Auto-trigger rebuild if stale
if (!$status['healthy']) {
    $ctx = stream_context_create(['http' => ['timeout' => 30]]);
    @file_get_contents('https://yellowgreen-emu-225498.hostingersite.com/cron-sync.php?key=carucars-sync-2026-x9f4', false, $ctx);
    $status['auto_rebuild_triggered'] = true;
}

// Log health check
file_put_contents(__DIR__ . '/sync-log.txt',
    '[' . date('Y-m-d H:i:s') . '] HEALTH: ' . ($status['healthy'] ? 'OK' : 'ISSUES: ' . implode('; ', $status['issues'])) . "\n",
    FILE_APPEND | LOCK_EX
);

echo json_encode($status, JSON_PRETTY_PRINT);
