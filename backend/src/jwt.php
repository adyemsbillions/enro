<?php
declare(strict_types=1);

function b64url(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function b64url_dec(string $s): string { return base64_decode(strtr($s, '-_', '+/')) ?: ''; }

function jwt_encode(array $payload): string
{
    $header = b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $body   = b64url(json_encode($payload));
    $sig    = b64url(hash_hmac('sha256', "$header.$body", config('jwt_secret'), true));
    return "$header.$body.$sig";
}

function jwt_decode(string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$h, $b, $s] = $parts;
    $expected = b64url(hash_hmac('sha256', "$h.$b", config('jwt_secret'), true));
    if (!hash_equals($expected, $s)) return null;
    $payload = json_decode(b64url_dec($b), true);
    if (!is_array($payload) || ($payload['exp'] ?? 0) < time()) return null;
    return $payload;
}

function issue_token(int $userId): string
{
    return jwt_encode(['sub' => $userId, 'iat' => time(), 'exp' => time() + 86400 * (int)config('jwt_ttl_days', 30)]);
}
