<?php
// Usage: php bin/install.php   -> creates the database (if allowed), tables, default data and runs upgrades.
// Safe to run again after every update. (No terminal? Open /setup.php in the browser instead.)
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit('CLI only');

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/src/migrate.php';

$config = require BASE_PATH . '/config.php';
$c = $config['db'];
$pdo = new PDO("mysql:host={$c['host']};port={$c['port']};charset=utf8mb4", $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$c['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (PDOException $e) {
    echo "Note: could not create database (create it in cPanel first): {$e->getMessage()}\n";
}
$pdo->exec("USE `{$c['name']}`");
foreach (run_migrations($pdo) as $line) echo "✓ $line\n";
echo "Done. Create an admin with:  php bin/create_admin.php \"Your Name\" you@email.com\n";
