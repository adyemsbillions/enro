<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
require __DIR__ . '/handlers/auth.php';
require __DIR__ . '/handlers/user.php';
require __DIR__ . '/handlers/tasks.php';
require __DIR__ . '/handlers/special.php';
require __DIR__ . '/handlers/wallet.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, X-Auth-Token, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }

$routes = [
    // auth
    ['POST', '/auth/register',        'auth_register'],
    ['POST', '/auth/verify-otp',      'auth_verify_otp'],
    ['POST', '/auth/resend-otp',      'auth_resend_otp'],
    ['POST', '/auth/login',           'auth_login'],
    ['POST', '/auth/forgot-password', 'auth_forgot'],
    ['POST', '/auth/reset-password',  'auth_reset'],
    // public
    ['GET',  '/config',               'app_config'],
    // user
    ['GET',  '/me',                   'me_show'],
    ['PUT',  '/me',                   'me_update'],
    ['POST', '/me/password',          'me_password'],
    ['GET',  '/notifications',        'notifications_list'],
    ['POST', '/notifications/read-all','notifications_read_all'],
    ['POST', '/me/push-token',        'push_token_save'],
    ['POST', '/me/push-token/remove', 'push_token_remove'],
    ['GET',  '/referrals',            'referrals_show'],
    ['GET',  '/transactions',         'transactions_list'],
    // coin tasks
    ['GET',  '/tasks',                'tasks_list'],
    ['POST', '/tasks/{id}/start',     'tasks_start'],
    ['POST', '/tasks/{id}/claim',     'tasks_claim'],
    // special tasks
    ['GET',  '/special-tasks',        'special_list'],
    ['POST', '/special-tasks/unlock', 'unlock_init'],
    ['POST', '/special-tasks/unlock/verify', 'unlock_verify'],
    ['GET',  '/special-tasks/{id}',   'special_show'],
    ['POST', '/special-tasks/{id}/submit', 'special_submit'],
    // wallet
    ['GET',  '/wallet/options',       'wallet_options'],
    ['POST', '/wallet/redeem',        'wallet_redeem'],
    ['POST', '/wallet/resolve-account','wallet_resolve'],
    ['POST', '/wallet/withdraw',      'wallet_withdraw'],
];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = '/' . trim(preg_replace('#^.*?/api(?=/|$)#', '', $path), '/');

try {
    foreach ($routes as [$m, $pattern, $handler]) {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>\d+)', $pattern) . '$#';
        if ($m === $method && preg_match($regex, $path, $mt)) {
            $params = array_filter($mt, 'is_string', ARRAY_FILTER_USE_KEY);
            json_out($handler(...array_map('intval', array_values($params))));
        }
    }
    json_out(['error' => 'Not found'], 404);
} catch (ApiError $e) {
    json_out(['error' => $e->getMessage()] + $e->extra, $e->status);
} catch (Throwable $e) {
    log_line('error', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    json_out(['error' => config('debug') ? $e->getMessage() : 'Something went wrong. Please try again.'], 500);
}

/* ---------- auth guard ---------- */

/**
 * The login token from the request. Many cPanel/PHP-FPM hosts strip the Authorization header,
 * so also look in the places servers move it to, and in X-Auth-Token (which the app always sends too).
 */
function request_token(): ?string
{
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($hdr === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0) $hdr = (string)$v;
        }
    }
    if (preg_match('/Bearer\s+(\S+)/i', $hdr, $m)) return $m[1];
    $alt = trim((string)($_SERVER['HTTP_X_AUTH_TOKEN'] ?? ''));
    return $alt !== '' ? $alt : null;
}
function auth_user(): array
{
    static $user = null;
    if ($user) return $user;
    $payload = jwt_decode(request_token() ?? fail('Please log in', 401));
    if (!$payload) fail('Session expired. Please log in again.', 401);
    $user = row('SELECT * FROM users WHERE id = ?', [(int)$payload['sub']]);
    if (!$user) fail('Account not found', 401);
    if ($user['status'] === 'banned') fail('Your account has been suspended.' . ($user['ban_reason'] ? ' Reason: ' . $user['ban_reason'] : ''), 403, ['banned' => true]);
    if (!$user['last_seen_at'] || strtotime($user['last_seen_at']) < time() - 300) {
        q('UPDATE users SET last_seen_at = ? WHERE id = ?', [now(), $user['id']]);
    }
    return $user;
}
