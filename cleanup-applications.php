<?php
/**
 * CARU CARS — Auto-delete credit application PDFs older than 30 days.
 * Credit apps contain SSN + driver's license — must not sit on the server forever.
 * Run via Hostinger cron: GET https://.../cleanup-applications.php?key=carucars-cleanup-2026
 * Recommended schedule: daily at 3 AM.
 */
if (($_GET['key'] ?? '') !== 'carucars-cleanup-2026') {
    http_response_code(403);
    die('Forbidden');
}

$dir = __DIR__ . '/applications';
$cutoff = time() - (30 * 86400); // 30 days
$deleted = 0;
$kept = 0;

if (is_dir($dir)) {
    foreach (glob($dir . '/*.pdf') as $file) {
        if (filemtime($file) < $cutoff) {
            if (@unlink($file)) $deleted++;
        } else {
            $kept++;
        }
    }
}

file_put_contents(__DIR__ . '/sync-log.txt',
    '[' . date('Y-m-d H:i:s') . "] cleanup: deleted={$deleted} kept={$kept}\n",
    FILE_APPEND | LOCK_EX
);

header('Content-Type: application/json');
echo json_encode(['deleted' => $deleted, 'kept' => $kept]);
