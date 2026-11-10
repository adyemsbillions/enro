<?php
declare(strict_types=1);

function app_config(): array
{
    $s = settings();
    $on = fn($k) => ($s[$k] ?? '0') === '1';
    return [
        'app_name'         => $s['app_name'] ?? 'Enro',
        'coins_per_naira'  => (int)($s['coins_per_naira'] ?? 10),
        'airtime_amounts'  => array_values(array_map('intval', array_filter(explode(',', $s['airtime_amounts'] ?? '')))),
        'min_withdraw'     => (int)($s['min_withdraw'] ?? 3000),
        'withdraw_fee'     => (int)($s['withdraw_fee'] ?? 50),
        'daily_task_limit' => (int)($s['daily_task_limit'] ?? 10),
        'ref_bonus_referrer' => (int)($s['ref_bonus_referrer'] ?? 0),
        'ref_bonus_new'    => (int)($s['ref_bonus_new'] ?? 0),
        'ref_unlock_tasks' => (int)($s['ref_unlock_tasks'] ?? 5),
        'streak_bonus'     => (int)($s['streak_bonus'] ?? 0),
        'support_email'    => $s['support_email'] ?? '',
        'push' => [
            'server' => Push::configured(),   // false → the app schedules its own local check-in reminder
            'evening_hour' => (int)($s['push_evening_hour'] ?? 19),
            'evening_enabled' => ($s['push_evening_enabled'] ?? '1') === '1',
        ],
        // Adsterra Smartlink only. banner/native/socialbar stay in the response, always off,
        // because app versions from before this change still read them.
        'ads' => [
            'interstitial' => ['enabled' => $on('ads_interstitial_enabled') && !empty($s['ads_smartlink_url']), 'html' => '', 'every' => max(1, (int)($s['ads_interstitial_freq'] ?? 3))],
            'smartlink'    => $s['ads_smartlink_url'] ?? '',
            'banner'       => ['enabled' => false, 'html' => ''],
            'native'       => ['enabled' => false, 'html' => ''],
            'socialbar'    => ['enabled' => false, 'html' => ''],
        ],
    ];
}

function me_show(): array
{
    $u = auth_user();
    $doneToday = (int)val('SELECT COUNT(*) FROM task_sessions WHERE user_id = ? AND claimed_at >= ?', [$u['id'], today()]);
    return [
        'user' => public_user($u),
        'streak' => checkin_streak((int)$u['id']),
        'tasks_today' => $doneToday,
        'daily_task_limit' => setting_int('daily_task_limit', 10),
        'unread_notifications' => unread_count($u),
        'stats' => [
            'tasks_done'   => (int)val('SELECT COUNT(*) FROM task_sessions WHERE user_id = ? AND claimed_at IS NOT NULL', [$u['id']]),
            'special_done' => (int)val("SELECT COUNT(*) FROM submissions WHERE user_id = ? AND status = 'approved'", [$u['id']]),
            'total_cashout'=> (int)val("SELECT COALESCE(SUM(amount),0) FROM payouts WHERE user_id = ? AND status = 'paid'", [$u['id']]),
        ],
    ];
}

function me_update(): array
{
    $u = auth_user();
    $name = (string)in('name', $u['name']);
    if (mb_strlen($name) < 2) fail('Enter your full name', 422);
    q('UPDATE users SET name = ? WHERE id = ?', [$name, $u['id']]);
    return ['user' => public_user(row('SELECT * FROM users WHERE id = ?', [$u['id']]))];
}

function me_password(): array
{
    $u = auth_user();
    require_fields(['current_password', 'password']);
    if (!password_verify((string)in('current_password'), $u['password_hash'])) fail('Current password is incorrect', 422);
    if (strlen((string)in('password')) < 8) fail('Password must be at least 8 characters', 422);
    q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash((string)in('password'), PASSWORD_DEFAULT), $u['id']]);
    return ['ok' => true];
}

