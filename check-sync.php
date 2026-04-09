<?php
/**
 * Lightweight sync checker — called as a hidden pixel from every page.
 * If inventory-data.js is stale and a newer CSV exists, rebuilds it.
 * Returns a 1x1 transparent GIF so it works as an <img> tag.
 */

// Run the auto-sync check
include __DIR__ . '/auto-sync.php';

// Return 1x1 transparent GIF
header('Content-Type: image/gif');
header('Cache-Control: no-cache, no-store, must-revalidate');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
