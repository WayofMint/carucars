<?php
/**
 * CARU CARS — Send ADF/XML Lead to DealerCenter CRM
 * Uses Hostinger SMTP (authenticated) for reliable delivery.
 * DCID: 12327535
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || ($input['key'] ?? '') !== 'carucars-crm-2026') {
    http_response_code(403);
    die(json_encode(['success' => false]));
}

$d = $input;
$source = $d['source'] ?? 'Website';
$now = date('c');

// Parse name
$firstName = $d['first_name'] ?? '';
$lastName = $d['last_name'] ?? '';
if (empty($lastName) && !empty($d['name'])) {
    $parts = explode(' ', trim($d['name']), 2);
    $firstName = $parts[0] ?? '';
    $lastName = $parts[1] ?? '';
}
$email = $d['email'] ?? '';
$phone = $d['phone'] ?? '';

// Build comments
$comments = [];
if (!empty($d['vehicle_interest'])) $comments[] = "Vehicle: " . $d['vehicle_interest'];
if (!empty($d['down_payment'])) $comments[] = "Down: $" . $d['down_payment'];
if (!empty($d['loan_amount'])) $comments[] = "Loan: $" . $d['loan_amount'];
if (!empty($d['desired_payment'])) $comments[] = "Monthly: $" . $d['desired_payment'];
if (!empty($d['ssn'])) $comments[] = "SSN: " . $d['ssn'];
if (!empty($d['drivers_license'])) $comments[] = "DL: " . $d['drivers_license'] . (!empty($d['drivers_license_state']) ? " (" . $d['drivers_license_state'] . ")" : "");
if (!empty($d['employer'])) $comments[] = "Employer: " . $d['employer'] . " (" . ($d['occupation'] ?? '') . ")";
if (!empty($d['employment_status'])) $comments[] = "Status: " . $d['employment_status'];
if (!empty($d['paycheck_amount'])) $comments[] = "Pay: $" . $d['paycheck_amount'] . "/" . ($d['pay_frequency'] ?? '');
if (!empty($d['form_of_income'])) $comments[] = "Form of Income: " . $d['form_of_income'];
if (!empty($d['address'])) $comments[] = "Addr: " . $d['address'] . ", " . ($d['city'] ?? '') . " " . ($d['state'] ?? '') . " " . ($d['zip'] ?? '');
if (!empty($d['residence_type'])) $comments[] = "Housing: " . $d['residence_type'] . " $" . ($d['monthly_housing'] ?? '0') . "/mo";
if (!empty($d['trade_in'])) $comments[] = "Trade: " . $d['trade_in'];
if (!empty($d['comments'])) $comments[] = $d['comments'];
if (!empty($d['message'])) $comments[] = $d['message'];
$commentStr = implode("\n", $comments);

// Vehicle
$e = function($s) { return htmlspecialchars($s, ENT_XML1, 'UTF-8'); };
$vYear = $vMake = $vModel = '';
if (!empty($d['vehicle_interest'])) {
    $vp = explode(' ', trim($d['vehicle_interest']), 3);
    if (is_numeric($vp[0] ?? '') && strlen($vp[0]) === 4) {
        $vYear = $vp[0]; $vMake = $vp[1] ?? ''; $vModel = $vp[2] ?? '';
    }
}

// ADF/XML — must be the ENTIRE email body, Content-Type text/plain
$adf = '<?xml version="1.0" encoding="UTF-8"?>
<?adf version="1.0"?>
<adf>
  <prospect>
    <requestdate>' . $e($now) . '</requestdate>
    <vehicle interest="buy" status="used">' .
($vYear ? "\n      <year>" . $e($vYear) . "</year>" : '') .
($vMake ? "\n      <make>" . $e($vMake) . "</make>" : '') .
($vModel ? "\n      <model>" . $e($vModel) . "</model>" : '') . '
    </vehicle>
    <customer>
      <contact>
        <name part="first">' . $e($firstName) . '</name>
        <name part="last">' . $e($lastName) . '</name>' .
($email ? "\n        <email>" . $e($email) . "</email>" : '') .
($phone ? "\n        <phone type=\"phone\">" . $e($phone) . "</phone>" : '') . '
      </contact>' .
($commentStr ? "\n      <comments>" . $e($commentStr) . "</comments>" : '') . '
    </customer>
    <vendor>
      <id>12327535</id>
      <vendorname>CARUCARS LLC</vendorname>
      <contact>
        <name part="full">CARUCARS LLC</name>
        <phone type="phone">7864284008</phone>
      </contact>
    </vendor>
    <provider>
      <name part="full">CaruCars Website - ' . $e($source) . '</name>
    </provider>
  </prospect>
</adf>';

// Send via mail() with text/plain content type (ADF spec requirement)
$to = '12327535@leadsprod.dealercenter.net';
$subject = 'New ADF Lead';
$headers = "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "MIME-Version: 1.0\r\n";

$sent = @mail($to, $subject, $adf, $headers);

$name = trim($firstName . ' ' . $lastName);
file_put_contents(__DIR__ . '/sync-log.txt',
    '[' . date('Y-m-d H:i:s') . '] CRM ADF ' . $name . ' (' . $source . '): ' . ($sent ? 'SENT' : 'FAILED') . "\n",
    FILE_APPEND | LOCK_EX
);

header('Content-Type: application/json');
echo json_encode(['success' => $sent, 'name' => $name]);
