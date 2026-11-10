<?php
require __DIR__ . '/_boot.php';
require_admin();

// "Check with Paystack" for a payment stuck on pending (the cron job does this automatically every few minutes)
if (is_post() && post('action') === 'recheck') {
    $st = payment_settle(post('reference'));
    flash("Paystack says: $st" . ($st === 'paid' ? ' — user unlocked' : ''));
    redirect($_SERVER['REQUEST_URI']);
}

$status = in_array($_GET['status'] ?? '', ['paid', 'pending', 'failed', 'abandoned'], true) ? $_GET['status'] : 'paid';
$list = rows('SELECT p.*, u.name, u.email FROM payments p JOIN users u ON u.id = p.user_id WHERE p.status = ? ORDER BY p.id DESC LIMIT 200', [$status]);
$counts = [];
foreach (rows('SELECT status, COUNT(*) c, COALESCE(SUM(amount),0) s FROM payments GROUP BY status') as $r) $counts[$r['status']] = $r;
$today = (int)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'paid' AND paid_at >= ?", [today()]);

layout_start('Unlock fees', 'payments');
?>
<div class="atop"><h1>Unlock fees</h1>
 <span class="sm">Fee: <?= unlock_required() ? naira(unlock_fee()) . ' one-time' : 'switched off' ?> · Today: <?= naira($today) ?> · Total: <?= naira((int)($counts['paid']['s'] ?? 0)) ?></span></div>

<?php if (!Paystack::configured()): ?>
 <div class="note" style="margin-bottom:16px"><b>Paystack isn't connected.</b> Add your secret key to <code>config.php</code> (<code>'paystack' => ['secret_key' => 'sk_live_…']</code>),
  Until then users can't pay. You can unlock people manually from their user page.</div>
<?php endif ?>

<div class="atabs">
 <?php foreach (['paid' => 'Paid', 'pending' => 'Pending', 'abandoned' => 'Abandoned', 'failed' => 'Failed'] as $k => $l): ?>
  <a href="?status=<?= $k ?>" class="<?= $status === $k ? 'on' : '' ?>"><?= $l ?> · <?= (int)($counts[$k]['c'] ?? 0) ?></a>
 <?php endforeach ?>
</div>
<div class="tblw"><table class="tbl"><thead><tr><th>User</th><th>Amount</th><th>Reference</th><th>Channel</th><th>When</th><th></th></tr></thead><tbody>
<?php if (!$list): ?><tr><td colspan="6" class="empty">Nothing here.</td></tr><?php endif ?>
<?php foreach ($list as $p): ?>
 <tr><td><a class="u" href="user.php?id=<?= $p['user_id'] ?>"><div class="av"><?= e(initials($p['name'])) ?></div><div><b style="font-weight:600"><?= e($p['name']) ?></b><div class="sm"><?= e($p['email']) ?></div></div></a></td>
  <td><b><?= naira($p['amount']) ?></b></td>
  <td class="sm"><?= e($p['reference']) ?></td>
  <td class="sm"><?= e($p['channel'] ?: '—') ?></td>
  <td class="sm"><?= ago($p['paid_at'] ?: $p['created_at']) ?></td>
  <td><?php if ($p['status'] === 'pending' && Paystack::configured()): ?>
   <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="recheck"><input type="hidden" name="reference" value="<?= e($p['reference']) ?>"><button class="ab">Check with Paystack</button></form>
  <?php endif ?></td></tr>
<?php endforeach ?>
</tbody></table></div>
<?php layout_end();
