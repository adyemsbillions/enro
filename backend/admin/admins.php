<?php
require __DIR__ . '/_boot.php';
$me = require_admin();

// Admin logins work on this web panel and in the app (Login → "Admin login").
if (is_post()) {
    if (post('action') === 'add') {
        $name = trim((string)post('name'));
        $email = strtolower(trim((string)post('email')));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Enter a name and a valid email');
        if (val('SELECT 1 FROM admins WHERE email = ?', [$email])) fail('That email is already an admin');
        // readable but strong: 4 groups of 4 (no 0/O/1/l mix-ups)
        $abc = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $parts = [];
        for ($i = 0; $i < 4; $i++) { $g = ''; for ($j = 0; $j < 4; $j++) $g .= $abc[random_int(0, strlen($abc) - 1)]; $parts[] = $g; }
        $password = implode('-', $parts);
        q('INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?)', [$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $_SESSION['new_admin'] = ['email' => $email, 'password' => $password];
        redirect('admins.php');
    }
    if (post('action') === 'delete') {
        $id = (int)post('id');
        if ($id === (int)$me['id']) fail("You can't remove yourself");
        q('DELETE FROM admin_push_tokens WHERE admin_id = ?', [$id]);
        q('DELETE FROM admins WHERE id = ?', [$id]);
        flash('Admin removed');
        redirect('admins.php');
    }
}

$created = $_SESSION['new_admin'] ?? null; // shown once
unset($_SESSION['new_admin']);
$list = rows('SELECT a.*, (SELECT COUNT(*) FROM admin_push_tokens t WHERE t.admin_id = a.id) phones FROM admins a ORDER BY a.id');

layout_start('Admins', 'admins');
?>
<div class="atop"><h1>Admins</h1><span class="sm">Everyone here can log in to this panel and to the admin screens in the app.</span></div>

<?php if ($created): ?>
 <div class="card" style="margin-bottom:16px;border-color:var(--cash)">
  <div class="h3">New admin login</div>
  <div class="set"><div class="t"><b style="font-weight:600">Email</b></div><code><?= e($created['email']) ?></code></div>
  <div class="set"><div class="t"><b style="font-weight:600">Password</b></div><code style="font-size:16px"><?= e($created['password']) ?></code></div>
  <p class="sm mt12">Copy it now — it won't be shown again. In the app: Login screen → <b>Admin login</b>. They can change it in Settings → Change password.</p>
 </div>
<?php endif ?>

<div class="grid2" style="margin-top:0">
 <div class="tblw"><table class="tbl"><thead><tr><th>Admin</th><th>Last login</th><th>App alerts</th><th></th></tr></thead><tbody>
 <?php foreach ($list as $a): ?>
  <tr><td><b style="font-weight:600"><?= e($a['name']) ?></b><div class="sm"><?= e($a['email']) ?></div></td>
   <td class="sm"><?= $a['last_login_at'] ? ago($a['last_login_at']) : 'never' ?></td>
   <td class="sm"><?= $a['phones'] ? (int)$a['phones'] . ' phone' . ($a['phones'] > 1 ? 's' : '') : '—' ?></td>
   <td><?php if ((int)$a['id'] !== (int)$me['id']): ?>
    <form method="post" data-confirm="Remove <?= e($a['name']) ?> as admin?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="ab no">Remove</button></form>
   <?php else: ?><span class="sm">You</span><?php endif ?></td></tr>
 <?php endforeach ?>
 </tbody></table></div>
 <form method="post" class="card" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="add">
  <div class="h3">Add an admin</div>
  <p class="sm" style="margin:-4px 0 12px">A strong password is generated for them.</p>
  <div class="field"><label>Name</label><input class="inp" name="name" required></div>
  <div class="field"><label>Email</label><input class="inp" type="email" name="email" required></div>
  <button class="btn">Create login</button>
 </form>
</div>
<?php layout_end();
