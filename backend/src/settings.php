<?php
declare(strict_types=1);

function settings(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (rows('SELECT k, v FROM settings') as $r) $cache[$r['k']] = $r['v'];
    }
    return $cache;
}

function setting(string $key, $default = null)
{
    return settings()[$key] ?? $default;
}

function setting_int(string $key, int $default = 0): int
{
    return (int)(settings()[$key] ?? $default);
}

function save_setting(string $key, ?string $value): void
{
    q('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$key, $value]);
}
