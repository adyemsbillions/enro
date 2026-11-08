<?php
declare(strict_types=1);

/**
 * Change a user's coin or cash balance and write a ledger row.
 * Must be called inside tx(). Throws if the balance would go negative.
 */
function ledger(int $userId, string $currency, int $amount, string $kind, string $description,
                string $status = 'success', ?string $refType = null, ?int $refId = null): int
{
    $col = $currency === 'coin' ? 'coins' : 'cash';
    $bal = (int)val("SELECT $col FROM users WHERE id = ? FOR UPDATE", [$userId]);
    if ($bal + $amount < 0) {
        fail($currency === 'coin' ? 'Not enough coins' : 'Insufficient cash balance', 422);
    }
    q("UPDATE users SET $col = $col + ? WHERE id = ?", [$amount, $userId]);
    q('INSERT INTO transactions (user_id, currency, amount, kind, description, status, ref_type, ref_id) VALUES (?,?,?,?,?,?,?,?)',
      [$userId, $currency, $amount, $kind, $description, $status, $refType, $refId]);
    return (int)db()->lastInsertId();
}

function set_ledger_status(string $refType, int $refId, string $status): void
{
    q('UPDATE transactions SET status = ? WHERE ref_type = ? AND ref_id = ?', [$status, $refType, $refId]);
}

/** Pay referral bonus once the new user has completed enough tasks. Call inside tx(). */
function maybe_pay_referral(int $userId): void
{
    $u = row('SELECT id, name, referred_by, referral_paid FROM users WHERE id = ?', [$userId]);
    if (!$u || !$u['referred_by'] || $u['referral_paid']) return;
    $done = (int)val('SELECT COUNT(*) FROM task_sessions WHERE user_id = ? AND claimed_at IS NOT NULL', [$userId]);
    if ($done < setting_int('ref_unlock_tasks', 5)) return;

    q('UPDATE users SET referral_paid = 1 WHERE id = ?', [$userId]);
    $refBonus = setting_int('ref_bonus_referrer', 200);
    $newBonus = setting_int('ref_bonus_new', 100);
    if ($refBonus > 0) {
        ledger((int)$u['referred_by'], 'coin', $refBonus, 'referral', "Referral bonus — {$u['name']} joined", 'success', 'user', $userId);
        notify((int)$u['referred_by'], 'Referral bonus', "{$u['name']} completed their first tasks. +$refBonus coins!", 'gift', ['screen' => 'referral']);
    }
    if ($newBonus > 0) {
        ledger($userId, 'coin', $newBonus, 'referral', 'Referral welcome bonus', 'success', 'user', $userId);
    }
}

/** Current daily check-in streak (consecutive days up to today/yesterday). */
function checkin_streak(int $userId): array
{
    $daily = val("SELECT id FROM tasks WHERE type = 'daily' ORDER BY id LIMIT 1");
    if (!$daily) return ['streak' => 0, 'checked_today' => false];
    $days = array_column(rows(
        'SELECT DISTINCT DATE(claimed_at) d FROM task_sessions WHERE user_id = ? AND task_id = ? AND claimed_at IS NOT NULL AND claimed_at >= ? ORDER BY d DESC',
        [$userId, $daily, date('Y-m-d', strtotime('-40 days'))]
    ), 'd');
    $set = array_flip($days);
    $checkedToday = isset($set[today()]);
    $cursor = $checkedToday ? time() : strtotime('-1 day');
    $streak = 0;
    while (isset($set[date('Y-m-d', $cursor)])) { $streak++; $cursor = strtotime('-1 day', $cursor); }
    return ['streak' => $streak, 'checked_today' => $checkedToday];
}
