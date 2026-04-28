<?php
/**
 * CARU CARS — SMS Lead Alerts via TextBelt
 * Fans out a single lead to multiple staff phone numbers.
 * Called server-side from send-application.php and client-side from
 * contact.html, apply.html, and maria-chat.js.
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || ($input['key'] ?? '') !== 'carucars-sms-2026') {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'forbidden']));
}

$TEXTBELT_KEY = '14b396b521b16b9d54ae8e4ffaa3d5c607163db8ECv4sLWu0pX8sSSArY5HjYKT1';

// Recipients — add the other 3 numbers when ready
$RECIPIENTS = [
    ['name' => 'Osvaldo Rodriguez', 'phone' => '+13059654109'],
    ['name' => 'Armani',            'phone' => '+13059727159'],
];

$d = $input;
$source    = $d['source']           ?? 'Website';
$firstName = $d['first_name']       ?? '';
$lastName  = $d['last_name']        ?? '';
$name      = trim($firstName . ' ' . $lastName);
if ($name === '' && !empty($d['name'])) $name = $d['name'];
$phone     = $d['phone']            ?? '';
$email     = $d['email']            ?? '';
$vehicle   = $d['vehicle_interest'] ?? '';
$down      = $d['down_payment']     ?? '';
$pdfUrl    = $d['pdf_url']          ?? '';
$message   = $d['message']          ?? '';

// Build SMS body — sender name "CaruCars" required by TextBelt for new recipients
$lines = ['CaruCars: New ' . $source . ' lead'];
if ($name)    $lines[] = $name;
if ($phone)   $lines[] = 'Call: ' . $phone;
if ($vehicle) $lines[] = 'Vehicle: ' . $vehicle;
if ($down)    $lines[] = 'Down: $' . $down;
if ($pdfUrl)  $lines[] = 'PDF: ' . $pdfUrl;
if ($message && !$pdfUrl && !$vehicle) {
    // For contact form / chat popup leads — include short message snippet
    $snippet = substr(trim(preg_replace('/\s+/', ' ', $message)), 0, 100);
    if ($snippet) $lines[] = 'Msg: ' . $snippet;
}
$body = implode("\n", $lines);

// Fan out — sequential curl. ~200ms per send, fine for 5 recipients.
$results = [];
foreach ($RECIPIENTS as $r) {
    $ch = curl_init('https://textbelt.com/text');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'phone'   => $r['phone'],
            'message' => $body,
            'key'     => $TEXTBELT_KEY,
            'sender'  => 'CaruCars',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $results[$r['name']] = json_decode($raw, true) ?: ['raw' => $raw];
}

file_put_contents(__DIR__ . '/sync-log.txt',
    '[' . date('Y-m-d H:i:s') . '] SMS ' . ($name ?: 'unknown') . ' (' . $source . '): ' . json_encode($results) . "\n",
    FILE_APPEND | LOCK_EX
);

header('Content-Type: application/json');
echo json_encode(['success' => true, 'results' => $results]);
