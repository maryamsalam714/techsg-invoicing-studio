<?php
// api/send-invoice.php: POST ?id=TSG-INV-2026-0001. Sends the invoice email again (the Resend button).
require __DIR__ . '/../includes/api.php';
require __DIR__ . '/../includes/documents.php';

require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('POST only.', 405);
}
require_csrf();

$no = trim((string)($_GET['id'] ?? ''));
$stmt = $mysqli->prepare("SELECT id, status FROM invoices WHERE invoice_no = ?");
$stmt->bind_param('s', $no);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) {
    json_error('Invoice not found.', 404);
}
if ($row['status'] !== 'Pending') {
    json_error('Only pending invoices are emailed.', 409);
}

$mail = send_invoice_email((int)$row['id']);
audit('email', 'invoice', $no, $mail['status']);
json_out(['ok' => true, 'email' => $mail]);