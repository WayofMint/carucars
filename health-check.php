<?php
/**
 * CARU CARS — Inventory Health Check
 *
 * Reports on the DealerCenter → carucars.com pipeline. Used by scheduled
 * monitors to detect when customer-visible inventory is broken or stale.
 *
 * Checks three things:
 *   1. DealerCenter CSV freshness on Hostinger (is DC still pushing?)
 *   2. The /check-sync.php endpoint (can we parse the CSV?)
 *   3. carucars.com/inventory-data.js (what customers actually see)
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (($_GET['key'] ?? '') !== 'carucars-health-2026') {
    http_response_code(403);
    die(json_encode(['error' => 'Forbidden']));
}

$status = [
    'timestamp' => date('c'),
    'csv'        => ['present' => false, 'latest' => null, 'age_hours' => null],
    'inventory_feed'   => ['reachable' => false, 'vehicle_count' => 0],
    'production' => ['reachable' => false, 'vehicle_count' => 0],
    'healthy'    => false,
    'issues'     => [],
];

// 1. CSV freshness on Hostinger
$csvs = glob(__DIR__ . '/DealerCenter_*.csv');
if ($csvs) {
    usort($csvs, fn($a, $b) => filemtime($b) <=> filemtime($a));
    $latest = $csvs[0];
    $status['csv'] = [
        'present'   => true,
        'latest'    => basename($latest),
        'age_hours' => round((time() - filemtime($latest)) / 3600, 1),
    ];
    if ($status['csv']['age_hours'] > 36) {
        $status['issues'][] = sprintf(
            'CSV_STALE: latest DealerCenter CSV is %.1fh old (push broken?)',
            $status['csv']['age_hours']
        );
    }
} else {
    $status['issues'][] = 'CSV_MISSING: no DealerCenter CSV on Hostinger';
}

// 2. check-sync.php endpoint (server-side parse)
$feed = @file_get_contents(
    'https://yellowgreen-emu-225498.hostingersite.com/check-sync.php?key=carucars-sync-2026-x9f4',
    false,
    stream_context_create(['http' => ['timeout' => 10]])
);
if ($feed !== false) {
    $arr = json_decode($feed, true);
    if (is_array($arr)) {
        $status['inventory_feed'] = ['reachable' => true, 'vehicle_count' => count($arr)];
        if (count($arr) < 5) {
            $status['issues'][] = 'FEED_TOO_FEW: check-sync.php returned only ' . count($arr) . ' vehicles';
        }
    } else {
        $status['issues'][] = 'FEED_INVALID: check-sync.php did not return a JSON array';
    }
} else {
    $status['issues'][] = 'FEED_UNREACHABLE: check-sync.php failed';
}

// 3. Production — what customers see on carucars.com
$prod = @file_get_contents(
    'https://carucars.com/inventory-data.js?cb=' . time(),
    false,
    stream_context_create(['http' => ['timeout' => 10, 'header' => 'Cache-Control: no-cache']])
);
if ($prod !== false && preg_match('/const\s+INVENTORY\s*=\s*(\[.*\]);/s', $prod, $m)) {
    $arr = json_decode($m[1], true);
    if (is_array($arr)) {
        $status['production'] = ['reachable' => true, 'vehicle_count' => count($arr)];
        if (count($arr) < 5) {
            $status['issues'][] = 'PROD_TOO_FEW: carucars.com shows only ' . count($arr) . ' vehicles';
        }
    } else {
        $status['issues'][] = 'PROD_UNPARSEABLE: carucars.com inventory-data.js has invalid JSON';
    }
} else {
    $status['issues'][] = 'PROD_BROKEN: carucars.com/inventory-data.js missing or malformed';
}

$status['healthy'] = empty($status['issues']);

echo json_encode($status, JSON_PRETTY_PRINT);
