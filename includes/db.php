<?php
// includes/db.php: every PHP file that needs the database loads this file
require_once __DIR__ . '/../config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);   // show errors instead of failing silently
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$mysqli->set_charset('utf8mb4');                              // so Urdu and emojis are stored correctly