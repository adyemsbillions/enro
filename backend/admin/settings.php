<?php
require __DIR__ . '/_boot.php';
require_admin();

$numbers = [
    'coins_per_naira'    => ['Coins per ₦1', 'e.g. 10 means 1,000 coins = ₦100 airtime'],
    'airtime_amounts'    => ['Airtime amounts (₦)', 'Comma separated, e.g. 100,200,500,1000'],
    'min_withdraw'       => ['Minimum cash withdrawal (₦)', ''],
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
   <div class="set"><div class="t"><b style="font-weight:600">Termii (OTP SMS)</b><span class="sm">Phone verification codes</span></div><?= config('termii.api_key') ? status_pill('active') : '<span class="pill grey">Not set</span>' ?></div>
   <p class="sm mt12">API keys live in <code>config.php</code> on the server, not in the database.</p>
   <?php if (config('debug')): ?><div class="flash error mt12" style="margin-bottom:0">Debug mode is ON. Turn it off in config.php before launch: it shows OTP codes in API responses.</div><?php endif ?>
  </div>
  <form method="post" class="card mt16"><?= csrf_field() ?><input type="hidden" name="action" value="password">
   <div class="h3">Change your password</div>
   <input class="inp mt12" type="password" name="current" placeholder="Current password" required>
   <input class="inp mt12" type="password" name="new" placeholder="New password (10+ characters)" required>
   <button class="btn mt12">Change password</button>
  </form>
 </div>
</div>
<?php layout_end();