function unread_count(array $u): int
{
    $own = (int)val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$u['id']]);
    $since = max($u['created_at'], $u['broadcasts_read_at'] ?? $u['created_at']);
    return $own + (int)val('SELECT COUNT(*) FROM broadcasts WHERE created_at > ?', [$since]);
}

function notifications_list(): array
{
    $u = auth_user();
    $items = rows('SELECT id, title, body, icon, read_at, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 50', [$u['id']]);
    foreach ($items as &$n) { $n['screen'] = null; $n['ref_id'] = null; }
    unset($n);
    // admin messages (announcements, new tasks) are stored once and shown to everyone who joined before them
    $readAt = $u['broadcasts_read_at'] ?? null;
    foreach (broadcasts_for_user($u) as $b) {
        $items[] = [
            'id' => 'b' . $b['id'], 'title' => $b['title'], 'body' => $b['body'], 'icon' => 'megaphone',
            'read_at' => $readAt && $readAt >= $b['created_at'] ? $readAt : null, 'created_at' => $b['created_at'],
            'screen' => $b['screen'], 'ref_id' => $b['ref_id'] ? (int)$b['ref_id'] : null,
        ];
    }
    usort($items, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
    return ['items' => array_slice($items, 0, 60)];
}

function notifications_read_all(): array
{
    $u = auth_user();
    q('UPDATE notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL', [now(), $u['id']]);
    q('UPDATE users SET broadcasts_read_at = ? WHERE id = ?', [now(), $u['id']]);
    return ['ok' => true];
}

/** The app calls this after the user allows notifications, and again whenever the token changes. */
function push_token_save(): array
{
    $u = auth_user();
    require_fields(['token']);
    $token = mb_substr((string)in('token'), 0, 255);
    $platform = in('platform') === 'ios' ? 'ios' : 'android';
    // a phone can change hands: the token always belongs to whoever logged in last
    q('INSERT INTO push_tokens (user_id, token, platform) VALUES (?,?,?)
       ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), platform = VALUES(platform), updated_at = NOW()', [$u['id'], $token, $platform]);
    // keep at most 5 devices per user
    $keep = array_column(rows('SELECT id FROM push_tokens WHERE user_id = ? ORDER BY updated_at DESC LIMIT 5', [$u['id']]), 'id');
    if ($keep) q('DELETE FROM push_tokens WHERE user_id = ? AND id NOT IN (' . implode(',', array_map('intval', $keep)) . ')', [$u['id']]);
    return ['ok' => true, 'server_reminders' => Push::configured()];
}

/** Called on logout so the phone stops getting this account's notifications. */
function push_token_remove(): array
{
    $u = auth_user();
    q('DELETE FROM push_tokens WHERE user_id = ? AND token = ?', [$u['id'], (string)in('token')]);
    return ['ok' => true];
}

function referrals_show(): array
{
    $u = auth_user();
    return [
        'code' => $u['referral_code'],
        'invited' => (int)val('SELECT COUNT(*) FROM users WHERE referred_by = ?', [$u['id']]),
        'qualified' => (int)val('SELECT COUNT(*) FROM users WHERE referred_by = ? AND referral_paid = 1', [$u['id']]),
        'coins_earned' => (int)val("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND kind = 'referral' AND ref_id <> ?", [$u['id'], $u['id']]),
        'bonus_referrer' => setting_int('ref_bonus_referrer', 200),
        'bonus_new' => setting_int('ref_bonus_new', 100),
        'unlock_tasks' => setting_int('ref_unlock_tasks', 5),
    ];
}

function transactions_list(): array
{
    $u = auth_user();
    $cur = $_GET['currency'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $sql = 'SELECT id, currency, amount, kind, description, status, created_at FROM transactions WHERE user_id = ?';
    $p = [$u['id']];
    if (in_array($cur, ['coin', 'cash'], true)) { $sql .= ' AND currency = ?'; $p[] = $cur; }
    $sql .= ' ORDER BY id DESC LIMIT 30 OFFSET ' . (($page - 1) * 30);
    $items = array_map(function ($r) { $r['amount'] = (int)$r['amount']; return $r; }, rows($sql, $p));
    return ['items' => $items, 'page' => $page];
}
