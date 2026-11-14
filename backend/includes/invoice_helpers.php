<?php
// includes/invoice_helpers.php: shared by api/invoices.php and api/payments.php

// Recalculate how much of an invoice is paid, and set its status to match.
// Call it inside the same transaction as the change that needs it.
function refresh_invoice_payment(int $invoiceId): void {
    global $mysqli;

    $stmt = $mysqli->prepare("SELECT total, status FROM invoices WHERE id = ?");
    $stmt->bind_param('i', $invoiceId);
    $stmt->execute();
    $inv = $stmt->get_result()->fetch_assoc();
    if (!$inv) {
        return;
    }

    // Only payments with the status 'Paid' count (a refunded payment does not)
    $stmt = $mysqli->prepare(
        "SELECT COALESCE(SUM(amount), 0), MAX(payment_date) FROM payments WHERE invoice_id = ? AND status = 'Paid'"
    );
    $stmt->bind_param('i', $invoiceId);
    $stmt->execute();
    [$paid, $lastDate] = $stmt->get_result()->fetch_row();
    $paid = round((float)$paid, 2);

    // Draft and Cancelled invoices keep their status. The others follow the payments.
    $status = $inv['status'];
    if ($status === 'Pending' || $status === 'Paid') {
        $status = ($paid > 0 && $paid >= (float)$inv['total'] - 0.005) ? 'Paid' : 'Pending';
    }
    $paidAt = ($status === 'Paid') ? $lastDate : null;

    $stmt = $mysqli->prepare("UPDATE invoices SET amount_paid = ?, status = ?, paid_at = ? WHERE id = ?");
    $stmt->bind_param('dssi', $paid, $status, $paidAt, $invoiceId);
    $stmt->execute();
}