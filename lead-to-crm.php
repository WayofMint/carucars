<?php
/**
 * CARU CARS — Send ADF/XML Lead to DealerCenter CRM
 * Receives lead data from website forms, formats as ADF/XML,
 * emails to DealerCenter's lead intake address.
 * DCID: 12327535
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || ($input['key'] ?? '') !== 'carucars-crm-2026') {
    http_response_code(403);
    die('Forbidden');
}

$d = $input;
$source = $d['source'] ?? 'Website';
$now = date('c'); // ISO 8601

// Build ADF/XML
$firstName = htmlspecialchars($d['first_name'] ?? $d['name'] ?? '', ENT_XML1, 'UTF-8');
$lastName = htmlspecialchars($d['last_name'] ?? '', ENT_XML1, 'UTF-8');
$email = htmlspecialchars($d['email'] ?? '', ENT_XML1, 'UTF-8');
$phone = htmlspecialchars($d['phone'] ?? '', ENT_XML1, 'UTF-8');

// If only 'name' provided (Maria chat), split it
if (empty($lastName) && !empty($d['name'])) {
    $parts = explode(' ', trim($d['name']), 2);
    $firstName = htmlspecialchars($parts[0] ?? '', ENT_XML1, 'UTF-8');
    $lastName = htmlspecialchars($parts[1] ?? '', ENT_XML1, 'UTF-8');
}

// Build comments from all extra fields
$comments = [];
if (!empty($d['vehicle_interest'])) $comments[] = "Vehicle Interest: " . $d['vehicle_interest'];
if (!empty($d['down_payment'])) $comments[] = "Down Payment: $" . $d['down_payment'];
if (!empty($d['loan_amount'])) $comments[] = "Loan Amount: $" . $d['loan_amount'];
if (!empty($d['desired_payment'])) $comments[] = "Desired Monthly: $" . $d['desired_payment'];
if (!empty($d['employer'])) $comments[] = "Employer: " . $d['employer'];
if (!empty($d['occupation'])) $comments[] = "Occupation: " . $d['occupation'];
if (!empty($d['employment_status'])) $comments[] = "Employment: " . $d['employment_status'];
if (!empty($d['paycheck_amount'])) $comments[] = "Paycheck: $" . $d['paycheck_amount'] . " / " . ($d['pay_frequency'] ?? '');
if (!empty($d['address'])) $comments[] = "Address: " . $d['address'] . ", " . ($d['city'] ?? '') . " " . ($d['state'] ?? '') . " " . ($d['zip'] ?? '');
if (!empty($d['residence_type'])) $comments[] = "Residence: " . $d['residence_type'] . " ($" . ($d['monthly_housing'] ?? '0') . "/mo)";
if (!empty($d['trade_in'])) $comments[] = "Trade-In: " . $d['trade_in'] . " (" . ($d['trade_mileage'] ?? '') . " mi)";
if (!empty($d['comments'])) $comments[] = "Comments: " . $d['comments'];
if (!empty($d['zip']) && empty($d['address'])) $comments[] = "Zip: " . $d['zip'];
if (!empty($d['message'])) $comments[] = "Message: " . $d['message'];
$commentStr = htmlspecialchars(implode("\n", $comments), ENT_XML1, 'UTF-8');

// Vehicle info (if provided)
$vehicleXml = '';
if (!empty($d['vehicle_interest'])) {
    // Try to parse "2024 Nissan Sentra" format
    $v = trim($d['vehicle_interest']);
    $vParts = explode(' ', $v, 3);
    $vYear = (is_numeric($vParts[0] ?? '') && strlen($vParts[0]) === 4) ? $vParts[0] : '';
    $vMake = $vYear ? ($vParts[1] ?? '') : ($vParts[0] ?? '');
    $vModel = $vYear ? ($vParts[2] ?? '') : (implode(' ', array_slice($vParts, 1)) ?: '');

    $vehicleXml = '    <vehicle interest="buy" status="used">';
    if ($vYear) $vehicleXml .= "\n      <year>" . htmlspecialchars($vYear, ENT_XML1) . "</year>";
    if ($vMake) $vehicleXml .= "\n      <make>" . htmlspecialchars($vMake, ENT_XML1) . "</make>";
    if ($vModel) $vehicleXml .= "\n      <model>" . htmlspecialchars($vModel, ENT_XML1) . "</model>";
    $vehicleXml .= "\n    </vehicle>";
} else {
    $vehicleXml = '    <vehicle interest="buy" status="used" />';
}

$adf = '<?xml version="1.0" encoding="UTF-8"?>
<?adf version="1.0"?>
<adf>
  <prospect>
    <requestdate>' . $now . '</requestdate>
' . $vehicleXml . '
    <customer>
      <contact>
        <name part="first">' . $firstName . '</name>
        <name part="last">' . $lastName . '</name>' .
($email ? "\n        <email>" . $email . "</email>" : '') .
($phone ? "\n        <phone type=\"phone\">" . $phone . "</phone>" : '') . '
      </contact>' .
($commentStr ? "\n      <comments>" . $commentStr . "</comments>" : '') . '
    </customer>
    <vendor>
      <id>12327535</id>
      <contact>
        <name part="full">CARUCARS LLC</name>
        <phone type="phone">7864284008</phone>
      </contact>
    </vendor>
    <provider>
      <name part="full">CaruCars Website - ' . htmlspecialchars($source, ENT_XML1) . '</name>
      <email>noreply@carucars.com</email>
    </provider>
  </prospect>
</adf>';

// Send ADF email to DealerCenter
$to = '12327535@leadsprod.dealercenter.net';
$subject = 'New Lead - ' . $firstName . ' ' . $lastName . ' - CaruCars Website';
$headers = "From: CaruCars Website <noreply@carucars.com>\r\n";
$headers .= "Reply-To: " . ($d['email'] ?? 'noreply@carucars.com') . "\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/xml; charset=UTF-8\r\n";

$sent = @mail($to, $subject, $adf, $headers);

// Log
file_put_contents(__DIR__ . '/sync-log.txt',
    '[' . date('Y-m-d H:i:s') . "] ADF lead for {$firstName} {$lastName} ({$source}): " . ($sent ? 'SENT to DealerCenter' : 'FAILED') . "\n",
    FILE_APPEND | LOCK_EX
);

header('Content-Type: application/json');
echo json_encode(['success' => $sent, 'name' => trim($firstName . ' ' . $lastName)]);
