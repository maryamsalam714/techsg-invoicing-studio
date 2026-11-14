<?php
// api/session.php: GET. Says who is signed in, and gives the browser its security token.
require __DIR__ . '/../includes/api.php';

$me = current_user();
$user = null;

if ($me) {
    // Check the database every time, so a disabled user is signed out immediately
    $stmt = $mysqli->prepare("SELECT code, name, username, role FROM users WHERE id = ? AND active = 1");
    $stmt->bind_param('i', $me['id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
}

if (!$user) {
    $_SESSION['user_id'] = null;   // not signed in (or the account was disabled)
    unset($_SESSION['user_id']);
    // Still hand out a token: the login form needs one
    json_out(['ok' => false, 'error' => 'Not signed in.', 'csrf_token' => csrf_token()], 401);
}

json_out([
    'ok'         => true,
    'user'       => ['uid' => $user['code'], 'name' => $user['name'],
                     'username' => $user['username'], 'role' => $user['role']],
    'csrf_token' => csrf_token(),
]);