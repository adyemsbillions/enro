<?php
declare(strict_types=1);

/*
 * One-time fee that unlocks special (real-cash) tasks, paid through Paystack.
 * Settings: special_unlock_enabled (1/0), special_unlock_fee (naira).
 *
 * Flow: app → POST /special-tasks/unlock → Paystack checkout → redirect to /pay/return.php → back to the app
 *       → POST /special-tasks/unlock/verify. No webhook: if the user closes the app mid-payment, pending
 *       payments are re-checked with Paystack when they next open Special tasks and by the cron job.
 *       payment_settle() is safe to run more than once.
 */

function unlock_required(): bool
{
    return setting('special_unlock_enabled', '1') === '1' && unlock_fee() > 0;
}

function unlock_fee(): int
{
    return setting_int('special_unlock_fee', 1000);
}

function user_unlocked(array $u): bool
{
    return !unlock_required() || !empty($u['special_unlocked_at']);
}

function unlock_user(int $userId, string $why): void
{
    q('UPDATE users SET special_unlocked_at = COALESCE(special_unlocked_at, ?) WHERE id = ?', [now(), $userId]);
    notify($userId, 'Special tasks unlocked 🎉', $why, 'star', ['screen' => 'special']);
}

/**
 * Checks a payment with Paystack and, if it really succeeded for the right amount, marks it paid and
 * unlocks the user. Returns the payment's status: paid | pending | failed | abandoned.
 */
function payment_settle(string $reference): string
{
    $p = row('SELECT * FROM payments WHERE reference = ?', [$reference]);
    if (!$p) fail('Payment not found', 404);
    if ($p['status'] === 'paid') return 'paid';

    $v = Paystack::verify($reference);
    $d = $v['data'];
    if (!$v['paid']) {
        $st = $d['status'] ?? '';
        if (in_array($st, ['failed', 'abandoned'], true)) {
            q('UPDATE payments SET status = ? WHERE id = ? AND status = ?', [$st, $p['id'], 'pending']);
            return $st;
        }
        return 'pending'; // ongoing / not finished yet
    }
    if ($v['currency'] !== 'NGN' || $v['amount_kobo'] < (int)$p['amount'] * 100) {
        log_line('paystack', "payment $reference amount/currency mismatch: " . json_encode([$v['amount_kobo'], $v['currency']]));
        q('UPDATE payments SET status = ? WHERE id = ?', ['failed', $p['id']]);
        return 'failed';
    }

    return tx(function () use ($p, $v, $d) {
        $row = row('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$p['id']]);
        if ($row['status'] === 'paid') return 'paid';
        q('UPDATE payments SET status = ?, provider_id = ?, channel = ?, paid_at = ? WHERE id = ?',
          ['paid', $v['id'], $d['channel'] ?? null, now(), $p['id']]);
        if ($p['purpose'] === 'special_unlock') {
            unlock_user((int)$p['user_id'], 'Payment received. You can now do special tasks and earn real cash.');
        }
        return 'paid';
    });
}

/** Re-checks this user's recent pending payments (at most once a minute per user). */
function settle_pending_for_user(int $userId): void
{
    if (!Paystack::configured()) return;
    $refs = array_column(rows('SELECT reference FROM payments WHERE user_id = ? AND status = ? AND created_at >= ? ORDER BY id DESC LIMIT 3',
        [$userId, 'pending', date('Y-m-d H:i:s', time() - 172800)]), 'reference');
    if (!$refs) return;
    $key = 'settle:' . $userId;
    $last = row('SELECT window_end FROM rate_limits WHERE k = ?', [$key]);
    if ($last && strtotime($last['window_end']) > time()) return;
    q('REPLACE INTO rate_limits (k, hits, window_end) VALUES (?, 1, ?)', [$key, date('Y-m-d H:i:s', time() + 60)]);
    foreach ($refs as $ref) {
        try { if (payment_settle($ref) === 'paid') break; } catch (Throwable $e) { log_line('paystack', $e->getMessage()); }
    }
}

/** Cron: re-check pending payments from the last 48 hours. Returns a short report. */
function settle_pending_recent(int $limit = 30): string
{
    if (!Paystack::configured()) return 'Paystack not configured';
    $refs = array_column(rows('SELECT reference FROM payments WHERE status = ? AND created_at >= ? AND created_at <= ? ORDER BY id LIMIT ' . (int)$limit,
        ['pending', date('Y-m-d H:i:s', time() - 172800), date('Y-m-d H:i:s', time() - 60)]), 'reference');
    $paid = 0;
    foreach ($refs as $ref) {
        try { if (payment_settle($ref) === 'paid') $paid++; } catch (Throwable $e) { log_line('paystack', $e->getMessage()); }
    }
    return count($refs) . " checked, $paid paid";
}