<?php
/**
 * CARU CARS — Inventory Health Check
 * Verifies inventory-data.js is fresh, syntactically valid, and has real vehicles.
 * Returns JSON status. Called by external scheduled task.
 *
 * NOTE: The customer-facing inventory is served by Netlify from the GitHub repo,
 * not from Hostinger. This endpoint checks both the Hostinger copy (served as
 * fallback) AND the production carucars.com copy.
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

function parseVehicleCount(string $js): int {
    // Actually parse the JS const, not the comment header.
    if (!preg_match('/const\s+INVENTORY\s*=\s*(\[.*?\]);?\s*$/s', $js, $m)) {
        return -1; // syntax-invalid
    }
    $arr = json_decode($m[1], true);
    if (!is_array($arr)) return -1;
    return count($arr);
}

$status = [
    'timestamp' => date('c'),
    'hostinger' => [
        'file_exists' => file_exists($jsFile),
        'file_size' => file_exists($jsFile) ? filesize($jsFile) : 0,
        'age_hours' => null,
        'modified' => null,
        'vehicle_count' => -1,
        'parseable' => false,
    ],
    'latest_csv' => null,
    'latest_csv_modified' => null,
    'csv_count' => count($csvFiles),
    'production' => null,
    'healthy' => false,
    'issues' => [],
];

// 1. Check Hostinger-local inventory-data.js (fallback copy)
if (file_exists($jsFile)) {
    $ageSeconds = time() - filemtime($jsFile);
    $status['hostinger']['age_hours'] = round($ageSeconds / 3600, 1);
    $status['hostinger']['modified'] = date('c', filemtime($jsFile));

    $js = file_get_contents($jsFile);
    $count = parseVehicleCount($js);
    $status['hostinger']['vehicle_count'] = $count;
    $status['hostinger']['parseable'] = $count >= 0;

    if ($count < 0) {
        $status['issues'][] = 'HOSTINGER JS IS SYNTACTICALLY INVALID - cannot parse INVENTORY array';
    } elseif ($count < 5) {
        $status['issues'][] = sprintf('HOSTINGER JS HAS ONLY %d vehicles - likely broken', $count);
    }
} else {
    $status['issues'][] = 'HOSTINGER inventory-data.js does not exist';
}

// 2. Check CSV freshness (DealerCenter FTP push health)
if (!$csvFiles) {
    $status['issues'][] = 'NO CSV FILES - DealerCenter FTP push may be broken';
} else {
    usort($csvFiles, fn($a, $b) => filemtime($b) - filemtime($a));
    $latestCsv = $csvFiles[0];
    $status['latest_csv'] = basename($latestCsv);
    $status['latest_csv_modified'] = date('c', filemtime($latestCsv));
    $csvAgeHours = (time() - filemtime($latestCsv)) / 3600;

    if ($csvAgeHours > 36) {
        $status['issues'][] = sprintf(
            'CSV STALE - latest CSV is %.1f hours old (DealerCenter push may be broken)',
            $csvAgeHours
        );
    }
}

// 3. Check production carucars.com inventory (what customers actually see)
$ctx = stream_context_create(['http' => ['timeout' => 10, 'header' => 'Cache-Control: no-cache']]);
$prodJs = @file_get_contents('https://carucars.com/inventory-data.js?cachebust=' . time(), false, $ctx);
if ($prodJs === false) {
    $status['issues'][] = 'PRODUCTION FETCH FAILED - could not reach carucars.com/inventory-data.js';
    $status['production'] = ['reachable' => false];
} else {
    $prodCount = parseVehicleCount($prodJs);
    $status['production'] = [
        'reachable' => true,
        'size' => strlen($prodJs),
        'vehicle_count' => $prodCount,
        'parseable' => $prodCount >= 0,
    ];
    if ($prodCount < 0) {
        $status['issues'][] = 'PRODUCTION JS IS SYNTACTICALLY INVALID on carucars.com';
    } elseif ($prodCount < 5) {
        $status['issues'][] = sprintf('PRODUCTION shows only %d vehicles on carucars.com', $prodCount);
    }
}

$status['healthy'] = empty($status['issues']);

// Log health check
file_put_contents(__DIR__ . '/sync-log.txt',
    '[' . date('Y-m-d H:i:s') . '] HEALTH: ' . ($status['healthy'] ? 'OK' : 'ISSUES: ' . implode('; ', $status['issues'])) . "\n",
    FILE_APPEND | LOCK_EX
);

echo json_encode($status, JSON_PRETTY_PRINT);
