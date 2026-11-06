<?php
declare(strict_types=1);

/** Paystack sends transfer.success / transfer.failed / transfer.reversed here. Set the URL in your Paystack dashboard. */
function webhook_paystack(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $sig = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
    $secret = (string)config('paystack.secret_key');
    if (!$secret || !hash_equals(hash_hmac('sha512', $raw, $secret), $sig)) fail('Bad signature', 401);

    $event = json_decode($raw, true) ?: [];
    $ref = $event['data']['reference'] ?? '';
    $p = $ref ? row("SELECT * FROM payouts WHERE provider_ref = ? AND kind = 'cash'", [$ref]) : null;
    log_line('paystack', 'webhook ' . ($event['event'] ?? '?') . " $ref");
    if (!$p || !in_array($p['status'], ['pending', 'processing'], true)) return ['ok' => true];

    match ($event['event'] ?? '') {
        'transfer.success' => payout_mark_paid((int)$p['id'], null, $ref),
        'transfer.failed', 'transfer.reversed' => payout_refund((int)$p['id'], null, 'failed', 'Bank transfer failed'),
        default => null,
    };
    return ['ok' => true];
}
