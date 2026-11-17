
<?php
// api/receipt.php
// GET ?id=RCT-2026-0001
// Returns one payment receipt with its invoice details.

require __DIR__ . '/../includes/api.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Only GET requests are allowed.', 405);
}

$receiptNo = trim((string)($_GET['id'] ?? ''));

if ($receiptNo === '') {
    json_error('Receipt number is required.', 400);
}

global $mysqli;

$stmt = $mysqli->prepare(
    "SELECT
        p.receipt_no,
        p.customer,
        p.payment_date,
        p.amount,
        p.method,
        p.reference,
        p.status,
        p.notes,
        p.created_by,
        i.invoice_no,
        i.client_id,
        i.total AS invoice_total,
        i.amount_paid,
        c.name AS client_name
     FROM payments p
     LEFT JOIN invoices i ON i.id = p.invoice_id
     LEFT JOIN clients c ON c.id = i.client_id
     WHERE p.receipt_no = ?
     LIMIT 1"
);

$stmt->bind_param('s', $receiptNo);
$stmt->execute();

$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    json_error('Receipt not found.', 404);
}

json_out([
    'ok' => true,

    'receipt' => [
        'id'        => $row['receipt_no'],
        'customer'  => $row['client_name'] ?: $row['customer'],
        'clientId'  => $row['client_id'],
        'invoice'   => $row['invoice_no'],
        'date'      => $row['payment_date'],
        'amount'    => (float)$row['amount'],
        'method'    => $row['method'],
        'reference' => $row['reference'],
        'status'    => $row['status'],
        'notes'     => $row['notes'],
        'createdBy' => $row['created_by']
    ],

    'invoice' => $row['invoice_no'] ? [
        'id'          => $row['invoice_no'],
        'total'       => (float)$row['invoice_total'],
        'amount_paid' => (float)$row['amount_paid']
    ] : null
]);
