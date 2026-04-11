<?php
/**
 * CARU CARS — Finance Application PDF + Email
 * Generates printable credit application PDF, emails as attachment.
 * Uses FPDF (single file, no dependencies).
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
$name = trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? ''));
$dateStr = date('m/d/Y');
$timeStr = date('g:i A');

require_once __DIR__ . '/fpdf.php';

class AppPDF extends FPDF {
    function SectionBar($title) {
        $this->SetFillColor(230, 57, 70);
        $this->SetTextColor(255);
        $this->SetFont('Helvetica', 'B', 9);
        $this->Cell(0, 7, strtoupper($title), 1, 1, 'L', true);
        $this->SetTextColor(0);
    }

    function FieldRow($label1, $val1, $label2 = '', $val2 = '') {
        $w = 95;
        $this->SetFont('Helvetica', '', 7);
        $this->SetFillColor(240, 240, 240);
        $this->Cell($w * 0.4, 5, strtoupper($label1), 1, 0, 'L', true);
        $this->SetFont('Helvetica', 'B', 10);
        $this->Cell($w * 0.6, 5, $val1, 1, 0, 'L');
        if ($label2) {
            $this->SetFont('Helvetica', '', 7);
            $this->Cell($w * 0.4, 5, strtoupper($label2), 1, 0, 'L', true);
            $this->SetFont('Helvetica', 'B', 10);
            $this->Cell($w * 0.6, 5, $val2, 1, 1, 'L');
        } else {
            $this->Cell($w, 5, '', 0, 1);
        }
    }

    function FieldRowWide($label, $val) {
        $this->SetFont('Helvetica', '', 7);
        $this->SetFillColor(240, 240, 240);
        $this->Cell(38, 5, strtoupper($label), 1, 0, 'L', true);
        $this->SetFont('Helvetica', 'B', 10);
        $this->Cell(152, 5, $val, 1, 1, 'L');
    }

    function DownPaymentRow($val) {
        $this->SetFillColor(230, 57, 70);
        $this->SetTextColor(255);
        $this->SetFont('Helvetica', 'B', 7);
        $this->Cell(38, 8, 'DOWN PAYMENT', 1, 0, 'L', true);
        $this->SetTextColor(22, 163, 74);
        $this->SetFont('Helvetica', 'B', 16);
        $this->Cell(152, 8, '$' . $val, 1, 1, 'L');
        $this->SetTextColor(0);
    }
}

$pdf = new AppPDF('P', 'mm', 'Letter');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 10);
$pdf->AddPage();

// Header
$pdf->SetFont('Helvetica', 'B', 20);
$pdf->Cell(95, 8, 'CARUCARS LLC', 0, 0);
$pdf->SetFont('Helvetica', 'B', 14);
$pdf->Cell(95, 8, 'CREDIT APPLICATION', 0, 1, 'R');
$pdf->SetFont('Helvetica', '', 8);
$pdf->SetTextColor(100);
$pdf->Cell(95, 4, '9600 NW 7th Ave, Miami FL 33150 | (786) 428-4008', 0, 0);
$pdf->Cell(95, 4, 'Date: ' . $dateStr . ' | Time: ' . $timeStr, 0, 1, 'R');
$pdf->SetTextColor(0);
$pdf->Ln(4);

// Section A
$pdf->SectionBar('Section A - Applicant Information');
$pdf->FieldRow('Last Name', $d['last_name'] ?? '', 'First Name', $d['first_name'] ?? '');
$pdf->FieldRow('Date of Birth', $d['dob'] ?? '', "Driver's License", $d['drivers_license'] ?? '');
$pdf->FieldRow('Phone', $d['phone'] ?? '', 'Email', $d['email'] ?? '');
$pdf->Ln(3);

// Section B
$pdf->SectionBar('Section B - Residence Information');
$pdf->FieldRowWide('Street Address', $d['address'] ?? '');
$pdf->FieldRow('City', $d['city'] ?? '', 'State', $d['state'] ?? '');
$pdf->FieldRow('Zip Code', $d['zip'] ?? '', 'Residence Type', $d['residence_type'] ?? '');
$pdf->FieldRow('Monthly Rent', '$' . ($d['monthly_housing'] ?? '0'), 'Time at Residence', ($d['residence_years'] ?? '0') . ' yr, ' . ($d['residence_months'] ?? '0') . ' mo');
$pdf->Ln(3);

// Section C
$pdf->SectionBar('Section C - Employment & Income');
$pdf->FieldRowWide('Employer Name', $d['employer'] ?? '');
$pdf->FieldRow('Occupation', $d['occupation'] ?? '', 'Employment Status', $d['employment_status'] ?? '');
$pdf->FieldRow('Time on Job', ($d['job_years'] ?? '0') . ' yr, ' . ($d['job_months'] ?? '0') . ' mo', 'Employer Phone', $d['employer_phone'] ?? '');
$pdf->FieldRow('Pay Frequency', $d['pay_frequency'] ?? '', 'Per Paycheck', '$' . ($d['paycheck_amount'] ?? '0'));
$pdf->FieldRow('Other Income', '$' . ($d['other_income'] ?? '0'), '', '');
$pdf->Ln(3);

// Section D
$pdf->SectionBar('Section D - Vehicle & Financing Request');
$pdf->FieldRowWide('Vehicle of Interest', $d['vehicle_interest'] ?? 'Any available');
$pdf->FieldRow('Loan Amount', '$' . ($d['loan_amount'] ?? ''), 'Monthly Payment', '$' . ($d['desired_payment'] ?? ''));
$pdf->DownPaymentRow($d['down_payment'] ?? '0');
$pdf->Ln(3);

// Section E (if trade-in)
if (!empty($d['trade_in'])) {
    $pdf->SectionBar('Section E - Trade-In Vehicle');
    $pdf->FieldRow('Year/Make/Model', $d['trade_in'], 'Mileage', $d['trade_mileage'] ?? '');
    $pdf->Ln(3);
}

// Comments
if (!empty($d['comments'])) {
    $pdf->SectionBar('Additional Comments');
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->MultiCell(0, 5, $d['comments'], 1);
    $pdf->Ln(3);
}

// Signature lines
$pdf->Ln(10);
$pdf->Cell(90, 0, '', 'T');
$pdf->Cell(10);
$pdf->Cell(80, 0, '', 'T');
$pdf->Ln(4);
$pdf->SetFont('Helvetica', '', 8);
$pdf->Cell(90, 4, 'Applicant Signature', 0, 0, 'C');
$pdf->Cell(10);
$pdf->Cell(80, 4, 'Date', 0, 1, 'C');

// Footer
$pdf->Ln(6);
$pdf->SetFont('Helvetica', '', 7);
$pdf->SetTextColor(120);
$pdf->Cell(0, 4, 'CARUCARS LLC | Buy Here Pay Here | 9600 NW 7th Ave, Miami FL 33150 | (786) 428-4008 | Submitted ' . $dateStr . ' ' . $timeStr, 0, 1, 'C');

// Get PDF as string
$pdfContent = $pdf->Output('S');
$pdfFilename = 'CreditApp_' . preg_replace('/[^a-zA-Z0-9]/', '', $name) . '_' . date('Ymd') . '.pdf';

// Email with PDF attachment
$to = 'salecarucars@gmail.com';
$subject = 'Credit Application - ' . $name;
$boundary = md5(time());

$headers = "From: Caru Cars <noreply@carucars.com>\r\n";
$headers .= "Reply-To: " . ($d['email'] ?? 'noreply@carucars.com') . "\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

$textBody = "NEW FINANCE APPLICATION\n\n"
    . "Name: {$name}\n"
    . "Phone: " . ($d['phone'] ?? '') . "\n"
    . "Email: " . ($d['email'] ?? '') . "\n"
    . "Vehicle: " . ($d['vehicle_interest'] ?? 'Any') . "\n"
    . "Down Payment: $" . ($d['down_payment'] ?? '0') . "\n\n"
    . "Printable application attached as PDF.\n\n-- Caru Cars Website";

$body = "--{$boundary}\r\n";
$body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
$body .= $textBody . "\r\n\r\n";
$body .= "--{$boundary}\r\n";
$body .= "Content-Type: application/pdf; name=\"{$pdfFilename}\"\r\n";
$body .= "Content-Transfer-Encoding: base64\r\n";
$body .= "Content-Disposition: attachment; filename=\"{$pdfFilename}\"\r\n\r\n";
$body .= chunk_split(base64_encode($pdfContent)) . "\r\n";
$body .= "--{$boundary}--";

$sent = @mail($to, $subject, $body, $headers);

file_put_contents(__DIR__ . '/sync-log.txt',
    '[' . date('Y-m-d H:i:s') . "] PDF for {$name}: " . ($sent ? 'SENT' : 'FAILED') . " ({$pdfFilename})\n",
    FILE_APPEND | LOCK_EX
);

header('Content-Type: application/json');
echo json_encode(['success' => $sent, 'pdf' => $pdfFilename]);
