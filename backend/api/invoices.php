<?php
// api/invoices.php
//   GET                  list the invoices (with their items)
//   POST                 create an invoice (the server chooses the number) and email it to the client
//   PUT    ?id=TSG-INV-2026-0001   update an invoice
//   DELETE ?id=TSG-INV-2026-0001   delete an invoice (admin only)
require __DIR__ . '/../includes/api.php';
require __DIR__ . '/../includes/invoice_helpers.php';
require __DIR__ . '/../includes/documents.php';

$user   = require_login();
$method = $_SERVER['REQUEST_METHOD'];

const INVOICE_STATUSES = ['Draft', 'Pending', 'Paid', 'Cancelled'];

// Read invoices (all, or just one) in the shape the frontend expects
function load_invoices(?int $onlyId = null): array {
    global $mysqli;
    $where = $onlyId ? ' WHERE i.id = ' . (int)$onlyId : '';        // (int) makes this safe
    $rows = $mysqli->query(
        "SELECT i.id, i.invoice_no, c.code AS client_code, i.issue_date, i.terms, i.due_date, i.tax_rate,
                i.discount, i.status, i.paid_at, i.amount_paid, i.notes, u.username AS created_by,
                DATE(i.created_at) AS created_date
         FROM invoices i
         JOIN clients c ON c.id = i.client_id
         LEFT JOIN users u ON u.id = i.created_by" . $where . " ORDER BY i.id"
    )->fetch_all(MYSQLI_ASSOC);

    $where = $onlyId ? ' WHERE invoice_id = ' . (int)$onlyId : '';
    $items = [];
    $itemRows = $mysqli->query(
        "SELECT invoice_id, description, details, qty, price FROM invoice_items" . $where . " ORDER BY invoice_id, sort_order, id"
    )->fetch_all(MYSQLI_ASSOC);
    foreach ($itemRows as $x) {
        // the app calls these d (description), s (details), q (quantity), p (price)
        $items[$x['invoice_id']][] = ['d' => $x['description'], 's' => (string)$x['details'],
                                      'q' => (float)$x['qty'], 'p' => (float)$x['price']];
    }

    $out = [];
    foreach ($rows as $r) {
        $inv = [
            'id'        => $r['invoice_no'],
            'clientId'  => $r['client_code'],
            'issue'     => $r['issue_date'],
            'terms'     => (int)$r['terms'],
            'due'       => $r['due_date'],
            'taxRate'   => (float)$r['tax_rate'],
            'discount'  => (float)$r['discount'],
            'status'    => $r['status'],
            'paid'      => (float)$r['amount_paid'],       // how much has been paid so far
            'notes'     => (string)$r['notes'],
            'createdBy' => (string)$r['created_by'],
            'createdAt' => $r['created_date'],
            'items'     => $items[$r['id']] ?? [],
        ];
        if ($r['paid_at']) {
            $inv['paidAt'] = $r['paid_at'];
        }
        $out[] = $inv;
    }
    return $out;
}

// Find one invoice by its number, or stop with a 404 answer
function find_invoice(string $no): array {
    global $mysqli;
    $stmt = $mysqli->prepare("SELECT id, status, paid_at, amount_paid FROM invoices WHERE invoice_no = ?");
    $stmt->bind_param('s', $no);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        json_error('Invoice not found.', 404);
    }
    return $row;
}

