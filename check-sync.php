<?php
/**
 * CARU CARS — Inventory Feed (lives at this filename because it's the only
 * PHP path Hostinger's caches let us update reliably).
 *
 * Reads the latest DealerCenter_*.csv on disk and returns a JSON vehicle
 * array. The sync workflow fetches this, wraps it as inventory-data.js,
 * and commits to the repo. Netlify redeploys on commit.
 */
declare(strict_types=1);

if (($_GET['key'] ?? '') !== 'carucars-sync-2026-x9f4') {
    http_response_code(403);
    die('Forbidden');
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, must-revalidate');

function classify(string $model, string $equipment): string {
    $m = strtolower($model);
    $e = strtolower($equipment);
    $match = function (string $hay, array $needles): bool {
        foreach ($needles as $n) if (str_contains($hay, $n)) return true;
        return false;
    };
    if ($match($m, ['crew cab','supercab','pickup','silverado','sierra','colorado','ranger','f-150','ram 1500','tacoma','frontier'])) return 'Truck';
    if ($match($m, ['odyssey','caravan','sienna','minivan','pacifica'])) return 'Minivan';
    if ($match($m, ['convertible','cabriolet','roadster'])) return 'Convertible';
    if ($match($m, ['coupe','2d'])) return 'Coupe';
    if ($match($m, ['sedan','civic','corolla','sentra','malibu','camry','accord','charger','altima','ghibli','c-class','ats','jetta'])) return 'Sedan';
    if (str_contains($e, 'third row')) return 'SUV';
    if ($match($m, ['pilot','tahoe','traverse','atlas','explorer','expedition','suburban','highlander','pathfinder'])) return 'SUV';
    if ($match($m, ['suv','sport utility','trax','equinox','tucson','cr-v','rav4','rogue','escape','compass','renegade','terrain','encore','kona','acadia','discovery','500x','grand cherokee'])) return 'SUV';
    return 'SUV';
}

$csvs = glob(__DIR__ . '/DealerCenter_*.csv');
if (!$csvs) {
    http_response_code(503);
    die(json_encode(['error' => 'No DealerCenter CSV found']));
}
usort($csvs, fn($a, $b) => filemtime($b) <=> filemtime($a));
$csv = $csvs[0];

$raw = file_get_contents($csv);
if ($raw === false) {
    http_response_code(500);
    die(json_encode(['error' => 'Cannot read CSV']));
}
if (!mb_check_encoding($raw, 'UTF-8')) {
    $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
}

$lines = preg_split('/\r\n|\n|\r/', $raw);
$header = null;
$vehicles = [];
foreach ($lines as $line) {
    if ($line === '') continue;
    $row = str_getcsv($line);
    if ($header === null) {
        $header = array_map(fn($h) => trim($h, "\xEF\xBB\xBF \t\n\r\0\x0B"), $row);
        continue;
    }
    if (count($row) !== count($header)) continue;
    $r = array_combine($header, $row);

    $price = (int) ($r['SpecialPrice'] ?? 0);
    if ($price <= 0) continue;

    $photos = [];
    foreach (explode(' ', $r['PhotoURLs'] ?? '') as $p) {
        $p = trim($p);
        if ($p !== '' && str_starts_with($p, 'http')) $photos[] = $p;
    }

    $equipRaw = $r['EquipmentCode'] ?? '';
    $equipment = array_values(array_filter(
        array_map('trim', preg_split('/\s{2,}/', $equipRaw)),
        fn($e) => $e !== ''
    ));

    $model = trim($r['Model'] ?? '');
    $vehicles[] = [
        'stock'        => trim($r['StockNumber'] ?? ''),
        'vin'          => trim($r['VIN'] ?? ''),
        'year'         => (int) ($r['Year'] ?? 0),
        'make'         => trim($r['Make'] ?? ''),
        'model'        => $model,
        'miles'        => (int) ($r['Odometer'] ?? 0),
        'price'        => $price,
        'extColor'     => trim($r['ExteriorColor'] ?? ''),
        'intColor'     => trim($r['InteriorColor'] ?? ''),
        'transmission' => trim($r['Transmission'] ?? ''),
        'photos'       => $photos,
        'img'          => $photos[0] ?? '',
        'equipment'    => $equipment,
        'description'  => str_replace(["\xEF\xBF\xBD", "\u{FFFD}"], '', $r['WebAdDescription'] ?? ''),
        'type'         => classify($model, $equipRaw),
    ];
}

usort($vehicles, fn($a, $b) =>
    $a['year'] !== $b['year'] ? $b['year'] <=> $a['year'] : $b['price'] <=> $a['price']
);

echo json_encode(
    $vehicles,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
);
