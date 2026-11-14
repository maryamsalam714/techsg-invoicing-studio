<?php
// api/login.php: POST {username, password}. Signs the user in.
require __DIR__ . '/../includes/api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('POST only.', 405);
}
require_csrf();

$in       = read_json();
$username = trim((string)($in['username'] ?? ''));
$password = (string)($in['password'] ?? '');
if ($username === '' || $password === '') {
    json_error('Enter your username and password.', 422);
}

// Too many wrong tries from this computer in the last 10 minutes? Stop here.
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$stmt = $mysqli->prepare(
    "SELECT COUNT(*) FROM audit_log
     WHERE action = 'login_failed' AND ip = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)"
);
$stmt->bind_param('s', $ip);
$stmt->execute();
if ((int)$stmt->get_result()->fetch_row()[0] >= 5) {
    json_error('Too many wrong attempts. Please try again in 10 minutes.', 429);
}

// Find the user (prepared statement, so a typed quote mark can do no harm)
$stmt = $mysqli->prepare(
    "SELECT id, code, name, username, role, password_hash FROM users WHERE username = ? AND active = 1"
);
$stmt->bind_param('s', $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Same message for "no such user" and "wrong password", so attackers learn nothing
if (!$user || !password_verify($password, $user['password_hash'])) {
    audit('login_failed', 'user', substr($username, 0, 40), 'wrong username or password');
    json_error('Wrong username or password.', 401);
}

session_regenerate_id(true);          // new session ID after login
unset($_SESSION['csrf_token']);       // and a new security token
$_SESSION['user_id']   = (int)$user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['role']      = $user['role'];

$stmt = $mysqli->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
audit('login', 'user', $user['code'], 'signed in');

json_out([
    'ok'         => true,
    'user'       => ['uid' => $user['code'], 'name' => $user['name'],
                     'username' => $user['username'], 'role' => $user['role']],
    'csrf_token' => csrf_token(),
]);