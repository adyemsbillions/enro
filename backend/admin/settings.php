<?php
require __DIR__ . '/_boot.php';
require_admin();

$numbers = [
    'coins_per_naira'    => ['Coins per ₦1', 'e.g. 10 means 1,000 coins = ₦100 airtime'],
    'airtime_amounts'    => ['Airtime amounts (₦)', 'Comma separated, e.g. 100,200,500,1000'],
    'min_withdraw'       => ['Minimum cash withdrawal (₦)', ''],
    'special_unlock_fee' => ['Special tasks unlock fee (₦)', 'One-time Paystack payment before a user can do cash tasks'],
    'withdraw_fee'       => ['Withdrawal fee (₦)', 'Deducted on top of the amount'],
    'daily_task_limit'   => ['Coin tasks per user per day', 'Daily check-in not counted'],
    'welcome_bonus'      => ['Welcome bonus (coins)', 'After phone is verified'],
    'ref_bonus_referrer' => ['Referral bonus — referrer (coins)', ''],
    'ref_bonus_new'      => ['Referral bonus — new user (coins)', ''],
    'ref_unlock_tasks'   => ['Tasks before referral bonus pays', 'Stops fake sign-ups farming bonuses'],
    'streak_bonus'       => ['7-day check-in streak bonus (coins)', ''],
    'support_email'      => ['Support email', ''],
];
$toggles = [
    'one_account_per_device' => ['One account per device', 'Blocks sign-up when the device already has an account'],
    'special_unlock_enabled' => ['Require the unlock fee for special tasks', 'Off = everyone can do special tasks for free'],
    'vtu_auto'               => ['Auto-send airtime & data', 'Deliver through VTpass instantly when users redeem (needs VTpass keys)'],
];

if (is_post()) {
    if (post('action') === 'password') {
        $a = row('SELECT * FROM admins WHERE id = ?', [admin()['id']]);
        if (!password_verify((string)post('current'), $a['password_hash'])) fail('Current password is incorrect');
        if (strlen((string)post('new')) < 10) fail('New password must be at least 10 characters');
        q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash((string)post('new'), PASSWORD_DEFAULT), $a['id']]);
        flash('Password changed');
        redirect('settings.php');
    }
    if (post('action') === 'mail') {
        $driver = post('mail_driver') === 'smtp' ? 'smtp' : 'mail';
        $from = post('mail_from');
        if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) fail('“From” must be an email address');
        if ($driver === 'smtp' && post('smtp_host') === '') fail('Enter the SMTP host, or choose “Hosting mail”');
        save_setting('mail_driver', $driver);
        save_setting('mail_from', $from);
        save_setting('mail_from_name', post('mail_from_name'));
        save_setting('smtp_host', post('smtp_host'));
        save_setting('smtp_port', (string)max(1, (int)post('smtp_port')));
        save_setting('smtp_secure', in_array(post('smtp_secure'), ['ssl', 'tls', 'none'], true) ? post('smtp_secure') : 'ssl');
        save_setting('smtp_user', post('smtp_user'));
        if (post('smtp_pass') !== '') save_setting('smtp_pass', (string)$_POST['smtp_pass']); // blank = keep the saved one
        flash('Email settings saved — send a test to check them');
        redirect('settings.php');
    }
    if (post('action') === 'mailtest') {
        $to = post('test_to');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) fail('Enter an email address to send the test to');
        [$subject, $html, $text] = code_email('123456', 'verify');
        $r = Mailer::send($to, '[Test] ' . $subject, $html, $text);
        $r['ok'] ? flash("Test email sent to $to — check the inbox and the spam folder")
                 : flash('Sending failed: ' . $r['error'], 'error');
        redirect('settings.php');
    }
    foreach ($numbers as $k => $_) {
        $v = post($k);
        if ($k === 'airtime_amounts') $v = implode(',', array_filter(array_map('intval', explode(',', $v))));
        elseif ($k !== 'support_email') $v = (string)max(0, (int)$v);
        save_setting($k, $v);
    }
    foreach ($toggles as $k => $_) save_setting($k, post($k) ? '1' : '0');
    flash('Settings saved');
    redirect('settings.php');
}

