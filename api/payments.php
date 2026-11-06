<?php
// api/payments.php
//   GET                       list the payments (add ?invoice=TSG-INV-2026-0001 for one invoice)
//   POST                      record a payment against an invoice
//   PUT    ?id=RCT-2026-0001  refund a payment (admin only)
// Payments are never deleted: a wrong payment is refunded, and the record stays.
require __DIR__ . '/../includes/api.php';
require __DIR__ . '/../includes/invoice_helpers.php';

$user   = require_login();
$method = $_SERVER['REQUEST_METHOD'];

const PAYMENT_METHODS = ['Cash', 'Bank Transfer', 'Cheque', 'Card', 'JazzCash', 'EasyPaisa', 'Other'];

// A database row becomes the shape the frontend expects (id = the receipt number)
function payment_out(array $r): array {
    return [
        'id'        => $r['receipt_no'],
        'invoice'   => (string)$r['invoice_no'],
        'customer'  => (string)$r['customer'],
        'date'      => $r['payment_date'],
        'amount'    => (float)$r['amount'],
        'method'    => (string)$r['method'],
        'reference' => (string)$r['reference'],
        'status'    => $r['status'],
        'notes'     => (string)$r['notes'],
        'createdBy' => (string)$r['created_by'],
    ];
}

// $filterSql is always one of our own fixed texts below, never something the browser sent
function load_payments(string $filterSql = '', ?string $arg = null): array {
    global $mysqli;
    $stmt = $mysqli->prepare(
        "SELECT p.receipt_no, i.invoice_no, p.customer, p.payment_date, p.amount, p.method, p.reference,
                p.status, p.notes, u.username AS created_by
         FROM payments p
         LEFT JOIN invoices i ON i.id = p.invoice_id
         LEFT JOIN users u ON u.id = p.created_by" . $filterSql . " ORDER BY p.payment_date DESC, p.id DESC"
    );
    if ($arg !== null) {
        $stmt->bind_param('s', $arg);
    }
    $stmt->execute();
    return array_map('payment_out', $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

// ---- GET: the list ----
if ($method === 'GET') {
    $no = trim((string)($_GET['invoice'] ?? ''));
    json_out(['ok' => true, 'payments' => $no !== '' ? load_payments(' WHERE i.invoice_no = ?', $no) : load_payments()]);
}

require_csrf();                                          // everything below changes data

// ---- POST: record a payment ----
if ($method === 'POST') {
    $in        = read_json();
    $invoiceNo = trim((string)($in['invoice'] ?? ''));
    $amount    = round((float)($in['amount'] ?? 0), 2);
    $date      = trim((string)($in['date'] ?? '')) ?: date('Y-m-d');
    $payMethod = (string)($in['method'] ?? '');
    $reference = trim((string)($in['reference'] ?? ''));
    $notes     = trim((string)($in['notes'] ?? ''));

    if ($amount <= 0) {
        json_error('Enter an amount above 0.', 422);
    }
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        json_error('The payment date is not valid.', 422);
    }
    if ($date > date('Y-m-d')) {
        json_error('The payment date cannot be in the future.', 422);
    }
    if (!in_array($payMethod, PAYMENT_METHODS, true)) {
        json_error('Choose a payment method.', 422);
    }
    if (mb_strlen($reference) > 100) {
        json_error('The reference is too long (maximum 100 characters).', 422);
    }
    if (mb_strlen($notes) > 500) {
        json_error('The note is too long (maximum 500 characters).', 422);
    }

    $mysqli->begin_transaction();
    // Lock the invoice, so two payments cannot be recorded at the same moment
    $stmt = $mysqli->prepare(
        "SELECT i.id, i.status, i.total, i.amount_paid, c.name AS client_name
         FROM invoices i JOIN clients c ON c.id = i.client_id
         WHERE i.invoice_no = ? FOR UPDATE"
    );
    $stmt->bind_param('s', $invoiceNo);
    $stmt->execute();
    $inv = $stmt->get_result()->fetch_assoc();
    if (!$inv) {
        json_error('Invoice not found.', 404);
    }
    if ($inv['status'] !== 'Pending') {
        json_error('Payments can only be recorded for pending invoices.', 409);
    }
    $balance = round((float)$inv['total'] - (float)$inv['amount_paid'], 2);
    if ($amount > $balance + 0.005) {
        json_error('The payment is more than the balance due (' . number_format($balance, 2) . ').', 422);
    }

    // One bank or wallet transaction cannot pay two invoices
    if ($reference !== '' && $payMethod !== 'Cash') {
        $stmt = $mysqli->prepare("SELECT COUNT(*) FROM payments WHERE method = ? AND reference = ? AND status = 'Paid'");
        $stmt->bind_param('ss', $payMethod, $reference);
        $stmt->execute();
        if ((int)$stmt->get_result()->fetch_row()[0] > 0) {
            json_error('This transaction reference was already used for another payment.', 409);
        }
    }

    $year    = (int)date('Y');
    $receipt = 'RCT-' . $year . '-' . str_pad((string)next_number('receipt', $year), 4, '0', STR_PAD_LEFT);
    $refDb   = ($reference === '') ? null : $reference;
    $notesDb = ($notes === '') ? null : $notes;
    $stmt = $mysqli->prepare(
        "INSERT INTO payments (receipt_no, invoice_id, customer, amount, method, reference, payment_date, status, notes, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'Paid', ?, ?)"
    );
    $stmt->bind_param('sisdssssi', $receipt, $inv['id'], $inv['client_name'], $amount, $payMethod, $refDb, $date, $notesDb, $user['id']);
    $stmt->execute();
    refresh_invoice_payment((int)$inv['id']);            // the invoice status follows the payments
    $mysqli->commit();

    audit('create', 'payment', $receipt, $invoiceNo . ' ' . number_format($amount, 2));

    $stmt = $mysqli->prepare("SELECT status, total, amount_paid FROM invoices WHERE id = ?");
    $stmt->bind_param('i', $inv['id']);
    $stmt->execute();
    $now = $stmt->get_result()->fetch_assoc();
    json_out([
        'ok'      => true,
        'payment' => load_payments(' WHERE p.receipt_no = ?', $receipt)[0],
        'invoice' => ['id' => $invoiceNo, 'status' => $now['status'], 'paid' => (float)$now['amount_paid'],
                      'balance' => round((float)$now['total'] - (float)$now['amount_paid'], 2)],
    ], 201);
}

// ---- PUT: refund a payment (admin only) ----
if ($method === 'PUT') {
    require_admin();
    $receipt = trim((string)($_GET['id'] ?? ''));
    $in      = read_json();
    if (($in['status'] ?? '') !== 'Refunded') {
        json_error('The only change allowed is refunding a payment.', 422);
    }

    $mysqli->begin_transaction();
    $stmt = $mysqli->prepare("SELECT id, invoice_id, status FROM payments WHERE receipt_no = ? FOR UPDATE");
    $stmt->bind_param('s', $receipt);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        json_error('Payment not found.', 404);
    }
    if ($row['status'] !== 'Paid') {
        json_error('This payment is already refunded.', 409);
    }
    $stmt = $mysqli->prepare("UPDATE payments SET status = 'Refunded' WHERE id = ?");
    $stmt->bind_param('i', $row['id']);
    $stmt->execute();
    if ($row['invoice_id']) {
        refresh_invoice_payment((int)$row['invoice_id']);
    }
    $mysqli->commit();

    audit('refund', 'payment', $receipt, null);
    json_out(['ok' => true, 'payment' => load_payments(' WHERE p.receipt_no = ?', $receipt)[0]]);
}

json_error('Payments cannot be deleted. Refund the payment instead.', 405);