// Clean and check what the browser sent. Stops with a 422 answer if something is wrong.
// $currentStatus is the invoice's status in the database (empty for a new invoice).
function invoice_in(array $in, string $currentStatus = ''): array {
    global $mysqli;

    $code = trim((string)($in['clientId'] ?? ''));
    $stmt = $mysqli->prepare("SELECT id FROM clients WHERE code = ? AND is_archived = 0");
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $client = $stmt->get_result()->fetch_assoc();
    if (!$client) {
        json_error('Please select a valid client.', 422);
    }

    $issue = (string)($in['issue'] ?? '');
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $issue, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        json_error('The issue date is not valid.', 422);
    }
    $terms = (int)($in['terms'] ?? 0);
    if ($terms < 0 || $terms > 365) {
        json_error('Payment terms must be between 0 and 365 days.', 422);
    }
    $due = (new DateTime($issue))->modify("+$terms days")->format('Y-m-d');

    $taxRate = (float)($in['taxRate'] ?? 0);
    if ($taxRate < 0 || $taxRate > 100) {
        json_error('Tax must be between 0 and 100 percent.', 422);
    }
    $discount = (float)($in['discount'] ?? 0);
    if ($discount < 0) {
        json_error('Discount cannot be negative.', 422);
    }
    $status = (string)($in['status'] ?? 'Pending');
    if (!in_array($status, INVOICE_STATUSES, true)) {
        json_error('Status is not valid.', 422);
    }
    // Nobody can set Paid by hand: it happens when the payments cover the total
    if ($status === 'Paid' && $currentStatus !== 'Paid') {
        json_error('An invoice becomes Paid when its payments cover the total. Record a payment instead.', 422);
    }
    $notes = trim((string)($in['notes'] ?? ''));
    if (mb_strlen($notes) > 2000) {
        json_error('The note is too long (maximum 2000 characters).', 422);
    }

    $items = [];
    foreach ((array)($in['items'] ?? []) as $x) {
        $d = trim((string)($x['d'] ?? ''));
        if ($d === '') {
            continue;                                   // empty rows are ignored
        }
        $q = round((float)($x['q'] ?? 0), 2);
        $p = round((float)($x['p'] ?? 0), 2);
        if ($q <= 0 || $p < 0) {
            json_error('Quantity must be above 0 and a price cannot be negative.', 422);
        }
        if (mb_strlen($d) > 255) {
            json_error('An item name is too long (maximum 255 characters).', 422);
        }
        $items[] = ['d' => $d, 's' => trim((string)($x['s'] ?? '')), 'q' => $q, 'p' => $p];
    }
    if (!$items) {
        json_error('Add at least one item.', 422);
    }
    if (count($items) > 100) {
        json_error('An invoice can have at most 100 items.', 422);
    }

    return ['client_id' => (int)$client['id'], 'issue' => $issue, 'terms' => $terms, 'due' => $due,
            'tax_rate' => $taxRate, 'discount' => $discount, 'status' => $status, 'notes' => $notes, 'items' => $items];
}

// The totals, calculated on the server. Same formula as the app.
function invoice_totals(array $items, float $discount, float $taxRate): array {
    $sub = 0.0;
    foreach ($items as $x) {
        $sub += $x['q'] * $x['p'];
    }
    $sub  = round($sub, 2);
    $disc = round(min($discount, $sub), 2);             // a discount can never be more than the subtotal
    $tax  = round(($sub - $disc) * $taxRate / 100, 2);
    return ['subtotal' => $sub, 'discount' => $disc, 'tax' => $tax, 'total' => round($sub - $disc + $tax, 2)];
}

// Replace all the items of an invoice
function save_items(int $invoiceId, array $items): void {
    global $mysqli;
    $stmt = $mysqli->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
    $stmt->bind_param('i', $invoiceId);
    $stmt->execute();
    $stmt = $mysqli->prepare(
        "INSERT INTO invoice_items (invoice_id, sort_order, description, details, qty, price) VALUES (?, ?, ?, ?, ?, ?)"
    );
    foreach ($items as $i => $x) {
        $stmt->bind_param('iissdd', $invoiceId, $i, $x['d'], $x['s'], $x['q'], $x['p']);
        $stmt->execute();
    }
}

// ---- GET: the list, and the number the next invoice will get ----
if ($method === 'GET') {
    $year = (int)date('Y');
    $stmt = $mysqli->prepare("SELECT last_number FROM sequences WHERE name = 'invoice' AND year = ?");
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $last = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
    json_out([
        'ok'       => true,
        'invoices' => load_invoices(),
        'next'     => 'TSG-INV-' . $year . '-' . str_pad((string)($last + 1), 4, '0', STR_PAD_LEFT),
    ]);
}

// Everything below changes data, so it needs the security token
require_csrf();

