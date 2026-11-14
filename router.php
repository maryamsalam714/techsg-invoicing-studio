<?php
// router.php: used only on your computer, started with:
//   C:\xampp\php\php.exe -S localhost:8000 -t frontend router.php
// Pages and files come from the frontend folder. Every /api/... address goes to the backend folder.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if (strpos($path, '/api/') === 0) {
    $file = realpath(__DIR__ . '/backend' . $path);
    $api  = realpath(__DIR__ . '/backend/api');
    // Only .php files inside backend/api may run (this also blocks ../ tricks)
    if ($file && $api && strpos($file, $api . DIRECTORY_SEPARATOR) === 0 && substr($file, -4) === '.php') {
        chdir(dirname($file));
        require $file;
        return true;
    }
    http_response_code(404);
    header('Content-Type: application/json');
    echo '{"ok":false,"error":"Not found"}';
    return true;
}

return false;   // everything else is a normal file from the frontend folder