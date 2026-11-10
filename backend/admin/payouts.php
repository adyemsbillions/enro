<?php
require __DIR__ . '/_boot.php';
$admin = require_admin();
$aid = (int)$admin['id'];

if (is_post()) {
    $id = (int)post('id');
    switch (post('action')) {
        case 'vtu':    flash('VTpass: ' . payout_deliver_vtu($id, $aid)); break;
        case 'paystack': flash(payout_transfer_cash($id, $aid)); break;
        case 'pscheck': flash('Paystack says: ' . payout_check_transfer($id)); break;
        case 'paid':   payout_mark_paid($id, $aid, post('ref') ?: null); flash('Marked as paid — user notified'); break;
        case 'reject': payout_refund($id, $aid, 'rejected', post('reason')); flash('Rejected — balance refunded'); break;
    }
    redirect($_SERVER['REQUEST_URI']);
}

$tab = ($_GET['tab'] ?? 'topup') === 'cash' ? 'cash' : 'topup';
$state = in_array($_GET['state'] ?? '', ['open', 'done'], true) ? $_GET['state'] : 'open';
$kindSql = $tab === 'cash' ? "p.kind = 'cash'" : "p.kind IN ('airtime','data')";
$stateSql = $state === 'open' ? "p.status IN ('pending','processing')" : "p.status IN ('paid','failed','rejected')";
$list = rows("SELECT p.*, u.name, u.email, u.created_at joined,
              (SELECT COUNT(*) FROM payouts x WHERE x.user_id = p.user_id AND x.status = 'paid') paid_before
              FROM payouts p JOIN users u ON u.id = p.user_id WHERE $kindSql AND $stateSql
              ORDER BY " . ($state === 'open' ? 'p.created_at ASC' : 'p.processed_at DESC') . ' LIMIT 200');
$openTop = (int)val("SELECT COUNT(*) FROM payouts WHERE kind <> 'cash' AND status IN ('pending','processing')");
$openCash = (int)val("SELECT COUNT(*) FROM payouts WHERE kind = 'cash' AND status IN ('pending','processing')");
$vtuReady = Vtpass::configured();
$psReady = Paystack::configured();

layout_start('Payouts', 'payouts');
?>
<div class="atop"><h1>Payouts</h1>
 <div class="row"><a class="ab <?= $state === 'open' ? 'pri' : '' ?>" href="?tab=<?= $tab ?>&state=open">Open</a><a class="ab <?= $state === 'done' ? 'pri' : '' ?>" href="?tab=<?= $tab ?>&state=done">History</a></div></div>
<div class="atabs">
 <a href="?tab=topup&state=<?= $state ?>" class="<?= $tab === 'topup' ? 'on' : '' ?>">Airtime & data (coins) · <?= $openTop ?></a>
 <a href="?tab=cash&state=<?= $state ?>" class="<?= $tab === 'cash' ? 'on' : '' ?>">Cash withdrawals · <?= $openCash ?></a>
</div>

<?php if ($tab === 'topup'): ?>
 <div class="note" style="margin-bottom:14px"><?= $vtuReady
   ? 'VTpass is connected. “Send” delivers instantly.' . (setting('vtu_auto') === '1' ? ' Auto-send is ON, so only failed requests stay here.' : ' Turn on auto-send in Settings to skip this queue.')
   : 'VTpass keys are not set in config.php. Top up the user from your own VTU platform, then click “Mark sent”.' ?></div>
<?php else: ?>
 <div class="note" style="margin-bottom:14px"><?= $psReady
   ? 'Paystack is connected. “Pay via Paystack” starts the transfer; it is marked paid automatically within a few minutes, once Paystack confirms it (or press “Check status”).'
   : 'Paystack key is not set. Transfer from your bank app, then click “Mark paid” and paste the reference.' ?>
   Check new accounts and first withdrawals carefully.</div>
<?php endif ?>

<div class="tblw"><table class="tbl"><thead><tr><th>User</th><th><?= $tab === 'cash' ? 'Bank / account' : 'Network / phone' ?></th><th>Amount</th><th><?= $tab === 'cash' ? 'Fee' : 'Coins' ?></th><th>Requested</th><th>Status</th><th></th></tr></thead><tbody>
<?php if (!$list): ?><tr><td colspan="7" class="empty">Nothing here.</td></tr><?php endif ?>
<?php foreach ($list as $p): $open = in_array($p['status'], ['pending', 'processing'], true); ?>
 <tr>
  <td><a class="u" href="user.php?id=<?= $p['user_id'] ?>"><div class="av"><?= e(initials($p['name'])) ?></div><div><b style="font-weight:600"><?= e($p['name']) ?></b>
   <div class="sm"><?= $p['paid_before'] ? (int)$p['paid_before'] . ' paid before' : '<span style="color:var(--amber);font-weight:700">First payout</span>' ?> · joined <?= date('M j', strtotime($p['joined'])) ?></div></div></a></td>
  <td><?php if ($p['kind'] === 'cash'): ?><b style="font-weight:600"><?= e($p['account_name']) ?></b><div class="sm"><?= e($p['bank_name']) ?> · <?= e($p['account_number']) ?></div>
      <?php else: ?><b style="font-weight:600"><?= strtoupper(e($p['network'])) ?></b> <?= $p['kind'] === 'data' ? '<span class="pill pur">Data</span>' : '<span class="pill coin">Airtime</span>' ?><div class="sm"><?= e($p['phone']) ?></div><?php endif ?></td>
  <td><b><?= $p['kind'] === 'data' ? e($p['plan_name']) : naira($p['amount']) ?></b></td>
  <td class="sm"><?= $p['kind'] === 'cash' ? naira($p['fee']) : number_format($p['coins_cost']) ?></td>
  <td class="sm"><?= ago($p['created_at']) ?></td>
  <td><?= status_pill($p['status']) ?><?= $p['admin_note'] ? '<div class="sm mt8" style="max-width:180px">' . e($p['admin_note']) . '</div>' : '' ?><?= $p['provider_ref'] ? '<div class="sm">Ref: ' . e($p['provider_ref']) . '</div>' : '' ?></td>
  <td><?php if ($open): ?><div class="acts">
   <?php if ($p['status'] === 'pending' && $p['kind'] !== 'cash' && $vtuReady): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="vtu"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="ab pri"><?= icon('send', 14) ?> Send</button></form>
   <?php endif ?>
   <?php if ($p['status'] === 'pending' && $p['kind'] === 'cash' && $psReady): ?>
    <form method="post" data-confirm="Send <?= naira($p['amount']) ?> to <?= e($p['account_name']) ?> via Paystack?"><?= csrf_field() ?><input type="hidden" name="action" value="paystack"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="ab pri"><?= icon('send', 14) ?> Pay via Paystack</button></form>
   <?php endif ?>
   <?php if ($p['status'] === 'processing' && $p['kind'] === 'cash' && $psReady && $p['provider_ref']): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="pscheck"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="ab">Check status</button></form>
   <?php endif ?>
   <form method="post" class="row" style="gap:6px" data-confirm="Mark this as <?= $p['kind'] === 'cash' ? 'paid' : 'sent' ?>? Only do this after the user has actually received it."><?= csrf_field() ?><input type="hidden" name="action" value="paid"><input type="hidden" name="id" value="<?= $p['id'] ?>">
    <input name="ref" class="inp" style="height:32px;width:110px;font-size:12.5px;padding:0 8px" placeholder="Reference"><button class="ab ok"><?= icon('check', 14) ?> Mark <?= $p['kind'] === 'cash' ? 'paid' : 'sent' ?></button></form>
   <form method="post" class="row" style="gap:6px" data-confirm="Reject and refund this request?"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= $p['id'] ?>">
    <input name="reason" class="inp" style="height:32px;width:120px;font-size:12.5px;padding:0 8px" placeholder="Reason"><button class="ab no"><?= icon('x', 14) ?> Reject</button></form>
  </div><?php endif ?></td></tr>
<?php endforeach ?>
</tbody></table></div>
<?php layout_end();
