<?php
// Usage: php bin/install.php            -> creates the database (if allowed), tables and default data
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit('CLI only');

$config = require dirname(__DIR__) . '/config.php';
$c = $config['db'];
$pdo = new PDO("mysql:host={$c['host']};port={$c['port']};charset=utf8mb4", $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$c['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (PDOException $e) {
    echo "Note: could not create database (create it in cPanel first): {$e->getMessage()}\n";
}
$pdo->exec("USE `{$c['name']}`");
foreach (['schema.sql', 'seed.sql'] as $f) {
    $pdo->exec(file_get_contents(dirname(__DIR__) . "/database/$f"));
    echo "✓ $f\n";
}
echo "Done. Now create an admin:  php bin/create_admin.php \"Your Name\" you@email.com\n";
