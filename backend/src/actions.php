<?php
declare(strict_types=1);

/* ---------------- Special task submissions ---------------- */

function submission_approve(int $id, int $adminId): void
{
    tx(function () use ($id, $adminId) {
        $s = row('SELECT s.*, t.title, t.reward_cash FROM submissions s JOIN special_tasks t ON t.id = s.special_task_id WHERE s.id = ? FOR UPDATE', [$id]);
        if (!$s || $s['status'] !== 'pending') fail('Submission is not pending');
        q('UPDATE submissions SET status = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ?', ['approved', $adminId, now(), $id]);
        q('UPDATE users SET cash = cash + ? WHERE id = ?', [(int)$s['reward_cash'], $s['user_id']]);
        set_ledger_status('submission', $id, 'success');
        notify((int)$s['user_id'], 'Special task approved', '+' . naira($s['reward_cash']) . " added for “{$s['title']}”.", 'check', ['screen' => 'wallet']);
    });
}

function submission_reject(int $id, int $adminId, string $reason): void
{
    tx(function () use ($id, $adminId, $reason) {
        $s = row('SELECT s.*, t.title FROM submissions s JOIN special_tasks t ON t.id = s.special_task_id WHERE s.id = ? FOR UPDATE', [$id]);
        if (!$s || $s['status'] !== 'pending') fail('Submission is not pending');
        q('UPDATE submissions SET status = ?, reject_reason = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ?', ['rejected', $reason, $adminId, now(), $id]);
        q('UPDATE special_tasks SET taken = GREATEST(taken - 1, 0) WHERE id = ?', [$s['special_task_id']]); // free the slot
        set_ledger_status('submission', $id, 'rejected');
        notify((int)$s['user_id'], 'Proof rejected', "{$s['title']}: " . ($reason ?: 'your proof did not meet the requirements') . '. You can resubmit.', 'ban', ['screen' => 'special_task', 'id' => (int)$s['special_task_id']]);
    });
}

/* ---------------- Payouts (airtime, data, cash) ---------------- */

function payout_title(array $p): string
{
    return match ($p['kind']) {
        'airtime' => strtoupper($p['network']) . ' airtime ' . naira($p['amount']),
        'data'    => strtoupper($p['network']) . ' data ' . $p['plan_name'],
        default   => 'Withdrawal to ' . $p['bank_name'],
    };
}

function payout_mark_paid(int $id, ?int $adminId, ?string $ref = null): void
{
    tx(function () use ($id, $adminId, $ref) {
        $p = row('SELECT * FROM payouts WHERE id = ? FOR UPDATE', [$id]);
        if (!$p || !in_array($p['status'], ['pending', 'processing'], true)) fail('Payout is not open');
        q('UPDATE payouts SET status = ?, provider_ref = COALESCE(?, provider_ref), processed_by = ?, processed_at = ? WHERE id = ?', ['paid', $ref, $adminId, now(), $id]);
        set_ledger_status('payout', $id, 'success');
        $msg = $p['kind'] === 'cash'
            ? naira($p['amount']) . " has been sent to {$p['bank_name']} •••• " . substr((string)$p['account_number'], -4) . '.'
            : payout_title($p) . " sent to {$p['phone']}.";
        notify((int)$p['user_id'], $p['kind'] === 'cash' ? 'Withdrawal paid' : 'Top-up delivered', $msg, $p['kind'] === 'cash' ? 'bank' : 'phone', ['screen' => 'history']);
    });
}

