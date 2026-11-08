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
    return $log;
}
