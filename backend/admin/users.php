<?php
require __DIR__ . '/_boot.php';
require_admin();

$search = trim($_GET['q'] ?? '');
$filter = $_GET['f'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$where = ['1=1'];
$p = [];
if ($search !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.referral_code = ?)';
    array_push($p, "%$search%", "%$search%", '%' . normalize_phone($search) . '%', strtoupper($search));
}
if ($filter === 'banned') $where[] = "u.status = 'banned'";
if ($filter === 'unverified') $where[] = 'u.email_verified_at IS NULL AND u.phone_verified_at IS NULL';
if ($filter === 'shared_device') $where[] = 'u.device_id IS NOT NULL AND (SELECT COUNT(*) FROM users d WHERE d.device_id = u.device_id) > 1';
$w = implode(' AND ', $where);
$total = (int)val("SELECT COUNT(*) FROM users u WHERE $w", $p);
$list = rows("SELECT u.*, (SELECT COUNT(*) FROM task_sessions s WHERE s.user_id = u.id AND s.claimed_at IS NOT NULL) tasks
              FROM users u WHERE $w ORDER BY u.id DESC LIMIT 50 OFFSET " . (($page - 1) * 50), $p);

layout_start('Users', 'users');
?>
<div class="atop"><h1>Users <span class="sm" style="font-size:14px;font-weight:500">· <?= number_format($total) ?></span></h1>
 <form class="search"><?= icon('search', 17) ?><input name="q" value="<?= e($search) ?>" placeholder="Name, email, phone or referral code"><input type="hidden" name="f" value="<?= e($filter) ?>"></form></div>
<div class="atabs">
 <?php foreach (['all' => 'All', 'unverified' => 'Unverified', 'shared_device' => 'Shared device', 'banned' => 'Banned'] as $k => $l): ?>
  <a href="?f=<?= $k ?>&q=<?= urlencode($search) ?>" class="<?= $filter === $k ? 'on' : '' ?>"><?= $l ?></a>
 <?php endforeach ?>
</div>
<div class="tblw"><table class="tbl"><thead><tr><th>User</th><th>Phone</th><th>Coins</th><th>Cash</th><th>Tasks</th><th>Joined</th><th>Last seen</th><th>Status</th></tr></thead><tbody>
<?php if (!$list): ?><tr><td colspan="8" class="empty">No users found.</td></tr><?php endif ?>
<?php foreach ($list as $u): ?>
 <tr style="cursor:pointer" onclick="location='user.php?id=<?= $u['id'] ?>'">
  <td><div class="u"><div class="av"><?= e(initials($u['name'])) ?></div><div><b style="font-weight:600"><?= e($u['name']) ?></b><div class="sm"><?= e($u['email']) ?></div></div></div></td>
  <td><?= e($u['phone']) ?><?= $u['email_verified_at'] || $u['phone_verified_at'] ? '' : ' <span class="pill grey">unverified</span>' ?></td>
  <td><?= number_format($u['coins']) ?></td><td><?= naira($u['cash']) ?></td><td><?= number_format($u['tasks']) ?></td>
  <td class="sm"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
  <td class="sm"><?= $u['last_seen_at'] ? ago($u['last_seen_at']) : '—' ?></td>
  <td><?= status_pill($u['status']) ?></td></tr>
<?php endforeach ?>
</tbody></table></div>
<div class="pager">
 <?php if ($page > 1): ?><a class="ab" href="?f=<?= e($filter) ?>&q=<?= urlencode($search) ?>&page=<?= $page - 1 ?>">← Previous</a><?php endif ?>
 <?php if ($page * 50 < $total): ?><a class="ab" href="?f=<?= e($filter) ?>&q=<?= urlencode($search) ?>&page=<?= $page + 1 ?>">Next →</a><?php endif ?>
</div>
<?php layout_end();
