
<?php
require __DIR__ . '/../includes/api.php';

$user = require_login();

$method = $_SERVER['REQUEST_METHOD'];

$allowedStatuses = [
    'Draft',
    'Sent',
    'Pending',
    'Accepted',
    'Declined',
    'Expired',
    'Converted'
];

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function q_fail(string $message, int $status = 400): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'ok' => false,
        'error' => $message
    ]);

    exit;
}

function q_valid_date($date): bool
{
    if (!is_string($date)) {
        return false;
    }

    $parsed = DateTime::createFromFormat('!Y-m-d', $date);

    return $parsed !== false &&
        $parsed->format('Y-m-d') === $date;
}

function q_get_input(array $data): array
{
    global $mysqli, $allowedStatuses;

    $clientCode = trim((string)($data['clientId'] ?? ''));

    if ($clientCode === '') {
        q_fail('Please select a client.');
    }

    // Convert the client code into the database client ID.
    $stmt = $mysqli->prepare(
        "SELECT id FROM clients
         WHERE code = ? AND is_archived = 0
         LIMIT 1"
    );

    if (!$stmt) {
        throw new Exception($mysqli->error);
    }

    $stmt->bind_param('s', $clientCode);
    $stmt->execute();

    $clientResult = $stmt->get_result();
    $client = $clientResult->fetch_assoc();

    $stmt->close();

    if (!$client) {
        q_fail('Selected client was not found or is archived.');
    }

    $clientId = (int)$client['id'];

    $issue = trim((string)($data['issue'] ?? ''));
    $validUntil = trim((string)($data['validUntil'] ?? ''));

    if (!q_valid_date($issue)) {
        q_fail('Please provide a valid issue date.');
    }

    if (!q_valid_date($validUntil)) {
        q_fail('Please provide a valid quotation expiry date.');
    }

    if ($validUntil < $issue) {
        q_fail('The valid-until date cannot be earlier than the issue date.');
    }

    $terms = filter_var(
        $data['terms'] ?? 30,
        FILTER_VALIDATE_INT
    );

    if ($terms === false || $terms < 0 || $terms > 3650) {
        q_fail('Payment terms must be between 0 and 3650 days.');
    }

    $taxRate = $data['taxRate'] ?? 0;
    $discount = $data['discount'] ?? 0;

    if (!is_numeric($taxRate) || !is_numeric($discount)) {
        q_fail('Tax rate and discount must be valid numbers.');
    }

    $taxRate = (float)$taxRate;
    $discount = (float)$discount;

    if ($taxRate < 0 || $taxRate > 100) {
        q_fail('Tax rate must be between 0 and 100.');
    }

    if ($discount < 0) {
        q_fail('Discount cannot be negative.');
    }

    $status = trim((string)($data['status'] ?? 'Draft'));

    if (!in_array($status, $allowedStatuses, true)) {
        q_fail('Invalid quotation status.');
    }

    $notes = trim((string)($data['notes'] ?? ''));

    $rawItems = $data['items'] ?? [];

    if (!is_array($rawItems) || count($rawItems) === 0) {
        q_fail('Please add at least one quotation item.');
    }

    $items = [];
    $subtotal = 0;

    foreach ($rawItems as $index => $item) {

        if (!is_array($item)) {
            q_fail('Invalid quotation item.');
        }

        $description = trim((string)($item['d'] ?? ''));
        $details = trim((string)($item['s'] ?? ''));

        $quantity = $item['q'] ?? 0;
        $unitPrice = $item['p'] ?? 0;

        if ($description === '') {
            q_fail('Every item must have a description.');
        }

        if (!is_numeric($quantity) || !is_numeric($unitPrice)) {
            q_fail('Item quantity and price must be numbers.');
        }

        $quantity = (float)$quantity;
        $unitPrice = (float)$unitPrice;

        if ($quantity <= 0) {
            q_fail('Item quantity must be greater than zero.');
        }

        if ($unitPrice < 0) {
            q_fail('Item price cannot be negative.');
        }

        $amount = round($quantity * $unitPrice, 2);

        $subtotal += $amount;

        $items[] = [
            'sort_order' => (int)$index,
            'description' => $description,
            'details' => $details,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'amount' => $amount
        ];
    }

    $subtotal = round($subtotal, 2);

    // Discount cannot exceed the subtotal.
    $discount = min($discount, $subtotal);

    $taxableAmount = max(0, $subtotal - $discount);

    $taxAmount = round(
        $taxableAmount * $taxRate / 100,
        2
    );

    $total = round($taxableAmount + $taxAmount, 2);

    return [
        'clientId' => $clientId,
        'issue' => $issue,
        'validUntil' => $validUntil,
        'terms' => $terms,
        'taxRate' => $taxRate,
        'discount' => $discount,
        'subtotal' => $subtotal,
        'taxAmount' => $taxAmount,
        'total' => $total,
        'status' => $status,
        'notes' => $notes,
        'items' => $items
    ];
}

