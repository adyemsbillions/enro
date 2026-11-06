<?php
declare(strict_types=1);

function task_public(array $t, int $doneToday): array
{
    return [
        'id' => (int)$t['id'], 'title' => $t['title'], 'description' => $t['description'], 'type' => $t['type'],
        'reward' => (int)$t['reward_coins'], 'duration' => (int)$t['duration_sec'], 'daily_cap' => (int)$t['daily_cap'],
        'done_today' => $doneToday, 'available' => $doneToday < (int)$t['daily_cap'],
    ];
}

function tasks_list(): array
{
    $u = auth_user();
    $counts = [];
    foreach (rows('SELECT task_id, COUNT(*) c FROM task_sessions WHERE user_id = ? AND claimed_at >= ? GROUP BY task_id', [$u['id'], today()]) as $r) {
        $counts[$r['task_id']] = (int)$r['c'];
    }
    $tasks = array_map(fn($t) => task_public($t, $counts[$t['id']] ?? 0), rows('SELECT * FROM tasks WHERE active = 1 ORDER BY sort, id'));
    return [
        'items' => $tasks,
        'done_today' => array_sum($counts),
        'daily_limit' => setting_int('daily_task_limit', 10),
    ];
}

function task_limits_ok(array $u, array $t): void
{
    $doneThis = (int)val('SELECT COUNT(*) FROM task_sessions WHERE user_id = ? AND task_id = ? AND claimed_at >= ?', [$u['id'], $t['id'], today()]);
    if ($doneThis >= (int)$t['daily_cap']) fail('You have completed this task for today. Come back tomorrow!', 422);
    // daily check-in doesn't count toward the daily limit
    if ($t['type'] !== 'daily') {
        $doneAll = (int)val("SELECT COUNT(*) FROM task_sessions s JOIN tasks t ON t.id = s.task_id WHERE s.user_id = ? AND s.claimed_at >= ? AND t.type <> 'daily'", [$u['id'], today()]);
        if ($doneAll >= setting_int('daily_task_limit', 10)) fail('Daily task limit reached. New tasks unlock tomorrow.', 422);
    }
}

function tasks_start(int $id): array
{
    $u = auth_user();
    rate_limit('start:' . $u['id'], 60, 3600);
    $t = row('SELECT * FROM tasks WHERE id = ? AND active = 1', [$id]);
    if (!$t) fail('Task not available', 404);
    task_limits_ok($u, $t);
    q('INSERT INTO task_sessions (user_id, task_id, started_at) VALUES (?, ?, ?)', [$u['id'], $id, now()]);
    $url = $t['url'] ?: ($t['type'] === 'watch' ? setting('ads_smartlink_url', '') : '');
    return ['session_id' => (int)db()->lastInsertId(), 'duration' => (int)$t['duration_sec'], 'url' => $url ?: null];
}

function tasks_claim(int $id): array
{
    $u = auth_user();
    require_fields(['session_id']);
    $sid = (int)in('session_id');

    return tx(function () use ($u, $id, $sid) {
        $t = row('SELECT * FROM tasks WHERE id = ?', [$id]);
        $s = row('SELECT * FROM task_sessions WHERE id = ? AND user_id = ? AND task_id = ? FOR UPDATE', [$sid, $u['id'], $id]);
        if (!$t || !$s) fail('Task session not found', 404);
        if ($s['claimed_at']) fail('Already claimed', 422);
        $elapsed = time() - strtotime($s['started_at']);
        if ($elapsed < (int)$t['duration_sec'] - 1) fail('Please complete the task first (' . ((int)$t['duration_sec'] - $elapsed) . 's left)', 422);
        if ($elapsed > 7200) fail('This session expired. Start the task again.', 422);
        task_limits_ok($u, $t);

        q('UPDATE task_sessions SET claimed_at = ? WHERE id = ?', [now(), $sid]);
        ledger((int)$u['id'], 'coin', (int)$t['reward_coins'], 'task', $t['title'], 'success', 'task_session', $sid);

        $bonus = 0;
        if ($t['type'] === 'daily') {
            $streak = checkin_streak((int)$u['id'])['streak'];
            if ($streak > 0 && $streak % 7 === 0 && ($bonus = setting_int('streak_bonus', 100)) > 0) {
                ledger((int)$u['id'], 'coin', $bonus, 'streak', "$streak-day streak bonus", 'success', 'task_session', $sid);
            }
        }
        maybe_pay_referral((int)$u['id']);

        $claimsToday = (int)val('SELECT COUNT(*) FROM task_sessions WHERE user_id = ? AND claimed_at >= ?', [$u['id'], today()]);
        $every = max(1, setting_int('ads_interstitial_freq', 3));
        return [
            'reward' => (int)$t['reward_coins'],
            'bonus' => $bonus,
            'coins' => (int)val('SELECT coins FROM users WHERE id = ?', [$u['id']]),
            'show_interstitial' => setting('ads_interstitial_enabled') === '1' && $claimsToday % $every === 0,
        ];
    });
}