$s = settings();
layout_start('Settings', 'settings');
?>
<div class="atop"><h1>Settings</h1></div>
<div class="grid2" style="margin-top:0">
 <form method="post" class="card"><?= csrf_field() ?>
  <div class="h3">Rewards & limits</div>
  <?php foreach ($numbers as $k => [$label, $help]): ?>
   <div class="set"><div class="t"><b style="font-weight:600"><?= $label ?></b><?php if ($help): ?><span class="sm"><?= $help ?></span><?php endif ?></div>
    <input class="inp" style="width:<?= in_array($k, ['airtime_amounts', 'support_email'], true) ? '200' : '110' ?>px" name="<?= $k ?>" value="<?= e($s[$k] ?? '') ?>"></div>
  <?php endforeach ?>
  <div class="h3 mt24">Automation & fraud</div>
  <?php foreach ($toggles as $k => [$label, $help]): ?>
   <div class="set"><div class="t"><b style="font-weight:600"><?= $label ?></b><span class="sm"><?= $help ?></span></div>
    <input type="checkbox" name="<?= $k ?>" value="1" <?= ($s[$k] ?? '0') === '1' ? 'checked' : '' ?> style="width:20px;height:20px;accent-color:var(--cash)"></div>
  <?php endforeach ?>
  <button class="btn mt16">Save settings</button>
 </form>
 <div>
  <div class="card"><div class="h3">Providers</div>
   <div class="set"><div class="t"><b style="font-weight:600">VTpass (airtime & data)</b><span class="sm"><?= e(config('vtpass.base_url')) ?></span></div><?= Vtpass::configured() ? status_pill('active') : '<span class="pill grey">Not set</span>' ?></div>
   <div class="set"><div class="t"><b style="font-weight:600">Paystack (bank payouts)</b><span class="sm">Account lookup + transfers</span></div><?= Paystack::configured() ? status_pill('active') : '<span class="pill grey">Not set</span>' ?></div>
   <div class="set"><div class="t"><b style="font-weight:600">Firebase (push notifications)</b><span class="sm">storage/firebase-service-account.json</span></div><?= Push::configured() ? status_pill('active') : '<span class="pill grey">Not set</span>' ?></div>
   <p class="sm mt12">VTpass and Paystack keys live in <code>config.php</code> on the server.</p>
   <?php if (config('debug')): ?><div class="flash error mt12" style="margin-bottom:0">Debug mode is ON. Turn it off in config.php before launch: it shows verification codes in API responses.</div><?php endif ?>
  </div>

  <form method="post" class="card mt16" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="mail">
   <div class="between"><div class="h3">Email (verification codes)</div><?= Mailer::usesSmtp() ? '<span class="pill ok">SMTP</span>' : '<span class="pill grey">Hosting mail</span>' ?></div>
   <p class="sm mt8">Sign-up and password-reset codes are emailed. SMTP from a real mailbox (cPanel → Email Accounts → “Connect Devices”) lands in the inbox far more often than hosting mail.</p>
   <div class="field mt16"><label>Send with</label>
    <select class="inp" name="mail_driver"><option value="mail" <?= ($s['mail_driver'] ?? 'mail') !== 'smtp' ? 'selected' : '' ?>>Hosting mail (no setup)</option><option value="smtp" <?= ($s['mail_driver'] ?? '') === 'smtp' ? 'selected' : '' ?>>SMTP (recommended)</option></select></div>
   <div class="f2">
    <div class="field"><label>From email</label><input class="inp" name="mail_from" value="<?= e($s['mail_from'] ?? '') ?>" placeholder="<?= e(Mailer::fromAddress()) ?>"></div>
    <div class="field"><label>From name</label><input class="inp" name="mail_from_name" value="<?= e($s['mail_from_name'] ?? '') ?>" placeholder="<?= e($s['app_name'] ?? 'Enro') ?>"></div>
    <div class="field"><label>SMTP host</label><input class="inp" name="smtp_host" value="<?= e($s['smtp_host'] ?? '') ?>" placeholder="mail.truszedproperties.com"></div>
    <div class="field"><label>Port / security</label><div class="row" style="gap:6px"><input class="inp" style="width:90px" type="number" name="smtp_port" value="<?= e($s['smtp_port'] ?? '465') ?>">
     <select class="inp" name="smtp_secure"><?php foreach (['ssl' => 'SSL (465)', 'tls' => 'TLS (587)', 'none' => 'None'] as $k => $l): ?><option value="<?= $k ?>" <?= ($s['smtp_secure'] ?? 'ssl') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></div></div>
    <div class="field"><label>SMTP username</label><input class="inp" name="smtp_user" value="<?= e($s['smtp_user'] ?? '') ?>" placeholder="no-reply@truszedproperties.com" autocomplete="off"></div>
    <div class="field"><label>SMTP password</label><input class="inp" type="password" name="smtp_pass" placeholder="<?= !empty($s['smtp_pass']) ? '•••••••• (saved — leave blank to keep)' : '' ?>" autocomplete="new-password"></div>
   </div>
   <button class="btn">Save email settings</button>
  </form>
  <form method="post" class="card mt16"><?= csrf_field() ?><input type="hidden" name="action" value="mailtest">
   <div class="h3">Send a test email</div>
   <div class="row mt12"><input class="inp" type="email" name="test_to" placeholder="you@gmail.com" value="<?= e(admin()['email']) ?>" required><button class="btn" style="flex-shrink:0">Send test</button></div>
  </form>
  <form method="post" class="card mt16"><?= csrf_field() ?><input type="hidden" name="action" value="password">
   <div class="h3">Change your password</div>
   <input class="inp mt12" type="password" name="current" placeholder="Current password" required>
   <input class="inp mt12" type="password" name="new" placeholder="New password (10+ characters)" required>
   <button class="btn mt12">Change password</button>
  </form>
 </div>
</div>
<?php layout_end();
