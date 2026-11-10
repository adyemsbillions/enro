<?php
/*
 * Enro setup & upgrade page — open https://your-domain/setup.php
 *
 *  1. No config.php yet  → enter the cPanel MySQL details; config.php is written for you.
 *  2. No admin yet       → tables are created and you create the first admin account.
 *  3. Already installed  → only a logged-in admin can use it, to apply database upgrades after uploading a new version.
 */
declare(strict_types=1);

const BASE = __DIR__;
session_name('enro_admin');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');

$csrf = $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$posted = $_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($csrf, (string)($_POST['_csrf'] ?? ''));
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$error = null;
$done = [];

/* ---------- Step 1: database details → config.php ---------- */
if (!is_file(BASE . '/config.php')) {
    $f = ['host' => 'localhost', 'port' => '3306', 'name' => '', 'user' => '', 'pass' => '',
          'app_url' => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'enro.truszedproperties.com')];
    $manual = null;
    if ($posted) {
        foreach ($f as $k => $_) $f[$k] = trim((string)($_POST[$k] ?? ''));
        try {
            new PDO("mysql:host={$f['host']};port={$f['port']};dbname={$f['name']};charset=utf8mb4", $f['user'], $f['pass'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 8]);
            $cfg = [
                'debug' => false,
                'app_url' => rtrim($f['app_url'], '/'),
                'timezone' => 'Africa/Lagos',
                'db' => ['host' => $f['host'], 'port' => (int)$f['port'], 'name' => $f['name'], 'user' => $f['user'], 'pass' => $f['pass']],
                'jwt_secret' => bin2hex(random_bytes(32)),
                'jwt_ttl_days' => 30,
                'vtpass' => ['base_url' => 'https://sandbox.vtpass.com/api', 'api_key' => '', 'secret_key' => '', 'public_key' => ''],
                'paystack' => ['secret_key' => ''],
                'fcm' => ['service_account_file' => BASE . '/storage/firebase-service-account.json'],
                'upload_max_mb' => 5,
            ];
            $php = "<?php\n// Written by setup.php on " . date('Y-m-d H:i') . ". Keep this file private.\nreturn " . var_export($cfg, true) . ";\n";
            if (@file_put_contents(BASE . '/config.php', $php) === false) {
                $manual = $php; // folder not writable: show it so they can create the file in File Manager
            } else {
                header('Location: setup.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Could not connect to the database: ' . $e->getMessage();
        }
    }
    page('Connect your database', function () use ($f, $csrf, $error, $manual, $h) { ?>
        <p class="sub">Create a MySQL database and user in cPanel → <b>MySQL® Databases</b> (give the user <b>All privileges</b> on the database), then enter them here.</p>
        <?php if ($error): ?><div class="flash error"><?= $h($error) ?></div><?php endif ?>
        <?php if ($manual): ?>
         <div class="flash error">The connection works, but this folder isn't writable. In cPanel File Manager, create <b>config.php</b> next to setup.php with exactly this content, then reload this page:</div>
         <textarea class="inp code" rows="14" readonly><?= $h($manual) ?></textarea>
        <?php else: ?>
        <form method="post" class="mt16">
         <input type="hidden" name="_csrf" value="<?= $csrf ?>">
         <div class="f2">
          <div class="field"><label>Database host</label><input class="inp" name="host" value="<?= $h($f['host']) ?>" required></div>
          <div class="field"><label>Port</label><input class="inp" name="port" value="<?= $h($f['port']) ?>" required></div>
         </div>
         <div class="field"><label>Database name</label><input class="inp" name="name" value="<?= $h($f['name']) ?>" placeholder="cpaneluser_enro" required></div>
         <div class="field"><label>Database user</label><input class="inp" name="user" value="<?= $h($f['user']) ?>" placeholder="cpaneluser_enro" required></div>
         <div class="field"><label>Database password</label><input class="inp" type="password" name="pass" required></div>
         <div class="field"><label>Site URL</label><input class="inp" name="app_url" value="<?= $h($f['app_url']) ?>" required></div>
         <button class="btn full">Connect & continue</button>
        </form>
        <?php endif;
    });
}

/* ---------- Config exists ---------- */
require BASE . '/src/bootstrap.php';

try {
    $hasAdmins = (int)db()->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admins'")->fetchColumn()
        && (int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
} catch (Throwable $e) {
    page('Database problem', fn() => print('<div class="flash error">' . $h($e->getMessage()) . '</div><p class="sub">Check the database details in config.php.</p>'));
}

/* ---------- Step 2: tables + first admin ---------- */
if (!$hasAdmins) {
    $done = run_migrations(db());
    if ($posted) {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $pass = (string)($_POST['password'] ?? '');
        if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter your name and a valid email';
        elseif (strlen($pass) < 10) $error = 'Password must be at least 10 characters';
        else {
            q('INSERT INTO admins (name, email, password_hash) VALUES (?,?,?)', [$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)db()->lastInsertId();
            header('Location: admin/');
            exit;
        }
    }
    page('Create your admin account', function () use ($csrf, $error, $done, $h) { ?>
        <div class="flash ok">Database ready: <?= $h(implode(' · ', $done)) ?></div>
        <?php if ($error): ?><div class="flash error"><?= $h($error) ?></div><?php endif ?>
        <form method="post" class="mt16">
         <input type="hidden" name="_csrf" value="<?= $csrf ?>">
         <div class="field"><label>Your name</label><input class="inp" name="name" required></div>
         <div class="field"><label>Email</label><input class="inp" type="email" name="email" required></div>
         <div class="field"><label>Password (10+ characters)</label><input class="inp" type="password" name="password" minlength="10" required></div>
         <button class="btn full">Create admin & open dashboard</button>
        </form>
    <?php });
}

/* ---------- Step 3: upgrades (admins only) ---------- */
if (empty($_SESSION['admin_id'])) {
    page('Already installed', fn() => print('<p class="sub">Enro is installed. To apply an upgrade, <a href="admin/login.php" style="color:#5B3DF5;font-weight:700">log in to the admin</a> first, then come back to this page.</p>'));
}
if ($posted) $done = run_migrations(db());
page('Database upgrade', function () use ($csrf, $done, $h) { ?>
    <?php if ($done): ?><div class="flash ok">Up to date: <?= $h(implode(' · ', $done)) ?></div><?php endif ?>
    <p class="sub">Run this after uploading a new version of Enro. It only adds what's missing and never deletes data.</p>
    <form method="post" class="mt16"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn full">Run database upgrade</button></form>
    <p class="sm mt16"><a href="admin/" style="color:#5B3DF5;font-weight:700">← Back to admin</a></p>
<?php });

function page(string $title, callable $body): never
{ ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($title) ?> · Enro setup</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin/assets/admin.css?v=2">
</head><body><div class="login"><div class="card" style="width:100%;max-width:480px;padding:30px">
 <div class="row"><div class="logo" style="width:42px;height:42px;border-radius:12px;font-size:20px">E</div><div><b style="font-size:17px">Enro setup</b><div class="sm">Install & upgrade</div></div></div>
 <h2 class="mt24" style="font-size:20px;margin-bottom:10px"><?= htmlspecialchars($title) ?></h2>
 <?php $body() ?>
</div></div></body></html><?php
    exit;
}
