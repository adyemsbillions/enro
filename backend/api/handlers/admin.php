<?php
declare(strict_types=1);

/*
 * Admin screens inside the mobile app. Admins log in with the same email and password as the web admin panel.
 * Admin tokens carry 'typ' => 'admin' and are never accepted as user tokens (and the other way round).
 */

function admin_issue_token(int $adminId): string
{
    return jwt_encode(['adm' => $adminId, 'typ' => 'admin', 'iat' => time(), 'exp' => time() + 86400 * 30]);
}

function auth_admin(): array
{
    static $a = null;
    if ($a) return $a;
    $p = jwt_decode(request_token() ?? fail('Please log in', 401));
    if (!$p || ($p['typ'] ?? '') !== 'admin' || empty($p['adm'])) fail('Please log in again', 401);
    $a = row('SELECT id, name, email FROM admins WHERE id = ?', [(int)$p['adm']]);
    if (!$a) fail('Admin account not found', 401);
    return $a;
}

function admin_api_login(): array
{
    rate_limit('admin-login:' . client_ip(), 8, 900);
    require_fields(['email', 'password']);
    $a = row('SELECT * FROM admins WHERE email = ?', [strtolower((string)in('email'))]);
    if (!$a || !password_verify((string)in('password'), $a['password_hash'])) fail('Incorrect email or password', 422);
    q('UPDATE admins SET last_login_at = ? WHERE id = ?', [now(), $a['id']]);
    return ['token' => admin_issue_token((int)$a['id']), 'admin' => ['id' => (int)$a['id'], 'name' => $a['name'], 'email' => $a['email']]];
}

function admin_api_me(): array
{
    $a = auth_admin();
    return [
        'admin' => $a,
        'pending' => (int)val("SELECT COUNT(*) FROM payments WHERE method = 'transfer' AND status = 'pending'"),
        'today' => (int)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'paid' AND paid_at >= ?", [today()]),
        'bank' => unlock_bank(),
        'fee' => unlock_fee(),
        'push' => Push::configured(),
    ];
}

function admin_payment_public(array $p): array
{
    return [
        'id' => (int)$p['id'], 'user_id' => (int)$p['user_id'], 'name' => $p['name'], 'email' => $p['email'], 'phone' => $p['phone'],
        'amount' => (int)$p['amount'], 'status' => $p['status'], 'reference' => $p['reference'],
        'reject_reason' => $p['reject_reason'], 'has_receipt' => !empty($p['proof_path']),
        'created_at' => $p['created_at'], 'reviewed_at' => $p['reviewed_at'], 'reviewed_by' => $p['reviewer'] ?? null,
        'waiting_sec' => $p['status'] === 'pending' ? max(0, time() - strtotime($p['created_at'])) : null,
    ];
}

/** Receipts sent by users. ?status=pending (default) | paid | rejected */
function admin_api_payments(): array
{
    auth_admin();
    $status = in_array($_GET['status'] ?? '', ['pending', 'paid', 'rejected'], true) ? $_GET['status'] : 'pending';
    $list = rows("SELECT p.*, u.name, u.email, u.phone, a.name reviewer FROM payments p
                  JOIN users u ON u.id = p.user_id LEFT JOIN admins a ON a.id = p.reviewed_by
                  WHERE p.method = 'transfer' AND p.status = ?
                  ORDER BY " . ($status === 'pending' ? 'p.id ASC' : 'p.id DESC') . ' LIMIT 100', [$status]);
    return ['items' => array_map('admin_payment_public', $list)];
}

function admin_api_payment(int $id): array
{
    auth_admin();
    $p = row("SELECT p.*, u.name, u.email, u.phone, u.created_at joined, a.name reviewer FROM payments p
              JOIN users u ON u.id = p.user_id LEFT JOIN admins a ON a.id = p.reviewed_by WHERE p.id = ?", [$id]);
    if (!$p) fail('Payment not found', 404);
    $earlier = (int)val("SELECT COUNT(*) FROM payments WHERE user_id = ? AND method = 'transfer' AND id < ?", [$p['user_id'], $id]);
    return ['payment' => admin_payment_public($p) + ['joined' => $p['joined'], 'earlier_receipts' => $earlier]];
}

/** Streams the receipt image (the app sends the admin token in a header). */
function admin_api_receipt(int $id): never
{
    auth_admin();
    $file = payment_receipt_file($id) ?? fail('Receipt not found', 404);
    header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($file));
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: private, max-age=86400');
    readfile($file);
    exit;
}

function admin_api_approve(int $id): array
{
    $a = auth_admin();
    payment_approve($id, (int)$a['id']);
    return ['ok' => true];
}

function admin_api_reject(int $id): array
{
    $a = auth_admin();
    payment_reject($id, (int)$a['id'], (string)in('reason', ''));
    return ['ok' => true];
}

function admin_api_bank_save(): array
{
    auth_admin();
    save_unlock_bank((string)in('bank', ''), (string)in('number', ''), (string)in('name', ''));
    return ['ok' => true, 'bank' => unlock_bank()];
}

function admin_api_push_save(): array
{
    $a = auth_admin();
    require_fields(['token']);
    q('INSERT INTO admin_push_tokens (admin_id, token, platform) VALUES (?,?,?)
       ON DUPLICATE KEY UPDATE admin_id = VALUES(admin_id), platform = VALUES(platform), updated_at = NOW()',
      [$a['id'], mb_substr((string)in('token'), 0, 255), in('platform') === 'ios' ? 'ios' : 'android']);
    return ['ok' => true, 'push' => Push::configured()];
}

function admin_api_push_remove(): array
{
    $a = auth_admin();
    q('DELETE FROM admin_push_tokens WHERE admin_id = ? AND token = ?', [$a['id'], (string)in('token')]);
    return ['ok' => true];
}
