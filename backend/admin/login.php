<?php
require __DIR__ . '/_boot.php';

if (admin()) redirect('index.php');
$error = null;
if (is_post()) {
    try {
        rate_limit('admin-login:' . client_ip(), 8, 900);
        $a = row('SELECT * FROM admins WHERE email = ?', [strtolower(post('email'))]);
        if ($a && password_verify((string)post('password'), $a['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$a['id'];
            q('UPDATE admins SET last_login_at = ? WHERE id = ?', [now(), $a['id']]);
            redirect('index.php');
        }
        $error = 'Incorrect email or password';
    } catch (ApiError $e) {
        $error = $e->getMessage();
    }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign in · Enro Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/admin.css?v=1">
</head><body>
<div class="login"><form method="post" class="card" style="width:100%;max-width:400px;padding:30px">
 <?= csrf_field() ?>
 <div class="row"><div class="logo" style="width:42px;height:42px;border-radius:12px;font-size:20px">E</div><div><b style="font-size:17px">Enro Admin</b><div class="sm">Staff access only</div></div></div>
 <h2 class="mt24" style="font-size:20px">Sign in</h2>
 <?php if ($error): ?><div class="flash error mt12" style="margin-bottom:0"><?= e($error) ?></div><?php endif ?>
 <div class="mt16">
  <div class="field"><label>Email</label><input class="inp" type="email" name="email" value="<?= e(post('email')) ?>" required autofocus></div>
  <div class="field"><label>Password</label><input class="inp" type="password" name="password" required></div>
  <button class="btn full mt8">Sign in</button>
 </div>
</form></div>
</body></html>
