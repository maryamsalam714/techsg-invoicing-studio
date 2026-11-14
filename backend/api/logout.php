<?php
// api/logout.php: POST. Signs the user out. (No security token needed: logging out is harmless.)
require __DIR__ . '/../includes/api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('POST only.', 405);
}

if (!empty($_SESSION['user_id'])) {
    audit('logout', 'user', null, 'signed out');
}

$_SESSION = [];                                   // empty the session
$p = session_get_cookie_params();
setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
session_destroy();

json_out(['ok' => true]);