<?php
/**
 * CARU CARS — DealerCenter Auto-Sync
 * Finds latest DealerCenter CSV on server, rebuilds inventory-data.js
 * Runs via cron 3x/day or URL with secret key
 */
$secret = 'carucars-sync-2026-x9f4';
if (php_sapi_name() !== 'cli' && ($_GET['key'] ?? '') !== $secret) { http_response_code(403); die('Forbidden'); }

define('OUTPUT', __DIR__ . '/inventory-data.js');
define('LOGF', __DIR__ . '/sync-log.txt');

function lg($m) { file_put_contents(LOGF, '['.date('Y-m-d H:i:s').'] '.$m."\n", FILE_APPEND|LOCK_EX); }

function classify($model, $equip) {
    $m = strtolower($model); $e = strtolower($equip);
    foreach (['crew cab','supercab','pickup','silverado','sierra','colorado','ranger','f-150','ram 1500','tacoma','frontier'] as $w) if (str_contains($m,$w)) return 'Truck';
    foreach (['odyssey','caravan','sienna','minivan','pacifica'] as $w) if (str_contains($m,$w)) return 'Minivan';
    foreach (['convertible','cabriolet','roadster'] as $w) if (str_contains($m,$w)) return 'Convertible';
    foreach (['coupe','2d'] as $w) if (str_contains($m,$w)) return 'Coupe';
    foreach (['sedan','civic','corolla','sentra','malibu','camry','accord','charger','altima','ghibli','c-class','ats','jetta'] as $w) if (str_contains($m,$w)) return 'Sedan';
    if (str_contains($e,'third row')) return 'SUV';
    foreach (['pilot','tahoe','traverse','atlas','explorer','expedition','suburban','highlander','pathfinder','suv','sport utility','trax','equinox','tucson','cr-v','rav4','rogue','escape','compass','renegade','terrain','encore','kona','acadia','discovery','500x','grand cherokee'] as $w) if (str_contains($m,$w)) return 'SUV';
    return 'SUV';
}

// Find latest CSV
$files = glob(__DIR__.'/DealerCenter_*.csv');
if (!$files) { lg('No CSV found'); echo 'No CSV'; exit; }
usort($files, fn($a,$b) => filemtime($b) <=> filemtime($a));
$csv = $files[0];
lg('Using: '.basename($csv));

// Parse
$fh = fopen($csv,'r'); $hdr = fgetcsv($fh);
$hdr = array_map(fn($h)=>trim($h,"\xEF\xBB\xBF \t\n\r\0\x0B"), $hdr);
$col = array_flip($hdr); $vehicles = [];
while (($r = fgetcsv($fh)) !== false) {
    if (count($r) !== count($hdr)) continue;
    $g = fn($c) => trim((string)($r[$col[$c] ?? -1] ?? ''));
    $price = (int)($g('SpecialPrice') ?: 0); if ($price <= 0) continue;
    $ph = $g('PhotoURLs'); $photos = $ph ? array_values(array_filter(array_map('trim',explode(' ',$ph)), fn($p)=>$p && str_starts_with($p,'http'))) : [];
    $eq = $g('EquipmentCode'); $equip = $eq ? array_values(array_filter(array_map('trim',preg_split('/\s{2,}/',$eq)))) : [];
    $model = $g('Model');
    $vehicles[] = ['stock'=>$g('StockNumber'),'vin'=>$g('VIN'),'year'=>(int)($g('Year')?:0),'make'=>$g('Make'),'model'=>$model,'miles'=>(int)($g('Odometer')?:0),'price'=>$price,'extColor'=>$g('ExteriorColor'),'intColor'=>$g('InteriorColor'),'transmission'=>$g('Transmission'),'photos'=>$photos,'img'=>$photos[0]??'','equipment'=>$equip,'description'=>str_replace(["\xEF\xBF\xBD",''],'',$g('WebAdDescription')),'type'=>classify($model,$eq)];
}
fclose($fh);
usort($vehicles, fn($a,$b) => $a['year']!==$b['year'] ? $b['year']<=>$a['year'] : $b['price']<=>$a['price']);

// Write JS
$n = count($vehicles);
$json = json_encode($vehicles, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
$json = preg_replace_callback('/^(    +)/m', fn($m)=>str_repeat('  ',(int)(strlen($m[1])/4)), $json);
$js = "/* ============================================\n   CARU CARS — Inventory Data\n   Auto-generated from DealerCenter feed\n   {$n} vehicles\n   ============================================ */\n\nconst INVENTORY = {$json};\n";
file_put_contents(OUTPUT, $js, LOCK_EX);

// Cleanup old CSVs (keep 3)
if (count($files) > 3) { for ($i=3;$i<count($files);$i++) @unlink($files[$i]); }

$makes = implode(', ', array_unique(array_column($vehicles,'make')));
lg("OK: {$n} vehicles. Makes: {$makes}");
echo "OK: {$n} vehicles synced from ".basename($csv)."\n";
