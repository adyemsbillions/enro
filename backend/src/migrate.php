<?php
declare(strict_types=1);

/**
 * Brings the database up to date. Safe to run any number of times:
 * tables use CREATE IF NOT EXISTS, seed rows use INSERT IGNORE, and new columns are only added when missing.
 * Returns a list of what was done.
 */
function run_migrations(PDO $pdo): array
{
    $log = [];
    foreach (['schema.sql', 'seed.sql'] as $f) {
        $pdo->exec(file_get_contents(BASE_PATH . "/database/$f"));
        $log[] = "Applied $f";
    }

    // Columns added after the first release
    $columns = [
        ['users', 'broadcasts_read_at', 'DATETIME NULL'],
        ['users', 'email_verified_at', 'DATETIME NULL'],
        ['users', 'special_unlocked_at', 'DATETIME NULL'],
        ['users', 'last_morning_push', 'DATE NULL'],
        ['users', 'last_evening_push', 'DATE NULL'],
    ];
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    foreach ($columns as [$table, $col, $def]) {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $st->execute([$db, $table, $col]);
        if (!$st->fetchColumn()) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
            $log[] = "Added $table.$col";
        }
    }

    // One-time data changes. Each runs once (a marker row in settings), so later edits in Admin → Settings stick.
    $once = [
        'nov2026_min_withdraw_3000' => ["UPDATE settings SET v = '3000' WHERE k = 'min_withdraw' AND CAST(v AS UNSIGNED) < 3000",
                                        'Minimum cash withdrawal raised to ₦3,000'],
    ];
    foreach ($once as $key => [$sql, $label]) {
        $st = $pdo->prepare('SELECT 1 FROM settings WHERE k = ?');
        $st->execute(["migration:$key"]);
        if ($st->fetchColumn()) continue;
        $pdo->exec($sql);
        $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?)')->execute(["migration:$key", date('Y-m-d H:i:s')]);
        $log[] = $label;
    }
    return $log;
}
