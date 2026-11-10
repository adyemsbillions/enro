<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

session_name('enro_admin');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

set_exception_handler(function (Throwable $e) {
    if ($e instanceof ApiError) {
        flash($e->getMessage(), 'error');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? './'));
        exit;
    }
    log_line('error', 'admin: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo config('debug') ? '<pre>' . e($e) . '</pre>' : 'Something went wrong.';
});

function admin(): ?array
{
    if (empty($_SESSION['admin_id'])) return null;
    static $a = null;
    return $a ??= row('SELECT id, name, email FROM admins WHERE id = ?', [$_SESSION['admin_id']]);
}

function require_admin(): array
{
    $a = admin();
    if (!$a) { header('Location: login.php'); exit; }
    return $a;
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

/** Returns true for a valid POST; dies on bad CSRF. */
function is_post(): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return false;
    if (!hash_equals(csrf_token(), (string)($_POST['_csrf'] ?? ''))) {
        http_response_code(419);
        exit('Session expired — go back and refresh the page.');
    }
    return true;
}

function flash(?string $msg = null, string $type = 'ok')
{
    if ($msg !== null) { $_SESSION['flash'] = [$msg, $type]; return null; }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function post(string $k, $d = '') { $v = $_POST[$k] ?? $d; return is_string($v) ? trim($v) : $v; }

function icon(string $n, int $s = 18): string
{
    static $I = [
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'list' => '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/>',
        'star' => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9z"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-8 8"/>',
        'wallet' => '<path d="M3 7a2 2 0 0 1 2-2h13v4"/><path d="M3 7v11a2 2 0 0 0 2 2h15V9H5a2 2 0 0 1-2-2z"/><circle cx="16" cy="14.5" r="1.2"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.5 3-5.5 6.5-5.5s6.5 2 6.5 5.5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14.8c2 .7 3.5 2.4 3.5 5.2"/>',
        'ad' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 15l2-6 2 6M7.7 13h2.6M14 9v6h1.5a2 2 0 0 0 2-2v-2a2 2 0 0 0-2-2z"/>',
        'wifi' => '<path d="M2 9a15 15 0 0 1 20 0M5.5 12.5a10 10 0 0 1 13 0M9 16a5 5 0 0 1 6 0"/><circle cx="12" cy="19.5" r=".8"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
        'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10 20a2 2 0 0 0 4 0"/>',
        'logout' => '<path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 16l-4-4 4-4M6 12h11"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7"/>',
        'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'ban' => '<circle cx="12" cy="12" r="9"/><path d="m5.6 5.6 12.8 12.8"/>',
        'send' => '<path d="M21 3 10 14"/><path d="m21 3-7 18-4-7-7-4z"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'coin' => '<circle cx="12" cy="12" r="9"/><path d="M14.5 9.3c-.4-.9-1.3-1.5-2.5-1.5-1.5 0-2.5.8-2.5 1.9 0 2.5 5 1.3 5 4 0 1.1-1 1.9-2.5 1.9-1.2 0-2.1-.6-2.5-1.5M12 6v1.8M12 16.2V18"/>',
        'cash' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/>',
    ];
    return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . ($I[$n] ?? '') . '</svg>';
}

function status_pill(string $s): string
{
    $map = ['pending' => ['pend', 'Pending'], 'processing' => ['pur', 'Processing'], 'paid' => ['ok', 'Paid'], 'approved' => ['ok', 'Approved'],
            'success' => ['ok', 'Success'], 'failed' => ['bad', 'Failed'], 'rejected' => ['bad', 'Rejected'], 'active' => ['ok', 'Active'], 'banned' => ['bad', 'Banned']];
    [$c, $l] = $map[$s] ?? ['grey', ucfirst($s)];
    return "<span class=\"pill $c\">$l</span>";
}

function initials(string $name): string
{
    $p = preg_split('/\s+/', trim($name));
    return strtoupper(mb_substr($p[0] ?? '?', 0, 1) . mb_substr($p[1] ?? '', 0, 1));
}

function ago(string $dt): string
{
    $d = time() - strtotime($dt);
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60) . ' min ago';
    if ($d < 86400) return floor($d / 3600) . 'h ago';
    return date('M j', strtotime($dt));
}

function layout_start(string $title, string $page): void
{
    $a = require_admin();
    $pendSubs = (int)val("SELECT COUNT(*) FROM submissions WHERE status = 'pending'");
    $pendPay = (int)val("SELECT COUNT(*) FROM payouts WHERE status IN ('pending','processing')");
    $nav = [
        ['index.php', 'dash', 'chart', 'Dashboard', 0],
        ['tasks.php', 'tasks', 'list', 'Tasks', 0],
        ['special.php', 'special', 'star', 'Special tasks', 0],
        ['submissions.php', 'subs', 'image', 'Submissions', $pendSubs],
        ['payouts.php', 'payouts', 'wallet', 'Payouts', $pendPay],
        ['payments.php', 'payments', 'cash', 'Unlock fees', 0],
        ['users.php', 'users', 'users', 'Users', 0],
        ['plans.php', 'plans', 'wifi', 'Data plans', 0],
        ['notifications.php', 'push', 'bell', 'Notifications', 0],
        ['ads.php', 'ads', 'ad', 'Adsterra ads', 0],
        ['settings.php', 'settings', 'settings', 'Settings', 0],
    ];
    $f = flash();
    ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> · Enro Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/admin.css?v=1">
</head><body>
<div class="adm">
 <nav class="side">
  <div class="lg"><div class="logo">E</div><b>Enro Admin</b></div>
  <?php foreach ($nav as [$href, $key, $ic, $label, $n]): ?>
   <a href="<?= $href ?>" class="<?= $page === $key ? 'on' : '' ?>" title="<?= $label ?>"><?= icon($ic, 19) ?><span><?= $label ?></span><?= $n ? "<em class=\"n\">$n</em>" : '' ?></a>
  <?php endforeach ?>
  <div style="flex:1"></div>
  <div class="me"><span><?= e($a['name']) ?></span></div>
  <a href="logout.php"><?= icon('logout', 19) ?><span>Log out</span></a>
 </nav>
 <main class="main">
 <?php if ($f): ?><div class="flash <?= $f[1] ?>"><?= e($f[0]) ?></div><?php endif ?>
<?php
}

function layout_end(): void
{
    echo "</main></div>\n<script>document.querySelectorAll('[data-confirm]').forEach(f=>f.addEventListener('submit',e=>{if(!confirm(f.dataset.confirm))e.preventDefault()}))</script>\n</body></html>";
}
