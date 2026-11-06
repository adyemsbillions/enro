<?php
declare(strict_types=1);

final class Sms
{
    /** Send an SMS through Termii. Without an API key the message is only logged (dev). */
    public static function send(string $phone, string $message): bool
    {
        $key = config('termii.api_key');
        if (!$key) {
            log_line('sms', "$phone: $message");
            return true;
        }
        $to = '234' . substr(normalize_phone($phone), 1);
        $res = http_json('POST', 'https://api.ng.termii.com/api/sms/send', [
            'to' => $to, 'from' => config('termii.sender_id', 'Enro'), 'sms' => $message,
            'type' => 'plain', 'channel' => 'dnd', 'api_key' => $key,
        ]);
        $ok = ($res['status'] ?? 0) === 200;
        if (!$ok) log_line('sms', "FAILED $to: " . json_encode($res['body']));
        return $ok;
    }
}

/** Minimal JSON HTTP client using cURL. */
function http_json(string $method, string $url, ?array $body = null, array $headers = []): array
{
    $ch = curl_init($url);
    $h = array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $h,
        CURLOPT_TIMEOUT        => 45,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    return ['status' => $status, 'body' => $raw ? (json_decode($raw, true) ?? $raw) : null, 'error' => $err];
}
