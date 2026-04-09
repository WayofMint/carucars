<?php
/**
 * CARU CARS — Auto-Sync Check
 * Include this at the top of index.html (renamed to index.php)
 * or call from any page. Checks if inventory-data.js is stale
 * (older than 6 hours) and if a newer CSV exists, rebuilds it.
 * Runs inline — no cron needed, no external dependencies.
 * Takes <100ms when no rebuild needed.
 */

$jsFile  = __DIR__ . '/inventory-data.js';
$lockFile = __DIR__ . '/sync.lock';
$maxAge  = 6 * 3600; // 6 hours — DealerCenter pushes 3x/day

// Quick check: is inventory-data.js fresh enough?
if (file_exists($jsFile) && (time() - filemtime($jsFile)) < $maxAge) {
    return; // Fresh enough, skip
}

// Check if a newer CSV exists than the current JS
$csvFiles = glob(__DIR__ . '/DealerCenter_*.csv');
if (!$csvFiles) return; // No CSVs, nothing to do

// Find newest CSV
usort($csvFiles, fn($a, $b) => filemtime($b) <=> filemtime($a));
$latestCsv = $csvFiles[0];

// If JS exists and is newer than the latest CSV, skip
if (file_exists($jsFile) && filemtime($jsFile) >= filemtime($latestCsv)) {
    return;
}

// Prevent concurrent rebuilds
if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 300) {
    return; // Another rebuild is in progress (lock < 5 min old)
}
file_put_contents($lockFile, date('c'));

// Rebuild: include the full sync script
include __DIR__ . '/sync-inventory.php';

// Clean up lock
@unlink($lockFile);
