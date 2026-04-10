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

// Send to all 3 dealer phones
$phones = ['3059654109', '3056090055', '7868970167'];

// Using Textbelt (free tier: 1/day total, paid: unlimited at $0.01/sms)
// 3 numbers x multiple apps/day = need paid key. Buy at textbelt.com ($0.01/sms)
// Replace 'textbelt' with your paid key below
$results = [];
foreach ($phones as $ph) {
    $ch = curl_init('https://textbelt.com/text');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'phone' => $ph,
            'message' => $msg,
            'key' => 'textbelt',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $results[$ph] = curl_exec($ch);
    curl_close($ch);
}

// Log
file_put_contents(__DIR__ . '/sync-log.txt',
    '[' . date('Y-m-d H:i:s') . "] SMS sent for {$name} to 3 numbers: " . json_encode($results) . "\n",
    FILE_APPEND | LOCK_EX
);

header('Content-Type: application/json');
echo json_encode(['success' => true, 'sent_to' => count($phones)]);
