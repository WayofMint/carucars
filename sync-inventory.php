<?php
/**
 * CARU CARS — DealerCenter CSV → inventory-data.js Sync
 *
 * Runs as a cron job on Hostinger shared hosting.
 * DealerCenter pushes CSV files via FTP 3x daily.
 * This script finds the most recent CSV, converts it to inventory-data.js.
 *
 * Cron example (every 8 hours):
 *   0 */8 * * * /usr/bin/php /home/u123456789/domains/carucars.com/public_html/sync-inventory.php
 *
 * PHP 8.x compatible. No composer dependencies.
 */

declare(strict_types=1);

// ── Configuration ──────────────────────────────────────────────────────────────

define('CSV_PATTERN',    'DealerCenter_*.csv');      // glob pattern for feed files
define('OUTPUT_FILE',    __DIR__ . '/inventory-data.js');
define('LOG_FILE',       __DIR__ . '/sync-log.txt');
define('MAX_CSV_KEEP',   3);                          // keep only 3 most recent CSVs

// ── Logging ────────────────────────────────────────────────────────────────────

function logMsg(string $msg): void
{
    $ts = date('Y-m-d H:i:s T');
    $line = "[{$ts}] {$msg}\n";
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

// ── Vehicle Type Classification ────────────────────────────────────────────────
// Mirrors the Python classify_type() logic exactly.

function classifyType(string $model, string $equipment): string
{
    $m = strtolower($model);
    $e = strtolower($equipment);

    // Trucks
    $truckWords = ['crew cab','supercab','pickup','silverado','sierra','colorado',
                   'ranger','f-150','ram 1500','tacoma','frontier'];
    foreach ($truckWords as $w) {
        if (str_contains($m, $w)) return 'Truck';
    }

    // Minivan
    $minivanWords = ['odyssey','caravan','sienna','minivan','pacifica'];
    foreach ($minivanWords as $w) {
        if (str_contains($m, $w)) return 'Minivan';
    }

    // Convertible
    $convertWords = ['convertible','cabriolet','roadster'];
    foreach ($convertWords as $w) {
        if (str_contains($m, $w)) return 'Convertible';
    }

    // Coupe
    $coupeWords = ['coupe','2d'];
    foreach ($coupeWords as $w) {
        if (str_contains($m, $w)) return 'Coupe';
    }

    // Sedan
    $sedanWords = ['sedan','civic','corolla','sentra','malibu','camry','accord',
                   'charger','altima','ghibli','c-class','ats','jetta'];
    foreach ($sedanWords as $w) {
        if (str_contains($m, $w)) return 'Sedan';
    }

    // SUV (third-row check in equipment)
    $suvLargeWords = ['pilot','tahoe','traverse','atlas','explorer','expedition',
                      'suburban','highlander','pathfinder'];
    if (str_contains($e, 'third row')) return 'SUV';
    foreach ($suvLargeWords as $w) {
        if (str_contains($m, $w)) return 'SUV';
    }

    // SUV (crossovers)
    $suvWords = ['suv','sport utility','trax','equinox','tucson','cr-v','rav4',
                 'rogue','escape','compass','renegade','terrain','encore','kona',
                 'acadia','discovery','500x','grand cherokee'];
    foreach ($suvWords as $w) {
        if (str_contains($m, $w)) return 'SUV';
    }

    // Default
    return 'SUV';
}

// ── HTML-Escape Helper ─────────────────────────────────────────────────────────

function esc(string $val): string
{
    return htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── Find Most Recent CSV ───────────────────────────────────────────────────────

function findLatestCsv(): ?string
{
    $files = glob(__DIR__ . '/' . CSV_PATTERN);
    if (!$files) return null;

    // Sort by filename descending (date in name YYYYMMDD gives natural sort)
    // Fall back to file modification time if filenames don't parse cleanly
    usort($files, function (string $a, string $b): int {
        // Try to extract YYYYMMDD from filename
        $dateA = 0;
        $dateB = 0;
        if (preg_match('/DealerCenter_(\d{8})/', basename($a), $ma)) {
            $dateA = (int) $ma[1];
        }
        if (preg_match('/DealerCenter_(\d{8})/', basename($b), $mb)) {
            $dateB = (int) $mb[1];
        }

        // If both have parseable dates, use those; otherwise fall back to mtime
        if ($dateA && $dateB) {
            return $dateB <=> $dateA; // descending
        }
        return filemtime($b) <=> filemtime($a); // descending
    });

    return $files[0];
}

// ── Parse CSV Into Vehicles Array ──────────────────────────────────────────────

function parseCsv(string $csvPath): array
{
    $vehicles = [];

    // DealerCenter exports CP1252 encoding (Spanish accents break strict UTF-8).
    // Read the whole file, convert to UTF-8, then parse from memory.
    $raw = file_get_contents($csvPath);
    if ($raw === false) {
        logMsg("ERROR: Cannot read CSV file: {$csvPath}");
        return [];
    }
    if (!mb_check_encoding($raw, 'UTF-8')) {
        $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    }
    $fh = tmpfile();
    if ($fh === false) {
        logMsg("ERROR: Cannot create tmpfile for CSV parsing");
        return [];
    }
    fwrite($fh, $raw);
    fseek($fh, 0);

    // Read header row
    $header = fgetcsv($fh);
    if ($header === false) {
        fclose($fh);
        logMsg("ERROR: CSV file is empty or has no header: {$csvPath}");
        return [];
    }

    // Trim BOM and whitespace from headers
    $header = array_map(function (string $h): string {
        return trim($h, "\xEF\xBB\xBF \t\n\r\0\x0B");
    }, $header);

    // Build column index map
    $colMap = array_flip($header);

    while (($row = fgetcsv($fh)) !== false) {
        // Skip rows with wrong column count
        if (count($row) !== count($header)) continue;

        // Helper to get column value by name
        $get = function (string $col) use ($row, $colMap): string {
            if (!isset($colMap[$col])) return '';
            return trim((string) ($row[$colMap[$col]] ?? ''));
        };

        $price = (int) ($get('SpecialPrice') ?: '0');

        // Filter out $0 price vehicles
        if ($price <= 0) continue;

        // Split photos — space-separated in the CSV
        $photoRaw = $get('PhotoURLs');
        $photos = [];
        if ($photoRaw !== '') {
            foreach (explode(' ', $photoRaw) as $p) {
                $p = trim($p);
                if ($p !== '' && str_starts_with($p, 'http')) {
                    $photos[] = $p;
                }
            }
        }

        // Parse equipment — double-space separated in the CSV
        $equipRaw = $get('EquipmentCode');
        $equipment = [];
        if ($equipRaw !== '') {
            foreach (preg_split('/\s{2,}/', $equipRaw) as $e) {
                $e = trim($e);
                if ($e !== '') $equipment[] = $e;
            }
        }

        // Clean description — remove encoding artifacts
        $desc = $get('WebAdDescription');
        $desc = str_replace(["\xEF\xBF\xBD", '�'], '', $desc);

        $model = $get('Model');

        $vehicles[] = [
            'stock'        => esc($get('StockNumber')),
            'vin'          => esc($get('VIN')),
            'year'         => (int) ($get('Year') ?: '0'),
            'make'         => esc($get('Make')),
            'model'        => esc($model),
            'miles'        => (int) ($get('Odometer') ?: '0'),
            'price'        => $price,
            'extColor'     => esc($get('ExteriorColor')),
            'intColor'     => esc($get('InteriorColor')),
            'transmission' => esc($get('Transmission')),
            'photos'       => $photos,
            'img'          => $photos[0] ?? '',
            'equipment'    => $equipment,
            'description'  => $desc,
            'type'         => classifyType($model, $equipRaw),
        ];
    }

    fclose($fh);

    // Sort by year descending, then price descending
    usort($vehicles, function (array $a, array $b): int {
        if ($a['year'] !== $b['year']) {
            return $b['year'] <=> $a['year'];
        }
        return $b['price'] <=> $a['price'];
    });

    return $vehicles;
}

// ── Write inventory-data.js ────────────────────────────────────────────────────

function writeJs(array $vehicles): bool
{
    $count = count($vehicles);
    if ($count < 5) {
        logMsg("ABORT: only {$count} vehicles — refusing to overwrite inventory-data.js");
        return false;
    }
    $json  = json_encode($vehicles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

    if ($json === false) {
        logMsg("ERROR: JSON encode failed — " . json_last_error_msg());
        return false;
    }

    // Match Python's 2-space indent (PHP defaults to 4-space)
    $json = preg_replace_callback('/^(    +)/m', function (array $m): string {
        return str_repeat('  ', (int) (strlen($m[1]) / 4));
    }, $json);

    $js = <<<JS
/* ============================================
   CARU CARS — Inventory Data
   Auto-generated from DealerCenter feed
   {$count} vehicles
   ============================================ */

const INVENTORY = {$json};
JS;

    $bytes = file_put_contents(OUTPUT_FILE, $js . "\n", LOCK_EX);
    if ($bytes === false) {
        logMsg("ERROR: Failed to write " . OUTPUT_FILE);
        return false;
    }

    return true;
}

// ── Cleanup Old CSVs ───────────────────────────────────────────────────────────

function cleanupOldCsvs(): int
{
    $files = glob(__DIR__ . '/' . CSV_PATTERN);
    if (!$files || count($files) <= MAX_CSV_KEEP) return 0;

    // Sort newest first (same logic as findLatestCsv)
    usort($files, function (string $a, string $b): int {
        $dateA = 0;
        $dateB = 0;
        if (preg_match('/DealerCenter_(\d{8})/', basename($a), $ma)) {
            $dateA = (int) $ma[1];
        }
        if (preg_match('/DealerCenter_(\d{8})/', basename($b), $mb)) {
            $dateB = (int) $mb[1];
        }
        if ($dateA && $dateB) return $dateB <=> $dateA;
        return filemtime($b) <=> filemtime($a);
    });

    $deleted = 0;
    // Delete everything after the first MAX_CSV_KEEP
    for ($i = MAX_CSV_KEEP; $i < count($files); $i++) {
        if (unlink($files[$i])) {
            $deleted++;
            logMsg("Cleaned up old CSV: " . basename($files[$i]));
        }
    }

    return $deleted;
}

// ── Main ───────────────────────────────────────────────────────────────────────

function main(): void
{
    logMsg("──── Sync started ────");

    // 1. Find latest CSV
    $csv = findLatestCsv();
    if ($csv === null) {
        logMsg("No DealerCenter CSV files found. Nothing to do.");
        logMsg("──── Sync ended (no CSV) ────\n");
        return;
    }

    logMsg("Using CSV: " . basename($csv) . " (" . number_format(filesize($csv)) . " bytes)");

    // 2. Parse CSV
    $vehicles = parseCsv($csv);
    if (empty($vehicles)) {
        logMsg("WARNING: No vehicles parsed from CSV (or all had $0 price).");
        logMsg("──── Sync ended (empty) ────\n");
        return;
    }

    // 3. Write inventory-data.js
    $ok = writeJs($vehicles);
    if (!$ok) {
        logMsg("──── Sync ended (write error) ────\n");
        return;
    }

    // 4. Summary stats
    $makes      = array_unique(array_column($vehicles, 'make'));
    sort($makes);
    $withPhotos = count(array_filter($vehicles, fn($v) => !empty($v['photos'])));
    $types      = array_count_values(array_column($vehicles, 'type'));
    ksort($types);

    logMsg(sprintf(
        "SUCCESS: %d vehicles written to inventory-data.js",
        count($vehicles)
    ));
    logMsg("  Makes: " . implode(', ', $makes));
    logMsg("  With photos: {$withPhotos}/" . count($vehicles));
    logMsg("  Types: " . implode(', ', array_map(
        fn($t, $c) => "{$t}({$c})",
        array_keys($types),
        array_values($types)
    )));

    // 5. Cleanup old CSVs
    $deleted = cleanupOldCsvs();
    if ($deleted > 0) {
        logMsg("Deleted {$deleted} old CSV file(s), kept " . MAX_CSV_KEEP . " most recent.");
    }

    logMsg("──── Sync completed ────\n");
}

// Run
main();
