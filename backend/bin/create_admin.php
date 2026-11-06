<?php
// Usage: php bin/create_admin.php "Full Name" email@domain.com [password]
// If no password is given, a random one is generated and printed once.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit('CLI only');
require dirname(__DIR__) . '/src/bootstrap.php';

[$_, $name, $email] = $argv + [null, null, null];
$password = $argv[3] ?? bin2hex(random_bytes(6));
if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL)) exit("Usage: php bin/create_admin.php \"Full Name\" email@domain.com [password]\n");
if (strlen($password) < 8) exit("Password must be at least 8 characters\n");

q('INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash)',
  [$name, strtolower($email), password_hash($password, PASSWORD_DEFAULT)]);
echo "Admin ready: $email\nPassword: $password\n";
