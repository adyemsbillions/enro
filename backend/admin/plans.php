<?php
require __DIR__ . '/_boot.php';
require_admin();

$networks = ['mtn' => 'MTN', 'glo' => 'Glo', 'airtel' => 'Airtel', '9mobile' => '9mobile'];

if (is_post()) {
    if (post('action') === 'add') {
        if (!isset($networks[post('network')]) || post('name') === '' || (int)post('coins_cost') <= 0) fail('Network, name and coin price are required');
        q('INSERT INTO data_plans (network, name, validity, coins_cost, provider_code) VALUES (?,?,?,?,?)',
          [post('network'), post('name'), post('validity'), (int)post('coins_cost'), post('provider_code') ?: null]);
        flash('Data plan added');
    }
    if (post('action') === 'save') {
        foreach ((array)($_POST['plans'] ?? []) as $pid => $pl) {
            q('UPDATE data_plans SET name = ?, validity = ?, coins_cost = ?, provider_code = ?, active = ? WHERE id = ?',
              [trim($pl['name']), trim($pl['validity']), max(1, (int)$pl['coins_cost']), trim($pl['provider_code']) ?: null, isset($pl['active']) ? 1 : 0, (int)$pid]);
        }
        flash('Data plans saved');
    }
    redirect('plans.php');
}

$plans = rows("SELECT * FROM data_plans ORDER BY FIELD(network,'mtn','glo','airtel','9mobile'), coins_cost");
$rate = max(1, setting_int('coins_per_naira', 10));
layout_start('Data plans', 'plans');
?>
<div class="atop"><h1>Data plans</h1><span class="sm">Coin price users pay · <?= number_format($rate) ?> coins = ₦1</span></div>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save">
<div class="tblw"><table class="tbl"><thead><tr><th>Network</th><th>Bundle</th><th>Validity</th><th>Coins</th><th>≈ Naira value</th><th>VTpass variation code</th><th>Active</th></tr></thead><tbody>
<?php foreach ($plans as $p): $k = "plans[{$p['id']}]"; ?>
 <tr><td><b><?= $networks[$p['network']] ?></b></td>
  <td><input class="inp" style="height:36px;width:100px" name="<?= $k ?>[name]" value="<?= e($p['name']) ?>"></td>
  <td><input class="inp" style="height:36px;width:110px" name="<?= $k ?>[validity]" value="<?= e($p['validity']) ?>"></td>
  <td><input class="inp" style="height:36px;width:100px" type="number" name="<?= $k ?>[coins_cost]" value="<?= (int)$p['coins_cost'] ?>"></td>
  <td class="sm"><?= naira(intdiv((int)$p['coins_cost'], $rate)) ?></td>
  <td><input class="inp" style="height:36px;width:180px" name="<?= $k ?>[provider_code]" value="<?= e($p['provider_code']) ?>"></td>
  <td><input type="checkbox" name="<?= $k ?>[active]" value="1" <?= $p['active'] ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--cash)"></td></tr>
<?php endforeach ?>
</tbody></table></div>
<div class="row mt12" style="justify-content:flex-end"><button class="btn">Save all</button></div>
</form>

<form method="post" class="card mt24" style="max-width:760px"><?= csrf_field() ?><input type="hidden" name="action" value="add">
 <div class="h3">Add a plan</div>
 <div class="f2 mt12">
  <select class="inp" name="network"><?php foreach ($networks as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach ?></select>
  <input class="inp" name="name" placeholder="Bundle, e.g. 1.5GB">
  <input class="inp" name="validity" placeholder="Validity, e.g. 30 days">
  <input class="inp" type="number" name="coins_cost" placeholder="Coin price">
 </div>
 <input class="inp mt12" name="provider_code" placeholder="VTpass variation_code (from VTpass service variations)">
 <button class="btn mt12"><?= icon('plus', 16) ?> Add plan</button>
</form>
<?php layout_end();
