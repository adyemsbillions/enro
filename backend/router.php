<?php
// Local dev only:  php -S 0.0.0.0:8080 router.php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/(config|src/|storage/|database/|bin/|lib/)#', $path)) { http_response_code(403); exit('Forbidden'); }
if ($path === '/' ) { header('Location: /admin/'); exit; }
if (str_starts_with($path, '/api')) { require __DIR__ . '/api/index.php'; return true; }
if (is_dir(__DIR__ . $path) && is_file(__DIR__ . rtrim($path, '/') . '/index.php')) {
    if (!str_ends_with($path, '/')) { header('Location: ' . $path . '/'); exit; }
    require __DIR__ . $path . 'index.php'; return true;
}
return false; // serve static files / .php files as-is