/** Reject or fail a payout and refund the user. */
function payout_refund(int $id, ?int $adminId, string $status, string $reason): void
{
    tx(function () use ($id, $adminId, $status, $reason) {
        $p = row('SELECT * FROM payouts WHERE id = ? FOR UPDATE', [$id]);
        if (!$p || !in_array($p['status'], ['pending', 'processing'], true)) fail('Payout is not open');
        q('UPDATE payouts SET status = ?, admin_note = ?, processed_by = ?, processed_at = ? WHERE id = ?', [$status, $reason, $adminId, now(), $id]);
        set_ledger_status('payout', $id, $status === 'failed' ? 'failed' : 'rejected');
        if ($p['kind'] === 'cash') {
            ledger((int)$p['user_id'], 'cash', (int)$p['amount'] + (int)$p['fee'], 'refund', 'Refund: ' . payout_title($p), 'success', 'payout_refund', $id);
        } else {
            ledger((int)$p['user_id'], 'coin', (int)$p['coins_cost'], 'refund', 'Refund: ' . payout_title($p), 'success', 'payout_refund', $id);
        }
        notify((int)$p['user_id'], 'Request not completed', payout_title($p) . ' was ' . ($status === 'failed' ? 'unsuccessful' : 'declined') . ($reason ? ": $reason" : '') . '. Your balance has been refunded.', 'ban', ['screen' => 'wallet']);
    });
}

/** Send airtime/data through VTpass. Returns a message for the caller. */
function payout_deliver_vtu(int $id, ?int $adminId): string
{
    $p = row('SELECT * FROM payouts WHERE id = ?', [$id]);
    if (!$p || $p['kind'] === 'cash' || $p['status'] !== 'pending') fail('Payout is not a pending airtime/data request');
    q('UPDATE payouts SET status = ? WHERE id = ?', ['processing', $id]);
    $r = Vtpass::deliver($p);
    if ($r['ok'] && !$r['pending']) { payout_mark_paid($id, $adminId, $r['ref']); return 'Delivered'; }
    if ($r['ok'] && $r['pending'])  { q('UPDATE payouts SET provider_ref = ? WHERE id = ?', [$r['ref'], $id]); return 'Processing at provider'; }
    q('UPDATE payouts SET status = ?, admin_note = ? WHERE id = ?', ['pending', $r['message'], $id]); // back to queue for retry/manual
    return 'Failed: ' . $r['message'];
}

/** Start a Paystack transfer. Its final status is checked with Paystack by the cron job (or the admin "Check status" button). */
function payout_transfer_cash(int $id, int $adminId): string
{
    $p = row('SELECT * FROM payouts WHERE id = ?', [$id]);
    if (!$p || $p['kind'] !== 'cash' || $p['status'] !== 'pending') fail('Payout is not a pending withdrawal');
    $r = Paystack::transfer($p);
    if (!$r['ok']) { q('UPDATE payouts SET admin_note = ? WHERE id = ?', [$r['message'], $id]); return 'Failed: ' . $r['message']; }
    q('UPDATE payouts SET status = ?, provider_ref = ?, processed_by = ? WHERE id = ?', ['processing', $r['ref'], $adminId, $id]);
    return 'Transfer started — it will be marked paid within a few minutes, once Paystack confirms';
}

/** Checks a processing Paystack transfer and marks the payout paid, or refunds it if the transfer failed. */
function payout_check_transfer(int $id): string
{
    $p = row("SELECT * FROM payouts WHERE id = ? AND kind = 'cash'", [$id]);
    if (!$p || $p['status'] !== 'processing' || !$p['provider_ref']) return 'not processing';
    $st = Paystack::transferStatus($p['provider_ref']);
    if ($st === 'success') { payout_mark_paid($id, null, $p['provider_ref']); return 'paid'; }
    if ($st === 'failed' || $st === 'reversed') { payout_refund($id, null, 'failed', 'Bank transfer failed'); return 'failed — balance refunded'; }
    return $st === 'unknown' ? 'could not reach Paystack' : 'still processing';
}

/** Cron: check Paystack transfers that are still processing. Returns a short report. */
function payouts_check_processing(int $limit = 30): string
{
    if (!Paystack::configured()) return 'Paystack not configured';
    $ids = array_column(rows("SELECT id FROM payouts WHERE kind = 'cash' AND status = 'processing' AND provider_ref IS NOT NULL AND provider_ref LIKE 'enro_wd_%' ORDER BY id LIMIT " . (int)$limit), 'id');
    $done = 0;
    foreach ($ids as $id) {
        try { if (payout_check_transfer((int)$id) !== 'still processing') $done++; } catch (Throwable $e) { log_line('paystack', $e->getMessage()); }
    }
    return count($ids) . " checked, $done finished";
}
