<?php
require __DIR__ . '/_boot.php';
require_admin();

$types = ['watch' => 'Watch ad', 'visit' => 'Visit link', 'social' => 'Social', 'daily' => 'Daily check-in'];
$edit = isset($_GET['edit']) ? row('SELECT * FROM tasks WHERE id = ?', [(int)$_GET['edit']]) : null;
$new = isset($_GET['new']);

if (is_post()) {
    $action = post('action');
    if ($action === 'toggle') {
        q('UPDATE tasks SET active = 1 - active WHERE id = ?', [(int)post('id')]);
        redirect('tasks.php');
    }
    if ($action === 'save') {
        $data = [
            'title' => post('title'), 'description' => post('description'), 'type' => post('type'),
            'url' => post('url') ?: null, 'reward_coins' => (int)post('reward_coins'), 'duration_sec' => (int)post('duration_sec'),
            'daily_cap' => max(1, (int)post('daily_cap')), 'sort' => (int)post('sort'), 'active' => post('active') ? 1 : 0,
        ];
        if ($data['title'] === '' || $data['reward_coins'] <= 0 || !isset($types[$data['type']])) {
            flash('Title, type and a reward above 0 are required', 'error');
            redirect($_SERVER['REQUEST_URI']);
        }
        if ($id = (int)post('id')) {
            q('UPDATE tasks SET title=?, description=?, type=?, url=?, reward_coins=?, duration_sec=?, daily_cap=?, sort=?, active=? WHERE id=?', [...array_values($data), $id]);
            flash('Task updated');
        } else {
            q('INSERT INTO tasks (title, description, type, url, reward_coins, duration_sec, daily_cap, sort, active) VALUES (?,?,?,?,?,?,?,?,?)', array_values($data));
            $newId = (int)db()->lastInsertId();
            if (post('notify') && $data['active']) {
                broadcast_create('New task: +' . $data['reward_coins'] . ' coins 🎯', $data['title'], 'task', $newId, 'all', true, (int)admin()['id']);
                broadcasts_process(15);
            }
            flash('Task published' . (post('notify') ? ' and users notified' : ''));
        }
        redirect('tasks.php');
    }
}

layout_start('Tasks', 'tasks');

if ($edit || $new):
    $t = $edit ?: ['id' => 0, 'title' => '', 'description' => '', 'type' => 'watch', 'url' => '', 'reward_coins' => 20, 'duration_sec' => 30, 'daily_cap' => 1, 'sort' => 0, 'active' => 1];
?>
<div class="atop"><h1><?= $edit ? 'Edit task' : 'New task' ?></h1><a class="btn ghost" href="tasks.php">Back</a></div>
<form method="post" class="card" style="max-width:640px"><?= csrf_field() ?>
 <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
 <p class="sub" style="margin-bottom:16px">Coin tasks are checked by a timer, then users can swap their coins for airtime or data.</p>
 <div class="field"><label>Title</label><input class="inp" name="title" value="<?= e($t['title']) ?>" required placeholder="e.g. Watch a 30-second video ad"></div>
 <div class="field"><label>Instructions</label><textarea class="inp" name="description" placeholder="Shown to the user on the task screen"><?= e($t['description']) ?></textarea></div>
 <div class="f2">
  <div class="field"><label>Type</label><select class="inp" name="type"><?php foreach ($types as $k => $l): ?><option value="<?= $k ?>" <?= $t['type'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></div>
  <div class="field"><label>Reward (coins)</label><input class="inp" type="number" min="1" name="reward_coins" value="<?= (int)$t['reward_coins'] ?>"></div>
  <div class="field"><label>Timer (seconds)</label><input class="inp" type="number" min="1" name="duration_sec" value="<?= (int)$t['duration_sec'] ?>"></div>
  <div class="field"><label>Times per user per day</label><input class="inp" type="number" min="1" name="daily_cap" value="<?= (int)$t['daily_cap'] ?>"></div>
 </div>
 <div class="field"><label>Link <span class="sm">(leave empty on “Watch ad” to use your Adsterra Smartlink)</span></label><input class="inp" name="url" value="<?= e($t['url']) ?>" placeholder="https://"></div>
 <div class="f2">
  <div class="field"><label>Sort order</label><input class="inp" type="number" name="sort" value="<?= (int)$t['sort'] ?>"></div>
  <div class="field"><label>&nbsp;</label><label class="chk"><input type="checkbox" name="active" value="1" <?= $t['active'] ? 'checked' : '' ?>> Active (visible in app)</label></div>
 </div>
 <?php if (!$edit): ?><label class="chk" style="margin-bottom:16px"><input type="checkbox" name="notify" value="1" <?= setting('push_new_tasks', '1') === '1' ? 'checked' : '' ?>> Notify users (push + in-app)</label><?php endif ?>
 <button class="btn"><?= $edit ? 'Save changes' : 'Publish task' ?></button>
</form>
<?php else:
    $list = rows("SELECT t.*, (SELECT COUNT(*) FROM task_sessions s WHERE s.task_id = t.id AND s.claimed_at IS NOT NULL) done,
                  (SELECT COUNT(*) FROM task_sessions s WHERE s.task_id = t.id AND s.claimed_at >= ?) today FROM tasks t ORDER BY t.sort, t.id", [today()]);
?>
<div class="atop"><h1>Tasks <span class="sm" style="font-size:14px;font-weight:500">· pay coins → airtime & data</span></h1><a class="btn" href="?new=1"><?= icon('plus', 16) ?> New task</a></div>
<div class="tblw"><table class="tbl"><thead><tr><th>Task</th><th>Type</th><th>Reward</th><th>Timer</th><th>Per day</th><th>Done today / total</th><th>Active</th><th></th></tr></thead><tbody>
<?php if (!$list): ?><tr><td colspan="8" class="empty">No tasks yet.</td></tr><?php endif ?>
<?php foreach ($list as $t): ?>
 <tr><td><b style="font-weight:600"><?= e($t['title']) ?></b><?= $t['url'] ? '<div class="sm">' . e(mb_strimwidth($t['url'], 0, 50, '…')) . '</div>' : '' ?></td>
  <td><span class="pill pur"><?= $types[$t['type']] ?></span></td>
  <td><span class="pill coin"><?= (int)$t['reward_coins'] ?> coins</span></td>
  <td><?= (int)$t['duration_sec'] ?>s</td><td><?= (int)$t['daily_cap'] ?>×</td>
  <td><?= number_format($t['today']) ?> / <?= number_format($t['done']) ?></td>
  <td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button class="ab <?= $t['active'] ? 'ok' : '' ?>"><?= $t['active'] ? 'On' : 'Off' ?></button></form></td>
  <td><a class="ab" href="?edit=<?= $t['id'] ?>"><?= icon('edit', 14) ?> Edit</a></td></tr>
<?php endforeach ?>
</tbody></table></div>
<?php endif;
layout_end();
