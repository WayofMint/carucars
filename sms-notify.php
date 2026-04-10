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

// Send to dealer phone (update this number)
$dealerPhone = '7864284008';

// Using Textbelt (free tier: 1/day, paid: unlimited at $0.01/sms)
// To upgrade: replace 'textbelt' key with a paid key from textbelt.com
$ch = curl_init('https://textbelt.com/text');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'phone' => $dealerPhone,
        'message' => $msg,
        'key' => 'textbelt', // free tier: 1 sms/day. Buy key at textbelt.com for unlimited ($0.01/sms)
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);
$result = curl_exec($ch);
curl_close($ch);

// Log
file_put_contents(__DIR__ . '/sync-log.txt',
    '[' . date('Y-m-d H:i:s') . "] SMS sent for {$name}: {$result}\n",
    FILE_APPEND | LOCK_EX
);

header('Content-Type: application/json');
echo $result ?: '{"success":false}';
