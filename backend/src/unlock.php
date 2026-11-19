<?php
declare(strict_types=1);

/*
 * One-time fee that unlocks special (real-cash) tasks, paid through Paystack.
 * Settings: special_unlock_enabled (1/0), special_unlock_fee (naira).
 *
 * Main flow (bank transfer): the user sends the fee to the account set in Admin → Settings, uploads the receipt
 * (POST /special-tasks/unlock/receipt), every admin phone gets a push, and an admin confirms it in the app or the
 * web admin (payment_approve / payment_reject).
 *
 * Older Paystack flow (kept for payments already started): app → POST /special-tasks/unlock → Paystack checkout → redirect to /pay/return.php → back to the app
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
/** The account users pay the unlock fee into (set in Admin → Settings or the app's admin screen). Null until set. */
function unlock_bank(): ?array
{
    $b = ['bank' => setting('unlock_bank_name', ''), 'number' => setting('unlock_account_number', ''), 'name' => setting('unlock_account_name', '')];
    return $b['bank'] !== '' && $b['number'] !== '' && $b['name'] !== '' ? $b : null;
}

function save_unlock_bank(string $bank, string $number, string $name): void
{
    $bank = trim(mb_substr($bank, 0, 80));
    $number = preg_replace('/\D/', '', $number);
    $name = trim(mb_substr($name, 0, 120));
    if ($bank === '' || $name === '') fail('Enter the bank name and the account name', 422);
    if (strlen($number) < 10 || strlen($number) > 12) fail('Enter a valid account number (10 digits)', 422);
    save_setting('unlock_bank_name', $bank);
    save_setting('unlock_account_number', $number);
    save_setting('unlock_account_name', $name);
}

/** The user's latest receipt that still matters to them: being checked, or refused (so they can send a new one). */
function unlock_receipt_state(int $userId): ?array
{
    $p = row("SELECT status, reject_reason, created_at FROM payments WHERE user_id = ? AND purpose = 'special_unlock' AND method = 'transfer'
              ORDER BY id DESC LIMIT 1", [$userId]);
    if (!$p || !in_array($p['status'], ['pending', 'rejected'], true)) return null;
    return ['status' => $p['status'], 'reason' => $p['reject_reason'], 'sent_at' => $p['created_at']];
}

/** Admin confirms a transfer receipt: marks it paid and unlocks the user. */
function payment_approve(int $id, int $adminId): void
{
    tx(function () use ($id, $adminId) {
        $p = row('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$id]);
        if (!$p) fail('Payment not found', 404);
        if ($p['status'] === 'paid') return;
        if ($p['status'] !== 'pending') fail('This receipt was already handled', 422);
        q('UPDATE payments SET status = ?, channel = ?, paid_at = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ?',
          ['paid', 'bank_transfer', now(), $adminId, now(), $id]);
        if ($p['purpose'] === 'special_unlock') {
            unlock_user((int)$p['user_id'], 'Payment confirmed. You can now do special tasks and earn real cash.');
        }
    });
}

/** Admin refuses a receipt; the user is told why and can upload a new one. */
function payment_reject(int $id, int $adminId, string $reason): void
{
    $reason = trim(mb_substr($reason, 0, 200)) ?: 'We could not find this payment';
    tx(function () use ($id, $adminId, $reason) {
        $p = row('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$id]);
        if (!$p) fail('Payment not found', 404);
        if ($p['status'] !== 'pending') fail('This receipt was already handled', 422);
        q('UPDATE payments SET status = ?, reject_reason = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ?',
          ['rejected', $reason, $adminId, now(), $id]);
        notify((int)$p['user_id'], 'Payment not confirmed', "$reason. Please upload the correct receipt.", 'ban', ['screen' => 'special']);
    });
}

/** Full path of a payment's receipt image, or null. Only files inside storage/proofs are ever served. */
function payment_receipt_file(int $id): ?string
{
    $path = val('SELECT proof_path FROM payments WHERE id = ?', [$id]);
    $file = $path ? realpath(STORAGE_PATH . '/' . $path) : false;
    $root = realpath(STORAGE_PATH . '/proofs');
    return $file && $root && str_starts_with($file, $root) ? $file : null;
}

/** Saves an uploaded image ($_FILES[$field]) under storage/proofs/<dir>/ and returns its relative path. */
function save_uploaded_image(string $field, string $dir, int $userId): string
{
    $f = $_FILES[$field] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) fail('Upload a screenshot of your payment receipt', 422);
    $max = (int)config('upload_max_mb', 5);
    if ($f['size'] > $max * 1024 * 1024) fail("Image is too large (max {$max}MB)", 422);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) fail('The receipt must be a JPG, PNG or WebP image', 422);
    $rel = "proofs/$dir/" . date('Y/m') . '/' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    @mkdir(dirname(STORAGE_PATH . '/' . $rel), 0775, true);
    if (!move_uploaded_file($f['tmp_name'], STORAGE_PATH . '/' . $rel)) fail('Could not save the upload', 500);
    return $rel;
}
