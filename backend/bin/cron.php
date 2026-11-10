<?php
/*
 * Run every 5 minutes. cPanel → Cron Jobs → "Once Per Five Minutes":
 *   /usr/local/bin/php /home/YOUR_CPANEL_USER/enro.truszedproperties.com/bin/cron.php >/dev/null 2>&1
 * (Use the PHP path and folder shown in your cPanel.)
 *
 * Sends queued admin broadcasts and the morning/evening reminders, and confirms Paystack payments
 * (unlock fees and withdrawal transfers) by asking Paystack directly. No webhook is used.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit('CLI only');
require dirname(__DIR__) . '/src/bootstrap.php';

$lock = fopen(STORAGE_PATH . '/cache/cron.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit("Already running\n");

$out = 'broadcasts: ' . broadcasts_process(120) . ' | reminders: ' . reminders_run(120) . ' | unlock payments: ' . settle_pending_recent()
     . ' | transfers: ' . payouts_check_processing();
q('DELETE FROM rate_limits WHERE window_end < ?', [now()]);
save_setting('cron_last_run', now());
echo $out . "\n";
log_line('cron', $out);
