<?php
/**
 * CARU CARS — Finance Application PDF Generator + Email Sender
 * Receives form data, generates a professional PDF credit application,
 * emails it as an attachment to the sales team.
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || ($input['key'] ?? '') !== 'carucars-app-2026') {
    http_response_code(403);
    die('Forbidden');
}

$d = $input;
$dateStr = date('m/d/Y');
$timeStr = date('g:i A');
$name = ($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? '');

// ── Build PDF HTML ──
$pdfHtml = '
<style>
    body { font-family: helvetica; font-size: 10px; color: #000; }
    h1 { font-size: 20px; margin: 0; }
    .header-sub { font-size: 9px; color: #555; }
    .section-bar { background-color: #E63946; color: #fff; padding: 5px 8px; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
    table { width: 100%; border-collapse: collapse; }
    .lbl { background-color: #f0f0f0; border: 1px solid #000; padding: 3px 6px; font-size: 8px; text-transform: uppercase; color: #333; vertical-align: top; width: 25%; }
    .val { border: 1px solid #000; padding: 5px 6px; font-size: 11px; font-weight: bold; vertical-align: top; }
    .val-big { border: 1px solid #000; padding: 6px; font-size: 16px; font-weight: bold; color: #16a34a; }
    .lbl-red { background-color: #E63946; color: #fff; border: 1px solid #000; padding: 3px 6px; font-size: 8px; text-transform: uppercase; font-weight: bold; vertical-align: top; width: 25%; }
    .sig-line { border-top: 1px solid #000; padding-top: 5px; font-size: 9px; text-align: center; margin-top: 25px; }
    .footer { font-size: 8px; color: #666; text-align: center; margin-top: 10px; }
</style>

<table><tr>
<td style="width:60%;vertical-align:middle;">
    <h1>CARUCARS LLC</h1>
    <span class="header-sub">9600 NW 7th Ave, Miami FL 33150 &bull; (786) 428-4008</span>
</td>
<td style="width:40%;text-align:right;vertical-align:middle;">
    <b style="font-size:14px;">CREDIT APPLICATION</b><br>
    <span class="header-sub">Date: ' . $dateStr . ' &bull; Time: ' . $timeStr . '</span>
</td>
</tr></table>
<br>

<div class="section-bar">Section A &mdash; Applicant Information</div>
<table>
<tr><td class="lbl">Last Name</td><td class="val">' . htmlspecialchars($d['last_name'] ?? '') . '</td>
    <td class="lbl">First Name</td><td class="val">' . htmlspecialchars($d['first_name'] ?? '') . '</td></tr>
<tr><td class="lbl">Date of Birth</td><td class="val">' . htmlspecialchars($d['dob'] ?? '') . '</td>
    <td class="lbl">Driver\'s License #</td><td class="val">' . htmlspecialchars($d['drivers_license'] ?? '') . '</td></tr>
<tr><td class="lbl">Phone</td><td class="val">' . htmlspecialchars($d['phone'] ?? '') . '</td>
    <td class="lbl">Email</td><td class="val">' . htmlspecialchars($d['email'] ?? '') . '</td></tr>
</table>
<br>

<div class="section-bar">Section B &mdash; Residence Information</div>
<table>
<tr><td class="lbl">Street Address</td><td class="val" colspan="3">' . htmlspecialchars($d['address'] ?? '') . '</td></tr>
<tr><td class="lbl">City</td><td class="val">' . htmlspecialchars($d['city'] ?? '') . '</td>
    <td class="lbl">State</td><td class="val">' . htmlspecialchars($d['state'] ?? '') . '</td></tr>
<tr><td class="lbl">Zip Code</td><td class="val">' . htmlspecialchars($d['zip'] ?? '') . '</td>
    <td class="lbl">Residence Type</td><td class="val">' . htmlspecialchars($d['residence_type'] ?? '') . '</td></tr>
<tr><td class="lbl">Monthly Rent/Mortgage</td><td class="val">$' . htmlspecialchars($d['monthly_housing'] ?? '0') . '</td>
    <td class="lbl">Time at Residence</td><td class="val">' . htmlspecialchars($d['residence_years'] ?? '0') . ' yr, ' . htmlspecialchars($d['residence_months'] ?? '0') . ' mo</td></tr>
</table>
<br>

<div class="section-bar">Section C &mdash; Employment &amp; Income</div>
<table>
<tr><td class="lbl">Employer Name</td><td class="val" colspan="3">' . htmlspecialchars($d['employer'] ?? '') . '</td></tr>
<tr><td class="lbl">Occupation / Title</td><td class="val">' . htmlspecialchars($d['occupation'] ?? '') . '</td>
    <td class="lbl">Employment Status</td><td class="val">' . htmlspecialchars($d['employment_status'] ?? '') . '</td></tr>
<tr><td class="lbl">Time on Job</td><td class="val">' . htmlspecialchars($d['job_years'] ?? '0') . ' yr, ' . htmlspecialchars($d['job_months'] ?? '0') . ' mo</td>
    <td class="lbl">Employer Phone</td><td class="val">' . htmlspecialchars($d['employer_phone'] ?? '') . '</td></tr>
<tr><td class="lbl">Pay Frequency</td><td class="val">' . htmlspecialchars($d['pay_frequency'] ?? '') . '</td>
    <td class="lbl">Amount per Paycheck</td><td class="val">$' . htmlspecialchars($d['paycheck_amount'] ?? '0') . '</td></tr>
<tr><td class="lbl">Other Monthly Income</td><td class="val" colspan="3">$' . htmlspecialchars($d['other_income'] ?? '0') . '</td></tr>
</table>
<br>

<div class="section-bar">Section D &mdash; Vehicle &amp; Financing Request</div>
<table>
<tr><td class="lbl">Vehicle of Interest</td><td class="val" colspan="3">' . htmlspecialchars($d['vehicle_interest'] ?? 'Any available') . '</td></tr>
<tr><td class="lbl">Desired Loan Amount</td><td class="val">$' . htmlspecialchars($d['loan_amount'] ?? '') . '</td>
    <td class="lbl">Desired Monthly Payment</td><td class="val">$' . htmlspecialchars($d['desired_payment'] ?? '') . '</td></tr>
<tr><td class="lbl-red">DOWN PAYMENT</td><td class="val-big" colspan="3">$' . htmlspecialchars($d['down_payment'] ?? '0') . '</td></tr>
</table>
<br>';

// Trade-In (optional)
if (!empty($d['trade_in'])) {
    $pdfHtml .= '
    <div class="section-bar">Section E &mdash; Trade-In Vehicle</div>
    <table>
    <tr><td class="lbl">Year / Make / Model</td><td class="val">' . htmlspecialchars($d['trade_in']) . '</td>
        <td class="lbl">Mileage</td><td class="val">' . htmlspecialchars($d['trade_mileage'] ?? '') . '</td></tr>
    </table>
    <br>';
}

// Comments (optional)
if (!empty($d['comments'])) {
    $pdfHtml .= '
    <div class="section-bar">Additional Comments</div>
    <table><tr><td class="val" style="padding:8px;font-weight:normal;line-height:1.5;">' . htmlspecialchars($d['comments']) . '</td></tr></table>
    <br>';
}

// Signature lines
$pdfHtml .= '
<br><br>
<table><tr>
<td style="width:50%;"><div class="sig-line">Applicant Signature</div></td>
<td style="width:10%;"></td>
<td style="width:40%;"><div class="sig-line">Date</div></td>
</tr></table>
<br>
<div class="footer">CARUCARS LLC &bull; Buy Here Pay Here &bull; 9600 NW 7th Ave, Miami FL 33150 &bull; (786) 428-4008 &bull; Submitted online ' . $dateStr . ' ' . $timeStr . '</div>';

// ── Generate PDF ──
require_once __DIR__ . '/tcpdf_min.php';

$pdf = new TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
$pdf->SetCreator('CaruCars');
$pdf->SetAuthor('CaruCars LLC');
$pdf->SetTitle('Credit Application - ' . $name);
$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(true, 12);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->AddPage();
$pdf->writeHTML($pdfHtml, true, false, true, false, '');

$pdfContent = $pdf->Output('', 'S'); // Return as string
$pdfFilename = 'CreditApplication_' . preg_replace('/[^a-zA-Z0-9]/', '', $name) . '_' . date('Ymd') . '.pdf';

// ── Send Email with PDF Attachment ──
$to = 'salecarucars@gmail.com';
$subject = 'New Finance Application - ' . $name;
$boundary = md5(time());

$headers = "From: Caru Cars <noreply@carucars.com>\r\n";
$headers .= "Reply-To: " . ($d['email'] ?? 'noreply@carucars.com') . "\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

// Email body (quick summary)
$textBody = "NEW FINANCE APPLICATION\n\n"
    . "Name: {$name}\n"
    . "Phone: " . ($d['phone'] ?? '') . "\n"
    . "Email: " . ($d['email'] ?? '') . "\n"
    . "Vehicle: " . ($d['vehicle_interest'] ?? 'Any') . "\n"
    . "Down Payment: $" . ($d['down_payment'] ?? '0') . "\n"
    . "Paycheck: $" . ($d['paycheck_amount'] ?? '0') . " / " . ($d['pay_frequency'] ?? '') . "\n\n"
    . "Full application attached as PDF. Print and file.\n\n"
    . "-- Caru Cars Website";

$body = "--{$boundary}\r\n";
$body .= "Content-Type: text/plain; charset=UTF-8\r\n";
$body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
$body .= $textBody . "\r\n\r\n";

$body .= "--{$boundary}\r\n";
$body .= "Content-Type: application/pdf; name=\"{$pdfFilename}\"\r\n";
$body .= "Content-Transfer-Encoding: base64\r\n";
$body .= "Content-Disposition: attachment; filename=\"{$pdfFilename}\"\r\n\r\n";
$body .= chunk_split(base64_encode($pdfContent)) . "\r\n";

$body .= "--{$boundary}--";

$sent = @mail($to, $subject, $body, $headers);

// Log
file_put_contents(__DIR__ . '/sync-log.txt',
    '[' . date('Y-m-d H:i:s') . "] PDF application for {$name}: email " . ($sent ? 'SENT' : 'FAILED') . "\n",
    FILE_APPEND | LOCK_EX
);

header('Content-Type: application/json');
echo json_encode(['success' => $sent, 'pdf' => $pdfFilename]);
