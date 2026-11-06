<?php
require __DIR__ . '/_boot.php';
$admin = require_admin();

if (is_post()) {
    $id = (int)post('id');
    if (post('action') === 'approve') { submission_approve($id, (int)$admin['id']); flash('Approved — cash credited to the user'); }
    if (post('action') === 'reject')  { submission_reject($id, (int)$admin['id'], post('reason')); flash('Rejected — user notified'); }
    redirect($_SERVER['REQUEST_URI']);
}

$status = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected'], true) ? $_GET['status'] : 'pending';
$taskId = (int)($_GET['task'] ?? 0);
$sql = 'SELECT s.*, u.name, u.email, u.id uid, t.title, t.reward_cash, t.proof_type,
        (SELECT COUNT(*) FROM submissions x WHERE x.user_id = s.user_id AND x.status = \'rejected\') rejects
        FROM submissions s JOIN users u ON u.id = s.user_id JOIN special_tasks t ON t.id = s.special_task_id WHERE s.status = ?';
$p = [$status];
if ($taskId) { $sql .= ' AND s.special_task_id = ?'; $p[] = $taskId; }
$sql .= $status === 'pending' ? ' ORDER BY s.created_at ASC LIMIT 100' : ' ORDER BY s.reviewed_at DESC LIMIT 100';
$list = rows($sql, $p);
$counts = [];
foreach (rows('SELECT status, COUNT(*) c FROM submissions GROUP BY status') as $r) $counts[$r['status']] = $r['c'];

layout_start('Submissions', 'subs');
?>
<div class="atop"><h1>Special task submissions</h1><span class="sm">Check each proof before approving — approval pays real cash.</span></div>
<div class="atabs">
 <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $k => $l): ?>
  <a href="?status=<?= $k ?>" class="<?= $status === $k ? 'on' : '' ?>"><?= $l ?> · <?= (int)($counts[$k] ?? 0) ?></a>
 <?php endforeach ?>
</div>
<div class="tblw"><table class="tbl"><thead><tr><th>User</th><th>Task</th><th>Proof</th><th>Reward</th><th>Submitted</th><th><?= $status === 'pending' ? '' : 'Status' ?></th></tr></thead><tbody>
<?php if (!$list): ?><tr><td colspan="6" class="empty">Nothing here.</td></tr><?php endif ?>
<?php foreach ($list as $s): ?>
 <tr>
  <td><a class="u" href="user.php?id=<?= $s['uid'] ?>"><div class="av"><?= e(initials($s['name'])) ?></div><div><b style="font-weight:600"><?= e($s['name']) ?></b><div class="sm"><?= e($s['email']) ?><?= $s['rejects'] ? ' · <span style="color:var(--red)">' . (int)$s['rejects'] . ' rejected before</span>' : '' ?></div></div></a></td>
  <td style="max-width:240px"><?= e($s['title']) ?></td>
  <td><div class="row">
   <?php if ($s['proof_path']): ?><a href="proof.php?id=<?= $s['id'] ?>" target="_blank" rel="noopener"><img class="thumb" src="proof.php?id=<?= $s['id'] ?>" alt="proof" loading="lazy"></a><?php endif ?>
   <span class="sm" style="max-width:180px;word-break:break-word"><?= e($s['note'] ?: '—') ?></span></div></td>
  <td><span class="pill cash"><?= naira($s['reward_cash']) ?></span></td>
  <td class="sm"><?= ago($s['created_at']) ?></td>
  <td>
  <?php if ($s['status'] === 'pending'): ?>
   <div class="acts">
    <form method="post" data-confirm="Approve and pay <?= naira($s['reward_cash']) ?> to <?= e($s['name']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="ab ok"><?= icon('check', 14) ?> Approve</button></form>
    <form method="post" class="row" style="gap:6px"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= $s['id'] ?>">
     <select name="reason" class="inp" style="height:32px;width:170px;font-size:12.5px;padding:0 8px">
      <option>Screenshot doesn't show the required page</option><option>Proof is unclear or cropped</option><option>Proof was reused or edited</option><option>Wrong account or details</option><option>Task not completed</option>
     </select><button class="ab no"><?= icon('x', 14) ?> Reject</button></form>
   </div>
  <?php else: ?><?= status_pill($s['status']) ?><?= $s['reject_reason'] ? '<div class="sm mt8">' . e($s['reject_reason']) . '</div>' : '' ?><?php endif ?>
  </td></tr>
<?php endforeach ?>
</tbody></table></div>
<?php layout_end();
