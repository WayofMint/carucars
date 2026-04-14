<?php
/**
 * Lightweight sync checker — called as a hidden pixel from every page.
 * If inventory-data.js is stale and a newer CSV exists, rebuilds it.
 * Returns a 1x1 transparent GIF so it works as an <img> tag.
 */

$jsFile = __DIR__ . '/inventory-data.js';
$lockFile = __DIR__ . '/sync.lock';
$maxAge = 6 * 3600; // 6 hours

// Output the pixel first (non-blocking for page load)
header('Content-Type: image/gif');
header('Cache-Control: no-cache, no-store, must-revalidate');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

// Flush output so the page doesn't wait
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    @ob_end_flush();
    @flush();
}

// Now do the sync check (after response sent)
try {
    // Quick check: is inventory-data.js fresh enough?
    if (file_exists($jsFile) && (time() - filemtime($jsFile)) < $maxAge) {
        exit; // Fresh, skip
    }

    // Find newest CSV
    $csvFiles = glob(__DIR__ . '/DealerCenter_*.csv');
    if (!$csvFiles) exit;
    usort($csvFiles, function($a, $b) { return filemtime($b) - filemtime($a); });
    $latestCsv = $csvFiles[0];

    // If JS is newer than latest CSV, skip
    if (file_exists($jsFile) && filemtime($jsFile) >= filemtime($latestCsv)) exit;

    // Prevent concurrent rebuilds (lock expires after 5 min)
    if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 300) exit;
    file_put_contents($lockFile, date('c'));

    // Trigger sync by hitting the cron endpoint internally
    $ctx = stream_context_create(['http' => ['timeout' => 30]]);
    @file_get_contents('https://yellowgreen-emu-225498.hostingersite.com/cron-sync.php?key=carucars-sync-2026-x9f4', false, $ctx);

    @unlink($lockFile);
} catch (Throwable $e) {
    @unlink($lockFile);
    @file_put_contents(__DIR__ . '/sync-log.txt',
        '[' . date('Y-m-d H:i:s') . '] check-sync error: ' . $e->getMessage() . "\n",
        FILE_APPEND | LOCK_EX
    );
}