// ---- POST: create ----
if ($method === 'POST') {
    $inv = invoice_in(read_json());
    $t   = invoice_totals($inv['items'], $inv['discount'], $inv['tax_rate']);
    $year = (int)date('Y');

    $mysqli->begin_transaction();                       // if anything fails, the number is given back
    $no = 'TSG-INV-' . $year . '-' . str_pad((string)next_number('invoice', $year), 4, '0', STR_PAD_LEFT);
    $stmt = $mysqli->prepare(
        "INSERT INTO invoices (invoice_no, client_id, issue_date, terms, due_date, tax_rate, discount, subtotal,
                               tax_amount, total, status, notes, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('sisisdddddssi', $no, $inv['client_id'], $inv['issue'], $inv['terms'], $inv['due'],
        $inv['tax_rate'], $t['discount'], $t['subtotal'], $t['tax'], $t['total'],
        $inv['status'], $inv['notes'], $user['id']);
    $stmt->execute();
    $id = $mysqli->insert_id;
    save_items($id, $inv['items']);
    $mysqli->commit();

    audit('create', 'invoice', $no, 'total ' . $t['total']);

    // Email the client at once (a Draft is not emailed).
    // A problem with email never stops the invoice from being saved.
    try {
        $mail = ($inv['status'] === 'Pending')
            ? send_invoice_email($id)
            : ['sent' => false, 'status' => 'skipped', 'to' => '', 'note' => 'Drafts are not emailed.'];
    } catch (Throwable $e) {
        $mail = ['sent' => false, 'status' => 'failed', 'to' => '', 'note' => $e->getMessage()];
    }
    json_out(['ok' => true, 'invoice' => load_invoices($id)[0], 'email' => $mail], 201);
}

$no = trim((string)($_GET['id'] ?? ''));

// ---- PUT: update ----
if ($method === 'PUT') {
    $row = find_invoice($no);
    $inv = invoice_in(read_json(), $row['status']);

    if (in_array($inv['status'], ['Draft', 'Cancelled'], true) && (float)$row['amount_paid'] > 0) {
        json_error('This invoice has payments. Refund them before you cancel it or make it a draft.', 409);
    }
    $t = invoice_totals($inv['items'], $inv['discount'], $inv['tax_rate']);

    $mysqli->begin_transaction();
    $stmt = $mysqli->prepare(
        "UPDATE invoices SET client_id = ?, issue_date = ?, terms = ?, due_date = ?, tax_rate = ?, discount = ?,
                subtotal = ?, tax_amount = ?, total = ?, status = ?, notes = ?
         WHERE id = ?"
    );
    $stmt->bind_param('isisdddddssi', $inv['client_id'], $inv['issue'], $inv['terms'], $inv['due'],
        $inv['tax_rate'], $t['discount'], $t['subtotal'], $t['tax'], $t['total'],
        $inv['status'], $inv['notes'], $row['id']);
    $stmt->execute();
    save_items((int)$row['id'], $inv['items']);
    refresh_invoice_payment((int)$row['id']);           // the status follows the payments
    $mysqli->commit();

    audit('update', 'invoice', $no, $inv['status'] . ', total ' . $t['total']);
    json_out(['ok' => true, 'invoice' => load_invoices((int)$row['id'])[0]]);
}

// ---- DELETE: admin only ----
if ($method === 'DELETE') {
    require_admin();
    $row = find_invoice($no);

    // Money records are never deleted
    $stmt = $mysqli->prepare("SELECT COUNT(*) FROM payments WHERE invoice_id = ?");
    $stmt->bind_param('i', $row['id']);
    $stmt->execute();
    if ((int)$stmt->get_result()->fetch_row()[0] > 0) {
        json_error('This invoice has payments, so it cannot be deleted. Cancel it instead.', 409);
    }

    $stmt = $mysqli->prepare("DELETE FROM invoices WHERE id = ?");   // its items are deleted with it
    $stmt->bind_param('i', $row['id']);
    $stmt->execute();
    audit('delete', 'invoice', $no, null);
    json_out(['ok' => true]);
}

json_error('Method not allowed.', 405);