<?php
require __DIR__ . '/_boot.php';
require_admin();

$proofTypes = ['screenshot' => 'Screenshot', 'screenshot_text' => 'Screenshot + username/code', 'text' => 'Username/code only'];
$edit = isset($_GET['edit']) ? row('SELECT * FROM special_tasks WHERE id = ?', [(int)$_GET['edit']]) : null;
$new = isset($_GET['new']);

if (is_post()) {
    $action = post('action');
    if ($action === 'toggle') {
        q('UPDATE special_tasks SET active = 1 - active WHERE id = ?', [(int)post('id')]);
        redirect('special.php');
    }
    if ($action === 'save') {
        $data = [
            'title' => post('title'), 'description' => post('description'), 'steps' => str_replace("\r", '', post('steps')),
            'url' => post('url') ?: null, 'reward_cash' => (int)post('reward_cash'), 'proof_type' => post('proof_type'),
            'slots' => max(1, (int)post('slots')), 'eta' => post('eta') ?: null, 'expires_at' => post('expires_at') ?: null,
            'active' => post('active') ? 1 : 0,
        ];
        if ($data['title'] === '' || $data['reward_cash'] <= 0 || !isset($proofTypes[$data['proof_type']])) {
            flash('Title, proof type and a reward above ₦0 are required', 'error');
            redirect($_SERVER['REQUEST_URI']);
        }
        if ($id = (int)post('id')) {
            q('UPDATE special_tasks SET title=?, description=?, steps=?, url=?, reward_cash=?, proof_type=?, slots=?, eta=?, expires_at=?, active=? WHERE id=?', [...array_values($data), $id]);
            flash('Special task updated');
        } else {
            q('INSERT INTO special_tasks (title, description, steps, url, reward_cash, proof_type, slots, eta, expires_at, active) VALUES (?,?,?,?,?,?,?,?,?,?)', array_values($data));
            $newId = (int)db()->lastInsertId();
            if (post('notify') && $data['active']) {
                broadcast_create('New cash task: earn ' . naira($data['reward_cash']) . ' 💰', $data['title'] . ' — only ' . number_format($data['slots']) . ' slots.', 'special_task', $newId, 'all', true, (int)admin()['id']);
                broadcasts_process(15);
            }
            flash('Special task published' . (post('notify') ? ' and users notified' : ''));
        }
        redirect('special.php');
    }
}

layout_start('Special tasks', 'special');

if ($edit || $new):
    $t = $edit ?: ['id' => 0, 'title' => '', 'description' => '', 'steps' => '', 'url' => '', 'reward_cash' => 150, 'proof_type' => 'screenshot', 'slots' => 500, 'eta' => '5 min', 'expires_at' => date('Y-m-d', strtotime('+30 days')), 'active' => 1];
?>
<div class="atop"><h1><?= $edit ? 'Edit special task' : 'New special task' ?></h1><a class="btn ghost" href="special.php">Back</a></div>
<form method="post" class="card" style="max-width:680px"><?= csrf_field() ?>
 <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
 <p class="sub" style="margin-bottom:16px">Special tasks pay real cash. Users upload proof, and you approve it under Submissions before the cash is added.</p>
 <div class="field"><label>Title</label><input class="inp" name="title" value="<?= e($t['title']) ?>" required placeholder="e.g. Install & sign up on XYZ app"></div>
 <div class="field"><label>Short description</label><input class="inp" name="description" value="<?= e($t['description']) ?>"></div>
 <div class="field"><label>Steps <span class="sm">(one per line)</span></label><textarea class="inp" name="steps" rows="5"><?= e($t['steps']) ?></textarea></div>
 <div class="field"><label>Task link</label><input class="inp" name="url" value="<?= e($t['url']) ?>" placeholder="https://"></div>
 <div class="f2">
  <div class="field"><label>Reward (₦ cash)</label><input class="inp" type="number" min="1" name="reward_cash" value="<?= (int)$t['reward_cash'] ?>"></div>
  <div class="field"><label>Proof required</label><select class="inp" name="proof_type"><?php foreach ($proofTypes as $k => $l): ?><option value="<?= $k ?>" <?= $t['proof_type'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></div>
  <div class="field"><label>Total slots</label><input class="inp" type="number" min="1" name="slots" value="<?= (int)$t['slots'] ?>"></div>
  <div class="field"><label>Time to complete</label><input class="inp" name="eta" value="<?= e($t['eta']) ?>" placeholder="5 min"></div>
  <div class="field"><label>Expires</label><input class="inp" type="date" name="expires_at" value="<?= e($t['expires_at']) ?>"></div>
  <div class="field"><label>&nbsp;</label><label class="chk"><input type="checkbox" name="active" value="1" <?= $t['active'] ? 'checked' : '' ?>> Active (visible in app)</label></div>
 </div>
 <p class="sm" style="margin:-4px 0 16px">Budget at full slots: <b><?= naira((int)$t['reward_cash'] * (int)$t['slots']) ?></b></p>
 <?php if (!$edit): ?><label class="chk" style="margin-bottom:16px"><input type="checkbox" name="notify" value="1" <?= setting('push_new_tasks', '1') === '1' ? 'checked' : '' ?>> Notify users (push + in-app)</label><?php endif ?>
 <button class="btn"><?= $edit ? 'Save changes' : 'Publish special task' ?></button>
</form>
<?php else:
    $list = rows("SELECT t.*, (SELECT COUNT(*) FROM submissions s WHERE s.special_task_id = t.id AND s.status='pending') pend,
                  (SELECT COUNT(*) FROM submissions s WHERE s.special_task_id = t.id AND s.status='approved') ok FROM special_tasks t ORDER BY t.id DESC");
?>
<div class="atop"><h1>Special tasks <span class="sm" style="font-size:14px;font-weight:500">· pay real cash</span></h1><a class="btn" href="?new=1"><?= icon('plus', 16) ?> New special task</a></div>
<div class="tblw"><table class="tbl"><thead><tr><th>Task</th><th>Reward</th><th>Slots used</th><th>Approved</th><th>Pending</th><th>Expires</th><th>Active</th><th></th></tr></thead><tbody>
<?php if (!$list): ?><tr><td colspan="8" class="empty">No special tasks yet.</td></tr><?php endif ?>
<?php foreach ($list as $t): $pct = min(100, round($t['taken'] / max(1, $t['slots']) * 100)); ?>
 <tr><td><b style="font-weight:600"><?= e($t['title']) ?></b><div class="sm"><?= $proofTypes[$t['proof_type']] ?></div></td>
  <td><span class="pill cash"><?= naira($t['reward_cash']) ?></span></td>
  <td style="min-width:130px"><div class="sm"><?= number_format($t['taken']) ?> / <?= number_format($t['slots']) ?></div><div class="bar mt8" style="height:6px"><i style="width:<?= $pct ?>%;background:var(--cash)"></i></div></td>
  <td><?= (int)$t['ok'] ?></td>
  <td><?= $t['pend'] ? '<a href="submissions.php?task=' . $t['id'] . '" class="pill pend">' . (int)$t['pend'] . ' to review</a>' : '0' ?></td>
  <td class="sm"><?= $t['expires_at'] ? date('M j, Y', strtotime($t['expires_at'])) : '—' ?></td>
  <td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button class="ab <?= $t['active'] ? 'ok' : '' ?>"><?= $t['active'] ? 'On' : 'Off' ?></button></form></td>
  <td><a class="ab" href="?edit=<?= $t['id'] ?>"><?= icon('edit', 14) ?> Edit</a></td></tr>
<?php endforeach ?>
</tbody></table></div>
<?php endif;
layout_end();
