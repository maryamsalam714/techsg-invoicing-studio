<?php
// includes/documents.php: PDFs and emails for invoices and payments.
// Needs the libraries installed with Composer (backend/vendor): dompdf and phpmailer.

use Dompdf\Dompdf;
use Dompdf\Options;
use PHPMailer\PHPMailer\PHPMailer;

// Load the Composer libraries, or say clearly that they are missing
function load_vendor(): void {
    $file = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($file)) {
        throw new Exception('The PDF and email libraries are not installed. Run: composer require phpmailer/phpmailer dompdf/dompdf');
    }
    require_once $file;
}

function doc_h($text): string {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

function doc_num($n): string {
    return number_format((float)$n, 2);
}

// One invoice with its client and its items, or null if it does not exist
function doc_invoice(int $invoiceId): ?array {
    global $mysqli;
    $stmt = $mysqli->prepare(
        "SELECT i.*, c.code AS client_code, c.name AS client_name, c.contact AS client_contact,
                c.email AS client_email, c.phone AS client_phone, c.address AS client_address, c.ntn AS client_ntn
         FROM invoices i JOIN clients c ON c.id = i.client_id WHERE i.id = ?"
    );
    $stmt->bind_param('i', $invoiceId);
    $stmt->execute();
    $inv = $stmt->get_result()->fetch_assoc();
    if (!$inv) {
        return null;
    }
    $stmt = $mysqli->prepare(
        "SELECT description, details, qty, price FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id"
    );
    $stmt->bind_param('i', $invoiceId);
    $stmt->execute();
    $inv['items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    return $inv;
}

// The secret part of the client's invoice link. Made once per invoice, then kept.
function doc_token(int $invoiceId, ?string $existing): string {
    global $mysqli;
    if ($existing) {
        return $existing;
    }
    $token = bin2hex(random_bytes(20));                       // 40 random characters
    $stmt = $mysqli->prepare("UPDATE invoices SET public_token = ? WHERE id = ? AND public_token IS NULL");
    $stmt->bind_param('si', $token, $invoiceId);
    $stmt->execute();
    $stmt = $mysqli->prepare("SELECT public_token FROM invoices WHERE id = ?");
    $stmt->bind_param('i', $invoiceId);
    $stmt->execute();
    return (string)$stmt->get_result()->fetch_row()[0];
}

// ---------- The look of the PDFs (Dompdf understands simple CSS and tables, not flexbox) ----------
const DOC_CSS = '
@page { margin: 0; }
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; margin: 0; }
.band { background: #0b2447; color: #ffffff; padding: 16px 28px; }
.line { height: 4px; background: #f5a623; }
.pad { padding: 20px 28px; }
h1 { font-size: 24px; margin: 0; color: #0b2447; }
table { border-collapse: collapse; width: 100%; }
td, th { vertical-align: top; }
.items th { background: #0b2447; color: #ffffff; padding: 7px 8px; text-align: left; font-size: 10px; }
.items td { padding: 7px 8px; border-bottom: 1px solid #e3e8f2; }
.r { text-align: right; }
.box { border: 1px solid #e3e8f2; padding: 10px 12px; }
.muted { color: #667085; }
.tot td { padding: 5px 8px; border-bottom: 1px solid #e3e8f2; }
.grand td { background: #0b2447; color: #ffffff; font-weight: bold; padding: 8px; }
.paid { color: #067647; border: 2px solid #067647; padding: 2px 10px; font-weight: bold; font-size: 13px; }
small { color: #667085; }
';

function doc_logo(): string {
    $file = __DIR__ . '/../../frontend/assets/logo.png';
    if (!is_file($file)) {
        return '';
    }
    return '<img src="data:image/png;base64,' . base64_encode(file_get_contents($file)) . '" style="height:46px">';
}

// The page around every PDF: blue band with the company name, amber line, then the content
function doc_page(array $co, string $body): string {
    return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>' . DOC_CSS . '</style></head><body>'
        . '<div class="band"><table><tr><td style="width:60px">' . doc_logo() . '</td><td>'
        . '<div style="font-size:18px;font-weight:bold">' . doc_h($co['legal']) . '</div>'
        . '<div style="font-size:10px">' . doc_h($co['slogan']) . ' &nbsp;|&nbsp; ' . doc_h($co['tagline']) . '</div>'
        . '</td></tr></table></div><div class="line"></div>'
        . '<div class="pad">' . $body . '</div></body></html>';
}

// One "label: value" row
function doc_row(string $label, $value): string {
    $v = ($value === null || $value === '') ? '—' : doc_h($value);
    return '<tr><td class="muted" style="padding:2px 0;width:42%">' . doc_h($label) . '</td><td style="padding:2px 0"><b>' . $v . '</b></td></tr>';
}

function doc_invoice_html(array $inv, array $co): string {
    $rows = '';
    foreach ($inv['items'] as $i => $x) {
        $rows .= '<tr><td>' . ($i + 1) . '</td><td><b>' . doc_h($x['description']) . '</b>'
            . (($x['details'] ?? '') !== '' ? '<br><small>' . doc_h($x['details']) . '</small>' : '')
            . '</td><td class="r">' . doc_h((float)$x['qty']) . '</td><td class="r">' . doc_num($x['price'])
            . '</td><td class="r">' . doc_num((float)$x['qty'] * (float)$x['price']) . '</td></tr>';
    }

    $paidSoFar = (float)$inv['amount_paid'];
    $balance   = max(0, round((float)$inv['total'] - $paidSoFar, 2));
    $totals = '<tr><td>Subtotal</td><td class="r"><b>' . doc_num($inv['subtotal']) . '</b></td></tr>'
        . ((float)$inv['discount'] > 0 ? '<tr><td>Discount</td><td class="r"><b>-' . doc_num($inv['discount']) . '</b></td></tr>' : '')
        . '<tr><td>Tax (' . doc_h((float)$inv['tax_rate']) . '%)</td><td class="r"><b>' . doc_num($inv['tax_amount']) . '</b></td></tr>'
        . '<tr class="grand"><td>Total Amount (PKR)</td><td class="r">' . doc_num($inv['total']) . '</td></tr>'
        . ($paidSoFar > 0
            ? '<tr><td>Paid so far</td><td class="r"><b>' . doc_num($paidSoFar) . '</b></td></tr>'
              . '<tr><td>Balance due</td><td class="r"><b>' . doc_num($balance) . '</b></td></tr>'
            : '');

    $terms = [
        'Payment is due within ' . ($inv['terms'] ?: $co['terms']) . ' days from the invoice date.',
        'Late payments may result in additional charges.',
        'All work remains the property of ' . $co['name'] . ' until full payment is received.',
        'For any queries, please contact us at ' . $co['email'] . '.',
    ];
    $termsHtml = '';
    foreach ($terms as $t) {
        $termsHtml .= '<li>' . doc_h($t) . '</li>';
    }

    $body = '<table><tr><td><h1>INVOICE</h1><div class="muted">Professional IT &amp; Software Development Services</div></td>'
        . '<td style="width:46%"><table>'
        . doc_row('Invoice No:', $inv['invoice_no']) . doc_row('Issue Date:', $inv['issue_date'])
        . doc_row('Due Date:', $inv['due_date']) . doc_row('Payment Terms:', 'Net ' . ($inv['terms'] ?: $co['terms']))
        . '</table></td></tr></table>'
        . ($inv['status'] === 'Paid' ? '<p><span class="paid">PAID</span></p>' : '')
        . '<table style="margin-top:12px"><tr>'
        . '<td class="box" style="width:49%"><b>BILL TO</b><br><br><b>' . doc_h($inv['client_name']) . '</b><br><small>Client ID: ' . doc_h($inv['client_code']) . '</small>'
        . ($inv['client_email'] ? '<br>' . doc_h($inv['client_email']) : '')
        . ($inv['client_phone'] ? '<br>' . doc_h($inv['client_phone']) : '')
        . ($inv['client_address'] ? '<br>' . doc_h($inv['client_address']) : '')
        . ($inv['client_ntn'] ? '<br>NTN/CNIC: ' . doc_h($inv['client_ntn']) : '')
        . '</td><td style="width:2%"></td>'
        . '<td class="box" style="width:49%"><b>FROM</b><br><br><b>' . doc_h($co['name']) . '</b><br><small>' . doc_h($co['tagline']) . '</small>'
        . '<br>' . doc_h($co['email']) . '<br>' . doc_h($co['phone']) . '<br>' . doc_h($co['address']) . '<br>' . doc_h($co['web'])
        . ($co['ntn'] ? '<br>NTN: ' . doc_h($co['ntn']) . ($co['strn'] ? ' | STRN: ' . doc_h($co['strn']) : '') : '')
        . '</td></tr></table>'
        . '<table class="items" style="margin-top:14px"><thead><tr><th style="width:5%">#</th><th>Service / Description</th>'
        . '<th class="r" style="width:9%">Qty</th><th class="r" style="width:17%">Unit Price (PKR)</th><th class="r" style="width:17%">Amount (PKR)</th></tr></thead>'
        . '<tbody>' . $rows . '</tbody></table>'
        . '<table style="margin-top:12px"><tr><td style="width:54%;padding-right:14px"><div class="box"><b>PAYMENT DETAILS</b><table style="margin-top:6px">'
        . doc_row('Bank Name', $co['bank']) . doc_row('Account Title', $co['acTitle']) . doc_row('Account Number', $co['acNo'])
        . doc_row('IBAN', $co['iban']) . doc_row('Payment Method', $co['methods'])
        . '</table></div></td><td><table class="tot">' . $totals . '</table></td></tr></table>'
        . '<div style="margin-top:12px"><b>TERMS &amp; CONDITIONS</b><ol style="margin:6px 0 0 16px;padding:0">' . $termsHtml . '</ol>'
        . (($inv['notes'] ?? '') !== '' ? '<p><b>Note:</b> ' . doc_h($inv['notes']) . '</p>' : '') . '</div>'
        . '<div style="margin-top:18px;text-align:center" class="muted">Thank you for your business. &nbsp;|&nbsp; '
        . doc_h($co['email']) . ' &nbsp;|&nbsp; ' . doc_h($co['phone']) . ' &nbsp;|&nbsp; ' . doc_h($co['web']) . '</div>';

    return doc_page($co, $body);
}

function doc_receipt_html(array $pay, array $inv, array $co): string {
    $total   = (float)$inv['total'];
    $paid    = (float)$inv['amount_paid'];
    $balance = max(0, round($total - $paid, 2));
    $body = '<table><tr><td><h1>PAYMENT RECEIPT</h1><div class="muted">' . doc_h($co['legal']) . '</div></td>'
        . '<td class="r"><div class="muted">Receipt No.</div><div style="font-size:18px;font-weight:bold">' . doc_h($pay['receipt_no']) . '</div></td></tr></table>'
        . '<div class="box" style="margin:16px 0;background:#f3f6fc"><table><tr><td class="muted">Amount received</td>'
        . '<td class="r" style="font-size:20px;font-weight:bold;color:#0b2447">Rs. ' . doc_num($pay['amount']) . '</td></tr></table></div>'
        . '<table>'
        . doc_row('Received from', $inv['client_name']) . doc_row('Client ID', $inv['client_code']) . doc_row('For invoice', $inv['invoice_no'])
        . doc_row('Payment date', $pay['payment_date']) . doc_row('Payment method', $pay['method'])
        . doc_row('Reference / transaction ID', $pay['reference']) . doc_row('Notes', $pay['notes'])
        . '</table>'
        . '<table style="margin-top:14px;border-top:1px solid #e3e8f2">'
        . doc_row('Invoice total', 'Rs. ' . doc_num($total)) . doc_row('Paid to date', 'Rs. ' . doc_num($paid)) . doc_row('Balance due', 'Rs. ' . doc_num($balance))
        . '</table>'
        . ($pay['status'] !== 'Paid' ? '<p style="color:#b42318"><b>This payment was ' . doc_h(strtolower($pay['status'])) . '. This receipt is no longer valid.</b></p>' : '')
        . '<p class="muted" style="margin-top:24px">Thank you for your payment. This is a computer-generated receipt. '
        . doc_h($co['email']) . ' | ' . doc_h($co['phone']) . '</p>';
    return doc_page($co, $body);
}

// HTML in, PDF file contents out
function doc_pdf(string $html): string {
    load_vendor();
    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'DejaVu Sans');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    return $dompdf->output();
}

// Send one email and write the result to the email_log table.
// It NEVER throws: a problem with email must not stop an invoice or a payment from being saved.
// $attachments is a function that gives ['file name.pdf' => file contents] (it runs only when we really send).
function doc_send_mail(string $kind, string $ref, string $to, string $subject, string $html, callable $attachments): array {
    global $mysqli;
    $status = 'sent';
    $error  = null;
    try {
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $status = 'skipped';
            $error  = 'The client has no valid email address.';
        } elseif (!MAIL_ENABLED) {
            $status = 'skipped';
            $error  = 'Email sending is switched off (MAIL_ENABLED in config.php).';
        } else {
            load_vendor();
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->Port       = MAIL_PORT;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USER;
            $mail->Password   = MAIL_PASS;
            $mail->SMTPSecure = (MAIL_SECURE === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Timeout    = 15;
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = trim(strip_tags(str_replace(['<br>', '</p>'], "\n", $html)));
            foreach ($attachments() as $name => $bytes) {
                $mail->addStringAttachment($bytes, $name, 'base64', 'application/pdf');
            }
            $mail->send();
        }
    } catch (Throwable $e) {
        $status = 'failed';
        $error  = $e->getMessage();
        error_log('Email error: ' . $error);
    }

    try {
        $stmt = $mysqli->prepare("INSERT INTO email_log (kind, ref, to_email, subject, status, error) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssss', $kind, $ref, $to, $subject, $status, $error);
        $stmt->execute();
    } catch (Throwable $e) {
        error_log('Email log error: ' . $e->getMessage());
    }
    return ['sent' => $status === 'sent', 'status' => $status, 'to' => $to, 'note' => $error];
}

// The email for a new invoice: a link and the invoice PDF
function send_invoice_email(int $invoiceId): array {
    $inv = doc_invoice($invoiceId);
    if (!$inv) {
        return ['sent' => false, 'status' => 'failed', 'to' => '', 'note' => 'Invoice not found.'];
    }
    $co    = COMPANY;
    $token = doc_token($invoiceId, $inv['public_token'] ?? null);
    $link  = rtrim(APP_URL, '/') . '/invoice-share.html?t=' . $token;
    $name  = $inv['client_contact'] ?: $inv['client_name'];
    $html  = '<p>Dear ' . doc_h($name) . ',</p>'
        . '<p>Please find your invoice <b>' . doc_h($inv['invoice_no']) . '</b> for <b>Rs. ' . doc_num($inv['total']) . '</b>, due on ' . doc_h($inv['due_date']) . '.</p>'
        . '<p>You can view it online here:<br><a href="' . doc_h($link) . '">' . doc_h($link) . '</a></p>'
        . '<p>The invoice is also attached to this email as a PDF.</p>'
        . '<p>Kind regards,<br>' . doc_h($co['legal']) . '<br>' . doc_h($co['phone']) . ' | ' . doc_h($co['email']) . '</p>';
    return doc_send_mail('invoice', $inv['invoice_no'], (string)$inv['client_email'],
        'Invoice ' . $inv['invoice_no'] . ' from ' . $co['name'], $html,
        function () use ($inv, $co) { return [$inv['invoice_no'] . '.pdf' => doc_pdf(doc_invoice_html($inv, $co))]; });
}

// The email for a payment: a thank-you and the receipt PDF
function send_payment_email(string $receiptNo): array {
    global $mysqli;
    $stmt = $mysqli->prepare(
        "SELECT receipt_no, invoice_id, amount, method, reference, payment_date, status, notes FROM payments WHERE receipt_no = ?"
    );
    $stmt->bind_param('s', $receiptNo);
    $stmt->execute();
    $pay = $stmt->get_result()->fetch_assoc();
    $inv = $pay ? doc_invoice((int)$pay['invoice_id']) : null;
    if (!$pay || !$inv) {
        return ['sent' => false, 'status' => 'failed', 'to' => '', 'note' => 'Payment not found.'];
    }
    $co      = COMPANY;
    $token   = doc_token((int)$inv['id'], $inv['public_token'] ?? null);
    $link    = rtrim(APP_URL, '/') . '/invoice-share.html?t=' . $token;
    $balance = max(0, round((float)$inv['total'] - (float)$inv['amount_paid'], 2));
    $name    = $inv['client_contact'] ?: $inv['client_name'];
    $html    = '<p>Dear ' . doc_h($name) . ',</p>'
        . '<p>Thank you. We received your payment of <b>Rs. ' . doc_num($pay['amount']) . '</b> on ' . doc_h($pay['payment_date'])
        . ' for invoice <b>' . doc_h($inv['invoice_no']) . '</b>.</p>'
        . '<p>' . ($balance > 0 ? 'The balance due is <b>Rs. ' . doc_num($balance) . '</b>.' : 'Your invoice is now <b>fully paid</b>.') . '</p>'
        . '<p>Your receipt <b>' . doc_h($pay['receipt_no']) . '</b> is attached as a PDF. You can also view your invoice online:<br>'
        . '<a href="' . doc_h($link) . '">' . doc_h($link) . '</a></p>'
        . '<p>Kind regards,<br>' . doc_h($co['legal']) . '<br>' . doc_h($co['phone']) . ' | ' . doc_h($co['email']) . '</p>';
    return doc_send_mail('payment', $pay['receipt_no'], (string)$inv['client_email'],
        'Payment receipt ' . $pay['receipt_no'] . ' from ' . $co['name'], $html,
        function () use ($pay, $inv, $co) { return [$pay['receipt_no'] . '.pdf' => doc_pdf(doc_receipt_html($pay, $inv, $co))]; });
}