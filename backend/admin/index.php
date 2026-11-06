<?php
require __DIR__ . '/_boot.php';
layout_start('Dashboard', 'dash');

$today = today();
$k = [
    'users'      => (int)val('SELECT COUNT(*) FROM users'),
    'new_today'  => (int)val('SELECT COUNT(*) FROM users WHERE created_at >= ?', [$today]),
    'active'     => (int)val('SELECT COUNT(*) FROM users WHERE last_seen_at >= ?', [$today]),
    'tasks'      => (int)val('SELECT COUNT(*) FROM task_sessions WHERE claimed_at >= ?', [$today]),
    'coins'      => (int)val("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE currency='coin' AND amount > 0 AND created_at >= ?", [$today]),
    'pend_cash'  => (int)val("SELECT COALESCE(SUM(amount),0) FROM payouts WHERE kind='cash' AND status IN ('pending','processing')"),
    'pend_count' => (int)val("SELECT COUNT(*) FROM payouts WHERE status IN ('pending','processing')"),
    'liab_coins' => (int)val('SELECT COALESCE(SUM(coins),0) FROM users'),
    'liab_cash'  => (int)val('SELECT COALESCE(SUM(cash),0) FROM users'),
];
$rate = max(1, setting_int('coins_per_naira', 10));

// last 7 days completions
$days = [];
for ($i = 6; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i day"))] = 0;
foreach (rows('SELECT DATE(claimed_at) d, COUNT(*) c FROM task_sessions WHERE claimed_at >= ? GROUP BY d', [array_key_first($days)]) as $r) $days[$r['d']] = (int)$r['c'];
$max = max(5, max($days));

$attention = [
    ['submissions.php', 'image', 'Special task proofs to review', (int)val("SELECT COUNT(*) FROM submissions WHERE status='pending'")],
    ['payouts.php?tab=topup', 'wifi', 'Airtime / data requests', (int)val("SELECT COUNT(*) FROM payouts WHERE kind<>'cash' AND status IN ('pending','processing')")],
    ['payouts.php?tab=cash', 'cash', 'Cash withdrawals', (int)val("SELECT COUNT(*) FROM payouts WHERE kind='cash' AND status IN ('pending','processing')")],
];
$top = rows('SELECT t.title, COUNT(*) c FROM task_sessions s JOIN tasks t ON t.id = s.task_id WHERE s.claimed_at >= ? GROUP BY t.id ORDER BY c DESC LIMIT 5', [$today]);
$month = date('Y-m-01');
$split = [];
foreach (['airtime' => 'var(--gold)', 'data' => 'var(--p)', 'cash' => 'var(--cash)'] as $kind => $c) {
    $split[$kind] = [(int)val("SELECT COALESCE(SUM(amount),0) FROM payouts WHERE kind = ? AND status = 'paid' AND created_at >= ?", [$kind, $month]), $c];
}
$splitTotal = max(1, array_sum(array_column($split, 0)));
?>
<div class="atop"><h1>Dashboard</h1><span class="sm"><?= date('D, j M Y') ?></span></div>
<div class="kpis">
 <div class="kpi"><small>Total users</small><b><?= number_format($k['users']) ?></b><em>+<?= $k['new_today'] ?> today</em></div>
 <div class="kpi"><small>Active today</small><b><?= number_format($k['active']) ?></b><em>opened the app</em></div>
 <div class="kpi"><small>Tasks completed today</small><b><?= number_format($k['tasks']) ?></b><em><?= number_format($k['coins']) ?> coins issued</em></div>
 <div class="kpi"><small>Pending cash payouts</small><b><?= naira($k['pend_cash']) ?></b><em><?= $k['pend_count'] ?> open requests</em></div>
 <div class="kpi"><small>Coins held by users</small><b><?= number_format($k['liab_coins']) ?></b><em>≈ <?= naira(intdiv($k['liab_coins'], $rate)) ?> airtime owed</em></div>
 <div class="kpi"><small>Cash held by users</small><b><?= naira($k['liab_cash']) ?></b><em>withdrawable balance owed</em></div>
</div>

<div class="grid2">
 <div class="card"><div class="between"><div class="h3">Tasks completed</div><span class="sm">Last 7 days</span></div>
  <svg viewBox="0 0 420 200" width="100%" style="margin-top:12px">
   <?php foreach ([0, .25, .5, .75, 1] as $f): $y = 170 - $f * 150; ?>
    <line x1="34" x2="415" y1="<?= $y ?>" y2="<?= $y ?>" stroke="#EEECF4"/><text x="28" y="<?= $y + 4 ?>" font-size="10" fill="#9A97AC" text-anchor="end"><?= round($max * $f) ?></text>
   <?php endforeach; $i = 0; foreach ($days as $d => $v): $h = $v / $max * 150; $x = 48 + $i * 53; ?>
    <rect x="<?= $x ?>" y="<?= 170 - $h ?>" width="30" height="<?= max($h, 1) ?>" rx="5" fill="<?= $d === $today ? '#5B3DF5' : '#C9BEFF' ?>"><title><?= $v ?></title></rect>
    <text x="<?= $x + 15 ?>" y="188" font-size="11" fill="#6D6A80" text-anchor="middle"><?= date('D', strtotime($d)) ?></text>
   <?php $i++; endforeach ?>
  </svg></div>
 <div class="card"><div class="h3">Needs attention</div>
  <?php foreach ($attention as [$href, $ic, $label, $n]): ?>
   <a href="<?= $href ?>" class="set"><span style="color:var(--p)"><?= icon($ic, 20) ?></span><div class="t"><?= $label ?></div><b><?= $n ?></b></a>
  <?php endforeach ?>
 </div>
</div>

<div class="grid2 even">
 <div class="card"><div class="h3">Top tasks today</div>
  <?php if (!$top): ?><p class="sub mt12">No tasks completed yet today.</p><?php endif ?>
  <?php foreach ($top as $t): ?><div class="set"><div class="t"><?= e($t['title']) ?></div><span class="sm"><?= number_format($t['c']) ?> done</span></div><?php endforeach ?>
 </div>
 <div class="card"><div class="h3">Paid out this month</div>
  <div class="mt16"><?php foreach ($split as $kind => [$v, $c]): $p = round($v / $splitTotal * 100); ?>
   <div style="margin-bottom:14px"><div class="between sm"><b style="color:var(--ink)"><?= ucfirst($kind) ?></b><span><?= naira($v) ?> · <?= $p ?>%</span></div><div class="bar mt8"><i style="width:<?= $p ?>%;background:<?= $c ?>"></i></div></div>
  <?php endforeach ?></div>
 </div>
</div>
<?php layout_end();
