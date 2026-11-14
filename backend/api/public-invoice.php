<?php
// api/public-invoice.php: GET ?t=TOKEN. No login needed: the long secret token is the key.
// It gives the client page the invoice, the client and the company in the shape that page expects.
require __DIR__ . '/../includes/api.php';
require __DIR__ . '/../includes/documents.php';

$token = trim((string)($_GET['t'] ?? ''));
if (!preg_match('/^[a-f0-9]{40}$/', $token)) {
    json_error('This invoice link is not valid.', 404);
}

$stmt = $mysqli->prepare("SELECT id FROM invoices WHERE public_token = ?");
$stmt->bind_param('s', $token);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$inv = $row ? doc_invoice((int)$row['id']) : null;
if (!$inv || $inv['status'] === 'Draft') {
    json_error('This invoice link is not valid.', 404);
}

$items = [];
foreach ($inv['items'] as $x) {
    $items[] = ['d' => $x['description'], 's' => (string)$x['details'], 'q' => (float)$x['qty'], 'p' => (float)$x['price']];
}
json_out([
    'ok' => true,
    'i'  => [
        'id' => $inv['invoice_no'], 'clientId' => $inv['client_code'], 'issue' => $inv['issue_date'],
        'terms' => (int)$inv['terms'], 'due' => $inv['due_date'], 'taxRate' => (float)$inv['tax_rate'],
        'discount' => (float)$inv['discount'], 'status' => $inv['status'], 'paid' => (float)$inv['amount_paid'],
        'notes' => (string)$inv['notes'], 'items' => $items,
    ],
    'c'  => ['id' => $inv['client_code'], 'name' => $inv['client_name'], 'email' => (string)$inv['client_email'],
             'phone' => (string)$inv['client_phone'], 'address' => (string)$inv['client_address'], 'ntn' => (string)$inv['client_ntn']],
    'o'  => COMPANY,
]);