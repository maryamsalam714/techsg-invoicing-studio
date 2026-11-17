
<?php
require __DIR__ . '/../includes/api.php';
require __DIR__ . '/../includes/invoice_helpers.php';
require __DIR__ . '/../includes/documents.php';

$user = require_login();
$method = $_SERVER['REQUEST_METHOD'];

$frequencies = ['Weekly', 'Monthly', 'Quarterly', 'Yearly'];
$statuses = ['Active', 'Paused', 'Completed', 'Cancelled'];

function recurring_id(string $value): int {
    if (!preg_match('/^REC-(\d+)$/', $value, $match)) {
        json_error('Invalid recurring invoice ID.', 400);
    }
    return (int)$match[1];
}

function recurring_output(array $r): array {
    return [
        'id' => 'REC-' . $r['id'],
        'title' => $r['title'],
        'customer' => $r['customer'],
        'email' => $r['email'],
        'amount' => (float)$r['amount'],
        'frequency' => $r['frequency'],
        'status' => $r['status'],
        'startDate' => $r['start_date'],
        'nextDate' => $r['next_date'],
        'endDate' => $r['end_date'] ?? '',
        'reference' => $r['reference'],
        'notes' => $r['notes'],
        'lastInvoice' => $r['last_invoice_id']
    ];
}

function next_recurring_date(string $date, string $frequency): string {
    $d = new DateTimeImmutable($date);

    switch ($frequency) {
        case 'Weekly': $d = $d->modify('+7 days'); break;
        case 'Monthly': $d = $d->modify('+1 month'); break;
        case 'Quarterly': $d = $d->modify('+3 months'); break;
        case 'Yearly': $d = $d->modify('+1 year'); break;
    }

    return $d->format('Y-m-d');
}

/*
 * Generate at most one invoice per due schedule on each request.
 * The schedule row is locked to prevent duplicate generation.
 * Generated invoices are Pending and are NOT emailed.
 */
