<?php
/**
 * CARU CARS — SMS Notification for Finance Applications
 * Called by apply.html after form submission.
 * Uses Textbelt free API (1 SMS/day free) or can be swapped for Twilio.
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || ($input['key'] ?? '') !== 'carucars-sms-2026') {
    http_response_code(403);
    die('Forbidden');
}

$name = $input['name'] ?? 'Unknown';
$phone = $input['phone'] ?? '';
$vehicle = $input['vehicle'] ?? 'Any';
$down = $input['down'] ?? 'N/A';

// SMS message
$msg = "NEW FINANCE APP - Caru Cars\n"
     . "Name: {$name}\n"
     . "Phone: {$phone}\n"
     . "Vehicle: {$vehicle}\n"
     . "Down: \${$down}\n"
     . "Check email for full application.";

// Send to all 3 dealer phones via Email-to-SMS (free, unlimited, no API key needed)
// Uses carrier email gateways. Works with all major US carriers.
$phones = [
    ['number' => '3059654109', 'name' => 'Phone1'],
    ['number' => '3056090055', 'name' => 'Phone2'],
    ['number' => '7868970167', 'name' => 'Phone3'],
];

// US carrier SMS gateways (email-to-SMS, completely free)
$gateways = [
    '@txt.att.net',           // AT&T
    '@tmomail.net',           // T-Mobile
    '@vtext.com',             // Verizon
    '@messaging.sprintpcs.com', // Sprint/T-Mobile
    '@sms.cricketwireless.net', // Cricket
    '@mymetropcs.com',        // Metro
    '@msg.fi.google.com',     // Google Fi
];

// Short SMS-friendly message
$smsMsg = "CARU CARS NEW APP\n{$name}\nPh: {$phone}\nCar: {$vehicle}\nDown: \${$down}";

$results = [];
foreach ($phones as $ph) {
    $num = $ph['number'];
    // Send to ALL carrier gateways — only the right one delivers, others silently fail
    foreach ($gateways as $gw) {
        $to = $num . $gw;
        $headers = "From: noreply@carucars.com\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        @mail($to, '', $smsMsg, $headers);
    }
    $results[] = $num;
}

// Log
file_put_contents(__DIR__ . '/sync-log.txt',
    '[' . date('Y-m-d H:i:s') . "] SMS (email gateway) sent for {$name} to: " . implode(', ', $results) . "\n",
    FILE_APPEND | LOCK_EX
);

header('Content-Type: application/json');
echo json_encode(['success' => true, 'sent_to' => count($phones)]);
