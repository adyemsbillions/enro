<?php
require __DIR__ . '/_boot.php';
$admin = require_admin();

$id = (int)($_GET['id'] ?? 0);
$u = row('SELECT * FROM users WHERE id = ?', [$id]);
if (!$u) { flash('User not found', 'error'); redirect('users.php'); }

if (is_post()) {
    switch (post('action')) {
        case 'ban':
            q("UPDATE users SET status = 'banned', ban_reason = ? WHERE id = ?", [post('reason') ?: null, $id]);
            flash('User banned');
            break;
        case 'unban':
            q("UPDATE users SET status = 'active', ban_reason = NULL WHERE id = ?", [$id]);
            flash('User unbanned');
            break;
        case 'testpush':
            $n = (int)val('SELECT COUNT(*) FROM push_tokens WHERE user_id = ?', [$id]);
            if (!Push::configured()) fail('Firebase is not connected yet (Admin → Notifications)');
            if (!$n) fail('This user has no phone registered for notifications');
            [$ok] = Push::send(array_column(rows('SELECT token FROM push_tokens WHERE user_id = ?', [$id]), 'token'), 'Test notification', 'Notifications are working 🎉', ['screen' => 'notifications']);
            flash("Test sent to $ok of $n phone(s)");
            break;
        case 'adjust':
            $amount = (int)post('amount');
            $cur = post('currency') === 'cash' ? 'cash' : 'coin';
            $reason = post('reason');
            if ($amount === 0 || $reason === '') fail('Enter a non-zero amount and a reason');
            tx(fn() => ledger($id, $cur, $amount, 'adjust', 'Adjustment: ' . $reason . ' (by ' . $admin['name'] . ')'));
            notify($id, 'Balance updated', ($amount > 0 ? '+' : '') . ($cur === 'cash' ? naira($amount) : number_format($amount) . ' coins') . ": $reason", 'wallet', ['screen' => 'history']);
            flash('Balance adjusted');
            break;
    }
    redirect("user.php?id=$id");
}

$tx = rows('SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 30', [$id]);
$ref = $u['referred_by'] ? row('SELECT id, name FROM users WHERE id = ?', [$u['referred_by']]) : null;
$sameDevice = $u['device_id'] ? rows('SELECT id, name, status FROM users WHERE device_id = ? AND id <> ?', [$u['device_id'], $id]) : [];
$stats = [
    'Tasks done' => number_format((int)val('SELECT COUNT(*) FROM task_sessions WHERE user_id = ? AND claimed_at IS NOT NULL', [$id])),
    'Tasks today' => number_format((int)val('SELECT COUNT(*) FROM task_sessions WHERE user_id = ? AND claimed_at >= ?', [$id, today()])),
    'Special approved' => (int)val("SELECT COUNT(*) FROM submissions WHERE user_id = ? AND status='approved'", [$id]) . ' / ' . (int)val('SELECT COUNT(*) FROM submissions WHERE user_id = ?', [$id]),
    'Total paid out' => naira((int)val("SELECT COALESCE(SUM(amount),0) FROM payouts WHERE user_id = ? AND status = 'paid'", [$id])),
    'Referrals' => (int)val('SELECT COUNT(*) FROM users WHERE referred_by = ?', [$id]),
];

