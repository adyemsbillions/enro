<?php
declare(strict_types=1);

function special_public(array $t): array
{
    return [
        'id' => (int)$t['id'], 'title' => $t['title'], 'description' => $t['description'],
        'steps' => array_values(array_filter(array_map('trim', explode("\n", (string)$t['steps'])))),
        'url' => $t['url'], 'reward' => (int)$t['reward_cash'], 'proof_type' => $t['proof_type'],
        'slots' => (int)$t['slots'], 'taken' => (int)$t['taken'], 'eta' => $t['eta'], 'expires_at' => $t['expires_at'],
        'my_status' => $t['my_status'] ?? null, 'reject_reason' => $t['reject_reason'] ?? null,
    ];
}

/** Until the unlock fee is paid nothing about a task is sent to the phone (except tasks they already submitted). */
function special_masked(array $t): array
{
    if (!empty($t['my_status'])) return special_public($t);
    return [
        'id' => (int)$t['id'], 'title' => 'Unlock to view this task', 'description' => '', 'steps' => [], 'url' => null,
        'reward' => 0, 'proof_type' => 'text', 'slots' => 1, 'taken' => 0, 'eta' => null, 'expires_at' => null,
        'my_status' => null, 'reject_reason' => null, 'locked' => true,
    ];
}

