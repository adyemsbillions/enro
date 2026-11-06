<?php
declare(strict_types=1);

/**
 * VTpass airtime & data. Docs: https://www.vtpass.com/documentation/
 * Check service IDs and variation codes against your VTpass dashboard before going live.
 */
final class Vtpass
{
    private const AIRTIME = ['mtn' => 'mtn', 'glo' => 'glo', 'airtel' => 'airtel', '9mobile' => 'etisalat'];
    private const DATA    = ['mtn' => 'mtn-data', 'glo' => 'glo-data', 'airtel' => 'airtel-data', '9mobile' => 'etisalat-data'];

    public static function configured(): bool
    {
        return (bool)config('vtpass.api_key') && (bool)config('vtpass.secret_key');
    }

    /** @return array{ok:bool, pending:bool, ref:?string, message:string} */
    public static function deliver(array $payout): array
    {
        if (!self::configured()) return ['ok' => false, 'pending' => false, 'ref' => null, 'message' => 'VTpass keys not set in config.php'];

        $requestId = date('YmdHi') . bin2hex(random_bytes(4)); // VTpass wants Lagos-time YYYYMMDDHHII prefix
        $body = ['request_id' => $requestId, 'phone' => $payout['phone']];
        if ($payout['kind'] === 'airtime') {
            $body += ['serviceID' => self::AIRTIME[$payout['network']] ?? $payout['network'], 'amount' => (int)$payout['amount']];
        } else {
            $plan = row('SELECT provider_code FROM data_plans WHERE id = ?', [$payout['plan_id']]);
            $body += ['serviceID' => self::DATA[$payout['network']] ?? $payout['network'],
                      'billersCode' => $payout['phone'], 'variation_code' => $plan['provider_code'] ?? ''];
        }
        $res = http_json('POST', rtrim(config('vtpass.base_url'), '/') . '/pay', $body, [
            'api-key: ' . config('vtpass.api_key'),
            'secret-key: ' . config('vtpass.secret_key'),
        ]);
        log_line('vtpass', json_encode(['req' => $body, 'res' => $res]));

        $b = is_array($res['body']) ? $res['body'] : [];
        $status = $b['content']['transactions']['status'] ?? '';
        $code = $b['code'] ?? '';
        if ($code === '000' && $status === 'delivered') return ['ok' => true, 'pending' => false, 'ref' => $requestId, 'message' => 'Delivered'];
        if ($code === '099' || $status === 'pending' || $status === 'initiated') return ['ok' => true, 'pending' => true, 'ref' => $requestId, 'message' => 'Processing'];
        return ['ok' => false, 'pending' => false, 'ref' => $requestId, 'message' => $b['response_description'] ?? ($res['error'] ?: 'VTU request failed')];
    }
}