layout_start($u['name'], 'users');
?>
<div class="atop"><h1><?= e($u['name']) ?> <?= status_pill($u['status']) ?></h1><a class="btn ghost" href="users.php">Back</a></div>
<div class="grid2">
 <div>
  <div class="card">
   <div class="kpis">
    <div class="kpi"><small>Coins</small><b><?= number_format($u['coins']) ?></b></div>
    <div class="kpi"><small>Cash</small><b><?= naira($u['cash']) ?></b></div>
    <?php foreach ($stats as $l => $v): ?><div class="kpi"><small><?= $l ?></small><b style="font-size:19px"><?= $v ?></b></div><?php endforeach ?>
   </div>
  </div>
  <div class="tblw mt16"><table class="tbl" style="min-width:560px"><thead><tr><th>Activity</th><th>Amount</th><th>Status</th><th>When</th></tr></thead><tbody>
  <?php if (!$tx): ?><tr><td colspan="4" class="empty">No activity yet.</td></tr><?php endif ?>
  <?php foreach ($tx as $t): ?>
   <tr><td><?= e($t['description']) ?></td>
    <td><b style="color:<?= $t['amount'] < 0 ? 'var(--ink)' : ($t['currency'] === 'coin' ? 'var(--amber)' : 'var(--cash)') ?>"><?= $t['amount'] > 0 ? '+' : '−' ?><?= $t['currency'] === 'coin' ? number_format(abs($t['amount'])) . ' coins' : naira(abs($t['amount'])) ?></b></td>
    <td><?= status_pill($t['status']) ?></td><td class="sm"><?= date('M j, g:ia', strtotime($t['created_at'])) ?></td></tr>
  <?php endforeach ?>
  </tbody></table></div>
 </div>
 <div>
  <div class="card">
   <div class="h3">Details</div>
   <?php foreach ([
       'Email' => e($u['email']), 'Phone' => e($u['phone']) . ($u['phone_verified_at'] ? ' ✓' : ' (unverified)'),
       'Referral code' => e($u['referral_code']), 'Referred by' => $ref ? '<a style="color:var(--p)" href="user.php?id=' . $ref['id'] . '">' . e($ref['name']) . '</a>' : '—',
       'Joined' => date('M j, Y', strtotime($u['created_at'])), 'Last seen' => $u['last_seen_at'] ? ago($u['last_seen_at']) : '—',
   ] as $l => $v): ?><div class="set"><div class="t sm"><?= $l ?></div><div><?= $v ?></div></div><?php endforeach ?>
   <?php if ($sameDevice): ?>
    <div class="flash error mt12" style="margin-bottom:0">Same device as: <?= implode(', ', array_map(fn($d) => '<a href="user.php?id=' . $d['id'] . '"><u>' . e($d['name']) . '</u></a>', $sameDevice)) ?></div>
   <?php endif ?>
   <?php if ($u['ban_reason']): ?><p class="sm mt12">Ban reason: <?= e($u['ban_reason']) ?></p><?php endif ?>
  </div>
  <form method="post" class="card mt16"><?= csrf_field() ?>
   <div class="between"><div><div class="h3">Push notifications</div><div class="sm mt8"><?= (int)val('SELECT COUNT(*) FROM push_tokens WHERE user_id = ?', [$id]) ?> phone(s) registered</div></div>
   <button class="ab" name="action" value="testpush"><?= icon('send', 14) ?> Send test</button></div>
  </form>
  <form method="post" class="card mt16"><?= csrf_field() ?>
   <div class="h3">Adjust balance</div><p class="sm mt8">Use a negative number to remove. The user is notified.</p>
   <div class="f2 mt12"><select class="inp" name="currency"><option value="coin">Coins</option><option value="cash">Cash (₦)</option></select><input class="inp" type="number" name="amount" placeholder="e.g. 500 or -500" required></div>
   <input class="inp mt12" name="reason" placeholder="Reason (shown to user)" required>
   <button class="btn mt12" name="action" value="adjust">Apply</button>
  </form>
  <form method="post" class="card mt16" data-confirm="<?= $u['status'] === 'banned' ? 'Unban this user?' : 'Ban this user? They will be logged out of the app.' ?>"><?= csrf_field() ?>
   <?php if ($u['status'] === 'banned'): ?>
    <button class="btn ghost" name="action" value="unban">Unban user</button>
   <?php else: ?>
    <div class="h3">Ban user</div><input class="inp mt12" name="reason" placeholder="Reason (shown to user)">
    <button class="btn mt12" style="background:var(--red)" name="action" value="ban"><?= icon('ban', 16) ?> Ban user</button>
   <?php endif ?>
  </form>
 </div>
</div>
<?php layout_end();
