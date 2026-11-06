<?php
require __DIR__ . '/_boot.php';
require_admin();

$slots = [
    'banner' => ['Banner 320×50', 'Shown on Home, Task detail and Wallet. Paste the Adsterra “Banner 320x50” code.'],
    'native' => ['Native banner', 'Shown inside the task list. Paste the Adsterra “Native Banner” code.'],
    'interstitial' => ['Full-screen ad after a task claim', 'Paste a Popunder/Banner 300x250 code, or leave empty to open your Smartlink full screen.'],
    'socialbar' => ['Social bar', 'Floating notification-style ad. Paste the Adsterra “Social Bar” code.'],
];

if (is_post()) {
    foreach ($slots as $k => $_) {
        save_setting("ads_{$k}_enabled", post("{$k}_enabled") ? '1' : '0');
        save_setting("ads_{$k}_html", (string)($_POST["{$k}_html"] ?? ''));
    }
    save_setting('ads_smartlink_url', post('smartlink'));
    save_setting('ads_interstitial_freq', (string)max(1, (int)post('freq')));
    flash('Ad settings saved — the app picks them up next time it opens');
    redirect('ads.php');
}

$s = settings();
layout_start('Adsterra ads', 'ads');
?>
<div class="atop"><h1>Adsterra ads</h1></div>
<form method="post"><?= csrf_field() ?>
<div class="grid2" style="margin-top:0">
 <div>
  <?php foreach ($slots as $k => [$title, $help]): ?>
   <div class="card" style="margin-bottom:14px">
    <div class="between"><div><div class="h3"><?= $title ?></div><div class="sm mt8"><?= $help ?></div></div>
     <label class="chk"><input type="checkbox" name="<?= $k ?>_enabled" value="1" <?= ($s["ads_{$k}_enabled"] ?? '0') === '1' ? 'checked' : '' ?>> On</label></div>
    <textarea class="inp code mt12" name="<?= $k ?>_html" rows="4" placeholder="<script type=&quot;text/javascript&quot;> atOptions = { ... } </script> <script src=&quot;//www.highperformanceformat.com/.../invoke.js&quot;></script>"><?= e($s["ads_{$k}_html"] ?? '') ?></textarea>
    <?php if ($k === 'interstitial'): ?>
     <div class="row mt12"><label class="sm" style="font-weight:600;color:var(--ink)">Show once every</label><input class="inp" style="width:80px;height:36px" type="number" min="1" name="freq" value="<?= (int)($s['ads_interstitial_freq'] ?? 3) ?>"><span class="sm">claimed tasks</span></div>
    <?php endif ?>
   </div>
  <?php endforeach ?>
  <div class="card">
   <div class="h3">Smartlink (Direct Link)</div>
   <div class="sm mt8">Opened for “Watch ad” tasks that have no link of their own, and as the full-screen ad when that slot has no code.</div>
   <input class="inp mt12" name="smartlink" value="<?= e($s['ads_smartlink_url'] ?? '') ?>" placeholder="https://www.example.com/abc123?key=...">
  </div>
  <button class="btn mt16">Save ad settings</button>
 </div>
 <div>
  <div class="note"><b>How this works in the app</b><br><br>
   Adsterra has no official mobile SDK. The app shows each code above inside a small built-in web view, and opens your Smartlink in the in-app browser for “Watch ad” tasks.<br><br>
   In your Adsterra dashboard, add your website/app as a source and create one ad unit per slot, then paste each unit's code here.<br><br>
   Keep rewards below what ads earn per user. Check Adsterra stats against coins issued on the Dashboard.</div>
 </div>
</div>
</form>
<?php layout_end();
