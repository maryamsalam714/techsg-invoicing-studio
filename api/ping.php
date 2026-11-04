<?php
// api/ping.php: temporary test file, we delete it later
require __DIR__ . '/../includes/api.php';

if (isset($_GET['fail'])) {
    throw new Exception('This is a test error');   // tests the error handler
}

$users = (int)$mysqli->query("SELECT COUNT(*) FROM users")->fetch_row()[0];

json_out([
    'ok'         => true,
    'time'       => date('c'),
    'users'      => $users,                // proves the API can read the database
    'logged_in'  => current_user() !== null,
    'csrf_token' => csrf_token(),
]);