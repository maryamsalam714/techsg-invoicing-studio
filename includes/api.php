<?php
// includes/api.php: shared helpers for every file in the api folder.
// Every API file starts with:  require __DIR__ . '/../includes/api.php';

// Start the session. httponly = JavaScript cannot read the session cookie.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');         // never cache API answers

// Send a JSON answer and stop.
function json_out($data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Send an error as JSON and stop. Example: json_error('Not found', 404);
function json_error(string $message, int $status = 400): void {
    json_out(['ok' => false, 'error' => $message], $status);
}

// Load the settings first, so APP_DEBUG is known inside the error handler below
require_once __DIR__ . '/../config.php';

// Any uncaught PHP or database error becomes a safe JSON answer.
// The details go to the PHP error log, and are shown only while APP_DEBUG is true.
set_exception_handler(function ($e) {
    error_log('API error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $out = ['ok' => false, 'error' => 'Server error'];
    if (defined('APP_DEBUG') && APP_DEBUG) {
        $out['debug'] = $e->getMessage();
    }
    json_out($out, 500);
});

require_once __DIR__ . '/db.php';          // gives us $mysqli

// The logged-in user from the session, or null if nobody is logged in.
function current_user(): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id'   => (int)$_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? '',
        'role' => $_SESSION['role'] ?? 'staff',    // 'admin' or 'staff'
    ];
}

// Stop with a 401 answer if nobody is logged in.
function require_login(): array {
    $user = current_user();
    if (!$user) {
        json_error('Please sign in first.', 401);
    }
    return $user;
}

// Stop with a 403 answer unless the user is an admin.
function require_admin(): array {
    $user = require_login();
    if ($user['role'] !== 'admin') {
        json_error('Admin only.', 403);
    }
    return $user;
}

// CSRF token: a secret proving a request came from OUR pages. The browser sends it in a header.
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Call this at the top of any request that changes data.
function require_csrf(): void {
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
        json_error('Invalid security token. Refresh the page and try again.', 403);
    }
}

// The JSON body the browser sent, as an array.
function read_json(): array {
    $data = json_decode(file_get_contents('php://input') ?: '[]', true);
    return is_array($data) ? $data : [];
}