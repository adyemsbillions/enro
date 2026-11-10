<?php
declare(strict_types=1);

const NETWORKS = ['mtn', 'glo', 'airtel', '9mobile'];

function wallet_options(): array
{
    auth_user();
    $rate = setting_int('coins_per_naira', 10);
    $plans = [];
    foreach (rows('SELECT id, network, name, validity, coins_cost FROM data_plans WHERE active = 1 ORDER BY coins_cost') as $p) {
        $plans[$p['network']][] = ['id' => (int)$p['id'], 'name' => $p['name'], 'validity' => $p['validity'], 'coins' => (int)$p['coins_cost']];
    }
    return [
        'networks' => NETWORKS,
        'coins_per_naira' => $rate,
        'airtime' => array_values(array_map(fn($a) => ['amount' => (int)$a, 'coins' => (int)$a * $rate], array_filter(explode(',', setting('airtime_amounts', '100,200,500,1000'))))),
        'data_plans' => $plans,
        'min_withdraw' => setting_int('min_withdraw', 3000),
        'withdraw_fee' => setting_int('withdraw_fee', 50),
        'banks' => Paystack::banks(),
    ];
}

function wallet_redeem(): array
{
    $u = auth_user();
    rate_limit('redeem:' . $u['id'], 10, 3600);
    require_fields(['type', 'network', 'phone']);
    $type = in('type');
    $network = strtolower((string)in('network'));
    $phone = normalize_phone((string)in('phone'));
    if (!in_array($type, ['airtime', 'data'], true)) fail('Choose airtime or data', 422);
    if (!in_array($network, NETWORKS, true)) fail('Choose a network', 422);
    if (!valid_ng_phone($phone)) fail('Enter a valid phone number', 422);

    $rate = setting_int('coins_per_naira', 10);
    $plan = null;
    if ($type === 'airtime') {
        $amount = (int)in('amount');
        $allowed = array_map('intval', explode(',', setting('airtime_amounts', '100,200,500,1000')));
        if (!in_array($amount, $allowed, true)) fail('Choose an airtime amount', 422);
        $cost = $amount * $rate;
    } else {
        $plan = row('SELECT * FROM data_plans WHERE id = ? AND network = ? AND active = 1', [(int)in('plan_id'), $network]);
        if (!$plan) fail('Choose a data bundle', 422);
        $cost = (int)$plan['coins_cost'];
        $amount = intdiv($cost, max(1, $rate));
    }

    $payoutId = tx(function () use ($u, $type, $network, $phone, $amount, $cost, $plan) {
        q('INSERT INTO payouts (user_id, kind, network, phone, plan_id, plan_name, amount, coins_cost) VALUES (?,?,?,?,?,?,?,?)',
          [$u['id'], $type, $network, $phone, $plan['id'] ?? null, $plan['name'] ?? null, $amount, $cost]);
        $pid = (int)db()->lastInsertId();
        $p = row('SELECT * FROM payouts WHERE id = ?', [$pid]);
        ledger((int)$u['id'], 'coin', -$cost, 'redeem', payout_title($p), 'pending', 'payout', $pid);
        return $pid;
    });

    $message = 'Your request has been received and will be delivered shortly.';
    if (setting('vtu_auto') === '1' && Vtpass::configured()) {
        $r = payout_deliver_vtu($payoutId, null);
        if ($r === 'Delivered') $message = 'Delivered! Check your phone.';
    }
    $p = row('SELECT id, kind, network, phone, plan_name, amount, coins_cost, status FROM payouts WHERE id = ?', [$payoutId]);
    return ['payout' => $p, 'coins' => (int)val('SELECT coins FROM users WHERE id = ?', [$u['id']]), 'message' => $message];
}

function wallet_resolve(): array
{
    $u = auth_user();
    rate_limit('resolve:' . $u['id'], 20, 3600);
    require_fields(['bank_code', 'account_number']);
    $acct = preg_replace('/\D/', '', (string)in('account_number'));
    if (strlen($acct) !== 10) fail('Account number must be 10 digits', 422);
    $name = Paystack::resolve($acct, (string)in('bank_code'));
    if (!$name) fail('Could not verify this account. Check the number and bank.', 422);
    return ['account_name' => $name];
}

function wallet_withdraw(): array
{
    $u = auth_user();
    rate_limit('withdraw:' . $u['id'], 5, 3600);
    require_fields(['bank_code', 'account_number', 'amount']);
    $acct = preg_replace('/\D/', '', (string)in('account_number'));
    $bankCode = (string)in('bank_code');
    $amount = (int)in('amount');
    $fee = setting_int('withdraw_fee', 50);
    $min = setting_int('min_withdraw', 3000);
    if (strlen($acct) !== 10) fail('Account number must be 10 digits', 422);
    if ($amount < $min) fail('Minimum withdrawal is ' . naira($min), 422);
    if (val("SELECT 1 FROM payouts WHERE user_id = ? AND kind = 'cash' AND status IN ('pending','processing')", [$u['id']])) {
        fail('You already have a withdrawal in progress', 422);
    }
    $bank = null;
    foreach (Paystack::banks() as $b) if ($b['code'] === $bankCode) $bank = $b;
    if (!$bank) fail('Choose your bank', 422);
    $name = Paystack::resolve($acct, $bankCode); // never trust a name from the app
    if (!$name) fail('Could not verify this account', 422);

    $pid = tx(function () use ($u, $amount, $fee, $bank, $acct, $name) {
        q('INSERT INTO payouts (user_id, kind, amount, fee, bank_name, bank_code, account_number, account_name) VALUES (?,?,?,?,?,?,?,?)',
          [$u['id'], 'cash', $amount, $fee, $bank['name'], $bank['code'], $acct, $name]);
        $pid = (int)db()->lastInsertId();
        ledger((int)$u['id'], 'cash', -($amount + $fee), 'withdraw', 'Withdrawal to ' . $bank['name'], 'pending', 'payout', $pid);
        return $pid;
    });
    return [
        'payout' => row('SELECT id, kind, amount, fee, bank_name, account_number, account_name, status FROM payouts WHERE id = ?', [$pid]),
        'cash' => (int)val('SELECT cash FROM users WHERE id = ?', [$u['id']]),
    ];
}
