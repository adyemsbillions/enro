<?php
declare(strict_types=1);

/** Paystack bank list, account lookup and transfers. Docs: https://paystack.com/docs/transfers/ */
final class Paystack
{
    private const BASE = 'https://api.paystack.co';

    public static function configured(): bool { return (bool)config('paystack.secret_key'); }

    private static function call(string $method, string $path, ?array $body = null): array
    {
        return http_json($method, self::BASE . $path, $body, ['Authorization: Bearer ' . config('paystack.secret_key')]);
    }

    /**
     * Start a checkout (card, bank transfer, USSD…). Returns the hosted payment page URL.
     * https://paystack.com/docs/api/transaction/#initialize
     * @return array{ok:bool, url:?string, message:string}
     */
    public static function initialize(string $email, int $amountNaira, string $reference, string $callbackUrl, array $metadata = []): array
    {
        if (!self::configured()) return ['ok' => false, 'url' => null, 'message' => 'Payments are not set up yet. Please try again later.'];
        $res = self::call('POST', '/transaction/initialize', [
            'email' => $email, 'amount' => $amountNaira * 100, 'currency' => 'NGN', 'reference' => $reference,
            'callback_url' => $callbackUrl, 'metadata' => $metadata,
        ]);
        $url = $res['body']['data']['authorization_url'] ?? null;
        if (!$url) log_line('paystack', 'initialize failed: ' . json_encode($res['body']));
        return ['ok' => (bool)$url, 'url' => $url, 'message' => $url ? 'ok' : ($res['body']['message'] ?? 'Could not start payment')];
    }

    /**
     * Ask Paystack what really happened with a payment (never trust the app or the redirect).
     * https://paystack.com/docs/api/transaction/#verify
     * @return array{paid:bool, amount_kobo:int, currency:string, id:?int, data:array}
     */
    public static function verify(string $reference): array
    {
        $res = self::call('GET', '/transaction/verify/' . rawurlencode($reference));
        $d = is_array($res['body']['data'] ?? null) ? $res['body']['data'] : [];
        return [
            'paid' => ($d['status'] ?? '') === 'success',
            'amount_kobo' => (int)($d['amount'] ?? 0),
            'currency' => (string)($d['currency'] ?? ''),
            'id' => isset($d['id']) ? (int)$d['id'] : null,
            'data' => $d,
        ];
    }

    /** Fallback list used when Paystack isn't configured. Codes are Paystack/NIBSS bank codes. */
    public const FALLBACK_BANKS = [
        ['name' => 'Access Bank', 'code' => '044'], ['name' => 'First Bank of Nigeria', 'code' => '011'],
        ['name' => 'Guaranty Trust Bank', 'code' => '058'], ['name' => 'Kuda Bank', 'code' => '50211'],
        ['name' => 'Moniepoint MFB', 'code' => '50515'], ['name' => 'OPay Digital Services', 'code' => '999992'],
        ['name' => 'PalmPay', 'code' => '999991'], ['name' => 'United Bank For Africa', 'code' => '033'],
        ['name' => 'Wema Bank', 'code' => '035'], ['name' => 'Zenith Bank', 'code' => '057'],
        ['name' => 'Fidelity Bank', 'code' => '070'], ['name' => 'Sterling Bank', 'code' => '232'],
    ];

    public static function banks(): array
    {
        if (!self::configured()) return self::FALLBACK_BANKS;
        $cache = STORAGE_PATH . '/cache/banks.json';
        if (is_file($cache) && filemtime($cache) > time() - 86400) return json_decode(file_get_contents($cache), true);
        $res = self::call('GET', '/bank?country=nigeria&perPage=200');
        $list = array_map(fn($b) => ['name' => $b['name'], 'code' => $b['code']], $res['body']['data'] ?? []);
        if (!$list) return self::FALLBACK_BANKS;
        @mkdir(dirname($cache), 0775, true);
        file_put_contents($cache, json_encode($list));
        return $list;
    }

    public static function resolve(string $account, string $bankCode): ?string
    {
        if (!self::configured()) {
            return config('debug') ? 'TEST ACCOUNT NAME' : null;
        }
        $res = self::call('GET', '/bank/resolve?account_number=' . urlencode($account) . '&bank_code=' . urlencode($bankCode));
        return $res['body']['data']['account_name'] ?? null;
    }

    /**
     * Ask Paystack what happened to a payout transfer.
     * https://paystack.com/docs/api/transfer/#verify
     * @return string success | failed | reversed | pending (anything not final) | unknown (could not reach Paystack)
     */
    public static function transferStatus(string $reference): string
    {
        $res = self::call('GET', '/transfer/verify/' . rawurlencode($reference));
        $st = $res['body']['data']['status'] ?? null;
        if (!is_string($st)) return 'unknown';
        return in_array($st, ['success', 'failed', 'reversed'], true) ? $st : 'pending';
    }

    /** @return array{ok:bool, ref:?string, message:string} */
    public static function transfer(array $payout): array
    {
        if (!self::configured()) return ['ok' => false, 'ref' => null, 'message' => 'Paystack key not set in config.php'];
        $rec = self::call('POST', '/transferrecipient', [
            'type' => 'nuban', 'name' => $payout['account_name'], 'account_number' => $payout['account_number'],
            'bank_code' => $payout['bank_code'], 'currency' => 'NGN',
        ]);
        $code = $rec['body']['data']['recipient_code'] ?? null;
        if (!$code) return ['ok' => false, 'ref' => null, 'message' => $rec['body']['message'] ?? 'Could not create recipient'];
        $ref = 'enro_wd_' . $payout['id'] . '_' . bin2hex(random_bytes(3));
        $tr = self::call('POST', '/transfer', [
            'source' => 'balance', 'amount' => (int)$payout['amount'] * 100, 'recipient' => $code,
            'reason' => 'Enro withdrawal #' . $payout['id'], 'reference' => $ref,
        ]);
        log_line('paystack', json_encode(['payout' => $payout['id'], 'res' => $tr]));
        $ok = ($tr['body']['status'] ?? false) === true;
        return ['ok' => $ok, 'ref' => $ref, 'message' => $tr['body']['message'] ?? ($ok ? 'Transfer queued' : 'Transfer failed')];
    }
}
