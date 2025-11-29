<?php
session_start();
define('FPDF_FONTPATH', __DIR__ . '/font/');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/pdf/fpdf.php';


if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access.");
}

$userId = $_SESSION['user_id'];


$stmt = $pdo->prepare("SELECT * FROM invoices WHERE user_id = ? ORDER BY date DESC");
$stmt->execute([$userId]);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$invoices) {
    die("No invoices found.");
}


$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'All Invoices Report', 0, 1, 'C');
$pdf->Ln(5);


$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(30, 10, 'ID', 1);
$pdf->Cell(40, 10, 'Date', 1);
$pdf->Cell(40, 10, 'Amount (JOD)', 1);
$pdf->Cell(40, 10, 'Status', 1);
$pdf->Ln();

$pdf->SetFont('Arial', '', 12);


foreach ($invoices as $inv) {
    $pdf->Cell(30, 10, $inv['invoice_id'], 1);
    $pdf->Cell(40, 10, $inv['date'], 1);
    $pdf->Cell(40, 10, number_format($inv['total_amount'], 2), 1);
    $pdf->Cell(40, 10, ucfirst($inv['status']), 1);
    $pdf->Ln();
}


$pdf->Output('D', 'all_invoices.pdf');
exit;
?>
