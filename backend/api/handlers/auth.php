<?php
declare(strict_types=1);

function public_user(array $u): array
{
    return [
        'id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email'], 'phone' => $u['phone'],
        'coins' => (int)$u['coins'], 'cash' => (int)$u['cash'], 'referral_code' => $u['referral_code'],
        'created_at' => $u['created_at'],
    ];
}

function send_otp(array $user, string $purpose = 'verify'): ?string
{
    $code = (string)random_int(100000, 999999);
    q('UPDATE otp_codes SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [now(), $user['id']]);
    q('INSERT INTO otp_codes (user_id, code_hash, expires_at) VALUES (?, ?, ?)',
      [$user['id'], password_hash($code, PASSWORD_DEFAULT), date('Y-m-d H:i:s', time() + 600)]);
    $msg = $purpose === 'reset'
        ? "Your Enro password reset code is $code. It expires in 10 minutes."
        : "Your Enro verification code is $code. It expires in 10 minutes.";
    Sms::send($user['phone'], $msg);
    return config('debug') ? $code : null; // only exposed in debug, so you can test without SMS
}

function check_otp(int $userId, string $code): void
{
    $otp = row('SELECT * FROM otp_codes WHERE user_id = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1', [$userId]);
    if (!$otp || strtotime($otp['expires_at']) < time()) fail('Code expired. Request a new one.', 422);
    if ((int)$otp['attempts'] >= 5) fail('Too many wrong attempts. Request a new code.', 429);
    if (!password_verify($code, $otp['code_hash'])) {
        q('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?', [$otp['id']]);
        fail('Incorrect code', 422);
    }
    q('UPDATE otp_codes SET used_at = ? WHERE id = ?', [now(), $otp['id']]);
}

function make_referral_code(string $name): string
{
    $base = strtoupper(preg_replace('/[^A-Za-z]/', '', explode(' ', $name)[0]) ?: 'USER');
    $base = substr($base, 0, 6);
    do { $code = 'ENRO-' . $base . random_int(10, 999); } while (val('SELECT 1 FROM users WHERE referral_code = ?', [$code]));
    return $code;
}

function auth_register(): array
{
    rate_limit('reg:' . client_ip(), 10, 3600);
    require_fields(['name', 'email', 'phone', 'password']);
    $name = in('name');
    $email = strtolower(in('email'));
    $phone = normalize_phone(in('phone'));
    $password = (string)in('password');
    $device = in('device_id');

    if (mb_strlen($name) < 2) fail('Enter your full name', 422);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Enter a valid email address', 422);
    if (!valid_ng_phone($phone)) fail('Enter a valid Nigerian phone number', 422);
    if (strlen($password) < 8) fail('Password must be at least 8 characters', 422);
    if (val('SELECT 1 FROM users WHERE email = ?', [$email])) fail('An account with this email already exists', 422);
    if (val('SELECT 1 FROM users WHERE phone = ?', [$phone])) fail('An account with this phone number already exists', 422);
    if ($device && setting('one_account_per_device') === '1' && val('SELECT 1 FROM users WHERE device_id = ?', [$device])) {
        fail('An Enro account already exists on this device', 422);
    }
    $referrer = null;
    if ($code = in('referral_code')) {
        $referrer = val('SELECT id FROM users WHERE referral_code = ?', [strtoupper($code)]);
        if (!$referrer) fail('Referral code not found', 422);
    }

    q('INSERT INTO users (name, email, phone, password_hash, referral_code, referred_by, device_id) VALUES (?,?,?,?,?,?,?)',
      [$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), make_referral_code($name), $referrer, $device]);
    $user = row('SELECT * FROM users WHERE id = ?', [(int)db()->lastInsertId()]);
    $otp = send_otp($user);
    return ['user_id' => (int)$user['id'], 'phone' => $phone, 'need_otp' => true, 'debug_otp' => $otp];
}

function auth_verify_otp(): array
{
    require_fields(['user_id', 'code']);
    $user = row('SELECT * FROM users WHERE id = ?', [(int)in('user_id')]);
    if (!$user) fail('Account not found', 404);
    rate_limit('otp:' . $user['id'], 10, 900);
    check_otp((int)$user['id'], (string)in('code'));

    tx(function () use ($user) {
        if (!$user['phone_verified_at']) {
            q('UPDATE users SET phone_verified_at = ? WHERE id = ?', [now(), $user['id']]);
            $bonus = setting_int('welcome_bonus', 100);
            if ($bonus > 0) ledger((int)$user['id'], 'coin', $bonus, 'bonus', 'Welcome bonus');
            notify((int)$user['id'], 'Welcome to Enro 🎉', "You got $bonus coins to start. Complete tasks to earn more.", 'gift', ['screen' => 'tasks']);
        }
    });
    $user = row('SELECT * FROM users WHERE id = ?', [$user['id']]);
    return ['token' => issue_token((int)$user['id']), 'user' => public_user($user)];
}

function auth_resend_otp(): array
{
    require_fields(['user_id']);
    $user = row('SELECT * FROM users WHERE id = ?', [(int)in('user_id')]);
    if (!$user) fail('Account not found', 404);
    rate_limit('resend:' . $user['id'], 3, 600);
    return ['ok' => true, 'debug_otp' => send_otp($user)];
}

function find_user_by_login(string $login): ?array
{
    $login = trim($login);
    if (str_contains($login, '@')) return row('SELECT * FROM users WHERE email = ?', [strtolower($login)]);
    return row('SELECT * FROM users WHERE phone = ?', [normalize_phone($login)]);
}

function auth_login(): array
{
    require_fields(['login', 'password']);
    rate_limit('login:' . client_ip(), 20, 900);
    $user = find_user_by_login((string)in('login'));
    if (!$user || !password_verify((string)in('password'), $user['password_hash'])) fail('Incorrect email/phone or password', 401);
    if ($user['status'] === 'banned') fail('Your account has been suspended.', 403, ['banned' => true]);
    if ($device = in('device_id')) q('UPDATE users SET device_id = COALESCE(device_id, ?) WHERE id = ?', [$device, $user['id']]);
    if (!$user['phone_verified_at']) {
        return ['need_otp' => true, 'user_id' => (int)$user['id'], 'phone' => $user['phone'], 'debug_otp' => send_otp($user)];
    }
    return ['token' => issue_token((int)$user['id']), 'user' => public_user($user)];
}

function auth_forgot(): array
{
    require_fields(['login']);
    rate_limit('forgot:' . client_ip(), 5, 900);
    $user = find_user_by_login((string)in('login'));
    if (!$user) fail('No account found with that email or phone', 404);
    return ['user_id' => (int)$user['id'], 'phone' => $user['phone'], 'debug_otp' => send_otp($user, 'reset')];
}

function auth_reset(): array
{
    require_fields(['user_id', 'code', 'password']);
    if (strlen((string)in('password')) < 8) fail('Password must be at least 8 characters', 422);
    $uid = (int)in('user_id');
    rate_limit('otp:' . $uid, 10, 900);
    check_otp($uid, (string)in('code'));
    q('UPDATE users SET password_hash = ?, phone_verified_at = COALESCE(phone_verified_at, ?) WHERE id = ?',
      [password_hash((string)in('password'), PASSWORD_DEFAULT), now(), $uid]);
    $user = row('SELECT * FROM users WHERE id = ?', [$uid]);
    return ['token' => issue_token($uid), 'user' => public_user($user)];
}