function generate_due_recurring_invoices(): void {
    global $mysqli;

    $today = date('Y-m-d');

    $result = $mysqli->query(
        "SELECT id FROM recurring_invoices
         WHERE status = 'Active' AND next_date <= '$today'
         ORDER BY next_date, id"
    );

    if (!$result) {
        return;
    }

    $ids = $result->fetch_all(MYSQLI_ASSOC);

    foreach ($ids as $item) {
        $mysqli->begin_transaction();

        try {
            $id = (int)$item['id'];

            $stmt = $mysqli->prepare(
                "SELECT * FROM recurring_invoices WHERE id = ? FOR UPDATE"
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $schedule = $stmt->get_result()->fetch_assoc();

            if (
                !$schedule ||
                $schedule['status'] !== 'Active' ||
                $schedule['next_date'] > $today
            ) {
                $mysqli->rollback();
                continue;
            }

            $clientId = (int)$schedule['client_id'];

            $stmt = $mysqli->prepare(
                "SELECT id FROM clients
                 WHERE id = ? AND is_archived = 0 LIMIT 1"
            );
            $stmt->bind_param('i', $clientId);
            $stmt->execute();

            if (!$stmt->get_result()->fetch_assoc()) {
                $mysqli->rollback();
                continue;
            }

            $issue = $schedule['next_date'];
            $terms = 30;
            $due = (new DateTime($issue))
                ->modify('+30 days')->format('Y-m-d');

            $year = (int)date('Y', strtotime($issue));
            $number = next_number('invoice', $year);
            $invoiceNo = 'TSG-INV-' . $year . '-' .
                str_pad((string)$number, 4, '0', STR_PAD_LEFT);

            $amount = (float)$schedule['amount'];
            $status = 'Pending';

            $notes = 'Automatically generated from recurring schedule ' .
                'REC-' . $id . '. ' . trim((string)$schedule['notes']);

            $stmt = $mysqli->prepare(
                "INSERT INTO invoices
                (invoice_no, client_id, issue_date, terms, due_date,
                 tax_rate, discount, subtotal, tax_amount, total,
                 status, notes, created_by)
                VALUES (?, ?, ?, ?, ?, 0, 0, ?, 0, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                'sisisddssi',
                $invoiceNo,
                $clientId,
                $issue,
                $terms,
                $due,
                $amount,
                $amount,
                $status,
                $notes,
                $schedule['created_by']
            );

            $stmt->execute();
            $invoiceId = (int)$mysqli->insert_id;

            $description = $schedule['title'];
            $details = 'Recurring invoice';
            $quantity = 1.0;
            $price = $amount;
            $sort = 0;

            $stmt = $mysqli->prepare(
                "INSERT INTO invoice_items
                (invoice_id, sort_order, description, details, qty, price)
                VALUES (?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                'iissdd',
                $invoiceId,
                $sort,
                $description,
                $details,
                $quantity,
                $price
            );
            $stmt->execute();

            $nextDate = next_recurring_date(
                $issue,
                $schedule['frequency']
            );

            $newStatus = 'Active';

            if (
                !empty($schedule['end_date']) &&
                $nextDate > $schedule['end_date']
            ) {
                $newStatus = 'Completed';
            }

            $stmt = $mysqli->prepare(
                "UPDATE recurring_invoices
                 SET next_date = ?, status = ?,
                     last_invoice_id = ?, last_generated_date = ?
                 WHERE id = ?"
            );

            $stmt->bind_param(
                'ssisi',
                $nextDate,
                $newStatus,
                $invoiceId,
                $issue,
                $id
            );
            $stmt->execute();

            $mysqli->commit();

            audit(
                'create',
                'invoice',
                $invoiceNo,
                'Generated from recurring schedule REC-' . $id
            );

        } catch (Throwable $e) {
            $mysqli->rollback();
            error_log('Recurring invoice generation failed: ' . $e->getMessage());
        }
    }
}

// GET: generate due invoices, then return schedules.
if ($method === 'GET') {
    generate_due_recurring_invoices();

    $result = $mysqli->query(
        "SELECT * FROM recurring_invoices ORDER BY id DESC"
    );

    $records = [];

    while ($row = $result->fetch_assoc()) {
        $records[] = recurring_output($row);
    }

    json_out(['ok' => true, 'records' => $records]);
}

require_csrf();
$input = read_json();

// POST: create a schedule.
if ($method === 'POST') {
    $title = trim((string)($input['title'] ?? ''));
    $customer = trim((string)($input['customer'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $amount = round((float)($input['amount'] ?? 0), 2);
    $frequency = (string)($input['frequency'] ?? 'Monthly');
    $status = (string)($input['status'] ?? 'Active');
    $start = (string)($input['startDate'] ?? '');
    $next = (string)($input['nextDate'] ?? '');
    $end = trim((string)($input['endDate'] ?? ''));
    $reference = trim((string)($input['reference'] ?? ''));
    $notes = trim((string)($input['notes'] ?? ''));

    if ($title === '' || $customer === '' || $amount <= 0) {
        json_error('Title, customer and a valid amount are required.', 422);
    }

    if (!in_array($frequency, $frequencies, true) ||
        !in_array($status, $statuses, true)) {
        json_error('Invalid frequency or status.', 422);
    }

    foreach ([$start, $next] as $date) {
        $d = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            json_error('Please provide valid dates.', 422);
        }
    }

    if ($next < $start || ($end !== '' && ($end < $start || $next > $end))) {
        json_error('Please check the start, next and end dates.', 422);
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Customer email is invalid.', 422);
    }

    $stmt = $mysqli->prepare(
        "SELECT id FROM clients
         WHERE name = ? AND is_archived = 0 LIMIT 1"
    );
    $stmt->bind_param('s', $customer);
    $stmt->execute();
    $client = $stmt->get_result()->fetch_assoc();

    if (!$client) {
        json_error('Customer must match an existing client name exactly.', 422);
    }

    $clientId = (int)$client['id'];
    $endValue = $end !== '' ? $end : null;

    $stmt = $mysqli->prepare(
        "INSERT INTO recurring_invoices
        (title, customer, client_id, email, amount, frequency, status,
         start_date, next_date, end_date, reference, notes, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        'ssisdsssssssi',
        $title, $customer, $clientId, $email, $amount,
        $frequency, $status, $start, $next, $endValue,
        $reference, $notes, $user['id']
    );

    $stmt->execute();
    $id = (int)$mysqli->insert_id;

    audit('create', 'recurring_invoice', 'REC-' . $id, $title);

    $stmt = $mysqli->prepare("SELECT * FROM recurring_invoices WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    json_out([
        'ok' => true,
        'record' => recurring_output($stmt->get_result()->fetch_assoc())
    ], 201);
}

// PUT and DELETE use the REC-1001 style ID.
$id = recurring_id(trim((string)($_GET['id'] ?? '')));

if ($method === 'PUT') {
    $title = trim((string)($input['title'] ?? ''));
    $customer = trim((string)($input['customer'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $amount = round((float)($input['amount'] ?? 0), 2);
    $frequency = (string)($input['frequency'] ?? 'Monthly');
    $status = (string)($input['status'] ?? 'Active');
    $start = (string)($input['startDate'] ?? '');
    $next = (string)($input['nextDate'] ?? '');
    $end = trim((string)($input['endDate'] ?? ''));
    $reference = trim((string)($input['reference'] ?? ''));
    $notes = trim((string)($input['notes'] ?? ''));

    if ($title === '' || $customer === '' || $amount <= 0) {
        json_error('Title, customer and a valid amount are required.', 422);
    }

    if (!in_array($frequency, $frequencies, true) ||
        !in_array($status, $statuses, true)) {
        json_error('Invalid frequency or status.', 422);
    }

    foreach ([$start, $next] as $date) {
        $d = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            json_error('Please provide valid dates.', 422);
        }
    }

    if ($next < $start || ($end !== '' && ($end < $start || $next > $end))) {
        json_error('Please check the start, next and end dates.', 422);
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Customer email is invalid.', 422);
    }

    $stmt = $mysqli->prepare(
        "SELECT id FROM clients
         WHERE name = ? AND is_archived = 0 LIMIT 1"
    );
    $stmt->bind_param('s', $customer);
    $stmt->execute();
    $client = $stmt->get_result()->fetch_assoc();

    if (!$client) {
        json_error('Customer must match an existing client name exactly.', 422);
    }

    $clientId = (int)$client['id'];
    $endValue = $end !== '' ? $end : null;

    $stmt = $mysqli->prepare(
        "UPDATE recurring_invoices SET
         title = ?, customer = ?, client_id = ?, email = ?, amount = ?,
         frequency = ?, status = ?, start_date = ?, next_date = ?,
         end_date = ?, reference = ?, notes = ?
         WHERE id = ?"
    );

    $stmt->bind_param(
        'ssisdsssssssi',
        $title, $customer, $clientId, $email, $amount,
        $frequency, $status, $start, $next, $endValue,
        $reference, $notes, $id
    );
    $stmt->execute();

    audit('update', 'recurring_invoice', 'REC-' . $id, $title);

    $stmt = $mysqli->prepare("SELECT * FROM recurring_invoices WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();

    if (!$record) {
        json_error('Recurring schedule not found.', 404);
    }

    json_out(['ok' => true, 'record' => recurring_output($record)]);
}

if ($method === 'DELETE') {
    $stmt = $mysqli->prepare("DELETE FROM recurring_invoices WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        json_error('Recurring schedule not found.', 404);
    }

    audit('delete', 'recurring_invoice', 'REC-' . $id, null);
    json_out(['ok' => true]);
}

json_error('Method not allowed.', 405);
