<?php
require __DIR__ . '/_boot.php';
$admin = require_admin();

$audiences = ['all' => 'All users', 'active7' => 'Active in the last 7 days', 'active30' => 'Active in the last 30 days'];

if (is_post()) {
    switch (post('action')) {
        case 'send':
            $title = mb_substr(post('title'), 0, 150);
            $body = mb_substr(post('body'), 0, 500);
            if ($title === '' || $body === '') fail('Write a title and a message');
            $screen = array_key_exists(post('screen'), BROADCAST_SCREENS) ? post('screen') : 'notifications';
            $aud = array_key_exists(post('audience'), $audiences) ? post('audience') : 'all';
            broadcast_create($title, $body, $screen, null, $aud, true, (int)$admin['id']);
            $r = broadcasts_process(20);
            flash('Message saved and shown in the app. Push: ' . $r);
            break;
        case 'process':
            flash('Push: ' . broadcasts_process(25));
            break;
        case 'reminders':
            foreach (['push_morning_enabled', 'push_evening_enabled', 'push_new_tasks'] as $k) save_setting($k, post($k) ? '1' : '0');
            save_setting('push_morning_hour', (string)min(21, max(5, (int)post('push_morning_hour'))));
            save_setting('push_evening_hour', (string)min(22, max(12, (int)post('push_evening_hour'))));
            flash('Reminder settings saved');
            break;
    }
    redirect('notifications.php');
}

$s = settings();
$ready = Push::configured();
$devices = (int)val('SELECT COUNT(*) FROM push_tokens');
$usersWithPush = (int)val('SELECT COUNT(DISTINCT user_id) FROM push_tokens');
$history = rows('SELECT b.*, a.name admin_name FROM broadcasts b LEFT JOIN admins a ON a.id = b.created_by ORDER BY b.id DESC LIMIT 30');
$queued = (int)val("SELECT COUNT(*) FROM broadcasts WHERE status IN ('queued','sending') AND push = 1");
$cronLast = $s['cron_last_run'] ?? null;

layout_start('Notifications', 'push');
?>
<div class="atop"><h1>Push notifications</h1>
 <span class="sm"><?= number_format($usersWithPush) ?> users · <?= number_format($devices) ?> phones can receive pushes</span></div>

<?php if (!$ready): ?>
 <div class="note" style="margin-bottom:16px"><b>Firebase isn't connected yet, so phones won't get pushes.</b> Messages still appear in each user's in-app notification list.<br><br>
  To connect: Firebase console → your project → Project settings → <b>Service accounts</b> → <b>Generate new private key</b>.
  Upload the downloaded JSON to the server as <code>storage/firebase-service-account.json</code>.</div>
<?php endif ?>
<?php if ($ready && (!$cronLast || strtotime($cronLast) < time() - 1800)): ?>
 <div class="note" style="margin-bottom:16px"><b>The cron job isn't running<?= $cronLast ? ' (last run ' . e(ago($cronLast)) . ')' : '' ?>.</b> Daily reminders and large broadcasts need it.
  In cPanel → Cron Jobs, add every 5 minutes:<br><code>php <?= e(BASE_PATH) ?>/bin/cron.php</code></div>
<?php endif ?>

