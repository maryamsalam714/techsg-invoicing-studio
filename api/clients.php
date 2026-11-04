<?php
// api/clients.php
//   GET                 list the clients
//   POST                create a client
//   PUT    ?id=CL-0001  update a client
//   DELETE ?id=CL-0001  delete a client (refused if it has invoices)
require __DIR__ . '/../includes/api.php';

$user   = require_login();               // every request here needs a signed-in user
$method = $_SERVER['REQUEST_METHOD'];

// A database row becomes the shape the frontend expects (id = the CL-0001 code)
function client_out(array $r): array {
    return [
        'id'      => $r['code'],
        'name'    => (string)$r['name'],
        'contact' => (string)$r['contact'],
        'email'   => (string)$r['email'],
        'phone'   => (string)$r['phone'],
        'address' => (string)$r['address'],
        'ntn'     => (string)$r['ntn'],
    ];
}

// Clean and check what the browser sent. Stops with a 422 answer if something is wrong.
function client_in(array $in): array {
    $c = [];
    foreach (['name' => 150, 'contact' => 150, 'email' => 150, 'phone' => 40, 'ntn' => 30, 'address' => 500] as $k => $max) {
        $c[$k] = trim((string)($in[$k] ?? ''));
        if (mb_strlen($c[$k]) > $max) {
            json_error("$k is too long (maximum $max characters).", 422);
        }
    }
    if ($c['name'] === '') {
        json_error('Client name is required.', 422);
    }
    if ($c['email'] !== '' && !filter_var($c['email'], FILTER_VALIDATE_EMAIL)) {
        json_error('The email address is not valid.', 422);
    }
    return $c;
}

// Find one client by its CL-0001 code, or stop with a 404 answer
function find_client(string $code): array {
    global $mysqli;
    $stmt = $mysqli->prepare(
        "SELECT id, code, name, contact, email, phone, address, ntn FROM clients WHERE code = ? AND is_archived = 0"
    );
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        json_error('Client not found.', 404);
    }
    return $row;
}

// ---- GET: the list ----
if ($method === 'GET') {
    $rows = $mysqli->query(
        "SELECT code, name, contact, email, phone, address, ntn FROM clients WHERE is_archived = 0 ORDER BY id"
    )->fetch_all(MYSQLI_ASSOC);
    json_out(['ok' => true, 'clients' => array_map('client_out', $rows)]);
}

// Everything below changes data, so it needs the security token
require_csrf();

// ---- POST: create ----
if ($method === 'POST') {
    $c = client_in(read_json());

    $mysqli->begin_transaction();         // if anything fails, the number is given back
    $code = 'CL-' . str_pad((string)next_number('client'), 4, '0', STR_PAD_LEFT);
    $stmt = $mysqli->prepare(
        "INSERT INTO clients (code, name, contact, email, phone, address, ntn) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('sssssss', $code, $c['name'], $c['contact'], $c['email'], $c['phone'], $c['address'], $c['ntn']);
    $stmt->execute();
    $mysqli->commit();

    audit('create', 'client', $code, $c['name']);
    json_out(['ok' => true, 'client' => client_out(['code' => $code] + $c)], 201);
}

$code = trim((string)($_GET['id'] ?? ''));

// ---- PUT: update ----
if ($method === 'PUT') {
    $row = find_client($code);
    $c   = client_in(read_json());
    $stmt = $mysqli->prepare(
        "UPDATE clients SET name = ?, contact = ?, email = ?, phone = ?, address = ?, ntn = ? WHERE id = ?"
    );
    $stmt->bind_param('ssssssi', $c['name'], $c['contact'], $c['email'], $c['phone'], $c['address'], $c['ntn'], $row['id']);
    $stmt->execute();
    audit('update', 'client', $code, $c['name']);
    json_out(['ok' => true, 'client' => client_out(find_client($code))]);
}

// ---- DELETE ----
if ($method === 'DELETE') {
    $row = find_client($code);
    $stmt = $mysqli->prepare("SELECT COUNT(*) FROM invoices WHERE client_id = ?");
    $stmt->bind_param('i', $row['id']);
    $stmt->execute();
    if ((int)$stmt->get_result()->fetch_row()[0] > 0) {
        json_error('This client has invoices, so it cannot be deleted.', 409);
    }
    $stmt = $mysqli->prepare("DELETE FROM clients WHERE id = ?");
    $stmt->bind_param('i', $row['id']);
    $stmt->execute();
    audit('delete', 'client', $code, $row['name']);
    json_out(['ok' => true]);
}

json_error('Method not allowed.', 405);