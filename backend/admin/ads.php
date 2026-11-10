<?php
require __DIR__ . '/_boot.php';
require_admin();

if (is_post()) {
    $url = post('smartlink');
    if ($url !== '' && !preg_match('#^https://#i', $url)) fail('The Smartlink must start with https://');
    save_setting('ads_smartlink_url', $url);
    save_setting('ads_interstitial_enabled', post('interstitial_enabled') ? '1' : '0');
    save_setting('ads_interstitial_freq', (string)max(1, (int)post('freq')));
    flash('Ad settings saved. The app picks them up the next time it opens.');
    redirect('ads.php');
}

$s = settings();
$url = $s['ads_smartlink_url'] ?? '';
$watchTasks = (int)val("SELECT COUNT(*) FROM tasks WHERE active = 1 AND type = 'watch' AND (url IS NULL OR url = '')");
layout_start('Adsterra ads', 'ads');
?>
<div class="atop"><h1>Adsterra ads</h1><?= $url ? '<span class="pill ok">Smartlink set</span>' : '<span class="pill grey">No Smartlink yet</span>' ?></div>
<form method="post"><?= csrf_field() ?>
<div class="grid2" style="margin-top:0">
 <div>
  <div class="card">
   <div class="h3">Smartlink (Direct Link)</div>
   <div class="sm mt8">Adsterra dashboard → <b>Direct Links</b> (Smartlink) → copy the link.</div>
   <input class="inp mt12" name="smartlink" value="<?= e($url) ?>" placeholder="https://www.example.com/abc123?key=…">
   <?php if ($url): ?><a class="ab mt12" href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer"><?= icon('eye', 14) ?> Open it to check</a><?php endif ?>
  </div>

  <div class="card mt16">
   <div class="h3">Where it shows in the app</div>
   <div class="set"><div class="t"><b style="font-weight:600">“Watch ad” tasks</b><span class="sm">Watch tasks with no link of their own open the Smartlink. <?= $watchTasks ?> active right now.</span></div>
    <span class="pill <?= $url ? 'ok' : 'grey' ?>"><?= $url ? 'On' : 'Needs Smartlink' ?></span></div>
   <div class="set"><div class="t"><b style="font-weight:600">Full-screen ad after task claims</b><span class="sm">Opens the Smartlink full-screen; users can close it after 5 seconds and then get their coins.</span></div>
    <label class="chk"><input type="checkbox" name="interstitial_enabled" value="1" <?= ($s['ads_interstitial_enabled'] ?? '0') === '1' ? 'checked' : '' ?>> On</label></div>
   <div class="set"><div class="t"><b style="font-weight:600">How often</b><span class="sm">Once every N claimed tasks per user per day</span></div>
    <input class="inp" style="width:80px" type="number" min="1" name="freq" value="<?= (int)($s['ads_interstitial_freq'] ?? 3) ?>"></div>
  </div>
  <button class="btn mt16">Save ad settings</button>
 </div>
 <div>
  <div class="note"><b>Why only Smartlinks?</b><br><br>
   Adsterra's banner, native and social-bar codes are made for websites and don't serve inside a mobile app.
   Smartlinks work anywhere a link can be opened, so the app uses them for “Watch ad” tasks and the full-screen ad.<br><br>
   Keep rewards below what Smartlink traffic earns per user. Compare Adsterra stats with coins issued on the Dashboard.</div>
 </div>
</div>
</form>
<?php layout_end();
