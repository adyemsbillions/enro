<?php
require __DIR__ . '/_boot.php';
$admin = require_admin();

if (is_post()) {
    $id = (int)post('id');
    switch (post('action')) {
        case 'approve': payment_approve($id, (int)$admin['id']); flash('Payment confirmed — user unlocked'); break;
        case 'reject':  payment_reject($id, (int)$admin['id'], post('reason')); flash('Receipt rejected — user asked to upload again'); break;
        case 'recheck': // older Paystack payments
            $st = payment_settle(post('reference'));
            flash("Paystack says: $st" . ($st === 'paid' ? ' — user unlocked' : ''));
            break;
    }
    redirect($_SERVER['REQUEST_URI']);
}

$tabs = ['pending' => 'To confirm', 'paid' => 'Paid', 'rejected' => 'Rejected', 'abandoned' => 'Abandoned', 'failed' => 'Failed'];
$status = isset($tabs[$_GET['status'] ?? '']) ? $_GET['status'] : 'pending';
$list = rows('SELECT p.*, u.name, u.email, a.name reviewer FROM payments p JOIN users u ON u.id = p.user_id
              LEFT JOIN admins a ON a.id = p.reviewed_by WHERE p.status = ?
              ORDER BY ' . ($status === 'pending' ? 'p.id ASC' : 'p.id DESC') . ' LIMIT 200', [$status]);
$counts = [];
foreach (rows('SELECT status, COUNT(*) c, COALESCE(SUM(amount),0) s FROM payments GROUP BY status') as $r) $counts[$r['status']] = $r;
$today = (int)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'paid' AND paid_at >= ?", [today()]);
$bank = unlock_bank();

layout_start('Unlock fees', 'payments');
?>
<div class="atop"><h1>Unlock fees</h1>
 <span class="sm">Fee: <?= unlock_required() ? naira(unlock_fee()) . ' one-time' : 'switched off' ?> · Today: <?= naira($today) ?> · Total: <?= naira((int)($counts['paid']['s'] ?? 0)) ?></span></div>

<div class="note" style="margin-bottom:16px">
 <?php if ($bank): ?>
  Users pay into <b><?= e($bank['bank']) ?> · <?= e($bank['number']) ?> · <?= e($bank['name']) ?></b> and upload the receipt.
  Check the money has arrived in that account, then press <b>Confirm</b>. <a href="settings.php">Change account</a>
 <?php else: ?>
  <b>No payment account yet.</b> Users can't pay until you add one in <a href="settings.php">Settings → Account for the unlock fee</a>.
 <?php endif ?>
</div>

<div class="atabs">
 <?php foreach ($tabs as $k => $l): ?>
  <a href="?status=<?= $k ?>" class="<?= $status === $k ? 'on' : '' ?>"><?= $l ?> · <?= (int)($counts[$k]['c'] ?? 0) ?></a>
 <?php endforeach ?>
</div>
<div class="tblw"><table class="tbl"><thead><tr><th>User</th><th>Receipt</th><th>Amount</th><th>Reference</th><th>When</th><th></th></tr></thead><tbody>
<?php if (!$list): ?><tr><td colspan="6" class="empty">Nothing here.</td></tr><?php endif ?>
<?php foreach ($list as $p): ?>
 <tr><td><a class="u" href="user.php?id=<?= $p['user_id'] ?>"><div class="av"><?= e(initials($p['name'])) ?></div><div><b style="font-weight:600"><?= e($p['name']) ?></b><div class="sm"><?= e($p['email']) ?></div></div></a></td>
  <td><?php if ($p['proof_path']): ?><a href="proof.php?payment=<?= $p['id'] ?>" target="_blank" rel="noopener"><img class="thumb" src="proof.php?payment=<?= $p['id'] ?>" alt="receipt" loading="lazy"></a>
      <?php else: ?><span class="sm"><?= $p['method'] === 'paystack' ? 'Paystack' : '—' ?></span><?php endif ?></td>
  <td><b><?= naira($p['amount']) ?></b></td>
  <td class="sm"><?= e($p['reference']) ?><?= $p['reject_reason'] ? '<div style="color:var(--red)">' . e($p['reject_reason']) . '</div>' : '' ?><?= $p['reviewer'] ? '<div>by ' . e($p['reviewer']) . '</div>' : '' ?></td>
  <td class="sm"><?= ago($p['paid_at'] ?: $p['created_at']) ?></td>
  <td><?php if ($p['status'] === 'pending' && $p['method'] === 'transfer'): ?><div class="acts">
    <form method="post" data-confirm="Confirm <?= naira($p['amount']) ?> from <?= e($p['name']) ?>? Only if the money is in the account."><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="ab ok"><?= icon('check', 14) ?> Confirm</button></form>
    <form method="post" class="row" style="gap:6px"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= $p['id'] ?>">
     <input name="reason" class="inp" style="height:32px;width:150px;font-size:12.5px;padding:0 8px" placeholder="Reason (optional)"><button class="ab no"><?= icon('x', 14) ?> Reject</button></form>
   </div>
  <?php elseif ($p['status'] === 'pending' && Paystack::configured()): ?>
   <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="recheck"><input type="hidden" name="reference" value="<?= e($p['reference']) ?>"><button class="ab">Check with Paystack</button></form>
  <?php endif ?></td></tr>
<?php endforeach ?>
</tbody></table></div>
<?php layout_end();
