<?php
declare(strict_types=1);

class ApiError extends Exception
{
    public int $status;
    public array $extra;
    public function __construct(string $message, int $status = 400, array $extra = [])
    {
        parent::__construct($message);
        $this->status = $status;
        $this->extra = $extra;
    }
}

function fail(string $message, int $status = 400, array $extra = []): never
{
    throw new ApiError($message, $status, $extra);
}

function json_out($data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Request body: JSON or form-data. */
function input(): array
{
    static $data = null;
    if ($data !== null) return $data;
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($ct, 'application/json')) {
        $data = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
    } else {
        $data = $_POST;
    }
    return $data;
}

function in(string $key, $default = null)
{
    $v = input()[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function require_fields(array $fields): void
{
    foreach ($fields as $f) {
        $v = in($f);
        if ($v === null || $v === '') fail(ucfirst(str_replace('_', ' ', $f)) . ' is required', 422);
    }
}

function normalize_phone(string $phone): string
{
    $d = preg_replace('/\D/', '', $phone);
    if (str_starts_with($d, '234')) $d = '0' . substr($d, 3);
    if (strlen($d) === 10) $d = '0' . $d;
    return $d;
}

function valid_ng_phone(string $phone): bool
{
    return (bool)preg_match('/^0[789][01]\d{8}$/', $phone);
}

function now(): string { return date('Y-m-d H:i:s'); }
function today(): string { return date('Y-m-d'); }

/** Simple fixed-window rate limit stored in MySQL. */
function rate_limit(string $key, int $max, int $windowSec): void
{
    $r = row('SELECT hits, window_end FROM rate_limits WHERE k = ?', [$key]);
    if (!$r || strtotime($r['window_end']) < time()) {
        q('REPLACE INTO rate_limits (k, hits, window_end) VALUES (?, 1, ?)', [$key, date('Y-m-d H:i:s', time() + $windowSec)]);
        return;
    }
    if ((int)$r['hits'] >= $max) fail('Too many attempts. Please wait a moment and try again.', 429);
    q('UPDATE rate_limits SET hits = hits + 1 WHERE k = ?', [$key]);
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function notify(int $userId, string $title, string $body, string $icon = 'bell'): void
{
    q('INSERT INTO notifications (user_id, title, body, icon) VALUES (?, ?, ?, ?)', [$userId, $title, $body, $icon]);
}

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function naira($n): string { return '₦' . number_format((int)$n); }

function log_line(string $file, string $line): void
{
    @mkdir(STORAGE_PATH . '/logs', 0775, true);
    file_put_contents(STORAGE_PATH . "/logs/$file.log", '[' . now() . "] $line\n", FILE_APPEND);
}