function special_list(): array
{
    $u = auth_user();
    if (!user_unlocked($u)) { settle_pending_for_user((int)$u['id']); $u = row('SELECT * FROM users WHERE id = ?', [$u['id']]); }
    $items = rows(
        'SELECT t.*, s.status my_status, s.reject_reason FROM special_tasks t
         LEFT JOIN submissions s ON s.special_task_id = t.id AND s.user_id = ?
         WHERE t.active = 1 AND (t.expires_at IS NULL OR t.expires_at >= CURDATE())
           AND (t.taken < t.slots OR s.id IS NOT NULL)
         ORDER BY (s.id IS NULL) DESC, t.id DESC', [$u['id']]);
    return ['items' => array_map(user_unlocked($u) ? 'special_public' : 'special_masked', $items)] + unlock_state($u);
}

function special_show(int $id): array
{
    $u = auth_user();
    $t = row('SELECT t.*, s.status my_status, s.reject_reason FROM special_tasks t
              LEFT JOIN submissions s ON s.special_task_id = t.id AND s.user_id = ? WHERE t.id = ?', [$u['id'], $id]);
    if (!$t) fail('Task not found', 404);
    return ['task' => user_unlocked($u) ? special_public($t) : special_masked($t)] + unlock_state($u);
}

function special_submit(int $id): array
{
    $u = auth_user();
    if (!user_unlocked($u)) fail('Unlock special tasks to submit proof.', 402, ['locked' => true] + unlock_state($u));
    rate_limit('submit:' . $u['id'], 20, 3600);
    $note = mb_substr((string)in('note', ''), 0, 500);

    $t = row('SELECT * FROM special_tasks WHERE id = ? AND active = 1', [$id]);
    if (!$t) fail('Task not available', 404);
    if ($t['expires_at'] && $t['expires_at'] < today()) fail('This task has expired', 422);
    if ($t['proof_type'] !== 'screenshot' && $note === '') fail('Enter the username, email or code you used', 422);
    $prev = val('SELECT status FROM submissions WHERE user_id = ? AND special_task_id = ?', [$u['id'], $id]);
    if ($prev && $prev !== 'rejected') fail('You have already submitted this task', 422);
    if ((int)$t['taken'] >= (int)$t['slots']) fail('All slots for this task are taken', 422);

    $path = null;
    if ($t['proof_type'] !== 'text') {
        $f = $_FILES['proof'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) fail('Upload a screenshot as proof', 422);
        if ($f['size'] > config('upload_max_mb', 5) * 1024 * 1024) fail('Image is too large (max ' . config('upload_max_mb', 5) . 'MB)', 422);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
        if (!$ext) fail('Proof must be a JPG, PNG or WebP image', 422);
        $rel = 'proofs/' . date('Y/m') . '/' . $u['id'] . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        @mkdir(dirname(STORAGE_PATH . '/' . $rel), 0775, true);
        if (!move_uploaded_file($f['tmp_name'], STORAGE_PATH . '/' . $rel)) fail('Could not save upload', 500);
        $path = $rel;
    }

    try {
        special_save_submission($u, $t, $id, $note, $path);
    } catch (Throwable $e) {
        if ($path) @unlink(STORAGE_PATH . '/' . $path);
        throw $e;
    }
    return ['ok' => true, 'status' => 'pending'];
}

function special_save_submission(array $u, array $t, int $id, string $note, ?string $path): void
{
    tx(function () use ($u, $t, $id, $note, $path) {
        $task = row('SELECT * FROM special_tasks WHERE id = ? FOR UPDATE', [$id]);
        $existing = row('SELECT * FROM submissions WHERE user_id = ? AND special_task_id = ? FOR UPDATE', [$u['id'], $id]);
        if ($existing && $existing['status'] !== 'rejected') fail('You have already submitted this task', 422);
        if ((int)$task['taken'] >= (int)$task['slots']) fail('All slots for this task are taken', 422);

        if ($existing) { // resubmission after rejection
            q('UPDATE submissions SET proof_path = ?, note = ?, status = ?, reject_reason = NULL, reviewed_by = NULL, reviewed_at = NULL, created_at = ? WHERE id = ?',
              [$path, $note, 'pending', now(), $existing['id']]);
            $sid = (int)$existing['id'];
            q('UPDATE transactions SET status = ?, created_at = ? WHERE ref_type = ? AND ref_id = ?', ['pending', now(), 'submission', $sid]);
        } else {
            q('INSERT INTO submissions (user_id, special_task_id, proof_path, note) VALUES (?,?,?,?)', [$u['id'], $id, $path, $note]);
            $sid = (int)db()->lastInsertId();
            // pending ledger row; balance is only credited when an admin approves
            q('INSERT INTO transactions (user_id, currency, amount, kind, description, status, ref_type, ref_id) VALUES (?,?,?,?,?,?,?,?)',
              [$u['id'], 'cash', (int)$t['reward_cash'], 'special', 'Special: ' . $t['title'], 'pending', 'submission', $sid]);
        }
        q('UPDATE special_tasks SET taken = taken + 1 WHERE id = ?', [$id]);
    });
}

/* ---------- one-time unlock fee (Paystack) ---------- */

function unlock_state(array $u): array
{
    return ['unlocked' => user_unlocked($u), 'unlock_fee' => unlock_required() ? unlock_fee() : 0];
}

/** Starts a Paystack checkout for the unlock fee and returns its URL. */
function unlock_init(): array
{
    $u = auth_user();
    if (user_unlocked($u)) return ['unlocked' => true];
    rate_limit('unlock:' . $u['id'], 10, 3600);
    $fee = unlock_fee();
    $ref = 'ENRO-UNL-' . $u['id'] . '-' . strtoupper(bin2hex(random_bytes(5)));
    q('INSERT INTO payments (user_id, purpose, reference, amount) VALUES (?, ?, ?, ?)', [$u['id'], 'special_unlock', $ref, $fee]);
    $r = Paystack::initialize($u['email'], $fee, $ref, rtrim((string)config('app_url'), '/') . '/pay/return.php',
        ['user_id' => (int)$u['id'], 'purpose' => 'special_unlock',
         'custom_fields' => [['display_name' => 'For', 'variable_name' => 'for', 'value' => 'Enro special tasks (one-time)']]]);
    if (!$r['ok']) {
        q('UPDATE payments SET status = ? WHERE reference = ?', ['failed', $ref]);
        fail($r['message'], 503);
    }
    return ['unlocked' => false, 'reference' => $ref, 'url' => $r['url'], 'amount' => $fee];
}

/** Called when the user comes back from Paystack. The server checks with Paystack itself. */
function unlock_verify(): array
{
    $u = auth_user();
    require_fields(['reference']);
    $ref = (string)in('reference');
    if (!val('SELECT 1 FROM payments WHERE reference = ? AND user_id = ?', [$ref, $u['id']])) fail('Payment not found', 404);
    $status = payment_settle($ref);
    $u = row('SELECT * FROM users WHERE id = ?', [$u['id']]);
    return ['status' => $status] + unlock_state($u);
}