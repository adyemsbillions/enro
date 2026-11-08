<?php
declare(strict_types=1);

/*
 * Broadcasts (admin → many users) and daily reminders.
 * Both are sent in batches by bin/cron.php (set it up as a cPanel cron job every 5 minutes).
 * Admin → Notifications also has a "Send now" button that runs the same code.
 */

const BROADCAST_SCREENS = [
    'home' => 'Home', 'tasks' => 'Tasks', 'special' => 'Special tasks', 'wallet' => 'Wallet',
    'referral' => 'Refer & earn', 'notifications' => 'Notifications',
];

function audience_sql(string $audience): string
{
    return match ($audience) {
        'active7' => ' AND u.last_seen_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
        'active30' => ' AND u.last_seen_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
        default => '',
    };
}

function broadcast_create(string $title, string $body, ?string $screen, ?int $refId, string $audience, bool $push, ?int $adminId): int
{
    // without Firebase it's in-app only — don't leave it queued to fire later as a stale push
    $push = $push && Push::configured();
    q('INSERT INTO broadcasts (title, body, screen, ref_id, audience, push, status, created_by) VALUES (?,?,?,?,?,?,?,?)',
      [$title, $body, $screen, $refId, $audience, $push ? 1 : 0, $push ? 'queued' : 'sent', $adminId]);
    return (int)db()->lastInsertId();
}

/** Users who can see a broadcast in their in-app list (joined before it and inside its audience). */
function broadcasts_for_user(array $u, int $limit = 30): array
{
    $rows = rows('SELECT id, title, body, screen, ref_id, audience, created_at FROM broadcasts
                  WHERE created_at >= ? ORDER BY id DESC LIMIT ' . (int)$limit, [$u['created_at']]);
    return $rows; // audience filtering is about who gets the push; everyone may read the message in-app
}

/** Send queued broadcasts until the time budget runs out. Returns a short report. */
function broadcasts_process(int $budgetSec = 20): string
{
    if (!Push::configured()) return 'Firebase not configured — broadcasts saved in-app only';
    $deadline = microtime(true) + $budgetSec;
    $report = [];
    while (microtime(true) < $deadline) {
        $b = row("SELECT * FROM broadcasts WHERE status IN ('queued','sending') AND push = 1 ORDER BY id LIMIT 1");
        if (!$b) break;
        q("UPDATE broadcasts SET status = 'sending' WHERE id = ?", [$b['id']]);
        $batch = rows("SELECT u.id, t.token FROM users u JOIN push_tokens t ON t.user_id = u.id
                       WHERE u.status = 'active' AND u.id > ?" . audience_sql($b['audience']) . '
                       ORDER BY u.id LIMIT 400', [$b['last_user_id']]);
        if (!$batch) {
            q("UPDATE broadcasts SET status = 'sent', finished_at = ? WHERE id = ?", [now(), $b['id']]);
            $report[] = "#{$b['id']} finished";
            continue;
        }
        // finish a user's tokens in one batch: drop the last user if the LIMIT may have cut them off
        $lastId = (int)end($batch)['id'];
        if (count($batch) === 400) {
            $trimmed = array_filter($batch, fn($r) => (int)$r['id'] !== $lastId);
            if ($trimmed) { $batch = $trimmed; $lastId = (int)end($batch)['id']; }
        }
        $data = ['screen' => $b['screen'] ?: 'notifications'] + ($b['ref_id'] ? ['id' => (int)$b['ref_id']] : []);
        [$ok, $bad] = Push::send(array_column($batch, 'token'), $b['title'], $b['body'], $data);
        q('UPDATE broadcasts SET last_user_id = ?, sent_count = sent_count + ?, fail_count = fail_count + ? WHERE id = ?', [$lastId, $ok, $bad, $b['id']]);
        $report[] = "#{$b['id']} +$ok sent";
    }
    return $report ? implode(', ', $report) : 'Nothing queued';
}

/**
 * Morning: "new tasks are ready". Evening: "check in / keep your streak" for users who haven't checked in today.
 * Only users seen in the last 14 days, at most once per day each.
 */
function reminders_run(int $budgetSec = 20): string
{
    if (!Push::configured()) return 'Firebase not configured';
    $hour = (int)date('G');
    $deadline = microtime(true) + $budgetSec;
    $done = [];

    $kinds = [];
    $mh = setting_int('push_morning_hour', 9);
    $eh = setting_int('push_evening_hour', 19);
    if (setting('push_morning_enabled') === '1' && $hour >= $mh && $hour < $mh + 3) $kinds[] = 'morning';
    if (setting('push_evening_enabled') === '1' && $hour >= $eh && $hour < $eh + 3) $kinds[] = 'evening';

    $daily = (int)val("SELECT id FROM tasks WHERE type = 'daily' AND active = 1 ORDER BY id LIMIT 1");
    foreach ($kinds as $kind) {
        $col = $kind === 'morning' ? 'last_morning_push' : 'last_evening_push';
        $count = 0;
        while (microtime(true) < $deadline) {
            $extra = '';
            $p = [today(), date('Y-m-d H:i:s', strtotime('-14 days'))];
            if ($kind === 'evening' && $daily) {
                $extra = ' AND NOT EXISTS (SELECT 1 FROM task_sessions s WHERE s.user_id = u.id AND s.task_id = ? AND s.claimed_at >= ?)';
                array_push($p, $daily, today());
            }
            $users = rows("SELECT DISTINCT u.id FROM users u JOIN push_tokens t ON t.user_id = u.id
                           WHERE u.status = 'active' AND (u.$col IS NULL OR u.$col < ?) AND u.last_seen_at >= ?$extra
                           ORDER BY u.id LIMIT 100", $p);
            if (!$users) break;
            $ids = array_map('intval', array_column($users, 'id'));
            q("UPDATE users SET $col = ? WHERE id IN (" . implode(',', $ids) . ')', [today()]);

            if ($kind === 'morning') {
                $tokens = array_column(rows('SELECT token FROM push_tokens WHERE user_id IN (' . implode(',', $ids) . ')'), 'token');
                Push::send($tokens, 'Your daily tasks are ready 🎯', 'New coin tasks just unlocked. Earn coins for airtime & data today.', ['screen' => 'tasks']);
            } else {
                // personalise by streak
                foreach ($ids as $id) {
                    $streak = checkin_streak($id)['streak'];
                    $tokens = array_column(rows('SELECT token FROM push_tokens WHERE user_id = ?', [$id]), 'token');
                    $title = $streak >= 2 ? "Don't lose your $streak-day streak 🔥" : 'You haven’t checked in today';
                    $body = $streak >= 2 ? 'Check in now to keep it going and earn your streak bonus.' : 'Tap to check in and collect free coins.';
                    $data = $daily ? ['screen' => 'task', 'id' => $daily] : ['screen' => 'tasks'];
                    Push::send($tokens, $title, $body, $data);
                }
            }
            $count += count($ids);
        }
        $done[] = "$kind: $count users";
    }
    return $done ? implode(', ', $done) : 'No reminder window right now';
}