function q_save_items(int $quotationId, array $items): void
{
    global $mysqli;

    $delete = $mysqli->prepare(
        "DELETE FROM quotation_items WHERE quotation_id = ?"
    );

    if (!$delete) {
        throw new Exception($mysqli->error);
    }

    $delete->bind_param('i', $quotationId);
    $delete->execute();
    $delete->close();

    $insert = $mysqli->prepare(
        "INSERT INTO quotation_items
        (
            quotation_id,
            sort_order,
            description,
            details,
            quantity,
            unit_price,
            amount
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$insert) {
        throw new Exception($mysqli->error);
    }

    foreach ($items as $item) {

        $sortOrder = $item['sort_order'];
        $description = $item['description'];
        $details = $item['details'];
        $quantity = $item['quantity'];
        $unitPrice = $item['unit_price'];
        $amount = $item['amount'];

        $insert->bind_param(
            'iissddd',
            $quotationId,
            $sortOrder,
            $description,
            $details,
            $quantity,
            $unitPrice,
            $amount
        );

        if (!$insert->execute()) {
            throw new Exception($insert->error);
        }
    }

    $insert->close();
}

/*
|--------------------------------------------------------------------------
| GET: Load quotations
|--------------------------------------------------------------------------
*/

try {

    if ($method === 'GET') {

        $sql = "
            SELECT
                q.id,
                q.quotation_no,
                q.client_id,
                q.issue_date,
                q.valid_until,
                q.terms,
                q.tax_rate,
                q.discount,
                q.subtotal,
                q.tax_amount,
                q.total,
                q.status,
                q.notes,
                q.converted_invoice_id,
                q.created_at,
                c.code AS client_code,
                c.name AS client_name

            FROM quotations q

            INNER JOIN clients c
                ON c.id = q.client_id

            ORDER BY q.created_at DESC, q.id DESC
        ";

        $result = $mysqli->query($sql);

        if (!$result) {
            throw new Exception($mysqli->error);
        }

        $quotations = [];

        while ($row = $result->fetch_assoc()) {

            $quotationId = (int)$row['id'];

            $itemStmt = $mysqli->prepare(
                "SELECT
                    description,
                    details,
                    quantity,
                    unit_price,
                    amount
                 FROM quotation_items
                 WHERE quotation_id = ?
                 ORDER BY sort_order ASC, id ASC"
            );

            if (!$itemStmt) {
                throw new Exception($mysqli->error);
            }

            $itemStmt->bind_param('i', $quotationId);
            $itemStmt->execute();

            $itemResult = $itemStmt->get_result();

            $items = [];

            while ($item = $itemResult->fetch_assoc()) {
                $items[] = [
                    'd' => $item['description'],
                    's' => $item['details'] ?? '',
                    'q' => (float)$item['quantity'],
                    'p' => (float)$item['unit_price'],
                    'amount' => (float)$item['amount']
                ];
            }

            $itemStmt->close();

            $quotations[] = [
                'id' => $quotationId,
                'quotationNo' => $row['quotation_no'],

                // Frontend sends and receives the client code.
                'clientId' => $row['client_code'],
                'clientName' => $row['client_name'],

                'issue' => $row['issue_date'],
                'validUntil' => $row['valid_until'],
                'terms' => (int)$row['terms'],

                'taxRate' => (float)$row['tax_rate'],
                'discount' => (float)$row['discount'],
                'subtotal' => (float)$row['subtotal'],
                'taxAmount' => (float)$row['tax_amount'],
                'total' => (float)$row['total'],

                'status' => $row['status'],
                'notes' => $row['notes'] ?? '',

                'convertedInvoiceId' =>
                    $row['converted_invoice_id'] !== null
                        ? (int)$row['converted_invoice_id']
                        : null,

                'createdAt' => $row['created_at'],
                'items' => $items
            ];
        }

        $result->free();

        json_out([
            'ok' => true,
            'quotations' => $quotations
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST: Create quotation
    |--------------------------------------------------------------------------
    */

    if ($method === 'POST') {

        require_csrf();

        $data = read_json();

        if (!is_array($data)) {
            q_fail('Invalid request data.');
        }

        $quotation = q_get_input($data);

        $mysqli->begin_transaction();

        try {

            $year = (int)date('Y');

            $sequence = (int)next_number('quotation', $year);

            if ($sequence <= 0) {
                throw new Exception('Unable to generate quotation number.');
            }

            $quotationNo = sprintf(
                'QT-%04d-%04d',
                $year,
                $sequence
            );

            $createdBy = isset($user['id'])
                ? (int)$user['id']
                : (isset($_SESSION['user_id'])
                    ? (int)$_SESSION['user_id']
                    : null);

            $stmt = $mysqli->prepare(
                "INSERT INTO quotations
                (
                    quotation_no,
                    client_id,
                    issue_date,
                    valid_until,
                    terms,
                    tax_rate,
                    discount,
                    subtotal,
                    tax_amount,
                    total,
                    status,
                    notes,
                    created_by
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            if (!$stmt) {
                throw new Exception($mysqli->error);
            }

            $stmt->bind_param(
                'sissidddddssi',
                $quotationNo,
                $quotation['clientId'],
                $quotation['issue'],
                $quotation['validUntil'],
                $quotation['terms'],
                $quotation['taxRate'],
                $quotation['discount'],
                $quotation['subtotal'],
                $quotation['taxAmount'],
                $quotation['total'],
                $quotation['status'],
                $quotation['notes'],
                $createdBy
            );

            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }

            $quotationId = (int)$mysqli->insert_id;

            $stmt->close();

            q_save_items($quotationId, $quotation['items']);

            $mysqli->commit();

            json_out([
                'ok' => true,
                'message' => 'Quotation created successfully.',
                'id' => $quotationId,
                'quotationNo' => $quotationNo
            ]);

        } catch (Throwable $e) {

            $mysqli->rollback();
            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PUT: Update quotation
    |--------------------------------------------------------------------------
    */

    if ($method === 'PUT') {

        require_csrf();

        $data = read_json();

        if (!is_array($data)) {
            q_fail('Invalid request data.');
        }

        $quotationId = filter_var(
            $data['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$quotationId || $quotationId <= 0) {
            q_fail('Invalid quotation ID.');
        }

        $quotation = q_get_input($data);

        $mysqli->begin_transaction();

        try {

            $check = $mysqli->prepare(
                "SELECT id, converted_invoice_id
                 FROM quotations
                 WHERE id = ?
                 LIMIT 1"
            );

            if (!$check) {
                throw new Exception($mysqli->error);
            }

            $check->bind_param('i', $quotationId);
            $check->execute();

            $existing = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$existing) {
                q_fail('Quotation not found.', 404);
            }

            if ($existing['converted_invoice_id'] !== null) {
                q_fail('A converted quotation cannot be edited.');
            }

            $stmt = $mysqli->prepare(
                "UPDATE quotations SET
                    client_id = ?,
                    issue_date = ?,
                    valid_until = ?,
                    terms = ?,
                    tax_rate = ?,
                    discount = ?,
                    subtotal = ?,
                    tax_amount = ?,
                    total = ?,
                    status = ?,
                    notes = ?
                 WHERE id = ?"
            );

            if (!$stmt) {
                throw new Exception($mysqli->error);
            }

            $stmt->bind_param(
                'issidddddssi',
                $quotation['clientId'],
                $quotation['issue'],
                $quotation['validUntil'],
                $quotation['terms'],
                $quotation['taxRate'],
                $quotation['discount'],
                $quotation['subtotal'],
                $quotation['taxAmount'],
                $quotation['total'],
                $quotation['status'],
                $quotation['notes'],
                $quotationId
            );

            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }

            $stmt->close();

            q_save_items($quotationId, $quotation['items']);

            $mysqli->commit();

            json_out([
                'ok' => true,
                'message' => 'Quotation updated successfully.',
                'id' => $quotationId
            ]);

        } catch (Throwable $e) {

            $mysqli->rollback();
            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE: Delete quotation
    |--------------------------------------------------------------------------
    */

    if ($method === 'DELETE') {

        require_csrf();
        require_admin();

        $data = read_json();

        $quotationId = filter_var(
            $data['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$quotationId || $quotationId <= 0) {
            q_fail('Invalid quotation ID.');
        }

        $check = $mysqli->prepare(
            "SELECT id, converted_invoice_id
             FROM quotations
             WHERE id = ?
             LIMIT 1"
        );

        if (!$check) {
            throw new Exception($mysqli->error);
        }

        $check->bind_param('i', $quotationId);
        $check->execute();

        $existing = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$existing) {
            q_fail('Quotation not found.', 404);
        }

        if ($existing['converted_invoice_id'] !== null) {
            q_fail('A converted quotation cannot be deleted.');
        }

        $stmt = $mysqli->prepare(
            "DELETE FROM quotations WHERE id = ?"
        );

        if (!$stmt) {
            throw new Exception($mysqli->error);
        }

        $stmt->bind_param('i', $quotationId);

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        $stmt->close();

        json_out([
            'ok' => true,
            'message' => 'Quotation deleted successfully.'
        ]);
    }

    q_fail('Method not allowed.', 405);

} catch (Throwable $e) {

    error_log('Quotation API error: ' . $e->getMessage());

    q_fail(
        'Quotation operation failed: ' . $e->getMessage(),
        500
    );
}
