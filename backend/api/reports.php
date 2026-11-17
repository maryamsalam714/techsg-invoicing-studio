
<?php
// api/reports.php
// GET: return invoice and payment records for Reports & Analytics.

require __DIR__ . '/../includes/api.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Only GET requests are allowed.', 405);
}

// Fetch invoices with their client names.
$invoiceSql = "
    SELECT
        i.invoice_no,
        i.issue_date,
        i.due_date,
        i.total,
        i.amount_paid,
        i.status,
        c.name AS customer_name,
        c.code AS client_code
    FROM invoices i
    INNER JOIN clients c ON c.id = i.client_id
    ORDER BY i.issue_date DESC, i.id DESC
";

$invoiceResult = $mysqli->query($invoiceSql);

if (!$invoiceResult) {
    json_error('Could not load invoice reports.', 500);
}

$invoices = [];

while ($row = $invoiceResult->fetch_assoc()) {
    $invoices[] = [
        'id'           => $row['invoice_no'],
        'invoiceNumber'=> $row['invoice_no'],
        'customerName' => $row['customer_name'],
        'clientCode'   => $row['client_code'],
        'issueDate'    => $row['issue_date'],
        'dueDate'      => $row['due_date'],
        'total'        => (float)$row['total'],
        'paidAmount'   => (float)$row['amount_paid'],
        'status'       => $row['status']
    ];
}

// Fetch payment records, including refunded records.
$paymentSql = "
    SELECT
        p.receipt_no,
        p.customer,
        p.payment_date,
        p.amount,
        p.method,
        p.reference,
        p.status,
        i.invoice_no
    FROM payments p
    LEFT JOIN invoices i ON i.id = p.invoice_id
    ORDER BY p.payment_date DESC, p.id DESC
";

$paymentResult = $mysqli->query($paymentSql);

if (!$paymentResult) {
    json_error('Could not load payment reports.', 500);
}

$payments = [];

while ($row = $paymentResult->fetch_assoc()) {
    $payments[] = [
        'id'           => $row['receipt_no'],
        'receiptNumber'=> $row['receipt_no'],
        'invoice'      => $row['invoice_no'] ?? '',
        'customerName' => $row['customer'],
        'date'         => $row['payment_date'],
        'amount'       => (float)$row['amount'],
        'method'       => $row['method'],
        'reference'    => $row['reference'],
        'status'       => $row['status']
    ];
}

json_out([
    'ok'       => true,
    'invoices' => $invoices,
    'payments' => $payments
]);
