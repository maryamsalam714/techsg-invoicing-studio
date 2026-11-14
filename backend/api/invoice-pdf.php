<?php
// api/invoice-pdf.php: GET ?id=TSG-INV-2026-0001. Shows the invoice PDF in the browser. Staff only (login needed).
require __DIR__ . '/../includes/api.php';
require __DIR__ . '/../includes/documents.php';

require_login();
$no = trim((string)($_GET['id'] ?? ''));
$stmt = $mysqli->prepare("SELECT id FROM invoices WHERE invoice_no = ?");
$stmt->bind_param('s', $no);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$inv = $row ? doc_invoice((int)$row['id']) : null;
if (!$inv) {
    json_error('Invoice not found.', 404);
}

$pdf = doc_pdf(doc_invoice_html($inv, COMPANY));
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $no . '.pdf"');
echo $pdf;