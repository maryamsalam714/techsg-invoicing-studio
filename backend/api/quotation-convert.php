
<?php
require __DIR__ . '/../includes/api.php';
require __DIR__ . '/../includes/invoice_helpers.php';
require __DIR__ . '/../includes/documents.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}

require_csrf();

$data = read_json();
$quotationId = (int)($data['id'] ?? 0);

if ($quotationId <= 0) {
    json_error('Invalid quotation ID.', 422);
}

try {
    $mysqli->begin_transaction();

    // Lock the quotation to prevent duplicate conversions.
    $stmt = $mysqli->prepare(
        "SELECT * FROM quotations WHERE id = ? FOR UPDATE"
    );
    $stmt->bind_param('i', $quotationId);
    $stmt->execute();

    $quotation = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$quotation) {
        throw new Exception('Quotation not found.');
    }

    if ($quotation['status'] !== 'Accepted') {
        throw new Exception('Only accepted quotations can be converted.');
    }

    if (!empty($quotation['converted_invoice_id'])) {
        throw new Exception('This quotation has already been converted.');
    }

    // Load quotation items.
    $stmt = $mysqli->prepare(
        "SELECT description, details, quantity, unit_price
         FROM quotation_items
         WHERE quotation_id = ?
         ORDER BY sort_order ASC, id ASC"
    );

    $stmt->bind_param('i', $quotationId);
    $stmt->execute();

    $itemsResult = $stmt->get_result();
    $items = [];

    while ($item = $itemsResult->fetch_assoc()) {
        $items[] = [
            'description' => $item['description'],
            'details' => $item['details'] ?? '',
            'quantity' => (float)$item['quantity'],
            'price' => (float)$item['unit_price']
        ];
    }

    $stmt->close();

    if (!$items) {
        throw new Exception('This quotation has no items.');
    }

    // Generate a new invoice number using the existing numbering helper.
    $year = (int)date('Y');

    $invoiceNo = sprintf(
        'TSG-INV-%04d-%04d',
        $year,
        (int)next_number('invoice', $year)
    );

    // Invoice date is the conversion date.
    $issueDate = date('Y-m-d');
    $terms = (int)$quotation['terms'];

    $dueDate = (new DateTime($issueDate))
        ->modify('+' . $terms . ' days')
        ->format('Y-m-d');

    // Calculate totals from the quotation items.
    $subtotal = 0;

    foreach ($items as $item) {
        $subtotal += $item['quantity'] * $item['price'];
    }

    $subtotal = round($subtotal, 2);

    $discount = min(
        (float)$quotation['discount'],
        $subtotal
    );

    $taxRate = (float)$quotation['tax_rate'];

    $taxAmount = round(
        ($subtotal - $discount) * $taxRate / 100,
        2
    );

    $total = round(
        $subtotal - $discount + $taxAmount,
        2
    );

    $clientId = (int)$quotation['client_id'];
    $status = 'Pending';
    $notes = (string)($quotation['notes'] ?? '');
    $createdBy = (int)($user['id'] ?? $_SESSION['user_id'] ?? 0);

    // Create the invoice.
    $stmt = $mysqli->prepare(
        "INSERT INTO invoices
        (
            invoice_no,
            client_id,
            issue_date,
            terms,
            due_date,
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

    $stmt->bind_param(
        'sisisdddddssi',
        $invoiceNo,
        $clientId,
        $issueDate,
        $terms,
        $dueDate,
        $taxRate,
        $discount,
        $subtotal,
        $taxAmount,
        $total,
        $status,
        $notes,
        $createdBy
    );

    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $invoiceId = (int)$mysqli->insert_id;
    $stmt->close();

    // Copy quotation items into invoice_items.
    $stmt = $mysqli->prepare(
        "INSERT INTO invoice_items
        (
            invoice_id,
            sort_order,
            description,
            details,
            qty,
            price
        )
        VALUES (?, ?, ?, ?, ?, ?)"
    );

    foreach ($items as $index => $item) {
        $description = $item['description'];
        $details = $item['details'];
        $quantity = $item['quantity'];
        $price = $item['price'];

        $stmt->bind_param(
            'iissdd',
            $invoiceId,
            $index,
            $description,
            $details,
            $quantity,
            $price
        );

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
    }

    $stmt->close();

    // Link the quotation to the new invoice.
    $convertedStatus = 'Converted';

    $stmt = $mysqli->prepare(
        "UPDATE quotations
         SET status = ?, converted_invoice_id = ?
         WHERE id = ?"
    );

    $stmt->bind_param(
        'sii',
        $convertedStatus,
        $invoiceId,
        $quotationId
    );

    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $stmt->close();

    $mysqli->commit();

    // Send invoice email after saving.
    // Email failure must not undo a successfully created invoice.
    try {
        $email = send_invoice_email($invoiceId);
    } catch (Throwable $mailError) {
        error_log('Converted invoice email error: ' . $mailError->getMessage());

        $email = [
            'sent' => false,
            'status' => 'failed',
            'note' => $mailError->getMessage()
        ];
    }

    json_out([
        'ok' => true,
        'message' => 'Quotation converted into an invoice successfully.',
        'invoice' => [
            'id' => $invoiceNo,
            'databaseId' => $invoiceId
        ],
        'email' => $email
    ], 201);

} catch (Throwable $e) {

    $mysqli->rollback();

    error_log('Quotation conversion error: ' . $e->getMessage());

    json_error($e->getMessage(), 400);
}