<div class="grid2" style="margin-top:0">
 <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="send">
  <div class="h3">Send a message</div>
  <p class="sm mt8">Goes to users' phones and their in-app notification list. Good for announcements, new tasks and promos.</p>
  <div class="field mt16"><label>Title</label><input class="inp" name="title" maxlength="150" required placeholder="e.g. Double coins this weekend 🎉"></div>
  <div class="field"><label>Message</label><textarea class="inp" name="body" maxlength="500" required placeholder="e.g. Every task pays 2× coins until Sunday night. Don't miss out!"></textarea></div>
  <div class="f2">
   <div class="field"><label>Tapping it opens</label><select class="inp" name="screen"><?php foreach (BROADCAST_SCREENS as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach ?></select></div>
   <div class="field"><label>Send to</label><select class="inp" name="audience"><?php foreach ($audiences as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach ?></select></div>
  </div>
  <button class="btn"><?= icon('send', 16) ?> Send</button>
 </form>

 <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="reminders">
  <div class="h3">Automatic reminders</div>
  <p class="sm mt8">Sent by the cron job, once a day per user, only to people who opened the app in the last 14 days.</p>
  <div class="set"><div class="t"><b style="font-weight:600">Morning: “Your daily tasks are ready”</b></div>
   <input class="inp" style="width:70px" type="number" name="push_morning_hour" min="5" max="21" value="<?= (int)($s['push_morning_hour'] ?? 9) ?>"><span class="sm">:00</span>
   <input type="checkbox" name="push_morning_enabled" value="1" <?= ($s['push_morning_enabled'] ?? '1') === '1' ? 'checked' : '' ?> style="width:20px;height:20px;accent-color:var(--cash)"></div>
  <div class="set"><div class="t"><b style="font-weight:600">Evening: “Check in / keep your streak”</b><span class="sm">Only to users who haven't checked in that day</span></div>
   <input class="inp" style="width:70px" type="number" name="push_evening_hour" min="12" max="22" value="<?= (int)($s['push_evening_hour'] ?? 19) ?>"><span class="sm">:00</span>
   <input type="checkbox" name="push_evening_enabled" value="1" <?= ($s['push_evening_enabled'] ?? '1') === '1' ? 'checked' : '' ?> style="width:20px;height:20px;accent-color:var(--cash)"></div>
  <div class="set"><div class="t"><b style="font-weight:600">Tick “Notify users” by default when publishing a task</b></div>
   <input type="checkbox" name="push_new_tasks" value="1" <?= ($s['push_new_tasks'] ?? '1') === '1' ? 'checked' : '' ?> style="width:20px;height:20px;accent-color:var(--cash)"></div>
  <p class="sm mt12">Approvals, payouts, rejections and referral bonuses are always pushed to the user right away.</p>
  <button class="btn mt12">Save reminders</button>
 </form>
</div>

<div class="between mt24" style="margin-bottom:12px"><div class="h3">Sent messages</div>
 <?php if ($queued && $ready): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="process"><button class="ab pri"><?= icon('send', 14) ?> Continue sending (<?= $queued ?> in progress)</button></form><?php endif ?></div>
<div class="tblw"><table class="tbl"><thead><tr><th>Message</th><th>Opens</th><th>Audience</th><th>Delivered</th><th>Status</th><th>When</th></tr></thead><tbody>
<?php if (!$history): ?><tr><td colspan="6" class="empty">No messages yet.</td></tr><?php endif ?>
<?php foreach ($history as $b): ?>
 <tr><td style="max-width:340px"><b style="font-weight:600"><?= e($b['title']) ?></b><div class="sm"><?= e($b['body']) ?></div></td>
  <td class="sm"><?= e(BROADCAST_SCREENS[$b['screen']] ?? ($b['screen'] === 'task' ? 'Task' : ($b['screen'] === 'special_task' ? 'Special task' : '—'))) ?></td>
  <td class="sm"><?= e($audiences[$b['audience']]) ?></td>
  <td><?= number_format($b['sent_count']) ?><?= $b['fail_count'] ? ' <span class="sm">(' . number_format($b['fail_count']) . ' failed)</span>' : '' ?></td>
  <td><?= $b['status'] === 'sent' ? status_pill('success') : ($ready ? status_pill('processing') : '<span class="pill grey">In-app only</span>') ?></td>
  <td class="sm"><?= ago($b['created_at']) ?><?= $b['admin_name'] ? '<br>by ' . e($b['admin_name']) : '' ?></td></tr>
<?php endforeach ?>
</tbody></table></div>
<?php layout_end();
