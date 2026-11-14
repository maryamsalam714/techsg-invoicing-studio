<?php
// api/users.php (admin only)
//   GET                 list the users
//   POST                create a user
//   PUT    ?id=U-0002   change a user: name, email, role, active and/or password
//   DELETE ?id=U-0002   delete a user
require __DIR__ . '/../includes/api.php';

$me     = require_admin();                 // staff cannot use anything in this file
$method = $_SERVER['REQUEST_METHOD'];

// A database row becomes the shape the frontend expects. The password hash is NEVER sent out.
function user_out(array $r): array {
    return [
        'id'       => $r['code'],
        'name'     => $r['name'],
        'username' => $r['username'],
        'email'    => (string)$r['email'],
        'role'     => $r['role'],
        'active'   => (bool)$r['active'],
        'created'  => substr((string)$r['created_at'], 0, 10),
    ];
}

// Find one user by the U-0001 code, or stop with a 404 answer
function find_user(string $code): array {
    global $mysqli;
    $stmt = $mysqli->prepare(
        "SELECT id, code, name, username, email, role, active, created_at FROM users WHERE code = ?"
    );
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        json_error('User not found.', 404);
    }
    return $row;
}

// How many OTHER active admins exist (not counting the user with this id)?
function other_active_admins(int $exceptId): int {
    global $mysqli;
    $stmt = $mysqli->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1 AND id <> ?");
    $stmt->bind_param('i', $exceptId);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_row()[0];
}

function check_password(string $pw): void {
    if (strlen($pw) < 8) {
        json_error('The password must be at least 8 characters.', 422);
    }
    if (strlen($pw) > 72) {                                  // the password scrambler only reads the first 72
        json_error('The password is too long (maximum 72 characters).', 422);
    }
}

// ---- GET: the list ----
if ($method === 'GET') {
    $rows = $mysqli->query(
        "SELECT code, name, username, email, role, active, created_at FROM users ORDER BY id"
    )->fetch_all(MYSQLI_ASSOC);
    json_out(['ok' => true, 'users' => array_map('user_out', $rows)]);
}

require_csrf();                                              // everything below changes data

// ---- POST: create ----
if ($method === 'POST') {
    $in       = read_json();
    $name     = trim((string)($in['name'] ?? ''));
    $username = trim((string)($in['username'] ?? ''));
    $email    = trim((string)($in['email'] ?? ''));
    $role     = (string)($in['role'] ?? 'staff');
    $password = (string)($in['password'] ?? '');

    if ($name === '' || mb_strlen($name) > 100) {
        json_error('Enter the full name (maximum 100 characters).', 422);
    }
    if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
        json_error('The username must be 3 to 50 letters, numbers, dots, dashes or underscores.', 422);
    }
    if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150)) {
        json_error('The email address is not valid.', 422);
    }
    if (!in_array($role, ['admin', 'staff'], true)) {
        json_error('The role is not valid.', 422);
    }
    check_password($password);

    $stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        json_error('That username already exists.', 409);
    }

    $hash    = password_hash($password, PASSWORD_DEFAULT);   // the scrambled password we store
    $emailDb = ($email === '') ? null : $email;

    $mysqli->begin_transaction();
    $code = 'U-' . str_pad((string)next_number('user'), 4, '0', STR_PAD_LEFT);
    $stmt = $mysqli->prepare(
        "INSERT INTO users (code, name, username, email, role, password_hash) VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('ssssss', $code, $name, $username, $emailDb, $role, $hash);
    $stmt->execute();
    $mysqli->commit();

    audit('create', 'user', $code, $username);
    json_out(['ok' => true, 'user' => user_out(find_user($code))], 201);
}

if ($method !== 'PUT' && $method !== 'DELETE') {
    json_error('Method not allowed.', 405);
}

$row  = find_user(trim((string)($_GET['id'] ?? '')));
$isMe = ((int)$row['id'] === $me['id']);
$isActiveAdmin = ($row['role'] === 'admin' && (int)$row['active'] === 1);

// ---- PUT: change a user ----
if ($method === 'PUT') {
    $in     = read_json();
    $name   = array_key_exists('name', $in)   ? trim((string)$in['name'])  : $row['name'];
    $email  = array_key_exists('email', $in)  ? trim((string)$in['email']) : (string)$row['email'];
    $role   = array_key_exists('role', $in)   ? (string)$in['role']        : $row['role'];
    $active = array_key_exists('active', $in) ? (int)(bool)$in['active']   : (int)$row['active'];

    if ($name === '' || mb_strlen($name) > 100) {
        json_error('Enter the full name (maximum 100 characters).', 422);
    }
    if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150)) {
        json_error('The email address is not valid.', 422);
    }
    if (!in_array($role, ['admin', 'staff'], true)) {
        json_error('The role is not valid.', 422);
    }

    // Protect the system: nobody can lock the admins out
    if ($isActiveAdmin && ($role !== 'admin' || $active === 0)) {
        if ($isMe) {
            json_error('You cannot demote or disable your own account.', 409);
        }
        if (other_active_admins((int)$row['id']) === 0) {
            json_error('At least one active admin is required.', 409);
        }
    }

    $emailDb = ($email === '') ? null : $email;
    $stmt = $mysqli->prepare("UPDATE users SET name = ?, email = ?, role = ?, active = ? WHERE id = ?");
    $stmt->bind_param('sssii', $name, $emailDb, $role, $active, $row['id']);
    $stmt->execute();

    if (array_key_exists('password', $in)) {                // a password reset
        $pw = (string)$in['password'];
        check_password($pw);
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->bind_param('si', $hash, $row['id']);
        $stmt->execute();
        audit('password_reset', 'user', $row['code'], null);
    }

    audit('update', 'user', $row['code'], $role . ($active ? ', active' : ', disabled'));
    json_out(['ok' => true, 'user' => user_out(find_user($row['code']))]);
}

// ---- DELETE ----
if ($isMe) {
    json_error('You cannot delete your own account.', 409);
}
if ($isActiveAdmin && other_active_admins((int)$row['id']) === 0) {
    json_error('At least one active admin is required.', 409);
}
$stmt = $mysqli->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param('i', $row['id']);
$stmt->execute();
audit('delete', 'user', $row['code'], $row['username']);
json_out(['ok' => true]);