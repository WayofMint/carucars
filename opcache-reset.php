<?php
/**
 * Force-clear PHP OPcache. Call after deploying new PHP files so edits take
 * effect immediately instead of after a minute-long bytecode TTL.
 */
$secret = 'carucars-sync-2026-x9f4';
if (php_sapi_name() !== 'cli' && ($_GET['key'] ?? '') !== $secret) {
    http_response_code(403);
    die('Forbidden');
}
header('Content-Type: text/plain');
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "opcache_reset: OK\n";
} else {
    echo "opcache not available\n";
}
